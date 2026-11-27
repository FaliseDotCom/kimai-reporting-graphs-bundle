<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Form;

use App\Form\Type\DateRangeType;
use App\Form\Type\ReportSumType;
use App\Form\Type\UserType;
use KimaiPlugin\ReportingGraphsBundle\Model\PeriodByUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The filter toolbar of the period report for one user, the same as Kimai's user reports with
 * Kimai's date range picker in place of their period picker. An empty period means all time.
 *
 * @extends AbstractType<PeriodByUser>
 */
final class PeriodByUserForm extends AbstractType
{
  /**
   * Adds the period, the optional user picker and the sum type.
   *
   * @param FormBuilderInterface $builder The form builder.
   * @param array<string, mixed> $options The form options.
   * @return void
   */
  public function buildForm( FormBuilderInterface $builder, array $options ) : void
  {
    $builder->add( 'daterange', DateRangeType::class, [
      'property_path' => 'dateRange',
      'label' => false,
      'required' => false,
      'allow_empty' => true,
      'timezone' => $options[ 'timezone' ],
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
      'data_class' => PeriodByUser::class,
      'timezone' => date_default_timezone_get(),
      'include_user' => false,
      'csrf_protection' => false,
      'method' => 'GET',
    ] );

    $resolver->setAllowedTypes( 'timezone', 'string' );
    $resolver->setAllowedTypes( 'include_user', 'bool' );
  }
}
