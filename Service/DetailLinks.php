<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Service;

use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use KimaiPlugin\ReportingGraphsBundle\Repository\SummaryRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Finds the customer and project detail pages the viewer may open, for the customers and
 * projects in a report.
 *
 * @phpstan-import-type SummaryRow from SummaryRepository
 * @phpstan-type Links array{customers: array<int, string>, projects: array<int, string>}
 */
final class DetailLinks
{
  /**
   * Kimai's route for a customer's detail page.
   *
   * @var string
   */
  private const CUSTOMER_ROUTE = 'customer_details';

  /**
   * Kimai's route for a project's detail page.
   *
   * @var string
   */
  private const PROJECT_ROUTE = 'project_details';

  /**
   * @param CustomerRepository $customerRepository Loads the customers in the report.
   * @param ProjectRepository $projectRepository Loads the projects in the report.
   * @param AuthorizationCheckerInterface $security Checks which detail pages the viewer may open.
   * @param UrlGeneratorInterface $urlGenerator Builds the detail page addresses.
   */
  public function __construct(
    private readonly CustomerRepository $customerRepository,
    private readonly ProjectRepository $projectRepository,
    private readonly AuthorizationCheckerInterface $security,
    private readonly UrlGeneratorInterface $urlGenerator
  )
  {
  }

  /**
   * Returns the detail page address per customer ID and per project ID, for the ones the
   * viewer may open.
   *
   * @param array<int, SummaryRow> $rows The rows of the report.
   * @return Links
   */
  public function create( array $rows ) : array
  {
    $links = [ 'customers' => [], 'projects' => [] ];

    foreach ( $this->customerRepository->findByIds( array_column( $rows, 'customerId' ) ) as $customer )
    {
      if ( $customer->getId() !== null && $this->security->isGranted( 'view', $customer ) )
      {
        $links[ 'customers' ][ $customer->getId() ] = $this->urlGenerator->generate( self::CUSTOMER_ROUTE, [ 'id' => $customer->getId() ] );
      }
    }

    foreach ( $this->projectRepository->findByIds( array_column( $rows, 'projectId' ) ) as $project )
    {
      if ( $project->getId() !== null && $this->security->isGranted( 'view', $project ) )
      {
        $links[ 'projects' ][ $project->getId() ] = $this->urlGenerator->generate( self::PROJECT_ROUTE, [ 'id' => $project->getId() ] );
      }
    }

    return $links;
  }
}
