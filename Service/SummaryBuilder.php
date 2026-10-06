<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Service;

use App\Utils\Color;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use IntlDateFormatter;
use KimaiPlugin\ReportingGraphsBundle\Model\SummaryQuery;
use KimaiPlugin\ReportingGraphsBundle\Repository\SummaryRepository;

/**
 * Turns aggregated time records into the totals, chart series and table of the summary report.
 *
 * @phpstan-import-type SummaryRow from SummaryRepository
 * @phpstan-type Bucket array{key: string, label: string, duration: int}
 * @phpstan-type Description array{text: string, duration: int, rate: float}
 * @phpstan-type Group array{
 *   key: string,
 *   name: string,
 *   subtitle: string,
 *   color: string,
 *   colorDark: string,
 *   duration: int,
 *   billableDuration: int,
 *   rate: float,
 *   percent: float,
 *   perBucket: array<int, int>,
 *   descriptions: array<int, Description>
 * }
 * @phpstan-type ChartSeries array{name: string, other: bool, color: string, colorDark: string, duration: int, perBucket: array<int, int>}
 * @phpstan-type Summary array{
 *   granularity: string,
 *   totals: array{duration: int, billableDuration: int, rate: float},
 *   buckets: array<int, Bucket>,
 *   groups: array<int, Group>,
 *   chartSeries: array<int, ChartSeries>
 * }
 */
final class SummaryBuilder
{
  public const GRANULARITY_DAY = 'day';
  public const GRANULARITY_MONTH = 'month';

  /**
   * Periods up to this many days are charted per day; longer periods per month.
   *
   * @var int
   */
  private const MAX_DAILY_BUCKETS = 62;

  /**
   * Periods up to this many days show the weekday in their day labels.
   *
   * @var int
   */
  private const MAX_WEEKDAY_LABELS = 7;

  /**
   * Most series a chart shows; beyond this the smallest groups are combined into "other".
   *
   * @var int
   */
  private const MAX_CHART_SERIES = 8;

  /**
   * @param ChartColors $chartColors Picks colours that can be told apart.
   */
  public function __construct( private readonly ChartColors $chartColors )
  {
  }

  /**
   * Builds the summary for the given rows and period.
   *
   * @param array<int, SummaryRow> $rows Aggregated rows from the repository.
   * @param DateTimeImmutable $begin The first day of the period.
   * @param DateTimeImmutable $end The last day of the period.
   * @param string $groupBy One of the SummaryQuery::GROUP_ constants.
   * @param string $locale The locale used for bucket labels.
   * @return Summary
   */
  public function build( array $rows, DateTimeImmutable $begin, DateTimeImmutable $end, string $groupBy, string $locale ) : array
  {
    $granularity = $this->getGranularity( $begin, $end );
    $buckets = $this->createBuckets( $begin, $end, $granularity, $locale );
    $bucketIndex = array_flip( array_column( $buckets, 'key' ) );

    $totals = [ 'duration' => 0, 'billableDuration' => 0, 'rate' => 0.0 ];
    $groups = [];
    $descriptions = [];

    foreach ( $rows as $row )
    {
      $key = $this->getGroupKey( $row, $groupBy );
      $index = $bucketIndex[ $row[ 'day' ]->format( $this->getBucketFormat( $granularity ) ) ] ?? null;

      if ( !isset( $groups[ $key ] ) )
      {
        $groups[ $key ] = $this->createGroup( $row, $groupBy, count( $buckets ) );
      }

      $groups[ $key ][ 'duration' ] += $row[ 'duration' ];
      $groups[ $key ][ 'billableDuration' ] += $row[ 'billableDuration' ];
      $groups[ $key ][ 'rate' ] += $row[ 'rate' ];

      if ( $index !== null )
      {
        $groups[ $key ][ 'perBucket' ][ $index ] += $row[ 'duration' ];
        $buckets[ $index ][ 'duration' ] += $row[ 'duration' ];
      }

      $text = $row[ 'description' ];
      $descriptions[ $key ][ $text ] ??= [ 'text' => $text, 'duration' => 0, 'rate' => 0.0 ];
      $descriptions[ $key ][ $text ][ 'duration' ] += $row[ 'duration' ];
      $descriptions[ $key ][ $text ][ 'rate' ] += $row[ 'rate' ];

      $totals[ 'duration' ] += $row[ 'duration' ];
      $totals[ 'billableDuration' ] += $row[ 'billableDuration' ];
      $totals[ 'rate' ] += $row[ 'rate' ];
    }

    foreach ( $groups as $key => $group )
    {
      $groups[ $key ][ 'percent' ] = $totals[ 'duration' ] > 0 ? $group[ 'duration' ] / $totals[ 'duration' ] * 100 : 0.0;
      $groups[ $key ][ 'descriptions' ] = $this->sortByDuration( array_values( $descriptions[ $key ] ?? [] ) );
    }

    $groups = $this->applyColors( $this->sortByDuration( array_values( $groups ) ) );

    return [
      'granularity' => $granularity,
      'totals' => $totals,
      'buckets' => $buckets,
      'groups' => $groups,
      'chartSeries' => $this->createChartSeries( $groups, count( $buckets ) ),
    ];
  }

  /**
   * Gives each group a colour that can be told apart from the larger groups before it.
   *
   * @param array<int, Group> $groups Groups, largest first.
   * @return array<int, Group>
   */
  private function applyColors( array $groups ) : array
  {
    $colors = $this->chartColors->assign( array_column( $groups, 'color' ) );

    foreach ( $groups as $index => $group )
    {
      $groups[ $index ][ 'color' ] = $colors[ $index ][ 0 ];
      $groups[ $index ][ 'colorDark' ] = $colors[ $index ][ 1 ];
    }

    return $groups;
  }

  /**
   * Returns the series to chart: every group, or the largest ones plus one "other" series
   * that combines the rest when there are too many to tell apart.
   *
   * @param array<int, Group> $groups Groups, largest first.
   * @param int $bucketCount The number of buckets in the period.
   * @return array<int, ChartSeries>
   */
  private function createChartSeries( array $groups, int $bucketCount ) : array
  {
    $series = [];
    $other = [
      'name' => '',
      'other' => true,
      'color' => ChartColors::OTHER[ 0 ],
      'colorDark' => ChartColors::OTHER[ 1 ],
      'duration' => 0,
      'perBucket' => array_fill( 0, $bucketCount, 0 ),
    ];
    $keep = count( $groups ) <= self::MAX_CHART_SERIES ? count( $groups ) : self::MAX_CHART_SERIES - 1;

    foreach ( $groups as $index => $group )
    {
      if ( $index < $keep )
      {
        $series[] = [
          'name' => $group[ 'name' ],
          'other' => false,
          'color' => $group[ 'color' ],
          'colorDark' => $group[ 'colorDark' ],
          'duration' => $group[ 'duration' ],
          'perBucket' => $group[ 'perBucket' ],
        ];

        continue;
      }

      $other[ 'duration' ] += $group[ 'duration' ];
      foreach ( $group[ 'perBucket' ] as $bucket => $duration )
      {
        $other[ 'perBucket' ][ $bucket ] += $duration;
      }
    }

    if ( $other[ 'duration' ] > 0 )
    {
      $series[] = $other;
    }

    return $series;
  }

  /**
   * Returns whether a period is charted per day or per month.
   *
   * @param DateTimeImmutable $begin The first day of the period.
   * @param DateTimeImmutable $end The last day of the period.
   * @return string One of the GRANULARITY_ constants.
   */
  private function getGranularity( DateTimeImmutable $begin, DateTimeImmutable $end ) : string
  {
    $days = (int) $begin->diff( $end )->days + 1;

    return $days <= self::MAX_DAILY_BUCKETS ? self::GRANULARITY_DAY : self::GRANULARITY_MONTH;
  }

  /**
   * Returns the date format that identifies a bucket.
   *
   * @param string $granularity One of the GRANULARITY_ constants.
   * @return string
   */
  private function getBucketFormat( string $granularity ) : string
  {
    return $granularity === self::GRANULARITY_DAY ? 'Y-m-d' : 'Y-m';
  }

  /**
   * Creates one empty bucket for every day or month in the period.
   *
   * @param DateTimeImmutable $begin The first day of the period.
   * @param DateTimeImmutable $end The last day of the period.
   * @param string $granularity One of the GRANULARITY_ constants.
   * @param string $locale The locale used for the labels.
   * @return array<int, Bucket>
   */
  private function createBuckets( DateTimeImmutable $begin, DateTimeImmutable $end, string $granularity, string $locale ) : array
  {
    $isDaily = $granularity === self::GRANULARITY_DAY;
    $first = $isDaily ? $begin : $begin->modify( 'first day of this month' );
    $step = new DateInterval( $isDaily ? 'P1D' : 'P1M' );
    $formatter = new IntlDateFormatter( $locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE );
    $formatter->setPattern( $this->getLabelPattern( $begin, $end, $isDaily ) );

    $buckets = [];
    foreach ( new DatePeriod( $first, $step, $end->setTime( 23, 59, 59 ) ) as $date )
    {
      $buckets[] = [
        'key' => $date->format( $this->getBucketFormat( $granularity ) ),
        'label' => (string) $formatter->format( $date ),
        'duration' => 0,
      ];
    }

    return $buckets;
  }

  /**
   * Returns the ICU pattern for bucket labels.
   *
   * @param DateTimeImmutable $begin The first day of the period.
   * @param DateTimeImmutable $end The last day of the period.
   * @param bool $isDaily Whether the buckets are days.
   * @return string
   */
  private function getLabelPattern( DateTimeImmutable $begin, DateTimeImmutable $end, bool $isDaily ) : string
  {
    if ( !$isDaily )
    {
      return 'LLL yyyy';
    }

    return (int) $begin->diff( $end )->days < self::MAX_WEEKDAY_LABELS ? 'EEE d MMM' : 'd MMM';
  }

  /**
   * Returns the key that identifies the group a row belongs to.
   *
   * @param SummaryRow $row An aggregated row.
   * @param string $groupBy One of the SummaryQuery::GROUP_ constants.
   * @return string
   */
  private function getGroupKey( array $row, string $groupBy ) : string
  {
    return match ( $groupBy )
    {
      SummaryQuery::GROUP_CUSTOMER => 'c' . $row[ 'customerId' ],
      SummaryQuery::GROUP_ACTIVITY => 'a' . $row[ 'activityId' ],
      SummaryQuery::GROUP_USER => 'u' . $row[ 'userId' ],
      default => 'p' . $row[ 'projectId' ],
    };
  }

  /**
   * Creates an empty group, named and coloured after the first row that belongs to it.
   *
   * @param SummaryRow $row The first row of the group.
   * @param string $groupBy One of the SummaryQuery::GROUP_ constants.
   * @param int $bucketCount The number of buckets in the period.
   * @return Group
   */
  private function createGroup( array $row, string $groupBy, int $bucketCount ) : array
  {
    [ $name, $subtitle, $color ] = match ( $groupBy )
    {
      SummaryQuery::GROUP_CUSTOMER => [ $row[ 'customerName' ], '', $row[ 'customerColor' ] ],
      SummaryQuery::GROUP_ACTIVITY => [ $row[ 'activityName' ], '', $row[ 'activityColor' ] ],
      SummaryQuery::GROUP_USER => [ $row[ 'userAlias' ] !== '' ? $row[ 'userAlias' ] : $row[ 'userName' ], '', $row[ 'userColor' ] ],
      default => [ $row[ 'projectName' ], $row[ 'customerName' ], $row[ 'projectColor' ] !== '' ? $row[ 'projectColor' ] : $row[ 'customerColor' ] ],
    };

    return [
      'key' => $this->getGroupKey( $row, $groupBy ),
      'name' => $name,
      'subtitle' => $subtitle,
      'color' => $color !== '' ? $color : ( new Color() )->getRandomFromPalette( $name ),
      'colorDark' => '',
      'duration' => 0,
      'billableDuration' => 0,
      'rate' => 0.0,
      'percent' => 0.0,
      'perBucket' => array_fill( 0, $bucketCount, 0 ),
      'descriptions' => [],
    ];
  }

  /**
   * Sorts items by duration, longest first.
   *
   * @template T of array{duration: int}
   * @param array<int, T> $items The items to sort.
   * @return array<int, T>
   */
  private function sortByDuration( array $items ) : array
  {
    usort( $items, static fn( array $first, array $second ) : int => $second[ 'duration' ] <=> $first[ 'duration' ] );

    return $items;
  }
}
