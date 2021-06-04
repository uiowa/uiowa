<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Panopto URL field widget.
 *
 * @FieldWidget(
 *   id = "panopto_url_widget",
 *   label = @Translation("Panopto Url"),
 *   description = @Translation("A field for a panopto url."),
 *   field_types = {
 *     "panopto_url"
 *   }
 * )
 */
class PanoptoUrlWidget extends LinkWidget {}
