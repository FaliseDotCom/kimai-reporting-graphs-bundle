<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Form\Model\DateRange;
use App\Timesheet\DateTimeFactory;
use DateTimeImmutable;
use DateTimeInterface;
use KimaiPlugin\ReportingGraphsBundle\Form\SummaryForm;
use KimaiPlugin\ReportingGraphsBundle\Model\SummaryQuery;
use KimaiPlugin\ReportingGraphsBundle\ReportingGraphsBundle;
use KimaiPlugin\ReportingGraphsBundle\Repository\SummaryRepository;
use KimaiPlugin\ReportingGraphsBundle\Service\PeriodNavigator;
use KimaiPlugin\ReportingGraphsBundle\Service\SummaryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Shows the summary report.
 */
#[Route( path: '/reporting/summary' )]
#[IsGranted( 'report:user' )]
final class SummaryController extends AbstractController
{
  public const ROUTE = 'summary_report';

  /**
   * @param SummaryRepository $repository Reads the aggregated time records.
   * @param SummaryBuilder $builder Turns the records into totals and chart data.
   * @param PeriodNavigator $navigator Calculates the previous and next periods.
   */
  public function __construct(
    private readonly SummaryRepository $repository,
    private readonly SummaryBuilder $builder,
    private readonly PeriodNavigator $navigator
  )
  {
  }

  /**
   * Renders the summary report for the requested period, user and grouping.
   *
   * @param Request $request The current request.
   * @return Response
   */
  #[Route( path: '', name: self::ROUTE, methods: [ 'GET' ] )]
  public function index( Request $request ) : Response
  {
    $viewer = $this->getUser();
    $factory = $this->getDateTimeFactory( $viewer );
    $canSelectUser = $this->isGranted( 'report:other' );

    $query = $this->createDefaultQuery( $viewer, $factory );
    $form = $this->createFormForGetRequest( SummaryForm::class, $query, [
      'include_user' => $canSelectUser,
      'timezone' => $factory->getTimezone()->getName(),
    ] );
    $form->submit( $request->query->all(), false );

    if ( !$form->isValid() )
    {
      $query = $this->createDefaultQuery( $viewer, $factory );
    }

    $userIds = $this->getUserIds( $query, $viewer, $canSelectUser );
    [ $begin, $end ] = $this->getPeriod( $query->getDateRange(), $userIds, $factory );
    $rows = $this->repository->findRows( $begin, $end, $userIds );

    return $this->render( '@ReportingGraphs/summary.html.twig', [
      'form' => $form->createView(),
      'summary' => $this->builder->build( $rows, $begin, $end, $query->getGroupBy(), $request->getLocale() ),
      'begin' => $begin,
      'end' => $end,
      'group_by' => $query->getGroupBy(),
      'show_rates' => $this->canSeeRates( $userIds, $viewer ),
      'previous_query' => $this->createPeriodQuery( $request, $this->navigator->getPrevious( $begin, $end ) ),
      'next_query' => $this->createPeriodQuery( $request, $this->navigator->getNext( $begin, $end ) ),
      'asset_version' => ReportingGraphsBundle::getAssetVersion(),
    ] );
  }

  /**
   * Creates the query for a first visit: the viewer's own time this week, grouped by project.
   *
   * @param User $viewer The logged-in user.
   * @param DateTimeFactory $factory Creates dates in the viewer's time zone.
   * @return SummaryQuery
   */
  private function createDefaultQuery( User $viewer, DateTimeFactory $factory ) : SummaryQuery
  {
    $range = new DateRange();
    $range->setBegin( $factory->getStartOfWeek() );
    $range->setEnd( $factory->getEndOfWeek() );

    $query = new SummaryQuery( $range );
    $query->setUser( $viewer );

    return $query;
  }

  /**
   * Returns the IDs of the users whose time is summarised.
   *
   * @param SummaryQuery $query The submitted filters.
   * @param User $viewer The logged-in user.
   * @param bool $canSelectUser Whether the viewer may see other users' time.
   * @return array<int, int>
   */
  private function getUserIds( SummaryQuery $query, User $viewer, bool $canSelectUser ) : array
  {
    $selected = $canSelectUser ? $query->getUser() : $viewer;

    if ( $selected === null )
    {
      return $this->repository->findVisibleUserIds( $viewer );
    }

    return $selected->getId() === null ? [] : [ $selected->getId() ];
  }

  /**
   * Returns the first and last day of the requested period. An empty period means all time.
   *
   * @param DateRange $range The requested period.
   * @param array<int, int> $userIds The users whose time is summarised.
   * @param DateTimeFactory $factory Creates dates in the viewer's time zone.
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
   */
  private function getPeriod( DateRange $range, array $userIds, DateTimeFactory $factory ) : array
  {
    $begin = $range->getBegin();
    $end = $range->getEnd();

    if ( $begin !== null && $end !== null )
    {
      return [ $this->toDay( $begin ), $this->toDay( $end ) ];
    }

    $bounds = $this->repository->findDateBounds( $userIds );
    if ( count( $bounds ) === 2 )
    {
      return [ $bounds[ 0 ], $bounds[ 1 ] ];
    }

    return [ $this->toDay( $factory->getStartOfWeek() ), $this->toDay( $factory->getEndOfWeek() ) ];
  }

  /**
   * Returns the calendar day of a date, without its time.
   *
   * @param DateTimeInterface $date A date in the viewer's time zone.
   * @return DateTimeImmutable
   */
  private function toDay( DateTimeInterface $date ) : DateTimeImmutable
  {
    return new DateTimeImmutable( $date->format( 'Y-m-d' ) );
  }

  /**
   * Returns whether the viewer may see the money amounts of the summarised time.
   *
   * @param array<int, int> $userIds The users whose time is summarised.
   * @param User $viewer The logged-in user.
   * @return bool
   */
  private function canSeeRates( array $userIds, User $viewer ) : bool
  {
    if ( $userIds === [ $viewer->getId() ] )
    {
      return $this->isGranted( 'view_rate_own_timesheet' );
    }

    return $this->isGranted( 'view_rate_other_timesheet' );
  }

  /**
   * Returns the current query parameters with another period.
   *
   * @param Request $request The current request.
   * @param array{0: DateTimeImmutable, 1: DateTimeImmutable} $period The first and last day.
   * @return array<string, mixed>
   */
  private function createPeriodQuery( Request $request, array $period ) : array
  {
    return array_merge( $request->query->all(), [
      'daterange' => $period[ 0 ]->format( 'Y-m-d' ) . ' - ' . $period[ 1 ]->format( 'Y-m-d' ),
    ] );
  }
}
