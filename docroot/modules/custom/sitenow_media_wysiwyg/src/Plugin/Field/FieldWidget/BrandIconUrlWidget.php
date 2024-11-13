<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldWidget;

use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Panopto URL field widget.
 *
 * @FieldWidget(
 *   id = "brand_icon_url_widget",
 *   label = @Translation("Brand Icon Url"),
 *   description = @Translation("A field for a brand icon svg url."),
 *   field_types = {
 *     "brand_icon_url"
 *   }
 * )
 */
class BrandIconUrlWidget extends LinkWidget {}
