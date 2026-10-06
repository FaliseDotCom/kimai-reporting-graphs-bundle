<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle;

use App\Plugin\PluginInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Reporting extras for Kimai: a summary report, and charts, indentation and detail links on the
 * weekly, monthly and yearly user reports.
 */
final class ReportingGraphsBundle extends Bundle implements PluginInterface
{
  public const TRANSLATION_DOMAIN = 'reporting_graphs';
  public const ASSET_DIRECTORY = __DIR__ . '/Resources/public';

  /**
   * The files that may be served as assets, with their content type.
   *
   * @var array<string, string>
   */
  public const ASSETS = [
    'reporting.js' => 'text/javascript',
    'reporting.css' => 'text/css',
  ];

  /**
   * Returns a value that changes whenever an asset changes, for cache busting.
   *
   * @return string
   */
  public static function getAssetVersion() : string
  {
    $version = 0;
    foreach ( array_keys( self::ASSETS ) as $name )
    {
      $version = max( $version, (int) filemtime( self::ASSET_DIRECTORY . '/' . $name ) );
    }

    return (string) $version;
  }
}
