<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\EventSubscriber;

use App\Widget\WidgetService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Links Kimai's duration cards on the dashboard to the report that shows the same period.
 *
 * Kimai's counter cards already support a "route" option, which turns their icon into a link;
 * this sets it on the shared widgets for the current request only, before the dashboard clones
 * them, so nothing is stored. The cards that count everyone's hours (Today, This week, ...)
 * open the all-users reports; the "My working hours ..." cards open the user's own reports.
 * A day has no report of its own, so the today cards open the week.
 */
final class DashboardLinksSubscriber implements EventSubscriberInterface
{
  /**
   * Kimai's dashboard route.
   *
   * @var string
   */
  private const DASHBOARD_ROUTE = 'dashboard';

  /**
   * Widget option that holds the route the card links to.
   *
   * @var string
   */
  private const ROUTE_OPTION = 'route';

  /**
   * Cards with everyone's hours, by the all-users report they link to. Needs report:other.
   *
   * @var array<string, string>
   */
  private const ALL_USERS_CARDS = [
    'DurationToday' => 'report_weekly_users',
    'DurationWeek' => 'report_weekly_users',
    'DurationMonth' => 'report_monthly_users',
    'DurationYear' => 'report_yearly_users',
  ];

  /**
   * Cards with the user's own hours, by the user report they link to. Needs report:user.
   *
   * @var array<string, string>
   */
  private const OWN_CARDS = [
    'userDurationToday' => UserReportChartsSubscriber::ROUTE_WEEK,
    'userDurationWeek' => UserReportChartsSubscriber::ROUTE_WEEK,
    'userDurationMonth' => UserReportChartsSubscriber::ROUTE_MONTH,
    'userDurationYear' => UserReportChartsSubscriber::ROUTE_YEAR,
  ];

  /**
   * @param WidgetService $widgets Kimai's registered dashboard widgets.
   * @param AuthorizationCheckerInterface $security Checks whether the reports may be opened.
   */
  public function __construct(
    private readonly WidgetService $widgets,
    private readonly AuthorizationCheckerInterface $security
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
      KernelEvents::CONTROLLER => 'onController',
    ];
  }

  /**
   * Sets the links before the dashboard renders its cards.
   *
   * @param ControllerEvent $event The event before Kimai's controller runs.
   * @return void
   */
  public function onController( ControllerEvent $event ) : void
  {
    if ( !$event->isMainRequest() || $event->getRequest()->attributes->get( '_route' ) !== self::DASHBOARD_ROUTE )
    {
      return;
    }

    if ( $this->security->isGranted( 'report:other' ) )
    {
      $this->link( self::ALL_USERS_CARDS );
    }

    if ( $this->security->isGranted( 'report:user' ) )
    {
      $this->link( self::OWN_CARDS );
    }
  }

  /**
   * Sets the route option of the given cards.
   *
   * @param array<string, string> $cards Routes by widget ID.
   * @return void
   */
  private function link( array $cards ) : void
  {
    foreach ( $cards as $id => $route )
    {
      if ( $this->widgets->hasWidget( $id ) )
      {
        $this->widgets->getWidget( $id )->setOption( self::ROUTE_OPTION, $route );
      }
    }
  }
}
