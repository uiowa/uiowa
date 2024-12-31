<?php

namespace Drupal\uiowa_core;

use Drupal\Core\Url;

/**
 * A class to handle internal, external, media links for entities.
 */
class LinkHelper {

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
