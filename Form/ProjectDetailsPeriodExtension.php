<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Form;

use App\Entity\Project;
use App\Entity\User;
use App\Form\Model\DateRange;
use App\Form\Type\DateRangeType;
use App\Reporting\ProjectDetails\ProjectDetailsForm;
use App\Reporting\ProjectDetails\ProjectDetailsQuery;
use KimaiPlugin\ReportingGraphsBundle\Service\ProjectDetailsPeriod;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds a period to the filters of Kimai's project details report. Without a picked period it
 * starts with the project's own start and end date; an emptied field shows all time.
 *
 * @extends AbstractTypeExtension<ProjectDetailsQuery>
 */
final class ProjectDetailsPeriodExtension extends AbstractTypeExtension
{
  public const FIELD = 'daterange';

  /**
   * Block prefix of the field, which the form theme uses to render it hidden for reporting.js
   * to move into the filter bar.
   *
   * @var string
   */
  private const BLOCK_PREFIX = 'reporting_graphs_project_period';

  /**
   * @param ProjectDetailsPeriod $period Passes the period to the project statistics.
   * @param AuthorizationCheckerInterface $security Checks whether the project's dates may be shown.
   */
  public function __construct(
    private readonly ProjectDetailsPeriod $period,
    private readonly AuthorizationCheckerInterface $security
  )
  {
  }

  /**
   * Returns the form types this extension adds to.
   *
   * @return iterable<class-string>
   */
  public static function getExtendedTypes() : iterable
  {
    return [ ProjectDetailsForm::class ];
  }

  /**
   * Adds the period field, which is not part of Kimai's query, and the listener that reads it.
   *
   * @param FormBuilderInterface $builder The form builder.
   * @param array<string, mixed> $options The form options.
   * @return void
   */
  public function buildForm( FormBuilderInterface $builder, array $options ) : void
  {
    $fieldOptions = [
      'mapped' => false,
      'required' => false,
      'label' => false,
      'allow_empty' => true,
      'block_prefix' => self::BLOCK_PREFIX,
    ];

    $user = $options[ 'user' ] ?? null;
    if ( $user instanceof User )
    {
      $fieldOptions[ 'timezone' ] = $user->getTimezone();
    }

    $builder->add( self::FIELD, DateRangeType::class, $fieldOptions );
    $builder->addEventListener( FormEvents::POST_SUBMIT, $this->onPostSubmit( ... ) );
  }

  /**
   * Tells the field's template which period is shown, for the links on the report and for
   * resetting the project's own dates when another project is picked.
   *
   * @param FormView $view The form view.
   * @param FormInterface $form The form.
   * @param array<string, mixed> $options The form options.
   * @return void
   */
  public function finishView( FormView $view, FormInterface $form, array $options ) : void
  {
    if ( !isset( $view->children[ self::FIELD ] ) )
    {
      return;
    }

    $view->children[ self::FIELD ]->vars[ 'period_query' ] = $this->period->toQueryString();
    $view->children[ self::FIELD ]->vars[ 'period_is_default' ] = $this->period->isProjectDefault();
  }

  /**
   * Takes the picked period, or the project's own dates when none was picked.
   *
   * @param FormEvent $event The submit event of the whole form.
   * @return void
   */
  private function onPostSubmit( FormEvent $event ) : void
  {
    $form = $event->getForm();
    $query = $event->getData();
    if ( !$query instanceof ProjectDetailsQuery || !$form->has( self::FIELD ) )
    {
      return;
    }

    $field = $form->get( self::FIELD );
    if ( $field->isSubmitted() )
    {
      $range = $field->getData();
      $this->period->set( $range instanceof DateRange ? $range : new DateRange(), false );

      return;
    }

    $range = $this->getProjectRange( $query->getProject() );
    $field->setData( $range );
    $this->period->set( $range, true );
  }

  /**
   * Returns the project's start and end date as a range, or an empty range when the project
   * lacks one of them or the user may not see its details.
   *
   * @param Project|null $project The picked project.
   * @return DateRange
   */
  private function getProjectRange( ?Project $project ) : DateRange
  {
    $range = new DateRange();
    $start = $project?->getStart();
    $end = $project?->getEnd();

    if ( $start === null || $end === null || !$this->security->isGranted( 'details', $project ) )
    {
      return $range;
    }

    $range->setBegin( $start );
    $range->setEnd( $end );

    return $range;
  }
}
