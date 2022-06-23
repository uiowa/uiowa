<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\media\Source;

use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;

/**
 * Provides media type plugin for Static Map.
 *
 * @MediaSource(
 *   id = "static_map",
 *   label = @Translation("Static Map"),
 *   description = @Translation("Use Static Map for reusable media."),
 *   allowed_field_types = {"string", "string_long", "link", "static_map_url"},
 * )
 */
class StaticMap extends MediaSourceBase {

  public function getMetadataAttributes() {
    return [
      'id' => $this->t('ID'),
      'uri' => $this->t('URL'),
      'zoom' => $this->t('Zoom'),
      'label' => $this->t('Label'),
    ];
  }

  public function getMetadata(MediaInterface $media, $attribute_name) {
    $remote_field = $media->get($this->configuration['source_field']);
    if (!$remote_field) {
      return parent::getMetadata($media, $attribute_name);
    }
    switch ($attribute_name) {
      // This is used to set the name of the media entity if the user leaves the field blank.
      case 'default_name':
        return $remote_field->value->alt_text;
      default:
        return $remote_field->value->$attribute_name ?? parent::getMetadata($media, $attribute_name);
    }
  }

}
