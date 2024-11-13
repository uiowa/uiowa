<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
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
class BrandIconUrlWidget extends LinkWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Add custom attributes for the brand icon SVG.
    if (isset($items[$delta]->uri)) {
      $element['uri']['#attributes']['data-brand-icon-path'] = $items[$delta]->uri;
    }

    $element['alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alternative text'),
      '#default_value' => $items[$delta]->alt ?? NULL,
      '#maxlength' => 255,
      '#description' => $this->t('Short description of the static map image used by screen readers and displayed when the static map image is not loaded. This is important for accessibility.'),
      '#required' => TRUE,
    ];

    return $element;
  }

}
