<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldWidget;

use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Static Map URL field widget.
 *
 * @FieldWidget(
 *   id = "static_map_url_widget",
 *   label = @Translation("Static Map URL"),
 *   description = @Translation("A field for a static map url."),
 *   field_types = {
 *    "static_map_url"
 *   },
 * )
 */
class StaticMapUrlWidget extends LinkWidget {}
