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
    // This is how views says ANDs should be strung together. Doesn't work though.
    $type_args = implode(',', $types);
    if (empty($type_args)) {
      $type_args = 'all';
    }
    $resident = [];
    foreach ($config['resident'] as $option) {
      if ($option !== 0) {
        $resident[] = $option;
      }
    }
    // This is how views says ANDs should be strung together. Doesn't work though.
    $resident_args = implode(',', $resident);
    if (empty($resident_args)) {
      $resident_args = 'all';
    }
    $view->setArguments([$type_args, $resident_args]);

    $view->preExecute();
    $view->execute();

    // Poor-man's solution to deal with the non-functioning views query...
    // The view provides back OR results which must be filtered more to meet the AND expectation.
    // @todo Remove when views core fixes https://github.com/uiowa/uiowa/issues/2109.
    foreach ($view->result as $key => $item) {
      $node_storage = \Drupal::entityTypeManager()->getStorage('node');
      $node = $node_storage->load($item->nid);
      if (!empty($type_args)) {
        $scholarship_types = $node->get('field_scholarship_type')->getValue();
        $values = [];
        foreach($scholarship_types as $value) {
          $values[] = $value['value'];
        }
        $actual_types = implode(',', $values);
          if ($actual_types !== $type_args && $type_args !== 'all') {
            unset($view->result[$key]);
          }
      }
      if (!empty($resident_args)) {
        $scholarship_resident = $node->get('field_scholarship_resident')->getValue();
        $values = [];
        foreach($scholarship_resident as $value) {
          $values[] = $value['value'];
        }
        $actual_resident = implode(',', $values);
        if ($actual_resident !== $resident_args && $resident_args !== 'all') {
          unset($view->result[$key]);
        }
      }
    }

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
      '#description' => $this->t('Filter scholarships by these types. Leave blank for all. Multiple selections are treated like ANDs.'),
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
      '#description' => $this->t('Filter scholarships by resident statuses. Leave blank for all. Multiple selections are treated like ANDs.'),
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
