<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Service;

use App\Configuration\SystemConfiguration;
use DateTimeImmutable;
use KimaiPlugin\ReportingGraphsBundle\EventSubscriber\UserReportChartsSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the links between the weekly, monthly and yearly report for one user, so each report
 * can switch to the other two for the same user and a matching period.
 *
 * @phpstan-type ViewLink array{label: string, url: string}
 */
final class ReportViewLinks
{
  /**
   * The user reports, in the order their buttons are shown, with their translation key.
   *
   * @var array<string, string>
   */
  private const VIEWS = [
    UserReportChartsSubscriber::ROUTE_WEEK => 'summary.view.week',
    UserReportChartsSubscriber::ROUTE_MONTH => 'summary.view.month',
    UserReportChartsSubscriber::ROUTE_YEAR => 'summary.view.year',
  ];

  /**
   * Report filters that are carried over to the other view.
   *
   * @var array<int, string>
   */
  private const KEPT_PARAMETERS = [ 'user', 'sumType' ];

  /**
   * @param UrlGeneratorInterface $urlGenerator Builds the report addresses.
   * @param SystemConfiguration $configuration Provides the start of the financial year.
   */
  public function __construct(
    private readonly UrlGeneratorInterface $urlGenerator,
    private readonly SystemConfiguration $configuration
  )
  {
  }

  /**
   * Returns a link to each of the other two user reports. The new period contains today when
   * the current period does, and otherwise the first day of the current period.
   *
   * @param string $currentRoute The report being shown.
   * @param DateTimeImmutable $begin The first day of the current period.
   * @param DateTimeImmutable $end The last day of the current period.
   * @param Request $request The report request, whose user and sum type are kept.
   * @return array<int, ViewLink>
   */
  public function create( string $currentRoute, DateTimeImmutable $begin, DateTimeImmutable $end, Request $request ) : array
  {
    $today = new DateTimeImmutable( 'today' );
    $target = $today >= $begin && $today <= $end ? $today : $begin;
    $kept = array_intersect_key( $request->query->all(), array_flip( self::KEPT_PARAMETERS ) );

    $links = [];
    foreach ( self::VIEWS as $route => $label )
    {
      if ( $route === $currentRoute )
      {
        continue;
      }

      $links[] = [
        'label' => $label,
        'url' => $this->urlGenerator->generate( $route, $kept + [ 'date' => $this->getStartDate( $route, $target )->format( 'Y-m-d' ) ] ),
      ];
    }

    return $links;
  }

  /**
   * Returns the date that opens the period containing the target day in the given report.
   *
   * @param string $route The report to link to.
   * @param DateTimeImmutable $target A day inside the wanted period.
   * @return DateTimeImmutable
   */
  private function getStartDate( string $route, DateTimeImmutable $target ) : DateTimeImmutable
  {
    if ( $route === UserReportChartsSubscriber::ROUTE_MONTH )
    {
      return $target->modify( 'first day of this month' );
    }

    if ( $route === UserReportChartsSubscriber::ROUTE_YEAR )
    {
      return $this->getYearStart( $target );
    }

    return $target;
  }

  /**
   * Returns the start of the (financial) year that contains the target day.
   *
   * @param DateTimeImmutable $target A day inside the wanted year.
   * @return DateTimeImmutable
   */
  private function getYearStart( DateTimeImmutable $target ) : DateTimeImmutable
  {
    $financialYear = $this->configuration->getFinancialYearStart();
    $monthDay = $financialYear === null ? '01-01' : ( new DateTimeImmutable( $financialYear ) )->format( 'm-d' );
    $start = new DateTimeImmutable( $target->format( 'Y' ) . '-' . $monthDay );

    return $start > $target ? $start->modify( '-1 year' ) : $start;
  }
}
