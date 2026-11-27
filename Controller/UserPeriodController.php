<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Controller;

use App\Controller\Reporting\AbstractUserReportController;
use App\Entity\User;
use App\Export\Spreadsheet\Writer\BinaryFileResponseWriter;
use App\Export\Spreadsheet\Writer\XlsxWriter;
use App\Form\Model\DateRange;
use App\Model\DailyStatistic;
use App\Model\DateStatisticInterface;
use App\Model\MonthlyStatistic;
use App\Repository\Query\TimesheetStatisticQuery;
use App\Timesheet\DateTimeFactory;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use KimaiPlugin\ReportingGraphsBundle\Form\PeriodByUserForm;
use KimaiPlugin\ReportingGraphsBundle\Model\PeriodByUser;
use KimaiPlugin\ReportingGraphsBundle\Repository\SummaryRepository;
use KimaiPlugin\ReportingGraphsBundle\Service\PeriodNavigator;
use KimaiPlugin\ReportingGraphsBundle\Service\SummaryBuilder;
use PhpOffice\PhpSpreadsheet\Reader\Html;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Shows the report for one user over any period: Kimai's monthly report for one user (a column
 * per day) for periods the charts show per day, and its yearly report (a column per month) for
 * longer periods.
 */
#[Route( path: '/reporting/user' )]
#[IsGranted( 'report:user' )]
final class UserPeriodController extends AbstractUserReportController
{
  public const ROUTE = 'report_user_period';
  public const EXPORT_ROUTE = 'report_user_period_export';
  public const TITLE = 'period.report_user';

  /**
   * The request attribute holding the shown period, so the charts follow the same period.
   *
   * @var string
   */
  public const PERIOD_ATTRIBUTE = '_reporting_graphs_period';

  /**
   * The last day of a month that every month has. A period per month that starts later reads
   * its records per day, because Kimai's monthly statistics would shift such a start into the
   * next month for shorter months.
   *
   * @var int
   */
  private const LAST_SAFE_MONTH_DAY = 28;

  /**
   * Whether the period being prepared is shown per month instead of per day.
   *
   * @var bool
   */
  private bool $perMonth = false;

  /**
   * Renders the report.
   *
   * @param Request $request The current request.
   * @param SummaryBuilder $builder Decides whether the period is shown per day or per month.
   * @param SummaryRepository $repository Finds the user's first and last record for all time.
   * @param PeriodNavigator $navigator Calculates the previous and next periods.
   * @return Response
   */
  #[Route( path: '/period', name: self::ROUTE, methods: [ 'GET' ] )]
  public function periodByUser( Request $request, SummaryBuilder $builder, SummaryRepository $repository, PeriodNavigator $navigator ) : Response
  {
    $data = $this->getData( $request, $builder, $repository );
    $request->attributes->set( self::PERIOD_ATTRIBUTE, [ $data[ 'first_day' ], $data[ 'last_day' ] ] );
    $queries = $navigator->getQueries( $request->query->all(), $data[ 'first_day' ], $data[ 'last_day' ] );

    return $this->render( '@ReportingGraphs/report_by_user_period.html.twig', $data + [
      'previous_query' => $queries[ 'previous' ],
      'next_query' => $queries[ 'next' ],
    ] );
  }

  /**
   * Downloads the report as a spreadsheet, with the export layout of Kimai's monthly or yearly
   * report.
   *
   * @param Request $request The current request.
   * @param SummaryBuilder $builder Decides whether the period is shown per day or per month.
   * @param SummaryRepository $repository Finds the user's first and last record for all time.
   * @return Response
   */
  #[Route( path: '/period_export', name: self::EXPORT_ROUTE, methods: [ 'GET' ] )]
  public function export( Request $request, SummaryBuilder $builder, SummaryRepository $repository ) : Response
  {
    $data = $this->getData( $request, $builder, $repository, true );
    $template = $data[ 'per_month' ] ? 'reporting/report_by_user_year_export.html.twig' : 'reporting/report_by_user_data.html.twig';
    $writer = new BinaryFileResponseWriter( new XlsxWriter(), 'kimai-export-user-period' );

    return $writer->getFileResponse( ( new Html() )->loadFromString( $this->renderView( $template, $data ) ) );
  }

  /**
   * Reads the filters and collects the report's rows, the same way Kimai's user reports do.
   *
   * @param Request $request The current request.
   * @param SummaryBuilder $builder Decides whether the period is shown per day or per month.
   * @param SummaryRepository $repository Finds the user's first and last record for all time.
   * @param bool $export Whether durations are shown as decimal numbers, as in the export.
   * @return array<string, mixed>
   */
  private function getData( Request $request, SummaryBuilder $builder, SummaryRepository $repository, bool $export = false ) : array
  {
    $currentUser = $this->getUser();
    $factory = $this->getDateTimeFactory( $currentUser );
    $canChangeUser = $this->canSelectUser();

    $values = new PeriodByUser( $this->createDefaultRange( $factory ) );
    $values->setDecimal( $export );
    $values->setUser( $currentUser );

    $form = $this->createFormForGetRequest( PeriodByUserForm::class, $values, [
      'include_user' => $canChangeUser,
      'timezone' => $factory->getTimezone()->getName(),
    ] );
    $form->submit( $request->query->all(), false );

    $selectedUser = $values->getUser() ?? $currentUser;
    if ( $selectedUser !== $currentUser && !$canChangeUser )
    {
      throw new AccessDeniedException( 'User is not allowed to see other users timesheet' );
    }

    [ $firstDay, $lastDay ] = $this->getDays( $values->getDateRange(), $repository->findDateBounds( [ (int) $selectedUser->getId() ] ), $factory );
    $begin = $factory->createDateTime( $firstDay->format( 'Y-m-d' ) . ' 00:00:00' );
    $end = $factory->createDateTime( $lastDay->format( 'Y-m-d' ) . ' 23:59:59' );
    $this->perMonth = $builder->isPerMonth( $firstDay, $lastDay );

    return [
      'report_title' => self::TITLE,
      'decimal' => $values->isDecimal(),
      'dataType' => $values->getSumType(),
      'box_id' => 'user-period-reporting-box',
      'form' => $form->createView(),
      'period' => $this->createStatisticModel( $begin, $end, $selectedUser ),
      'rows' => $this->prepareReport( $begin, $end, $selectedUser ),
      'user' => $selectedUser,
      'begin' => $begin,
      'end' => $end,
      'first_day' => $firstDay,
      'last_day' => $lastDay,
      'per_month' => $this->perMonth,
      'export_route' => self::EXPORT_ROUTE,
    ];
  }

  /**
   * Returns the period of a first visit: the current month.
   *
   * @param DateTimeFactory $factory Creates dates in the viewer's time zone.
   * @return DateRange
   */
  private function createDefaultRange( DateTimeFactory $factory ) : DateRange
  {
    $range = new DateRange();
    $range->setBegin( $factory->getStartOfMonth() );
    $range->setEnd( $factory->getEndOfMonth() );

    return $range;
  }

  /**
   * Returns the first and last day of the requested period. An empty period means all time:
   * from the user's first to their last record, or the current month when they have none. A
   * period given backwards is turned around.
   *
   * @param DateRange $range The requested period.
   * @param array<int, DateTimeImmutable> $bounds The days of the user's first and last record.
   * @param DateTimeFactory $factory Creates dates in the viewer's time zone.
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
   */
  private function getDays( DateRange $range, array $bounds, DateTimeFactory $factory ) : array
  {
    $begin = $range->getBegin();
    $end = $range->getEnd();

    if ( $begin === null || $end === null )
    {
      $begin = $bounds[ 0 ] ?? $factory->getStartOfMonth();
      $end = $bounds[ 1 ] ?? $factory->getEndOfMonth();
    }

    $days = [ $this->toDay( $begin ), $this->toDay( $end ) ];
    sort( $days );

    return $days;
  }

  /**
   * Returns the calendar day of a date, without its time.
   *
   * @param DateTimeInterface $date A date in the viewer's time zone.
   * @return DateTimeImmutable
   */
  private function toDay( DateTimeInterface $date ) : DateTimeImmutable
  {
    return new DateTimeImmutable( $date->format( 'Y-m-d' ) );
  }

  /**
   * Reads the statistics per month for long periods, like Kimai's yearly report, and per day
   * otherwise, like its monthly report.
   *
   * @param DateTimeInterface $begin The first moment of the period.
   * @param DateTimeInterface $end The last moment of the period.
   * @param User $user The user the report shows.
   * @return array<mixed>
   */
  protected function getStatisticDataRaw( DateTimeInterface $begin, DateTimeInterface $end, User $user ) : array
  {
    $query = new TimesheetStatisticQuery( $begin, $end, [ $user ] );

    if ( $this->perMonth && (int) $begin->format( 'j' ) <= self::LAST_SAFE_MONTH_DAY )
    {
      return $this->statisticService->getMonthlyStatisticsGrouped( $query );
    }

    return $this->statisticService->getDailyStatisticsGrouped( $query );
  }

  /**
   * Creates the statistic the rows are summed into: a column per month, starting with the
   * month of the first day, for long periods, and a column per day otherwise.
   *
   * @param DateTimeInterface $begin The first moment of the period.
   * @param DateTimeInterface $end The last moment of the period.
   * @param User $user The user the report shows.
   * @return DateStatisticInterface
   */
  protected function createStatisticModel( DateTimeInterface $begin, DateTimeInterface $end, User $user ) : DateStatisticInterface
  {
    if ( !$this->perMonth )
    {
      return new DailyStatistic( $begin, $end, $user );
    }

    $firstMonth = DateTime::createFromInterface( $begin );
    $firstMonth->modify( 'first day of this month 00:00:00' );

    return new MonthlyStatistic( $firstMonth, $end, $user );
  }
}
