<?php

namespace Drupal\uiowa_maps\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\uiowa_maps\Plugin\Field\FieldType\StaticMapItem;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Defines the 'uiowa_maps_static_map' field widget.
 *
 * @FieldWidget(
 *   id = "uiowa_maps_static_map",
 *   label = @Translation("Static Map"),
 *   field_types = {"uiowa_maps_static_map"},
 * )
 */
class StaticMapWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element += [
      '#type' => 'fieldset',
    ];

    $element['link'] = [
      '#type' => 'url',
      '#title' => $this->t('Link'),
      '#description' => $this->t('A map marker share URL from maps.uiowa.edu'),
      '#default_value' => isset($items[$delta]->link) ? $items[$delta]->link : NULL,
      '#size' => 20,
    ];

    $element['zoom'] = [
      '#type' => 'select',
      '#title' => $this->t('Zoom'),
      '#description' => $this->t('The higher the number the more zoomed in the map will be.'),
      '#options' => ['' => $this->t('- Select a value -')] + StaticMapItem::allowedZoomValues(),
      '#default_value' => isset($items[$delta]->zoom) ? $items[$delta]->zoom : NULL,
    ];

    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('An accessible label for the map/link.'),
      '#default_value' => isset($items[$delta]->label) ? $items[$delta]->label : NULL,
      '#size' => 20,
    ];

    $element['#attributes']['class'][] = 'uiowa-maps-static-map-elements';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    return isset($violation->arrayPropertyPath[0]) ? $element[$violation->arrayPropertyPath[0]] : $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      if ($value['link'] === '') {
        $values[$delta]['link'] = NULL;
      }
      if ($value['zoom'] === '') {
        $values[$delta]['zoom'] = NULL;
      }
      if ($value['label'] === '') {
        $values[$delta]['label'] = NULL;
      }
    }
    return $values;
  }

}
