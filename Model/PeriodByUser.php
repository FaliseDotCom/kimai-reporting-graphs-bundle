<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Model;

use App\Form\Model\DateRange;
use App\Reporting\DateByUser;

/**
 * The filters of the period report for one user: a free date range, user and sum type.
 */
final class PeriodByUser extends DateByUser
{
  /**
   * The period the report shows.
   *
   * @var DateRange
   */
  private DateRange $dateRange;

  /**
   * Creates the filters for the given default period.
   *
   * @param DateRange $dateRange The period shown when no other period is requested.
   */
  public function __construct( DateRange $dateRange )
  {
    $this->dateRange = $dateRange;
  }

  /**
   * Returns the requested period.
   *
   * @return DateRange
   */
  public function getDateRange() : DateRange
  {
    return $this->dateRange;
  }

  /**
   * Sets the requested period.
   *
   * @param DateRange $dateRange The period to show.
   * @return void
   */
  public function setDateRange( DateRange $dateRange ) : void
  {
    $this->dateRange = $dateRange;
  }
}
