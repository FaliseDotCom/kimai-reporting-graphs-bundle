<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Service;

use App\Form\Type\DateRangeType;
use DateTimeImmutable;

/**
 * Calculates the period before or after a period: whole months and whole years move by a
 * month or a year, any other period moves by its own length.
 */
final class PeriodNavigator
{
  public const DIRECTION_PREVIOUS = -1;
  public const DIRECTION_NEXT = 1;

  /**
   * The query parameter of Kimai's date range picker.
   *
   * @var string
   */
  public const DATE_RANGE_PARAMETER = 'daterange';

  /**
   * Returns the period before the given one.
   *
   * @param DateTimeImmutable $begin The first day of the period.
   * @param DateTimeImmutable $end The last day of the period.
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
   */
  public function getPrevious( DateTimeImmutable $begin, DateTimeImmutable $end ) : array
  {
    return $this->shift( $begin, $end, self::DIRECTION_PREVIOUS );
  }

  /**
   * Returns the period after the given one.
   *
   * @param DateTimeImmutable $begin The first day of the period.
   * @param DateTimeImmutable $end The last day of the period.
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
   */
  public function getNext( DateTimeImmutable $begin, DateTimeImmutable $end ) : array
  {
    return $this->shift( $begin, $end, self::DIRECTION_NEXT );
  }

  /**
   * Returns the query parameters of the previous and the next period: the given parameters
   * with another date range, in Kimai's date range query format.
   *
   * @param array<string, mixed> $parameters The current query parameters.
   * @param DateTimeImmutable $begin The first day of the period.
   * @param DateTimeImmutable $end The last day of the period.
   * @return array{previous: array<string, mixed>, next: array<string, mixed>}
   */
  public function getQueries( array $parameters, DateTimeImmutable $begin, DateTimeImmutable $end ) : array
  {
    return [
      'previous' => $this->withPeriod( $parameters, $this->getPrevious( $begin, $end ) ),
      'next' => $this->withPeriod( $parameters, $this->getNext( $begin, $end ) ),
    ];
  }

  /**
   * Returns query parameters with their date range replaced by the given period.
   *
   * @param array<string, mixed> $parameters The current query parameters.
   * @param array{0: DateTimeImmutable, 1: DateTimeImmutable} $period The first and last day.
   * @return array<string, mixed>
   */
  private function withPeriod( array $parameters, array $period ) : array
  {
    return array_merge( $parameters, [
      self::DATE_RANGE_PARAMETER => DateRangeType::formatQueryString( $period[ 0 ], $period[ 1 ] ),
    ] );
  }

  /**
   * Moves a period one step back or forward.
   *
   * @param DateTimeImmutable $begin The first day of the period.
   * @param DateTimeImmutable $end The last day of the period.
   * @param int $direction One of the DIRECTION_ constants.
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
   */
  private function shift( DateTimeImmutable $begin, DateTimeImmutable $end, int $direction ) : array
  {
    $sign = $direction < 0 ? '-' : '+';

    if ( $this->isWholeYears( $begin, $end ) )
    {
      $years = (int) $end->format( 'Y' ) - (int) $begin->format( 'Y' ) + 1;
      $first = $begin->modify( $sign . $years . ' years' );

      return [ $first, $first->modify( '+' . $years . ' years -1 day' ) ];
    }

    if ( $this->isWholeMonths( $begin, $end ) )
    {
      $months = $this->countMonths( $begin, $end );
      $first = $begin->modify( $sign . $months . ' months' );

      return [ $first, $first->modify( '+' . $months . ' months -1 day' ) ];
    }

    $days = (int) $begin->diff( $end )->days + 1;

    return [ $begin->modify( $sign . $days . ' days' ), $end->modify( $sign . $days . ' days' ) ];
  }

  /**
   * Returns whether a period starts on 1 January and ends on 31 December.
   *
   * @param DateTimeImmutable $begin The first day of the period.
   * @param DateTimeImmutable $end The last day of the period.
   * @return bool
   */
  private function isWholeYears( DateTimeImmutable $begin, DateTimeImmutable $end ) : bool
  {
    return $begin->format( 'm-d' ) === '01-01' && $end->format( 'm-d' ) === '12-31';
  }

  /**
   * Returns whether a period starts on the first and ends on the last day of a month.
   *
   * @param DateTimeImmutable $begin The first day of the period.
   * @param DateTimeImmutable $end The last day of the period.
   * @return bool
   */
  private function isWholeMonths( DateTimeImmutable $begin, DateTimeImmutable $end ) : bool
  {
    return $begin->format( 'd' ) === '01' && $end->format( 'd' ) === $end->format( 't' );
  }

  /**
   * Returns the number of calendar months a period covers.
   *
   * @param DateTimeImmutable $begin The first day of the period.
   * @param DateTimeImmutable $end The last day of the period.
   * @return int
   */
  private function countMonths( DateTimeImmutable $begin, DateTimeImmutable $end ) : int
  {
    $years = (int) $end->format( 'Y' ) - (int) $begin->format( 'Y' );

    return $years * 12 + (int) $end->format( 'n' ) - (int) $begin->format( 'n' ) + 1;
  }
}
