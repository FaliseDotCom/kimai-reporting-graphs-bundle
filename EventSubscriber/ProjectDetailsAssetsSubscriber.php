<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\EventSubscriber;

use App\Event\ThemeEvent;
use KimaiPlugin\ReportingGraphsBundle\ReportingGraphsBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * Loads the plugin's stylesheet and script on Kimai's project details report, which place the
 * period field in the report's filter bar.
 */
final class ProjectDetailsAssetsSubscriber implements EventSubscriberInterface
{
  public const ROUTE = 'report_project_details';

  /**
   * @param RequestStack $requestStack Tells which page is shown.
   * @param Environment $twig Renders the asset tags.
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
   * Adds the asset tags to the project details report.
   *
   * @param ThemeEvent $event The event that collects content for the top of the page.
   * @return void
   */
  public function onContentStart( ThemeEvent $event ) : void
  {
    if ( $this->requestStack->getMainRequest()?->attributes->get( '_route' ) !== self::ROUTE )
    {
      return;
    }

    $event->addContent( $this->twig->render( '@ReportingGraphs/project_details_assets.html.twig', [
      'asset_version' => ReportingGraphsBundle::getAssetVersion(),
    ] ) );
  }
}
