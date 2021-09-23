<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldType;

use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * Plugin implementation of the 'panopto_url' field type.
 *
 * @FieldType(
 *   id = "panopto_url",
 *   label = @Translation("Panopto URL"),
 *   description = @Translation("This field is used to capture the URL of a panopto instance."),
 *   category = @Translation("General"),
 *   default_widget = "panopto_url_widget",
 *   default_formatter = "panopto_url_formatter"
 * )
 */
class PanoptoUrl extends LinkItem {}
