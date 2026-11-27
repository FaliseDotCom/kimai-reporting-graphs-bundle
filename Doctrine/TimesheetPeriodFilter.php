<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Doctrine;

use App\Entity\Timesheet;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Limits timesheet queries to the days of a period. It is only switched on for the duration of
 * one statistics call, never for a whole request.
 */
final class TimesheetPeriodFilter extends SQLFilter
{
  public const NAME = 'reporting_graphs_timesheet_period';
  public const PARAMETER_BEGIN = 'begin';
  public const PARAMETER_END = 'end';

  /**
   * The timesheet field that holds the day of a record, in the user's time zone.
   *
   * @var string
   */
  private const DATE_FIELD = 'date';

  /**
   * Adds the period's bounds to queries on the timesheet table.
   *
   * @param ClassMetadata<object> $targetEntity The entity the query reads.
   * @param string $targetTableAlias The SQL alias of its table.
   * @return string
   */
  public function addFilterConstraint( ClassMetadata $targetEntity, $targetTableAlias ) : string
  {
    if ( $targetEntity->getName() !== Timesheet::class )
    {
      return '';
    }

    $column = $targetTableAlias . '.' . $targetEntity->getColumnName( self::DATE_FIELD );
    $conditions = [];

    if ( $this->hasParameter( self::PARAMETER_BEGIN ) )
    {
      $conditions[] = $column . ' >= ' . $this->getParameter( self::PARAMETER_BEGIN );
    }

    if ( $this->hasParameter( self::PARAMETER_END ) )
    {
      $conditions[] = $column . ' <= ' . $this->getParameter( self::PARAMETER_END );
    }

    return implode( ' AND ', $conditions );
  }
}
