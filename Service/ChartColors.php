<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Service;

/**
 * Gives every group in a chart a colour that can be told apart from the others.
 *
 * Groups keep the colour they have in Kimai, so a project looks the same in the charts as in
 * Kimai's own lists. Only when that colour is too close to the colour of a larger group (for
 * example two projects that both inherit a red customer colour) does the group get the next
 * free colour from a categorical palette that was checked for colour-blind separation.
 */
final class ChartColors
{
  /**
   * Categorical palette, in fixed order, as light-theme and dark-theme steps of the same hues.
   *
   * @var array<int, array{0: string, 1: string}>
   */
  private const PALETTE = [
    [ '#2a78d6', '#3987e5' ],
    [ '#eb6834', '#d95926' ],
    [ '#1baf7a', '#199e70' ],
    [ '#eda100', '#c98500' ],
    [ '#e87ba4', '#d55181' ],
    [ '#008300', '#008300' ],
    [ '#4a3aa7', '#9085e9' ],
    [ '#e34948', '#e66767' ],
  ];

  /**
   * Neutral colour for the combined "other" group, light and dark.
   *
   * @var array{0: string, 1: string}
   */
  public const OTHER = [ '#8c8a85', '#8f8d86' ];

  /**
   * Two colours closer than this (OKLab distance × 100) are hard to tell apart.
   *
   * @var float
   */
  private const MIN_DISTANCE = 15.0;

  /**
   * Returns the light and dark colour for each entity colour, in order of importance.
   *
   * @param array<int, string> $colors Entity colours as hex, largest group first.
   * @return array<int, array{0: string, 1: string}>
   */
  public function assign( array $colors ) : array
  {
    $assigned = [];
    $used = [];

    foreach ( $colors as $index => $color )
    {
      $pair = $this->isDistinct( $color, $used ) ? [ $color, $color ] : $this->nextFree( $used );
      $assigned[ $index ] = $pair;
      $used[] = $pair[ 0 ];
    }

    return $assigned;
  }

  /**
   * Returns the first palette colour that is distinct from every colour in use.
   *
   * @param array<int, string> $used Colours already in the chart.
   * @return array{0: string, 1: string}
   */
  private function nextFree( array $used ) : array
  {
    foreach ( self::PALETTE as $pair )
    {
      if ( $this->isDistinct( $pair[ 0 ], $used ) )
      {
        return $pair;
      }
    }

    return self::OTHER;
  }

  /**
   * Returns whether a colour can be told apart from every colour in use.
   *
   * @param string $color A hex colour.
   * @param array<int, string> $used Colours already in the chart.
   * @return bool
   */
  private function isDistinct( string $color, array $used ) : bool
  {
    $lab = $this->toOklab( $color );

    foreach ( $used as $other )
    {
      $otherLab = $this->toOklab( $other );
      $distance = sqrt( ( $lab[ 0 ] - $otherLab[ 0 ] ) ** 2 + ( $lab[ 1 ] - $otherLab[ 1 ] ) ** 2 + ( $lab[ 2 ] - $otherLab[ 2 ] ) ** 2 ) * 100;

      if ( $distance < self::MIN_DISTANCE )
      {
        return false;
      }
    }

    return true;
  }

  /**
   * Converts a hex colour to OKLab.
   *
   * @param string $color A hex colour, #rgb or #rrggbb.
   * @return array{0: float, 1: float, 2: float}
   */
  private function toOklab( string $color ) : array
  {
    $hex = ltrim( $color, '#' );
    if ( strlen( $hex ) === 3 )
    {
      $hex = $hex[ 0 ] . $hex[ 0 ] . $hex[ 1 ] . $hex[ 1 ] . $hex[ 2 ] . $hex[ 2 ];
    }

    if ( preg_match( '/^[0-9a-f]{6}$/i', $hex ) !== 1 )
    {
      return [ 0.0, 0.0, 0.0 ];
    }

    [ $r, $g, $b ] = array_map(
      static function ( string $channel ) : float
      {
        $value = hexdec( $channel ) / 255;

        return $value <= 0.04045 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
      },
      str_split( $hex, 2 )
    );

    $l = ( 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b ) ** ( 1 / 3 );
    $m = ( 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b ) ** ( 1 / 3 );
    $s = ( 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b ) ** ( 1 / 3 );

    return [
      0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s,
      1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s,
      0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s,
    ];
  }
}
