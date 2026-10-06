<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Form;

use App\Form\Type\DateRangeType;
use App\Form\Type\UserType;
use KimaiPlugin\ReportingGraphsBundle\Model\SummaryQuery;
use KimaiPlugin\ReportingGraphsBundle\ReportingGraphsBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The filter toolbar of the summary report.
 *
 * @extends AbstractType<SummaryQuery>
 */
final class SummaryForm extends AbstractType
{
  /**
   * Adds the period, the optional user picker and the grouping.
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
        'label' => false,
        'required' => false,
        'width' => false,
        'placeholder' => 'summary.all_users',
        'include_current_user_if_system_account' => true,
      ] );
    }

    $builder->add( 'groupBy', ChoiceType::class, [
      'label' => false,
      'required' => true,
      'placeholder' => false,
      'choices' => $this->getGroupChoices( $options[ 'include_user' ] === true ),
      'choice_translation_domain' => ReportingGraphsBundle::TRANSLATION_DOMAIN,
    ] );
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
      'data_class' => SummaryQuery::class,
      'timezone' => date_default_timezone_get(),
      'include_user' => false,
      'csrf_protection' => false,
      'method' => 'GET',
      'translation_domain' => ReportingGraphsBundle::TRANSLATION_DOMAIN,
    ] );

    $resolver->setAllowedTypes( 'timezone', 'string' );
    $resolver->setAllowedTypes( 'include_user', 'bool' );
  }

  /**
   * Returns the grouping choices, keyed by their translation label.
   *
   * @param bool $includeUser Whether grouping by user is offered.
   * @return array<string, string>
   */
  private function getGroupChoices( bool $includeUser ) : array
  {
    $choices = [];
    foreach ( SummaryQuery::getGroupings() as $grouping )
    {
      if ( $grouping === SummaryQuery::GROUP_USER && !$includeUser )
      {
        continue;
      }

      $choices[ 'summary.group_by.' . $grouping ] = $grouping;
    }

    return $choices;
  }
}
