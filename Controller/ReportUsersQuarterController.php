<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Controller;

use App\Controller\AbstractController;
use App\Export\Spreadsheet\Writer\BinaryFileResponseWriter;
use App\Export\Spreadsheet\Writer\XlsxWriter;
use App\Model\MonthlyStatistic;
use App\Repository\Query\TimesheetStatisticQuery;
use App\Repository\Query\UserQuery;
use App\Repository\Query\VisibilityInterface;
use App\Repository\UserRepository;
use App\Timesheet\TimesheetStatisticService;
use DateTime;
use KimaiPlugin\ReportingGraphsBundle\Form\QuarterlyUserListForm;
use KimaiPlugin\ReportingGraphsBundle\Model\QuarterlyUserList;
use KimaiPlugin\ReportingGraphsBundle\Service\QuarterPeriod;
use PhpOffice\PhpSpreadsheet\Reader\Html;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Shows the quarterly report for all users: Kimai's yearly report for all users, limited to the
 * three months of a quarter.
 */
#[Route( path: '/reporting/users' )]
#[IsGranted( 'report:other' )]
final class ReportUsersQuarterController extends AbstractController
{
  public const ROUTE = 'report_quarterly_users';
  public const EXPORT_ROUTE = 'report_quarterly_users_export';
  public const TITLE = 'quarter.report_users';

  /**
   * @param TimesheetStatisticService $statisticService Sums the time records per user and month.
   * @param UserRepository $userRepository Finds the users the viewer may see.
   * @param QuarterPeriod $quarters Calculates the quarters.
   */
  public function __construct(
    private readonly TimesheetStatisticService $statisticService,
    private readonly UserRepository $userRepository,
    private readonly QuarterPeriod $quarters
  )
  {
  }

  /**
   * Renders the report.
   *
   * @param Request $request The current request.
   * @return Response
   */
  #[Route( path: '/quarter', name: self::ROUTE, methods: [ 'GET' ] )]
  public function report( Request $request ) : Response
  {
    return $this->render( '@ReportingGraphs/report_users_quarter.html.twig', $this->getData( $request ) );
  }

  /**
   * Downloads the report as a spreadsheet, with Kimai's yearly export layout.
   *
   * @param Request $request The current request.
   * @return Response
   */
  #[Route( path: '/quarter_export', name: self::EXPORT_ROUTE, methods: [ 'GET' ] )]
  public function export( Request $request ) : Response
  {
    $content = $this->renderView( 'reporting/report_user_list_monthly_export.html.twig', $this->getData( $request ) );
    $writer = new BinaryFileResponseWriter( new XlsxWriter(), 'kimai-export-users-quarterly' );

    return $writer->getFileResponse( ( new Html() )->loadFromString( $content ) );
  }

  /**
   * Reads the filters and sums every visible user's time per month, the same way Kimai's
   * yearly report for all users does.
   *
   * @param Request $request The current request.
   * @return array<string, mixed>
   */
  private function getData( Request $request ) : array
  {
    $currentUser = $this->getUser();
    $factory = $this->getDateTimeFactory( $currentUser );
    $defaultDate = DateTime::createFromImmutable( $this->quarters->getStart( $factory->create() ) );

    $values = new QuarterlyUserList();
    $values->setDate( clone $defaultDate );

    $form = $this->createFormForGetRequest( QuarterlyUserListForm::class, $values, [
      'timezone' => $factory->getTimezone()->getName(),
      'start_date' => $defaultDate,
    ] );
    $form->submit( $request->query->all(), false );

    $query = new UserQuery();
    $query->setVisibility( VisibilityInterface::SHOW_BOTH );
    $query->setSystemAccount( false );
    $query->setCurrentUser( $currentUser );

    if ( !$form->isValid() )
    {
      $values->setDate( clone $defaultDate );
    }
    elseif ( $values->getTeam() !== null )
    {
      $query->setSearchTeams( [ $values->getTeam() ] );
    }

    $date = $values->getDate() ?? $defaultDate;
    $begin = $this->quarters->getStart( $date );
    $end = $this->quarters->getEnd( $date );
    $users = $this->userRepository->getUsersForQuery( $query );

    $stats = [];
    if ( $users !== [] )
    {
      $statsQuery = new TimesheetStatisticQuery( $begin, $end, $users );
      $statsQuery->setProject( $values->getProject() );
      $stats = $this->statisticService->getMonthlyStats( $statsQuery );
    }

    $hasData = $stats !== [];
    if ( !$hasData )
    {
      $stats = [ new MonthlyStatistic( $begin, $end, $currentUser ) ];
    }

    return [
      'report_title' => self::TITLE,
      'subReportDate' => $begin,
      'user_report_route' => UserQuarterController::ROUTE,
      'period_attribute' => 'months',
      'dataType' => $values->getSumType(),
      'box_id' => 'quarterly-user-list-reporting-box',
      'export_route' => self::EXPORT_ROUTE,
      'decimal' => $values->isDecimal(),
      'form' => $form->createView(),
      'stats' => $stats,
      'hasData' => $hasData,
    ];
  }
}
