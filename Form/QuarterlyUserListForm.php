<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Form;

use App\Form\Type\ProjectType;
use App\Form\Type\ReportSumType;
use App\Form\Type\TeamType;
use DateTime;
use KimaiPlugin\ReportingGraphsBundle\Model\QuarterlyUserList;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The filter toolbar of the quarterly report for all users, the same as Kimai's yearly one with
 * a quarter picker in place of the year picker.
 *
 * @extends AbstractType<QuarterlyUserList>
 */
final class QuarterlyUserListForm extends AbstractType
{
  /**
   * Adds the quarter, team, project and sum type.
   *
   * @param FormBuilderInterface $builder The form builder.
   * @param array<string, mixed> $options The form options.
   * @return void
   */
  public function buildForm( FormBuilderInterface $builder, array $options ) : void
  {
    $builder->add( 'date', QuarterPickerType::class, [
      'model_timezone' => $options[ 'timezone' ],
      'view_timezone' => $options[ 'timezone' ],
      'start_date' => $options[ 'start_date' ],
    ] );
    $builder->add( 'team', TeamType::class, [
      'multiple' => false,
      'required' => false,
      'width' => false,
    ] );
    $builder->add( 'project', ProjectType::class, [
      'multiple' => false,
      'required' => false,
      'width' => false,
    ] );
    $builder->add( 'sumType', ReportSumType::class );
  }

  /**
   * Sets the defaults for a GET form without CSRF protection.
   *
   * @param OptionsResolver $resolver The options resolver.
   * @return void
   */
  public function configureOptions( OptionsResolver $resolver ) : void
  {
    $resolver->setDefaults( [
      'data_class' => QuarterlyUserList::class,
      'timezone' => date_default_timezone_get(),
      'start_date' => new DateTime(),
      'csrf_protection' => false,
      'method' => 'GET',
    ] );

    $resolver->setAllowedTypes( 'timezone', 'string' );
  }
}
