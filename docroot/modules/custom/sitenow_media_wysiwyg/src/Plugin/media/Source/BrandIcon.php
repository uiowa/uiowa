<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\media\Source;

use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;

/**
 * Provides media type plugin for Brand Icon.
 *
 * @MediaSource(
 *   id = "brand_icon",
 *   label = @Translation("Brand Icon"),
 *   description = @Translation("Use Brand Icon for reusable svg media."),
 *   allowed_field_types = {"string", "string_long", "link", "brand_icon_url"},
 *   forms = {
 *     "media_library_add" = "Drupal\sitenow_media_wysiwyg\Form\BrandIconForm",
 *   },
 * )
 */
class BrandIcon extends MediaSourceBase {

  /**
   * The name of the source field.
   */
  const SOURCE_FIELD = 'brand_icon_url';

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [
      'default_name' => $this->t('Default name'),
      'thumbnail_uri' => $this->t('Thumbnail URI'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    $source = $media->get($this->configuration['source_field']);
    $brand_icon_path = $source->getValue()[0]['uri'] ?? NULL;

    if (!$brand_icon_path) {
      return NULL;
    }

    $filename = pathinfo($brand_icon_path, PATHINFO_FILENAME);

    switch ($attribute_name) {
      case 'default_name':
      case 'thumbnail_uri':
        return 'media:' . $media->bundle() . ':brand-icon-' . $filename;
    }

    return NULL;
  }

}
