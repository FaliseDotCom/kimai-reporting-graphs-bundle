<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Service;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Calculates calendar quarters: January to March, April to June, July to September and
 * October to December.
 */
final class QuarterPeriod
{
  /**
   * The number of months in a quarter.
   *
   * @var int
   */
  private const MONTHS = 3;

  /**
   * Returns the first moment of the quarter that contains the given date.
   *
   * @param DateTimeInterface $date A date inside the quarter.
   * @return DateTimeImmutable
   */
  public function getStart( DateTimeInterface $date ) : DateTimeImmutable
  {
    $day = DateTimeImmutable::createFromInterface( $date );
    $month = ( $this->getNumber( $day ) - 1 ) * self::MONTHS + 1;

    return $day->setDate( (int) $day->format( 'Y' ), $month, 1 )->setTime( 0, 0 );
  }

  /**
   * Returns the last moment of the quarter that contains the given date.
   *
   * @param DateTimeInterface $date A date inside the quarter.
   * @return DateTimeImmutable
   */
  public function getEnd( DateTimeInterface $date ) : DateTimeImmutable
  {
    return $this->getStart( $date )->modify( '+' . self::MONTHS . ' months -1 day' )->setTime( 23, 59, 59 );
  }

  /**
   * Returns the first moment of the quarter before the one that contains the given date.
   *
   * @param DateTimeInterface $date A date inside the current quarter.
   * @return DateTimeImmutable
   */
  public function getPrevious( DateTimeInterface $date ) : DateTimeImmutable
  {
    return $this->getStart( $date )->modify( '-' . self::MONTHS . ' months' );
  }

  /**
   * Returns the first moment of the quarter after the one that contains the given date.
   *
   * @param DateTimeInterface $date A date inside the current quarter.
   * @return DateTimeImmutable
   */
  public function getNext( DateTimeInterface $date ) : DateTimeImmutable
  {
    return $this->getStart( $date )->modify( '+' . self::MONTHS . ' months' );
  }

  /**
   * Returns the number of the quarter that contains the given date, from 1 to 4.
   *
   * @param DateTimeInterface $date A date inside the quarter.
   * @return int
   */
  public function getNumber( DateTimeInterface $date ) : int
  {
    return intdiv( (int) $date->format( 'n' ) - 1, self::MONTHS ) + 1;
  }
}
