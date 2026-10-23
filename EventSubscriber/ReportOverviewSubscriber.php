<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\EventSubscriber;

use App\Event\ThemeEvent;
use KimaiPlugin\ReportingGraphsBundle\Controller\ReportUsersQuarterController;
use KimaiPlugin\ReportingGraphsBundle\ReportingGraphsBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * Makes the weekly, monthly, quarterly and yearly reports easier to tell apart on Kimai's
 * reporting overview: their icon gets a period letter and the period in their name is bold.
 */
final class ReportOverviewSubscriber implements EventSubscriberInterface
{
  public const ROUTE_USERS_WEEK = 'report_weekly_users';
  public const ROUTE_USERS_MONTH = 'report_monthly_users';
  public const ROUTE_USERS_YEAR = 'report_yearly_users';

  /**
   * Kimai's reporting overview route.
   *
   * @var string
   */
  private const OVERVIEW_ROUTE = 'reporting';

  /**
   * The period of each report, which picks the translation of its letter.
   *
   * @var array<string, string>
   */
  private const PERIODS = [
    UserReportChartsSubscriber::ROUTE_WEEK => 'week',
    UserReportChartsSubscriber::ROUTE_MONTH => 'month',
    UserReportChartsSubscriber::ROUTE_QUARTER => 'quarter',
    UserReportChartsSubscriber::ROUTE_YEAR => 'year',
    self::ROUTE_USERS_WEEK => 'week',
    self::ROUTE_USERS_MONTH => 'month',
    ReportUsersQuarterController::ROUTE => 'quarter',
    self::ROUTE_USERS_YEAR => 'year',
  ];

  /**
   * @param RequestStack $requestStack Tells which page is shown.
   * @param Environment $twig Renders the period letters and the assets that place them.
   */
  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly Environment $twig
  )
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
      ThemeEvent::CONTENT_START => 'onContentStart',
    ];
  }

  /**
   * Adds the period letters to the reporting overview, for reporting.js to place.
   *
   * @param ThemeEvent $event The event that collects content for the top of the page.
   * @return void
   */
  public function onContentStart( ThemeEvent $event ) : void
  {
    if ( $this->requestStack->getMainRequest()?->attributes->get( '_route' ) !== self::OVERVIEW_ROUTE )
    {
      return;
    }

    $event->addContent( $this->twig->render( '@ReportingGraphs/report_overview.html.twig', [
      'periods' => self::PERIODS,
      'asset_version' => ReportingGraphsBundle::getAssetVersion(),
    ] ) );
  }
}
