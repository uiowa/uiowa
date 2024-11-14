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

    $element['alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alternative text'),
      '#default_value' => $items[$delta]->alt ?? NULL,
      '#maxlength' => 255,
      '#description' => $this->t('Short description of the brand icon used by screen readers. This is important for accessibility.'),
      '#required' => TRUE,
    ];

    $element['icon_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Icon ID'),
      '#default_value' => $items[$delta]->icon_id ?? NULL,
      '#description' => $this->t('The unique identifier for this icon.'),
      '#min' => 1,
      '#step' => 1,
      '#required' => FALSE,
    ];



    return $element;
  }

}
