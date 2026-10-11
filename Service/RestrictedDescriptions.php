<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Service;

use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Security\RolePermissionManager;
use KimaiPlugin\ReportingGraphsBundle\Repository\SummaryRepository;

/**
 * Hides the descriptions of other users' records that the viewer may not see: records on a
 * project, customer or activity restricted to teams the viewer is not in. Kimai hides those
 * records from its lists and exports, and its reports only count them in the totals, so the
 * summary report keeps their time but not what they were about.
 *
 * @phpstan-import-type SummaryRow from SummaryRepository
 */
final class RestrictedDescriptions
{
  /**
   * @param ProjectRepository $projectRepository Loads the projects in the report.
   * @param ActivityRepository $activityRepository Loads the activities in the report.
   * @param RolePermissionManager $permissions Applies Kimai's team rules.
   */
  public function __construct(
    private readonly ProjectRepository $projectRepository,
    private readonly ActivityRepository $activityRepository,
    private readonly RolePermissionManager $permissions
  )
  {
  }

  /**
   * Marks the rows whose records the viewer may not see as hidden, without their description.
   *
   * @param array<int, SummaryRow> $rows The rows of the report.
   * @param User $viewer The logged-in user.
   * @return array<int, SummaryRow>
   */
  public function hide( array $rows, User $viewer ) : array
  {
    if ( $viewer->canSeeAllData() )
    {
      return $rows;
    }

    $others = array_filter( $rows, fn( array $row ) : bool => $row[ 'userId' ] !== $viewer->getId() );
    if ( empty( $others ) )
    {
      return $rows;
    }

    $projects = $this->findAllowedProjects( array_column( $others, 'projectId' ), $viewer );
    $activities = $this->findAllowedActivities( array_column( $others, 'activityId' ), $viewer );

    return array_map( function ( array $row ) use ( $viewer, $projects, $activities ) : array
    {
      $allowed = $row[ 'userId' ] === $viewer->getId()
        || ( isset( $projects[ $row[ 'projectId' ] ] ) && isset( $activities[ $row[ 'activityId' ] ] ) );

      return $allowed ? $row : [ 'description' => '', 'hidden' => true ] + $row;
    }, $rows );
  }

  /**
   * Returns the projects the viewer's teams give access to, by ID.
   *
   * @param array<int, int> $ids Project IDs.
   * @param User $viewer The logged-in user.
   * @return array<int, true>
   */
  private function findAllowedProjects( array $ids, User $viewer ) : array
  {
    $allowed = [];
    foreach ( $this->projectRepository->findByIds( array_values( array_unique( $ids ) ) ) as $project )
    {
      if ( $project->getId() !== null && $this->permissions->checkTeamAccessProject( $project, $viewer ) )
      {
        $allowed[ $project->getId() ] = true;
      }
    }

    return $allowed;
  }

  /**
   * Returns the activities the viewer's teams give access to, by ID.
   *
   * @param array<int, int> $ids Activity IDs.
   * @param User $viewer The logged-in user.
   * @return array<int, true>
   */
  private function findAllowedActivities( array $ids, User $viewer ) : array
  {
    $allowed = [];
    foreach ( $this->activityRepository->findByIds( array_values( array_unique( $ids ) ) ) as $activity )
    {
      if ( $activity->getId() !== null && $this->permissions->checkTeamAccessActivity( $activity, $viewer ) )
      {
        $allowed[ $activity->getId() ] = true;
      }
    }

    return $allowed;
  }
}
