<?php

namespace Drupal\facilities_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\uiowa_core\HeadlineHelper;

/**
 * A Utility Alerts block.
 *
 * @Block(
 *   id = "utility_alerts_block",
 *   admin_label = @Translation("Utility Alerts Block"),
 *   category = @Translation("Site custom"),
 * )
 */
class UtilityAlertsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'headline' => NULL,
      'hide_headline' => 0,
      'heading_size' => 'h2',
      'headline_style' => 'default',
      'headline_alignment' => 'default',
      'child_heading_size' => 'h2',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['headline'] = HeadlineHelper::getElement([
      'headline' => $config['headline'] ?? NULL,
      'hide_headline' => $config['hide_headline'] ?? 0,
      'heading_size' => $config['heading_size'] ?? 'h2',
      'headline_style' => $config['headline_style'] ?? 'default',
      'headline_alignment' => $config['headline_alignment'] ?? 'default',
      'child_heading_size' => $config['child_heading_size'] ?? 'h2',
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    foreach ($form_state->getValues()['headline']['container'] as $name => $value) {
      $this->configuration[$name] = $value;
    }
    parent::blockSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();

    if (empty($config['headline'])) {
      $child_heading_size = $config['child_heading_size'];
    }
    else {
      $child_heading_size = HeadlineHelper::getHeadingSizeUp($config['heading_size']);
    }

    $build['heading'] = [
      '#theme' => 'uiowa_core_headline',
      '#headline' => $config['headline'],
      '#hide_headline' => $config['hide_headline'],
      '#heading_size' => $config['heading_size'],
      '#headline_style' => $config['headline_style'],
      '#headline_alignment' => $config['headline_alignment'] ?? 'default',
    ];

    $build['alerts'] = [
      '#markup' => '<div class="utility-alerts-container"><p>Loading utility alerts...</p></div>',
      '#attached' => [
        'library' => [
          'facilities_core/utility_alerts',
        ],
        'drupalSettings' => [
          'facilities_core' => [
            'utilityAlertsUrl' => Url::fromRoute('facilities_core.utility_alerts')->toString(),
            'headingSize' => $child_heading_size,
          ],
        ],
      ],
    ];

    return $build;
  }

}
