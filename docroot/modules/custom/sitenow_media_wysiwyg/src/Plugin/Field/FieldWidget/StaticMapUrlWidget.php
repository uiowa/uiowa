<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldWidget;

use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldType\StaticMapUrl;

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
class StaticMapUrlWidget extends LinkWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['zoom'] = [
      '#type' => 'select',
      '#title' => $this->t('Zoom'),
      '#description' => $this->t('The higher the number the more zoomed in the map will be.'),
      '#options' => ['' => $this->t('- Select a value -')] + StaticMapUrl::allowedZoomValues(),
      '#default_value' => $items[$delta]->zoom ?? NULL,
    ];

    $element['alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alternative text'),
      '#default_value' => $items[$delta]->alt ?? NULL,
      '#maxlength' => 255,
      '#description' => $this->t('Short description of the static map image used by screen readers and displayed when the static map image is not loaded. This is important for accessibility.'),
    ];

    return $element;
  }

}
