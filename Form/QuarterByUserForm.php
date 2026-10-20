<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Form;

use App\Form\Type\ReportSumType;
use App\Form\Type\UserType;
use DateTime;
use KimaiPlugin\ReportingGraphsBundle\Model\QuarterByUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The filter toolbar of the quarterly report for one user, the same as Kimai's yearly one with
 * a quarter picker in place of the year picker.
 *
 * @extends AbstractType<QuarterByUser>
 */
final class QuarterByUserForm extends AbstractType
{
  /**
   * Adds the quarter, the optional user picker and the sum type.
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

    if ( $options[ 'include_user' ] === true )
    {
      $builder->add( 'user', UserType::class, [
        'width' => false,
        'include_current_user_if_system_account' => true,
      ] );
    }

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
      'data_class' => QuarterByUser::class,
      'timezone' => date_default_timezone_get(),
      'start_date' => new DateTime(),
      'include_user' => false,
      'csrf_protection' => false,
      'method' => 'GET',
    ] );

    $resolver->setAllowedTypes( 'timezone', 'string' );
    $resolver->setAllowedTypes( 'include_user', 'bool' );
  }
}
