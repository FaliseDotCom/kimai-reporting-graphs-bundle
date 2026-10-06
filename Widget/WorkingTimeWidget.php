<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Widget;

use App\Timesheet\DateTimeFactory;
use App\Widget\Type\AbstractWidget;
use App\Widget\WidgetInterface;
use DateTimeImmutable;
use KimaiPlugin\ReportingGraphsBundle\Model\SummaryQuery;
use KimaiPlugin\ReportingGraphsBundle\ReportingGraphsBundle;
use KimaiPlugin\ReportingGraphsBundle\Repository\SummaryRepository;
use KimaiPlugin\ReportingGraphsBundle\Service\HoursTotals;
use KimaiPlugin\ReportingGraphsBundle\Service\SummaryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Dashboard widget with the user's working hours of one week, in the style of the reporting
 * charts: a bar chart per day stacked by project, a doughnut chart of the projects' shares,
 * and the totals of today, the week, the month and the year, linked to the user reports.
 * Arrows step to the previous and next week.
 *
 * @phpstan-import-type Summary from SummaryBuilder
 * @phpstan-import-type Total from HoursTotals
 * @phpstan-type WorkingTimeData array{
 *   summary: Summary,
 *   begin: DateTimeImmutable,
 *   end: DateTimeImmutable,
 *   previous: string,
 *   next: string,
 *   today: Total,
 *   week: Total,
 *   month: Total,
 *   year: Total,
 *   financialYear: bool,
 *   canViewReports: bool,
 *   assetVersion: string
 * }
 */
final class WorkingTimeWidget extends AbstractWidget
{
  /**
   * Query parameter that holds the first day of the week shown.
   *
   * @var string
   */
  public const WEEK_PARAMETER = 'working_time_week';

  /**
   * Date format of the week parameter and of the report links.
   *
   * @var string
   */
  private const DATE_FORMAT = 'Y-m-d';

  /**
   * @param RequestStack $requestStack Tells which week is asked for, and the page locale.
   * @param SummaryRepository $repository Reads the aggregated time records.
   * @param SummaryBuilder $builder Turns the records into chart data.
   * @param HoursTotals $totals Sums today, the week, its month and its year.
   * @param AuthorizationCheckerInterface $security Checks whether the reports may be opened.
   */
  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly SummaryRepository $repository,
    private readonly SummaryBuilder $builder,
    private readonly HoursTotals $totals,
    private readonly AuthorizationCheckerInterface $security
  )
  {
  }

  /**
   * Returns the unique ID of the widget.
   *
   * @return string
   */
  public function getId() : string
  {
    return 'ReportingGraphsWorkingTime';
  }

  /**
   * Returns the translation key of the title.
   *
   * @return string
   */
  public function getTitle() : string
  {
    return 'widget.working_time';
  }

  /**
   * Returns the translation domain of the title.
   *
   * @return string
   */
  public function getTranslationDomain() : string
  {
    return ReportingGraphsBundle::TRANSLATION_DOMAIN;
  }

  /**
   * Returns the width: the whole dashboard.
   *
   * @return int
   */
  public function getWidth() : int
  {
    return WidgetInterface::WIDTH_FULL;
  }

  /**
   * Returns the height, the same as Kimai's own working hours chart.
   *
   * @return int
   */
  public function getHeight() : int
  {
    return WidgetInterface::HEIGHT_MAXIMUM;
  }

  /**
   * Returns the permissions that show the widget: users who see their own records.
   *
   * @return array<int, string>
   */
  public function getPermissions() : array
  {
    return [ 'view_own_timesheet' ];
  }

  /**
   * Returns the template of the widget.
   *
   * @return string
   */
  public function getTemplateName() : string
  {
    return '@ReportingGraphs/widget_working_time.html.twig';
  }

  /**
   * Returns the chart data and totals for the requested week, or the current one.
   *
   * @param array<string, string|bool|int|null|array<string, mixed>> $options Not used.
   * @return WorkingTimeData
   */
  public function getData( array $options = [] ) : mixed
  {
    $user = $this->getUser();
    $factory = DateTimeFactory::createByUser( $user );
    $request = $this->requestStack->getMainRequest();
    $requested = $factory->createDateTimeFromFormat( '!' . self::DATE_FORMAT, (string) $request?->query->get( self::WEEK_PARAMETER ) );

    $weekBegin = DateTimeImmutable::createFromMutable( $factory->getStartOfWeek( $requested === false ? $factory->createDateTime() : $requested ) );
    $begin = new DateTimeImmutable( $weekBegin->format( self::DATE_FORMAT ) );
    $end = new DateTimeImmutable( $factory->getEndOfWeek( $weekBegin )->format( self::DATE_FORMAT ) );
    $rows = $this->repository->findRows( $begin, $end, [ (int) $user->getId() ] );

    return $this->totals->create( $user, $user, $weekBegin ) + [
      'summary' => $this->builder->build( $rows, $begin, $end, SummaryQuery::GROUP_PROJECT, $request?->getLocale() ?? 'en' ),
      'begin' => $begin,
      'end' => $end,
      'previous' => $weekBegin->modify( '-7 days' )->format( self::DATE_FORMAT ),
      'next' => $weekBegin->modify( '+7 days' )->format( self::DATE_FORMAT ),
      'canViewReports' => $this->security->isGranted( 'report:user' ),
      'assetVersion' => ReportingGraphsBundle::getAssetVersion(),
    ];
  }
}
