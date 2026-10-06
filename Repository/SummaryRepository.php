<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Repository;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\Query\UserFormTypeQuery;
use App\Repository\Query\VisibilityInterface;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reads aggregated time records for the summary report.
 *
 * @phpstan-type SummaryRow array{
 *   day: DateTimeImmutable,
 *   projectId: int,
 *   projectName: string,
 *   projectColor: string,
 *   customerId: int,
 *   customerName: string,
 *   customerColor: string,
 *   activityId: int,
 *   activityName: string,
 *   activityColor: string,
 *   userId: int,
 *   userName: string,
 *   userAlias: string,
 *   userColor: string,
 *   description: string,
 *   duration: int,
 *   billableDuration: int,
 *   rate: float
 * }
 */
final class SummaryRepository
{
  /**
   * @param EntityManagerInterface $entityManager Runs the aggregate query.
   * @param UserRepository $userRepository Resolves which users a viewer may see.
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly UserRepository $userRepository
  )
  {
  }

  /**
   * Returns the IDs of every user whose time the viewer may see, including disabled users.
   *
   * @param User $viewer The logged-in user.
   * @return array<int, int>
   */
  public function findVisibleUserIds( User $viewer ) : array
  {
    $query = new UserFormTypeQuery();
    $query->setUser( $viewer );
    $query->setVisibility( VisibilityInterface::SHOW_BOTH );

    $ids = [];
    foreach ( $this->userRepository->getQueryBuilderForFormType( $query )->getQuery()->getResult() as $user )
    {
      if ( $user instanceof User && $user->getId() !== null )
      {
        $ids[] = $user->getId();
      }
    }

    return $ids;
  }

  /**
   * Returns the first and last day with finished time records for the given users.
   *
   * @param array<int, int> $userIds The users to include.
   * @return array<int, DateTimeImmutable>
   */
  public function findDateBounds( array $userIds ) : array
  {
    if ( empty( $userIds ) )
    {
      return [];
    }

    $result = $this->entityManager->createQueryBuilder()
      ->select( 'MIN( t.date ) AS first', 'MAX( t.date ) AS last' )
      ->from( Timesheet::class, 't' )
      ->where( 't.end IS NOT NULL' )
      ->andWhere( 'IDENTITY( t.user ) IN ( :users )' )
      ->setParameter( 'users', $userIds )
      ->getQuery()
      ->getSingleResult();

    if ( !is_array( $result ) || empty( $result[ 'first' ] ) || empty( $result[ 'last' ] ) )
    {
      return [];
    }

    return [
      new DateTimeImmutable( (string) $result[ 'first' ] ),
      new DateTimeImmutable( (string) $result[ 'last' ] ),
    ];
  }

  /**
   * Returns finished time records between two days, summed per day, project, activity, user
   * and description.
   *
   * @param DateTimeInterface $begin The first day to include.
   * @param DateTimeInterface $end The last day to include.
   * @param array<int, int> $userIds The users to include.
   * @return array<int, SummaryRow>
   */
  public function findRows( DateTimeInterface $begin, DateTimeInterface $end, array $userIds ) : array
  {
    if ( empty( $userIds ) )
    {
      return [];
    }

    $rows = $this->entityManager->createQueryBuilder()
      ->select(
        't.date AS day',
        'p.id AS projectId',
        'p.name AS projectName',
        'p.color AS projectColor',
        'c.id AS customerId',
        'c.name AS customerName',
        'c.color AS customerColor',
        'a.id AS activityId',
        'a.name AS activityName',
        'a.color AS activityColor',
        'u.id AS userId',
        'u.username AS userName',
        'u.alias AS userAlias',
        'u.color AS userColor',
        't.description AS description',
        'SUM( t.duration ) AS duration',
        'SUM( CASE WHEN t.billable = true THEN t.duration ELSE 0 END ) AS billableDuration',
        'SUM( t.rate ) AS rate'
      )
      ->from( Timesheet::class, 't' )
      ->join( 't.project', 'p' )
      ->join( 'p.customer', 'c' )
      ->join( 't.activity', 'a' )
      ->join( 't.user', 'u' )
      ->where( 't.end IS NOT NULL' )
      ->andWhere( 't.date BETWEEN :begin AND :end' )
      ->andWhere( 'u.id IN ( :users )' )
      ->groupBy( 't.date' )
      ->addGroupBy( 'p.id, p.name, p.color, c.id, c.name, c.color' )
      ->addGroupBy( 'a.id, a.name, a.color, u.id, u.username, u.alias, u.color' )
      ->addGroupBy( 't.description' )
      ->setParameter( 'begin', $begin->format( 'Y-m-d' ) )
      ->setParameter( 'end', $end->format( 'Y-m-d' ) )
      ->setParameter( 'users', $userIds )
      ->getQuery()
      ->getArrayResult();

    return array_map( [ $this, 'normaliseRow' ], $rows );
  }

  /**
   * Converts one raw query row into a typed summary row.
   *
   * @param array<string, mixed> $row A row from the aggregate query.
   * @return SummaryRow
   */
  private function normaliseRow( array $row ) : array
  {
    $day = $row[ 'day' ] instanceof DateTimeInterface
      ? DateTimeImmutable::createFromInterface( $row[ 'day' ] )
      : new DateTimeImmutable( (string) $row[ 'day' ] );

    return [
      'day' => $day,
      'projectId' => (int) $row[ 'projectId' ],
      'projectName' => (string) $row[ 'projectName' ],
      'projectColor' => $this->cleanString( $row[ 'projectColor' ] ),
      'customerId' => (int) $row[ 'customerId' ],
      'customerName' => (string) $row[ 'customerName' ],
      'customerColor' => $this->cleanString( $row[ 'customerColor' ] ),
      'activityId' => (int) $row[ 'activityId' ],
      'activityName' => (string) $row[ 'activityName' ],
      'activityColor' => $this->cleanString( $row[ 'activityColor' ] ),
      'userId' => (int) $row[ 'userId' ],
      'userName' => (string) $row[ 'userName' ],
      'userAlias' => $this->cleanString( $row[ 'userAlias' ] ),
      'userColor' => $this->cleanString( $row[ 'userColor' ] ),
      'description' => $this->cleanString( $row[ 'description' ] ),
      'duration' => (int) $row[ 'duration' ],
      'billableDuration' => (int) $row[ 'billableDuration' ],
      'rate' => (float) $row[ 'rate' ],
    ];
  }

  /**
   * Returns a database value as a trimmed string, or an empty string when it has no value.
   *
   * @param mixed $value A database value.
   * @return string
   */
  private function cleanString( mixed $value ) : string
  {
    if ( !is_scalar( $value ) )
    {
      return '';
    }

    return trim( (string) $value );
  }
}
