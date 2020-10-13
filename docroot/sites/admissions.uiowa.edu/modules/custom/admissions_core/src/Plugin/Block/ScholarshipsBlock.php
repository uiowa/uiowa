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

    $type = $config['scholarship_type'];
    $resident = $config['resident'];
    $view = Views::getView('scholarships');
    $view->setDisplay('block_scholarships');
    $view->setArguments([$type, $resident]);
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
      '#type' => 'select',
      '#title' => $this->t('Scholarship Type'),
      '#description' => $this->t('Filter scholarships by this type.'),
      '#options' => [
        'first-year' => 'First Year',
        'transfer' => 'Transfer',
        'international' => 'International'
      ],
      '#default_value' => isset($config['scholarship_type']) ? $config['scholarship_type'] : 'first-year',
    ];
    $form['resident'] = [
      '#type' => 'select',
      '#title' => $this->t('Resident'),
      '#description' => $this->t('Filter scholarships by resident status.'),
      '#options' => [
        'resident' => 'Resident',
        'nonresident' => 'Non-Resident'
      ],
      '#default_value' => isset($config['resident']) ? $config['resident'] : 'resident',
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
