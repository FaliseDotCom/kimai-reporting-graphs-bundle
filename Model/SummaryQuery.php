<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Model;

use App\Entity\User;
use App\Form\Model\DateRange;

/**
 * The filters of the summary report: period, user and grouping.
 */
final class SummaryQuery
{
  public const GROUP_PROJECT = 'project';
  public const GROUP_CUSTOMER = 'customer';
  public const GROUP_ACTIVITY = 'activity';
  public const GROUP_USER = 'user';

  /**
   * The period to summarise.
   *
   * @var DateRange
   */
  private DateRange $dateRange;

  /**
   * The user to summarise, or null for every user the viewer may see.
   *
   * @var User|null
   */
  private ?User $user = null;

  /**
   * How time is grouped in the charts and the table; one of the GROUP_ constants.
   *
   * @var string
   */
  private string $groupBy = self::GROUP_PROJECT;

  /**
   * Creates a query for the given default period.
   *
   * @param DateRange $dateRange The period shown when no other period is requested.
   */
  public function __construct( DateRange $dateRange )
  {
    $this->dateRange = $dateRange;
  }

  /**
   * Returns every supported grouping.
   *
   * @return array<int, string>
   */
  public static function getGroupings() : array
  {
    return [
      self::GROUP_PROJECT,
      self::GROUP_CUSTOMER,
      self::GROUP_ACTIVITY,
      self::GROUP_USER,
    ];
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
   * @param DateRange $dateRange The period to summarise.
   * @return void
   */
  public function setDateRange( DateRange $dateRange ) : void
  {
    $this->dateRange = $dateRange;
  }

  /**
   * Returns the selected user, or null for all visible users.
   *
   * @return User|null
   */
  public function getUser() : ?User
  {
    return $this->user;
  }

  /**
   * Sets the selected user; null selects all visible users.
   *
   * @param User|null $user The user to summarise.
   * @return void
   */
  public function setUser( ?User $user ) : void
  {
    $this->user = $user;
  }

  /**
   * Returns the selected grouping.
   *
   * @return string
   */
  public function getGroupBy() : string
  {
    return $this->groupBy;
  }

  /**
   * Sets the grouping, falling back to project grouping for unknown values.
   *
   * @param string|null $groupBy One of the GROUP_ constants.
   * @return void
   */
  public function setGroupBy( ?string $groupBy ) : void
  {
    $this->groupBy = in_array( $groupBy, self::getGroupings(), true ) ? $groupBy : self::GROUP_PROJECT;
  }
}
