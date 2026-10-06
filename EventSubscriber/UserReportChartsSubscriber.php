<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\EventSubscriber;

use App\Configuration\SystemConfiguration;
use App\Entity\User;
use App\Event\ThemeEvent;
use App\Timesheet\DateTimeFactory;
use DateTimeImmutable;
use DateTimeInterface;
use KimaiPlugin\ReportingGraphsBundle\Model\SummaryQuery;
use KimaiPlugin\ReportingGraphsBundle\ReportingGraphsBundle;
use KimaiPlugin\ReportingGraphsBundle\Repository\SummaryRepository;
use KimaiPlugin\ReportingGraphsBundle\Service\DetailLinks;
use KimaiPlugin\ReportingGraphsBundle\Service\ReportViewLinks;
use KimaiPlugin\ReportingGraphsBundle\Service\SummaryBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

/**
 * Adds a bar chart per day (or month) and a doughnut chart per project to Kimai's weekly,
 * monthly and yearly report for one user, and links its customers and projects to their
 * detail pages, and adds buttons to switch to the other two reports.
 */
final class UserReportChartsSubscriber implements EventSubscriberInterface
{
  public const ROUTE_WEEK = 'report_user_week';
  public const ROUTE_MONTH = 'report_user_month';
  public const ROUTE_YEAR = 'report_user_year';

  /**
   * @param RequestStack $requestStack Tells which report, period and user are shown.
   * @param AuthorizationCheckerInterface $security Checks whether other users may be shown.
   * @param SystemConfiguration $configuration Provides the start of the financial year.
   * @param SummaryRepository $repository Reads the aggregated time records.
   * @param SummaryBuilder $builder Turns the records into chart data.
   * @param DetailLinks $detailLinks Finds the customer and project pages the viewer may open.
   * @param ReportViewLinks $viewLinks Links to the other two user reports.
   * @param Environment $twig Renders the charts.
   */
  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly AuthorizationCheckerInterface $security,
    private readonly SystemConfiguration $configuration,
    private readonly SummaryRepository $repository,
    private readonly SummaryBuilder $builder,
    private readonly DetailLinks $detailLinks,
    private readonly ReportViewLinks $viewLinks,
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
   * Adds the charts to the user reports.
   *
   * @param ThemeEvent $event The event that collects content for the top of the page.
   * @return void
   */
  public function onContentStart( ThemeEvent $event ) : void
  {
    $viewer = $event->getUser();
    $request = $this->requestStack->getMainRequest();
    $route = $request?->attributes->get( '_route' );

    if ( !$viewer instanceof User || $request === null || !in_array( $route, [ self::ROUTE_WEEK, self::ROUTE_MONTH, self::ROUTE_YEAR ], true ) )
    {
      return;
    }

    if ( !$this->security->isGranted( 'report:user' ) )
    {
      return;
    }

    $userId = $this->getUserId( $request, $viewer );
    [ $begin, $end ] = $this->getPeriod( (string) $route, $request, $viewer );
    $rows = $this->repository->findRows( $begin, $end, [ $userId ] );

    $event->addContent( $this->twig->render( '@ReportingGraphs/report_charts.html.twig', [
      'summary' => $this->builder->build( $rows, $begin, $end, SummaryQuery::GROUP_PROJECT, $request->getLocale() ),
      'detail_links' => $this->detailLinks->create( $rows ),
      'view_links' => $this->viewLinks->create( (string) $route, $begin, $end, $request ),
      'asset_version' => ReportingGraphsBundle::getAssetVersion(),
    ] ) );
  }

  /**
   * Returns the ID of the user the report shows: the requested user when the viewer may see
   * them, otherwise the viewer.
   *
   * @param Request $request The report request.
   * @param User $viewer The logged-in user.
   * @return int
   */
  private function getUserId( Request $request, User $viewer ) : int
  {
    $viewerId = (int) $viewer->getId();
    $requested = $request->query->getInt( 'user' );

    if ( $requested === 0 || $requested === $viewerId || !$this->security->isGranted( 'report:other' ) )
    {
      return $viewerId;
    }

    return in_array( $requested, $this->repository->findVisibleUserIds( $viewer ), true ) ? $requested : $viewerId;
  }

  /**
   * Returns the first and last day of the report period, the same way Kimai's report does.
   *
   * @param string $route The report route.
   * @param Request $request The report request.
   * @param User $viewer The logged-in user.
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
   */
  private function getPeriod( string $route, Request $request, User $viewer ) : array
  {
    $factory = DateTimeFactory::createByUser( $viewer );
    $date = $this->getRequestedDate( $request, $factory );

    if ( $route === self::ROUTE_WEEK )
    {
      return $this->toDays( $factory->getStartOfWeek( $date ), $factory->getEndOfWeek( $date ) );
    }

    if ( $route === self::ROUTE_MONTH )
    {
      return $this->toDays( $factory->getStartOfMonth( $date ), $factory->getEndOfMonth( $date ) );
    }

    $start = $date ?? $this->getDefaultYearStart( $factory );

    return $this->toDays( $start, $factory->createEndOfFinancialYear( $start ) );
  }

  /**
   * Returns the date picked in the report form, or null when none or an invalid one was sent.
   *
   * @param Request $request The report request.
   * @param DateTimeFactory $factory Creates dates in the viewer's time zone.
   * @return DateTimeInterface|null
   */
  private function getRequestedDate( Request $request, DateTimeFactory $factory ) : ?DateTimeInterface
  {
    $value = (string) $request->query->get( 'date' );
    $date = $value === '' ? false : $factory->createDateTimeFromFormat( '!Y-m-d', $value );

    return $date === false ? null : $date;
  }

  /**
   * Returns the start of the current year, or of the financial year when one is configured.
   *
   * @param DateTimeFactory $factory Creates dates in the viewer's time zone.
   * @return DateTimeInterface
   */
  private function getDefaultYearStart( DateTimeFactory $factory ) : DateTimeInterface
  {
    $financialYear = $this->configuration->getFinancialYearStart();

    return $financialYear === null ? $factory->createStartOfYear() : $factory->createStartOfFinancialYear( $financialYear );
  }

  /**
   * Returns two dates as calendar days without time.
   *
   * @param DateTimeInterface $begin The first day.
   * @param DateTimeInterface $end The last day.
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
   */
  private function toDays( DateTimeInterface $begin, DateTimeInterface $end ) : array
  {
    return [
      new DateTimeImmutable( $begin->format( 'Y-m-d' ) ),
      new DateTimeImmutable( $end->format( 'Y-m-d' ) ),
    ];
  }
}
