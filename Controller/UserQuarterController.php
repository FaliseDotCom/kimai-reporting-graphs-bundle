<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Controller;

use App\Controller\Reporting\AbstractUserReportController;
use App\Entity\User;
use App\Export\Spreadsheet\Writer\BinaryFileResponseWriter;
use App\Export\Spreadsheet\Writer\XlsxWriter;
use App\Model\DateStatisticInterface;
use App\Model\MonthlyStatistic;
use App\Repository\Query\TimesheetStatisticQuery;
use DateTime;
use DateTimeInterface;
use KimaiPlugin\ReportingGraphsBundle\Form\QuarterByUserForm;
use KimaiPlugin\ReportingGraphsBundle\Model\QuarterByUser;
use KimaiPlugin\ReportingGraphsBundle\Service\QuarterPeriod;
use PhpOffice\PhpSpreadsheet\Reader\Html;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Shows the quarterly report for one user: Kimai's yearly report for one user, limited to the
 * three months of a quarter.
 */
#[Route( path: '/reporting/user' )]
#[IsGranted( 'report:user' )]
final class UserQuarterController extends AbstractUserReportController
{
  public const ROUTE = 'report_user_quarter';
  public const EXPORT_ROUTE = 'report_user_quarter_export';
  public const TITLE = 'quarter.report_user';

  /**
   * Renders the report.
   *
   * @param Request $request The current request.
   * @param QuarterPeriod $quarters Calculates the quarters.
   * @return Response
   */
  #[Route( path: '/quarter', name: self::ROUTE, methods: [ 'GET' ] )]
  public function quarterByUser( Request $request, QuarterPeriod $quarters ) : Response
  {
    return $this->render( '@ReportingGraphs/report_by_user_quarter.html.twig', $this->getData( $request, $quarters ) );
  }

  /**
   * Downloads the report as a spreadsheet, with Kimai's yearly export layout.
   *
   * @param Request $request The current request.
   * @param QuarterPeriod $quarters Calculates the quarters.
   * @return Response
   */
  #[Route( path: '/quarter_export', name: self::EXPORT_ROUTE, methods: [ 'GET' ] )]
  public function export( Request $request, QuarterPeriod $quarters ) : Response
  {
    $content = $this->renderView( 'reporting/report_by_user_year_export.html.twig', $this->getData( $request, $quarters, true ) );
    $writer = new BinaryFileResponseWriter( new XlsxWriter(), 'kimai-export-user-quarterly' );

    return $writer->getFileResponse( ( new Html() )->loadFromString( $content ) );
  }

  /**
   * Reads the filters and collects the report's rows, the same way Kimai's yearly report does.
   *
   * @param Request $request The current request.
   * @param QuarterPeriod $quarters Calculates the quarters.
   * @param bool $export Whether durations are shown as decimal numbers, as in the export.
   * @return array<string, mixed>
   */
  private function getData( Request $request, QuarterPeriod $quarters, bool $export = false ) : array
  {
    $currentUser = $this->getUser();
    $factory = $this->getDateTimeFactory( $currentUser );
    $canChangeUser = $this->canSelectUser();
    $defaultDate = DateTime::createFromImmutable( $quarters->getStart( $factory->create() ) );

    $values = new QuarterByUser();
    $values->setDecimal( $export );
    $values->setUser( $currentUser );
    $values->setDate( clone $defaultDate );

    $form = $this->createFormForGetRequest( QuarterByUserForm::class, $values, [
      'include_user' => $canChangeUser,
      'timezone' => $factory->getTimezone()->getName(),
      'start_date' => $defaultDate,
    ] );
    $form->submit( $request->query->all(), false );

    $selectedUser = $values->getUser() ?? $currentUser;
    if ( $selectedUser !== $currentUser && !$canChangeUser )
    {
      throw new AccessDeniedException( 'User is not allowed to see other users timesheet' );
    }

    $date = $values->getDate() ?? $defaultDate;
    $begin = $quarters->getStart( $date );
    $end = $quarters->getEnd( $date );

    return [
      'report_title' => self::TITLE,
      'decimal' => $values->isDecimal(),
      'dataType' => $values->getSumType(),
      'box_id' => 'user-quarter-reporting-box',
      'form' => $form->createView(),
      'period' => new MonthlyStatistic( $begin, $end, $selectedUser ),
      'rows' => $this->prepareReport( $begin, $end, $selectedUser ),
      'user' => $selectedUser,
      'begin' => $begin,
      'end' => $end,
      'export_route' => self::EXPORT_ROUTE,
    ];
  }

  /**
   * Reads the statistics per month, like Kimai's yearly report.
   *
   * @param DateTimeInterface $begin The first moment of the quarter.
   * @param DateTimeInterface $end The last moment of the quarter.
   * @param User $user The user the report shows.
   * @return array<mixed>
   */
  protected function getStatisticDataRaw( DateTimeInterface $begin, DateTimeInterface $end, User $user ) : array
  {
    return $this->statisticService->getMonthlyStatisticsGrouped( new TimesheetStatisticQuery( $begin, $end, [ $user ] ) );
  }

  /**
   * Creates the per-month statistic the rows are summed into, like Kimai's yearly report.
   *
   * @param DateTimeInterface $begin The first moment of the quarter.
   * @param DateTimeInterface $end The last moment of the quarter.
   * @param User $user The user the report shows.
   * @return DateStatisticInterface
   */
  protected function createStatisticModel( DateTimeInterface $begin, DateTimeInterface $end, User $user ) : DateStatisticInterface
  {
    return new MonthlyStatistic( $begin, $end, $user );
  }
}
