<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Service;

use App\Configuration\SystemConfiguration;
use App\Entity\User;
use App\Repository\TimesheetRepository;
use App\Timesheet\DateTimeFactory;
use DateTimeImmutable;
use DateTimeInterface;
use KimaiPlugin\ReportingGraphsBundle\EventSubscriber\UserReportChartsSubscriber;

/**
 * Sums the hours of today, a week, its month and its year for the dashboard widgets, each with
 * the report that shows the same period: the user's own report, or the one for all users.
 *
 * @phpstan-type Total array{duration: int, route: string, date: string}
 * @phpstan-type Totals array{today: Total, week: Total, month: Total, year: Total, financialYear: bool}
 */
final class HoursTotals
{
  /**
   * Date format of the report links.
   *
   * @var string
   */
  private const DATE_FORMAT = 'Y-m-d';

  /**
   * The user reports by period; a day has no report of its own, so today opens the week.
   *
   * @var array<string, string>
   */
  private const OWN_ROUTES = [
    'today' => UserReportChartsSubscriber::ROUTE_WEEK,
    'week' => UserReportChartsSubscriber::ROUTE_WEEK,
    'month' => UserReportChartsSubscriber::ROUTE_MONTH,
    'year' => UserReportChartsSubscriber::ROUTE_YEAR,
  ];

  /**
   * The reports for all users by period.
   *
   * @var array<string, string>
   */
  private const ALL_USERS_ROUTES = [
    'today' => 'report_weekly_users',
    'week' => 'report_weekly_users',
    'month' => 'report_monthly_users',
    'year' => 'report_yearly_users',
  ];

  /**
   * @param TimesheetRepository $repository Sums the durations.
   * @param SystemConfiguration $configuration Provides the start of the financial year.
   */
  public function __construct(
    private readonly TimesheetRepository $repository,
    private readonly SystemConfiguration $configuration
  )
  {
  }

  /**
   * Returns the totals of today and of the week, month and (financial) year of a day.
   *
   * @param User $viewer The logged-in user, whose time zone and first weekday count.
   * @param User|null $user Whose hours to count, or null for all users.
   * @param DateTimeInterface|null $day A day in the week to show, or null for this week.
   * @return Totals
   */
  public function create( User $viewer, ?User $user, ?DateTimeInterface $day = null ) : array
  {
    $factory = DateTimeFactory::createByUser( $viewer );
    $now = $factory->createDateTime();
    $weekBegin = DateTimeImmutable::createFromMutable( $factory->getStartOfWeek( $day ?? $now ) );
    $monthBegin = $factory->getStartOfMonth( $weekBegin );
    $routes = $user === null ? self::ALL_USERS_ROUTES : self::OWN_ROUTES;

    $financialYear = $this->configuration->getFinancialYearStart();
    $yearBegin = $financialYear === null ? $factory->createStartOfYear( $weekBegin ) : $factory->createStartOfFinancialYear( $financialYear );
    $yearEnd = $financialYear === null ? $factory->createEndOfYear( $weekBegin ) : $factory->createEndOfFinancialYear( $yearBegin );

    return [
      'today' => $this->total( $user, $factory->createDateTime( '00:00:00' ), $factory->createDateTime( '23:59:59' ), $routes[ 'today' ], $now ),
      'week' => $this->total( $user, $weekBegin, $factory->getEndOfWeek( $weekBegin ), $routes[ 'week' ], $weekBegin ),
      'month' => $this->total( $user, $monthBegin, $factory->getEndOfMonth( $weekBegin ), $routes[ 'month' ], $monthBegin ),
      'year' => $this->total( $user, $yearBegin, $yearEnd, $routes[ 'year' ], $yearBegin ),
      'financialYear' => $financialYear !== null,
    ];
  }

  /**
   * Describes one total: its duration and the report that shows it.
   *
   * @param User|null $user Whose hours to count, or null for all users.
   * @param DateTimeInterface $begin Start of the period.
   * @param DateTimeInterface $end End of the period.
   * @param string $route The report to link to.
   * @param DateTimeInterface $date The date the report opens on.
   * @return Total
   */
  private function total( ?User $user, DateTimeInterface $begin, DateTimeInterface $end, string $route, DateTimeInterface $date ) : array
  {
    return [
      'duration' => $this->repository->getDurationForTimeRange( $begin, $end, $user ),
      'route' => $route,
      'date' => $date->format( self::DATE_FORMAT ),
    ];
  }
}
