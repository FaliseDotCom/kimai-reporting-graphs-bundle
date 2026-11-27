<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Service;

use App\Form\Model\DateRange;
use App\Form\Type\DateRangeType;
use DateTimeImmutable;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The period picked on Kimai's project details report, for the request that shows it. The
 * report's form sets it, and the project statistics read it to limit their figures.
 */
final class ProjectDetailsPeriod implements ResetInterface
{
  /**
   * Date format of the period's days, as stored in the timesheet's date column.
   *
   * @var string
   */
  private const DAY_FORMAT = 'Y-m-d';

  /**
   * The first day of the period, or null when the period has no start.
   *
   * @var DateTimeImmutable|null
   */
  private ?DateTimeImmutable $begin = null;

  /**
   * The last day of the period, or null when the period has no end.
   *
   * @var DateTimeImmutable|null
   */
  private ?DateTimeImmutable $end = null;

  /**
   * Whether the period is the project's own start and end date, rather than one the user picked.
   *
   * @var bool
   */
  private bool $projectDefault = false;

  /**
   * Sets the period from the report's date range. An empty range means all time.
   *
   * @param DateRange $range The picked date range.
   * @param bool $projectDefault Whether the range is the project's own start and end date.
   * @return void
   */
  public function set( DateRange $range, bool $projectDefault ) : void
  {
    $this->begin = $range->getBegin() === null ? null : DateTimeImmutable::createFromInterface( $range->getBegin() );
    $this->end = $range->getEnd() === null ? null : DateTimeImmutable::createFromInterface( $range->getEnd() );
    $this->projectDefault = $projectDefault;
  }

  /**
   * Returns whether a period limits the report.
   *
   * @return bool
   */
  public function isActive() : bool
  {
    return $this->begin !== null || $this->end !== null;
  }

  /**
   * Returns whether the period is the project's own start and end date.
   *
   * @return bool
   */
  public function isProjectDefault() : bool
  {
    return $this->projectDefault;
  }

  /**
   * Returns the first day of the period as Y-m-d, or an empty string when it has no start.
   *
   * @return string
   */
  public function getBeginDay() : string
  {
    return $this->begin?->format( self::DAY_FORMAT ) ?? '';
  }

  /**
   * Returns the last day of the period as Y-m-d, or an empty string when it has no end.
   *
   * @return string
   */
  public function getEndDay() : string
  {
    return $this->end?->format( self::DAY_FORMAT ) ?? '';
  }

  /**
   * Returns whether the period includes days of the given year.
   *
   * @param int $year The year.
   * @return bool
   */
  public function overlapsYear( int $year ) : bool
  {
    $startsAfter = $this->begin !== null && (int) $this->begin->format( 'Y' ) > $year;
    $endsBefore = $this->end !== null && (int) $this->end->format( 'Y' ) < $year;

    return !$startsAfter && !$endsBefore;
  }

  /**
   * Returns the period in the format Kimai's date range field accepts in links, or an empty
   * string when the period is not complete.
   *
   * @return string
   */
  public function toQueryString() : string
  {
    if ( $this->begin === null || $this->end === null )
    {
      return '';
    }

    return DateRangeType::formatQueryString( $this->begin, $this->end );
  }

  /**
   * Forgets the period, so a long-running worker does not carry it to the next request.
   *
   * @return void
   */
  public function reset() : void
  {
    $this->begin = null;
    $this->end = null;
    $this->projectDefault = false;
  }
}
