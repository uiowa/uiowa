<?php

namespace Drupal\uiowa_core;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Url;

/**
 * A class to handle internal, external, media links for entities.
 */
class LinkHelper {

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * This method is copied from
   * Drupal\link\Plugin\Field\FieldWidget\LinkWidget::getUriAsDisplayableString()
   * to work around the issue that the method is protected.
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *   The displayable string.
   *
   * @see Drupal\link\Plugin\Field\FieldWidget\LinkWidget::getUriAsDisplayableString()
   */
  public static function getUriAsDisplayableString($uri): string {
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
      // Show the 'entity:' URI as the entity autocomplete would.
      // @todo Support entity types other than 'node'. Will be fixed in
      //   https://www.drupal.org/node/2423093.
      if ($entity_type === 'node' && $entity = \Drupal::entityTypeManager()
        ->getStorage($entity_type)
        ->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    elseif ($scheme === 'route') {
      $displayable_string = ltrim($displayable_string, 'route:');
    }

    return $displayable_string;
  }

  /**
   * Processes a link from a Linkit Form API implementation.
   *
   * @param string|\Drupal\Core\Url $url
   *   The URL to process (can be a string or an Url object).
   *
   * @return string
   *   The processed URL.
   */
  public static function processLinkIt(string|Url $url): string {
    // Check if the URL is media-related and process it accordingly.
    if (self::isMediaUrl($url)) {
      return self::processMediaUrl($url);
    }
    // Return the URL unchanged if no special processing is needed.
    return $url;
  }

  /**
   * Checks if a URL is media-related.
   */
  private static function isMediaUrl($url): bool {
    $url_str = (string) $url;
    return str_starts_with($url_str, 'entity:media') ||
      str_starts_with($url_str, '/media/') ||
      str_starts_with($url_str, 'internal:/media');
  }

  /**
   * Process media URLs to return a direct file URL.
   */
  private static function processMediaUrl($url) {
    // Identify and extract media ID from the URL.
    $mid = self::extractMediaId($url);
    if ($mid) {
      return self::loadMediaFileUrl($mid);
    }

    // Return the original URL if it doesn't match media patterns.
    return $url;
  }

  /**
   * Extracts the media ID from the given URL.
   */
  private static function extractMediaId($url): ?string {
    if (str_starts_with($url, '/media/')) {
      return substr($url, strlen('/media/'));
    }
    if (str_starts_with($url, 'entity:media:')) {
      return substr($url, strlen('entity:media:'));
    }
    if (str_starts_with($url, 'internal:/media')) {
      return basename($url);
    }
    return NULL;
  }

  /**
   * Load the direct file URL for a given media ID.
   */
  public static function loadMediaFileUrl($mid) {
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
    if ($media && $media->hasField('field_media_file') && $file = $media->get('field_media_file')->entity) {
      return $file->createFileUrl(FALSE);
    }
    return NULL;
  }

  /**
   * Determines if the title should be cleared based on its value.
   *
   * @param string|null $title
   *   The title to check.
   *
   * @return bool
   *   TRUE if the title should be cleared, FALSE otherwise.
   */
  public static function shouldClearTitle(?string $title): bool {
    // Check if the title is empty, or if it's an absolute or relative path.
    return empty($title) || str_starts_with($title, 'http') || str_starts_with($title, '/');
  }

}
