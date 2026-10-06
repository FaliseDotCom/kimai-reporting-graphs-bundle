<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\EventSubscriber;

use App\Event\ReportingEvent;
use App\Reporting\Report;
use KimaiPlugin\ReportingGraphsBundle\Controller\SummaryController;
use KimaiPlugin\ReportingGraphsBundle\ReportingGraphsBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds the summary report to Kimai's reporting overview.
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
   * @param AuthorizationCheckerInterface $security Checks whether the user may see the report.
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
   * Adds the report for users who may see their own reports.
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

    $event->addReport( new Report(
      self::REPORT_ID,
      SummaryController::ROUTE,
      'summary.title',
      self::REPORT_ICON,
      ReportingGraphsBundle::TRANSLATION_DOMAIN
    ) );
  }
}
