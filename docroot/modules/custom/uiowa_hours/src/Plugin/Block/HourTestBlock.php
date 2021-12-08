<?php

namespace Drupal\uiowa_hours\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the hour block.
 *
 * @Block(
 *   id = "uiowa_hour_api",
 *   admin_label = @Translation("Hour API"),
 *   category = @Translation("Site custom"),
 *   derivative = "Drupal\uiowa_hours\Plugin\Derivative\HourTestBlock"
 * )
 */
class HourTestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $block_id = $this->getDerivativeId();

    $config = $this->getConfiguration();
    
    $resource_key = isset($config['resource_key']) ? $config['resource_key'] : '';
    $display_hours = isset($config['display_hours']) ? $config['display_hours'] : '';
    $display_calendar = isset($config['display_calendar']) ? $config['display_calendar'] : '';

    $date = isset($form_state['values'], $form_state['values']['date']) ? $form_state['values']['date'] : 'today';

    $build['hours_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $block_id,
        'class' => ['hours-container'], /* Class on the wrapping DIV element */
      ],

    ];
    $build['hours_container']['resource'] = [
      '#type' => 'markup',
      '#markup' => '<span class="label">' . $resource_key .'</span>',
    ];
    $build['hours_container']['has_hours'] = [
      '#type' => 'markup',
      '#markup' => '<span role="presentation" aria-hidden="true" class="fa-clock far">'. t($resource_key) .'</span>',
    ];
    if ($display_hours == TRUE) {
      $build['hours_container']['display_hour_block'] = [
        '#type' => 'markup',
        '#markup' => '<span class="fa-li"><span class="fa-angle-right text--gold fas"></span></span>' . $resource_key,
        '#attributes' => ['class' => ['hour-block']],
      ];
    }
    if ($display_calendar == TRUE) {
      $build['hours_container']['#attached']['library'][] = 'uiowa_hours/uiowa-hours-datepicker';
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['global']['resource_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Resource Name'),
      '#description' => $this->t('Resource Key'),
      '#default_value' => isset($config['resource_key']) ? $config['resource_key'] : '',
      '#required' => TRUE,
    ];
    $form['global']['display_hours'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Hour Block'),
      '#description' => $this->t('Show if open or closed.'),
      '#default_value' => $this->configuration['display_hours'] ?? TRUE,
    ];

    $form['global']['display_calendar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Datepicker'),
      '#description' => $this->t('Show date range.'),
      '#default_value' => $this->configuration['display_calendar'] ?? TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['resource_key'] = trim($values['resource_key']);
    $this->configuration['display_hours'] = $form_state->getValue('display_hours');
    $this->configuration['display_calendar'] = $form_state->getValue('display_calendar');
  }

}
