<?php

declare( strict_types=1 );

namespace KimaiPlugin\ReportingGraphsBundle\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads single query parameters safely. Symfony throws when a parameter read as a single value
 * was sent as a list, such as ?user[]=1, which would turn a report into an error page.
 */
final class QueryParameters
{
  /**
   * Returns a query parameter as text, or an empty string when it is missing or a list.
   *
   * @param Request|null $request The request.
   * @param string $name The parameter name.
   * @return string
   */
  public function getString( ?Request $request, string $name ) : string
  {
    $value = $request?->query->all()[ $name ] ?? '';

    return is_string( $value ) ? $value : '';
  }

  /**
   * Returns a query parameter as a whole number, or 0 when it is missing, a list or not a number.
   *
   * @param Request|null $request The request.
   * @param string $name The parameter name.
   * @return int
   */
  public function getInt( ?Request $request, string $name ) : int
  {
    $value = $this->getString( $request, $name );

    return ctype_digit( $value ) ? (int) $value : 0;
  }
}
