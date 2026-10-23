<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\EventSubscriber;

use App\Event\ReportingEvent;
use App\Reporting\Report;
use App\Reporting\ReportInterface;
use KimaiPlugin\ReportingGraphsBundle\Controller\ReportUsersQuarterController;
use KimaiPlugin\ReportingGraphsBundle\Controller\SummaryController;
use KimaiPlugin\ReportingGraphsBundle\Controller\UserQuarterController;
use KimaiPlugin\ReportingGraphsBundle\ReportingGraphsBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds the quarterly reports and the summary report to Kimai's reporting overview.
 */
final class ReportingSubscriber implements EventSubscriberInterface
{
  /**
   * The ID of the report in the reporting overview.
   *
   * @var string
   */
  private const REPORT_ID = 'summary';

  /**
   * The icon shown next to the report.
   *
   * @var string
   */
  private const REPORT_ICON = 'fas fa-chart-pie';

  /**
   * The ID of the quarterly report for one user in the reporting overview.
   *
   * @var string
   */
  private const QUARTER_USER_ID = 'quarter_by_user';

  /**
   * The ID of the quarterly report for all users in the reporting overview.
   *
   * @var string
   */
  private const QUARTER_USERS_ID = 'quarterly_users_list';

  /**
   * Kimai's icon for its reports about one user, so the quarterly one looks the same.
   *
   * @var string
   */
  private const USER_ICON = 'user';

  /**
   * Kimai's icon for its reports about all users, so the quarterly one looks the same.
   *
   * @var string
   */
  private const USERS_ICON = 'users';

  /**
   * The Kimai report each quarterly report follows in the overview, so it sits between the
   * monthly and yearly report of the same kind.
   *
   * @var array<string, string>
   */
  private const PLACE_AFTER = [
    self::QUARTER_USER_ID => 'month_by_user',
    self::QUARTER_USERS_ID => 'monthly_users_list',
  ];

  /**
   * The private property of ReportingEvent that holds the reports, keyed by their ID.
   *
   * @var string
   */
  private const EVENT_REPORTS_PROPERTY = 'reports';

  /**
   * @param AuthorizationCheckerInterface $security Checks whether the user may see the reports.
   */
  public function __construct( private readonly AuthorizationCheckerInterface $security )
  {
  }

  /**
   * Returns the events this subscriber listens to.
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents() : array
  {
    return [
      ReportingEvent::class => 'onReporting',
    ];
  }

  /**
   * Adds the reports the user may see: the quarterly report for one user and the summary with
   * report:user, and the quarterly report for all users with report:other as well.
   *
   * @param ReportingEvent $event The event that collects the available reports.
   * @return void
   */
  public function onReporting( ReportingEvent $event ) : void
  {
    if ( !$this->security->isGranted( 'report:user' ) )
    {
      return;
    }

    $event->addReport( $this->createReport( self::QUARTER_USER_ID, UserQuarterController::ROUTE, UserQuarterController::TITLE, self::USER_ICON ) );

    if ( $this->security->isGranted( 'report:other' ) )
    {
      $event->addReport( $this->createReport( self::QUARTER_USERS_ID, ReportUsersQuarterController::ROUTE, ReportUsersQuarterController::TITLE, self::USERS_ICON ) );
    }

    $event->addReport( $this->createReport( self::REPORT_ID, SummaryController::ROUTE, 'summary.title', self::REPORT_ICON ) );
    $this->placeQuarterlyReports( $event );
  }

  /**
   * Moves the quarterly reports next to Kimai's monthly ones. ReportingEvent can only append
   * reports, so this rewrites its private list; when a Kimai release renames that property the
   * reports simply stay at the end.
   *
   * @param ReportingEvent $event The event that collects the available reports.
   * @return void
   */
  private function placeQuarterlyReports( ReportingEvent $event ) : void
  {
    if ( !property_exists( $event, self::EVENT_REPORTS_PROPERTY ) )
    {
      return;
    }

    $property = new \ReflectionProperty( ReportingEvent::class, self::EVENT_REPORTS_PROPERTY );
    $property->setValue( $event, $this->sortReports( $event->getReports() ) );
  }

  /**
   * Orders the reports so each quarterly report follows its Kimai report, keyed by report ID as
   * ReportingEvent keeps them. A quarterly report whose Kimai report is missing keeps its place.
   *
   * @param array<ReportInterface> $reports The reports in the order they were added.
   * @return array<string, ReportInterface>
   */
  private function sortReports( array $reports ) : array
  {
    $byId = [];

    foreach ( $reports as $report )
    {
      $byId[ $report->getId() ] = $report;
    }

    $sorted = [];

    foreach ( $byId as $id => $report )
    {
      $anchor = self::PLACE_AFTER[ $id ] ?? '';

      if ( $anchor !== '' && isset( $byId[ $anchor ] ) )
      {
        continue;
      }

      $sorted[ $id ] = $report;
      $follower = array_search( $id, self::PLACE_AFTER, true );

      if ( $follower !== false && isset( $byId[ $follower ] ) )
      {
        $sorted[ $follower ] = $byId[ $follower ];
      }
    }

    return $sorted;
  }

  /**
   * Creates an entry for the reporting overview, named in this plugin's translations.
   *
   * @param string $id The ID of the report.
   * @param string $route The route that shows the report.
   * @param string $label The translation key of the report's name.
   * @param string $icon The icon shown next to the report.
   * @return Report
   */
  private function createReport( string $id, string $route, string $label, string $icon ) : Report
  {
    return new Report( $id, $route, $label, $icon, ReportingGraphsBundle::TRANSLATION_DOMAIN );
  }
}
