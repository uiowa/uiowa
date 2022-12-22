<?php

namespace Drupal\sitenow_intranet;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Methods to facilitate checking HTTP status codes.
 */
class IntranetHelper {

  /**
   * Returns the current exception status code or null if one doesn't exist.
   *
   * @return int|null
   *   The status code.
   */
  public static function getStatusCode(): ?int {
    $exception = \Drupal::request()
      ?->attributes
      ?->get('exception');
    if ($exception instanceof HttpExceptionInterface) {
      return $exception->getStatusCode();
    }
    return NULL;
  }

  /**
   * Return an array of status codes mapped to config labels.
   *
   * @return string[]
   *   The array of codes mapped to their config labels.
   */
  public static function getStatusCodeMap(): array {
    return [
      401 => 'unauthorized',
      403 => 'access_denied',
    ];
  }

  /**
   * Check a status code to set if it isn't null and is in our map.
   *
   * @param int|null $code
   *   The status code.
   *
   * @return bool
   *   Whether the code exists and is in the map.
   */
  public static function checkStatusCode(int $code = NULL): bool {
    return !is_null($code) && in_array($code, array_keys(self::getStatusCodeMap()));
  }

}
