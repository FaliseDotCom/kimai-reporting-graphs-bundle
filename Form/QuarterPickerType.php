<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Form;

use DateTimeImmutable;
use DateTimeInterface;
use KimaiPlugin\ReportingGraphsBundle\ReportingGraphsBundle;
use KimaiPlugin\ReportingGraphsBundle\Service\QuarterPeriod;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Picks a quarter, with arrows to the previous and next quarter, in the style of Kimai's month
 * and year pickers. The value is the quarter's first day; without one, the quarter of the
 * start date is shown.
 *
 * @phpstan-type QuarterOption array{date: string, label: string}
 * @extends AbstractType<DateTimeInterface|null>
 */
final class QuarterPickerType extends AbstractType
{
  /**
   * The number of quarters offered in the dropdown, counting back from the start date.
   *
   * @var int
   */
  private const RANGE = 12;

  /**
   * The translation key of a quarter's name, such as "Q4 2026".
   *
   * @var string
   */
  private const LABEL = 'quarter.label';

  /**
   * @param QuarterPeriod $quarters Calculates the quarters.
   * @param TranslatorInterface $translator Names the quarters.
   */
  public function __construct(
    private readonly QuarterPeriod $quarters,
    private readonly TranslatorInterface $translator
  )
  {
  }

  /**
   * Sets the defaults of a single text date field in Kimai's report date format.
   *
   * @param OptionsResolver $resolver The options resolver.
   * @return void
   */
  public function configureOptions( OptionsResolver $resolver ) : void
  {
    $resolver->setDefaults( [
      'widget' => 'single_text',
      'html5' => false,
      'format' => DateType::HTML5_FORMAT,
      'start_date' => new DateTimeImmutable(),
    ] );

    $resolver->setAllowedTypes( 'start_date', DateTimeInterface::class );
  }

  /**
   * Passes the shown quarter, its neighbours and the dropdown's quarters to the template.
   *
   * @param FormView $view The field's view.
   * @param FormInterface $form The field.
   * @param array<string, mixed> $options The field options.
   * @return void
   */
  public function buildView( FormView $view, FormInterface $form, array $options ) : void
  {
    /** @var DateTimeInterface $startDate */
    $startDate = $options[ 'start_date' ];
    $data = $form->getData();
    $date = $data instanceof DateTimeInterface ? $data : $startDate;

    $view->vars[ 'quarter' ] = $this->describe( $this->quarters->getStart( $date ) );
    $view->vars[ 'previous' ] = $this->describe( $this->quarters->getPrevious( $date ) );
    $view->vars[ 'next' ] = $this->describe( $this->quarters->getNext( $date ) );
    $view->vars[ 'range' ] = $this->getRange( $startDate );
  }

  /**
   * Returns Symfony's date field as the parent, which converts the value.
   *
   * @return string
   */
  public function getParent() : string
  {
    return DateType::class;
  }

  /**
   * Returns the prefix of the template block that draws the field.
   *
   * @return string
   */
  public function getBlockPrefix() : string
  {
    return 'quarterpicker';
  }

  /**
   * Returns the quarters offered in the dropdown: the start date's quarter and those before it.
   *
   * @param DateTimeInterface $startDate A date in the newest quarter.
   * @return array<int, QuarterOption>
   */
  private function getRange( DateTimeInterface $startDate ) : array
  {
    $quarter = $this->quarters->getStart( $startDate );

    $range = [];
    for ( $i = 0; $i < self::RANGE; $i++ )
    {
      $range[] = $this->describe( $quarter );
      $quarter = $this->quarters->getPrevious( $quarter );
    }

    return $range;
  }

  /**
   * Returns a quarter's value and name.
   *
   * @param DateTimeImmutable $start The first day of the quarter.
   * @return QuarterOption
   */
  private function describe( DateTimeImmutable $start ) : array
  {
    return [
      'date' => $start->format( 'Y-m-d' ),
      'label' => $this->translator->trans( self::LABEL, [
        '%quarter%' => $this->quarters->getNumber( $start ),
        '%year%' => $start->format( 'Y' ),
      ], ReportingGraphsBundle::TRANSLATION_DOMAIN ),
    ];
  }
}
