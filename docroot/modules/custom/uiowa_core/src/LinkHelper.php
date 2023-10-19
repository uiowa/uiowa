<?php

namespace Drupal\uiowa_core;

use Drupal\Core\Entity\Element\EntityAutocomplete;

/**
 * A class to handle links for entities, internal links, and external routes.
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
  public static function getUriAsDisplayableString(string $uri): string {
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

}
