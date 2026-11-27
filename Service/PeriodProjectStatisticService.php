<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Service;

use App\Entity\User;
use App\Model\Statistic\Year;
use App\Project\ProjectStatisticService;
use App\Reporting\ProjectDetails\ProjectDetailsModel;
use App\Reporting\ProjectDetails\ProjectDetailsQuery;
use App\Reporting\ProjectView\ProjectViewModel;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Filter\SQLFilter;
use KimaiPlugin\ReportingGraphsBundle\Doctrine\TimesheetPeriodFilter;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Kimai's project statistics, limited to the period picked on the project details report. The
 * figures of that report (totals, years, activities and users) only count the records of the
 * period; the budget keeps counting all records, as a budget is not tied to the period.
 * Without a period, or anywhere else in Kimai, it behaves exactly like Kimai's service.
 *
 * Kimai marks the service as final in its documentation only; it has no interface to decorate.
 */
final class PeriodProjectStatisticService extends ProjectStatisticService // @phpstan-ignore class.extendsFinalByPhpDoc
{
  /**
   * @param ProjectRepository $projectRepository Kimai's project repository.
   * @param ActivityRepository $activityRepository Kimai's activity repository.
   * @param TimesheetRepository $timesheetRepository Kimai's timesheet repository.
   * @param EventDispatcherInterface $dispatcher Kimai's event dispatcher.
   * @param UserRepository $userRepository Kimai's user repository.
   * @param EntityManagerInterface $entityManager Switches the period filter on and off.
   * @param ProjectDetailsPeriod $period The period picked on the report.
   */
  public function __construct(
    ProjectRepository $projectRepository,
    ActivityRepository $activityRepository,
    TimesheetRepository $timesheetRepository,
    EventDispatcherInterface $dispatcher,
    UserRepository $userRepository,
    private readonly EntityManagerInterface $entityManager,
    private readonly ProjectDetailsPeriod $period
  )
  {
    parent::__construct( $projectRepository, $activityRepository, $timesheetRepository, $dispatcher, $userRepository );
  }

  /**
   * Returns the details of a project, counting only the records of the picked period.
   *
   * @param ProjectDetailsQuery $query The report query.
   * @return ProjectDetailsModel
   */
  public function getProjectsDetails( ProjectDetailsQuery $query ) : ProjectDetailsModel
  {
    if ( !$this->period->isActive() )
    {
      return parent::getProjectsDetails( $query );
    }

    $model = $this->withPeriod( fn () : ProjectDetailsModel => parent::getProjectsDetails( $query ) );
    $model->setBudgetStatisticModel( parent::getBudgetStatisticModel( $model->getProject(), $query->getToday() ) );
    $model->setYears( $this->getYearsInPeriod( $model->getYears() ) );

    return $model;
  }

  /**
   * Returns the overview figures of projects, counting only the records of the picked period.
   *
   * @param User $user The user the figures are for.
   * @param array<mixed> $projects The projects.
   * @param DateTimeInterface $today The day that counts as today.
   * @return ProjectViewModel[]
   */
  public function getProjectView( User $user, array $projects, DateTimeInterface $today ) : array
  {
    if ( !$this->period->isActive() )
    {
      return parent::getProjectView( $user, $projects, $today );
    }

    return $this->withPeriod( fn () : array => parent::getProjectView( $user, $projects, $today ) );
  }

  /**
   * Runs a statistics call with the period filter switched on, and always switches it off again.
   *
   * @template T
   * @param callable(): T $callback The statistics call.
   * @return T
   */
  private function withPeriod( callable $callback ) : mixed
  {
    $configuration = $this->entityManager->getConfiguration();
    if ( $configuration->getFilterClassName( TimesheetPeriodFilter::NAME ) === null )
    {
      $configuration->addFilter( TimesheetPeriodFilter::NAME, TimesheetPeriodFilter::class );
    }

    $filters = $this->entityManager->getFilters();
    $filter = $filters->enable( TimesheetPeriodFilter::NAME );
    $this->setBound( $filter, TimesheetPeriodFilter::PARAMETER_BEGIN, $this->period->getBeginDay() );
    $this->setBound( $filter, TimesheetPeriodFilter::PARAMETER_END, $this->period->getEndDay() );

    try
    {
      return $callback();
    }
    finally
    {
      $filters->disable( TimesheetPeriodFilter::NAME );
    }
  }

  /**
   * Passes one bound of the period to the filter, when the period has it.
   *
   * @param SQLFilter $filter The period filter.
   * @param string $name The parameter name.
   * @param string $day The day as Y-m-d, or an empty string for an open bound.
   * @return void
   */
  private function setBound( SQLFilter $filter, string $name, string $day ) : void
  {
    if ( $day === '' )
    {
      return;
    }

    $filter->setParameter( $name, $day, Types::STRING );
  }

  /**
   * Drops the years outside the period. Kimai adds every year between the project's start and
   * end date, also when the period holds none of their records.
   *
   * @param Year[] $years The years of the report.
   * @return Year[]
   */
  private function getYearsInPeriod( array $years ) : array
  {
    return array_values( array_filter( $years, fn ( Year $year ) : bool => $this->period->overlapsYear( (int) $year->getYear() ) ) );
  }
}
