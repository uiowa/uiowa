<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldType;

use Drupal\link\Plugin\Field\FieldType\LinkItem;
/**
 * Plugin implementation of the 'static_map_url' field type.
 *
 * @FieldType(
 *   id = "static_map_url",
 *   label = @Translation("Static Map URL"),
 *   description = @Translation("This field is used to capture the URL of a static map."),
 *   category = @Translation("General"),
 *   default_widget = "static_map_url_widget",
 *   default_formatter = "static_map_url_formattert"
 * )
 */
class StaticMapUrl extends LinkItem {}
