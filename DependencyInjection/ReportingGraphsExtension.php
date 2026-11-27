<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Registers the services of the reporting extras.
 */
final class ReportingGraphsExtension extends Extension implements PrependExtensionInterface
{
  /**
   * Form themes for fields the plugin adds to Kimai's own forms, whose templates it cannot
   * change.
   *
   * @var array<int, string>
   */
  private const FORM_THEMES = [ '@ReportingGraphs/project_period_theme.html.twig' ];

  /**
   * Loads the service definitions of the bundle.
   *
   * @param array<mixed> $configs The bundle configuration, unused.
   * @param ContainerBuilder $container The container being built.
   * @return void
   */
  public function load( array $configs, ContainerBuilder $container ) : void
  {
    $loader = new YamlFileLoader( $container, new FileLocator( __DIR__ . '/../Resources/config' ) );
    $loader->load( 'services.yaml' );
  }

  /**
   * Adds the plugin's form themes to Twig.
   *
   * @param ContainerBuilder $container The container being built.
   * @return void
   */
  public function prepend( ContainerBuilder $container ) : void
  {
    $container->prependExtensionConfig( 'twig', [ 'form_themes' => self::FORM_THEMES ] );
  }
}
