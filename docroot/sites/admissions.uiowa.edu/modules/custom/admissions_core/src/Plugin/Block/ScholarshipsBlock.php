<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;

/**
 * Provides the Scholarships block.
 *
 * @Block(
 *   id = "admissions_core_scholarships",
 *   admin_label = @Translation("Scholarships"),
 *   category = @Translation("Admissions Core")
 * )
 */
class ScholarshipsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();

    $view = Views::getView('scholarships');
    $view->setDisplay('block_scholarships');

    $types = [];
    foreach ($config["scholarship_type"] as $option) {
      if ($option !== 0) {
        $types[] = $option;
      }
    }
    $type_args = implode('+', $types);
    if (empty($type_args)) {
      $type_args = 'all';
    }
    $resident = [];
    foreach ($config['resident'] as $option) {
      if ($option !== 0) {
        $resident[] = $option;
      }
    }
    $resident_args = implode('+', $resident);
    if (empty($resident_args)) {
      $resident_args = 'all';
    }
    $view->setArguments([$type_args, $resident_args]);

    $view->preExecute();
    $view->execute();
    $build['content'] = $view->render();

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['scholarship_type'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Type'),
      '#description' => $this->t('Filter scholarships by these types. Multiple selections are treated like ORs.'),
      '#options' => [
        'first-year' => 'First Year',
        'transfer' => 'Transfer',
        'international' => 'International'
      ],
      '#default_value' => isset($config['scholarship_type']) ? $config['scholarship_type'] : '',
    ];
    $form['resident'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Resident'),
      '#description' => $this->t('Filter scholarships by resident statuses. Multiple selections are treated like ORs.'),
      '#options' => [
        'resident' => 'Resident',
        'nonresident' => 'Non-Resident'
      ],
      '#default_value' => isset($config['resident']) ? $config['resident'] : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['scholarship_type'] = $values['scholarship_type'];
    $this->configuration['resident'] = $values['resident'];
  }

}
