<?php

namespace Drupal\uiowa_core;

use Drupal\Component\Utility\UrlHelper;
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
   * Processes a link and returns an array with URL and link text.
   *
   * @param string|\Drupal\Core\Url $url
   *   The URL to process (can be a string or an Url object).
   * @param string|null $title
   *   The link title, optional, if provided.
   * @param bool $clear_title
   *   Whether to clear the link title (used for cards circle button).
   *
   * @return array
   *   An array containing:
   *   - 'link_url': The processed URL.
   *   - 'link_text': The processed link text (or null if not applicable).
   *   - 'is_external': A boolean indicating if the link is external.
   */
  public static function processLink(string|Url $url, ?string $title = NULL, bool $clear_title = FALSE): array {
    // Initialize the result array.
    $result = [
      'link_url' => NULL,
      'link_text' => NULL,
    ];

    // If it's an Url object, get the URL string.
    if ($url instanceof Url) {
      $url = $url->toString();
    }

    // Determine if the link is external.
    $is_external = UrlHelper::isExternal($url);
    $result['is_external'] = $is_external;

    // Process media links.
    if (str_starts_with($url, 'entity:media') || str_starts_with($url, 'internal:/media')) {
      $result = self::processMediaUrl($url);
    }
    // Process external links.
    elseif ($is_external) {
      $result['link_url'] = $url;
    }
    // Process internal links.
    else {
      $internal_path = str_starts_with($url, '/') ? $url : '/' . $url;
      $alias = \Drupal::service('path_alias.manager')->getAliasByPath($internal_path);
      $result['link_url'] = $alias ?: $url;
    }

    // If title is provided, use it. Otherwise, use the URL as fallback.
    $result['link_text'] = $title ?: $url;

    if ($clear_title) {
      if (self::shouldClearTitle($result['link_text'])) {
        $result['link_text'] = NULL;
      }
    }

    return $result;
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
  private static function shouldClearTitle(?string $title): bool {
    // Check if the title is empty, or if it's an absolute or relative path.
    return empty($title) || str_starts_with($title, 'http') || str_starts_with($title, '/');
  }

  /**
   * Get the file URL for a media entity.
   */
  private static function processMediaUrl($url) {
    if (str_starts_with($url, 'internal:/media')) {
      $media_id = basename($url);
      $media = \Drupal::entityTypeManager()
        ->getStorage('media')
        ->load($media_id);

      if ($media && $media->hasField('field_media_file')) {
        $file = $media->get('field_media_file')->entity;
        return $file ? $file->createFileUrl(FALSE) : $url;
      }
    }
    // Default to URL if media can't be processed.
    return $url;
  }

  /**
   * Processes multiple links from a field and returns their structured details.
   *
   * @param object $entity
   *   The entity containing the field with links.
   * @param string $field_name
   *   The field name containing links.
   *
   * @return array
   *   An array of structured link information.
   */
  public static function processLinksFromField(object $entity, string $field_name): array {
    $links = [];

    if ($entity->hasField($field_name)) {
      foreach ($entity->get($field_name)->getIterator() as $link_item) {
        $uri = $link_item->get('uri')->getString();
        $title = $link_item->get('title')->getString();
        $links[] = self::processLink($uri, empty($title));
      }
    }

    return $links;
  }

}
