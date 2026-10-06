<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Widget;

use App\Widget\Type\AbstractWidget;
use App\Widget\WidgetInterface;
use KimaiPlugin\ReportingGraphsBundle\ReportingGraphsBundle;
use KimaiPlugin\ReportingGraphsBundle\Service\HoursTotals;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Dashboard widget with the hours of today, this week, this month and this year in Kimai's
 * card style, under clear headings: "My hours" for everyone, and "All users" with everyone's
 * hours for users who may see other people's records. Each card opens the matching report.
 *
 * It replaces Kimai's separate duration cards, whose titles do not say whose hours they count.
 *
 * @phpstan-import-type Totals from HoursTotals
 * @phpstan-type HoursOverviewData array{own: Totals, all: Totals|null, ownLinks: bool, allLinks: bool}
 */
final class HoursOverviewWidget extends AbstractWidget
{
  /**
   * @param HoursTotals $totals Sums today, this week, this month and this year.
   * @param AuthorizationCheckerInterface $security Checks who may see everyone's hours and the reports.
   */
  public function __construct(
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
    return 'ReportingGraphsHoursOverview';
  }

  /**
   * Returns the translation key of the title.
   *
   * @return string
   */
  public function getTitle() : string
  {
    return 'widget.hours_overview';
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
   * Returns the width: the whole dashboard, four cards in a row.
   *
   * @return int
   */
  public function getWidth() : int
  {
    return WidgetInterface::WIDTH_FULL;
  }

  /**
   * Returns the height: two rows of cards with their headings.
   *
   * @return int
   */
  public function getHeight() : int
  {
    return WidgetInterface::HEIGHT_MEDIUM;
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
    return '@ReportingGraphs/widget_hours_overview.html.twig';
  }

  /**
   * Returns the user's own totals, and everyone's when the user may see other people's records.
   *
   * @param array<string, string|bool|int|null|array<string, mixed>> $options Not used.
   * @return HoursOverviewData
   */
  public function getData( array $options = [] ) : mixed
  {
    $user = $this->getUser();

    return [
      'own' => $this->totals->create( $user, $user ),
      'all' => $this->security->isGranted( 'view_other_timesheet' ) ? $this->totals->create( $user, null ) : null,
      'ownLinks' => $this->security->isGranted( 'report:user' ),
      'allLinks' => $this->security->isGranted( 'report:other' ),
    ];
  }
}
