<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldType;

use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * Plugin implementation of the 'brand_icon_url' field type.
 *
 * @FieldType(
 *   id = "brand_icon_url",
 *   label = @Translation("Brand Icon URL"),
 *   description = @Translation("This field is used to capture the URL of the brand icon svg"),
 *   category = @Translation("General"),
 *   default_widget = "brand_icon_url_widget",
 *   default_formatter = "brand_icon_url_formatter"
 * )
 */
class BrandIconUrl extends LinkItem {}
