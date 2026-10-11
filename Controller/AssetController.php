<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\ReportingGraphsBundle\ReportingGraphsBundle;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves the plugin's script and stylesheet, which Kimai's asset build does not include. Any
 * logged-in user may load them: the dashboard widget needs them without reporting access.
 */
#[Route( path: '/reporting/bundle-assets' )]
#[IsGranted( 'IS_AUTHENTICATED_REMEMBERED' )]
final class AssetController extends AbstractController
{
  public const ROUTE = 'reporting_bundle_asset';

  /**
   * How long browsers may cache the assets, in seconds.
   *
   * @var int
   */
  private const MAX_AGE = 86400;

  /**
   * Serves one asset. Browsers keep it for a day, and afterwards ask whether it changed; a new
   * release changes the version in its address anyway.
   *
   * @param Request $request The request, for answering "not modified".
   * @param string $name The file name, one of the keys of ReportingGraphsBundle::ASSETS.
   * @return Response
   */
  #[Route( path: '/{name}', name: self::ROUTE, methods: [ 'GET' ] )]
  public function asset( Request $request, string $name ) : Response
  {
    if ( !isset( ReportingGraphsBundle::ASSETS[ $name ] ) )
    {
      throw new NotFoundHttpException();
    }

    $response = new BinaryFileResponse( ReportingGraphsBundle::ASSET_DIRECTORY . '/' . $name );
    $response->headers->set( 'Content-Type', ReportingGraphsBundle::ASSETS[ $name ] );
    // Private, because the route needs a login. Symfony turns caching off for every response
    // of a request that reads the session, unless this header says the response is safe.
    $response->setPrivate();
    $response->setMaxAge( self::MAX_AGE );
    $response->headers->set( AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true' );
    $response->isNotModified( $request );

    return $response;
  }
}
