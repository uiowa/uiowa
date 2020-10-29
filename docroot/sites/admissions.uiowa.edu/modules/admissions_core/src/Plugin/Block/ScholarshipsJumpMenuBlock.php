<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Provides the Scholarships "Jump Menu" block.
 *
 * @Block(
 *   id = "admissions_core_scholarships_jump_menu",
 *   admin_label = @Translation("Scholarships Jump Menu"),
 *   category = @Translation("Admissions Core")
 * )
 */
class ScholarshipsJumpMenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $paths = [
      'transfer' => $config['transfer_path'],
      'international' => $config['international_path'],
      'resident' => $config['resident_path'],
      'nonresident' => $config['nonresident_path']
    ];
    $form_state = new FormState();
    $form_state->addBuildInfo('scholarship_paths', $paths);
    return \Drupal::formBuilder()->buildForm('Drupal\admissions_core\Form\ScholarshipsJumpMenu', $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();
    if (isset($config['transfer_path'])) {
      $transfer_entity = Node::load($config['transfer_path']);
    }
    $form['transfer_path'] = array(
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Transfer Path'),
      '#description' => $this->t('Url for the transfer option'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => array(
        'target_bundles' => array('page'),
      ),
      '#default_value' => isset($transfer_entity) ? $transfer_entity : NULL,
      '#required' => TRUE
    );
    if (isset($config['international_path'])) {
      $international_entity = Node::load($config['international_path']);
    }
    $form['international_path'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('International Path'),
      '#description' => $this->t('Url for the international option'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => array(
        'target_bundles' => array('page'),
      ),
      '#default_value' => isset($international_entity) ? $international_entity : NULL,
      '#required' => TRUE
    ];
    if (isset($config['resident_path'])) {
      $resident_entity = Node::load($config['resident_path']);
    }
    $form['resident_path'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Resident Path'),
      '#description' => $this->t('Url for the resident option'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => array(
        'target_bundles' => array('page'),
      ),
      '#default_value' => isset($resident_entity) ? $resident_entity : NULL,
      '#required' => TRUE
    ];
    if (isset($config['nonresident_path'])) {
      $nonresident_entity = Node::load($config['nonresident_path']);
    }
    $form['nonresident_path'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Non-Resident Path'),
      '#description' => $this->t('Url for the nonresident option'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => array(
        'target_bundles' => array('page'),
      ),
      '#default_value' => isset($nonresident_entity) ? $nonresident_entity : NULL,
      '#required' => TRUE
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['transfer_path'] = $values['transfer_path'];
    $this->configuration['international_path'] = $values['international_path'];
    $this->configuration['nonresident_path'] = $values['nonresident_path'];
    $this->configuration['resident_path'] = $values['resident_path'];
  }

}
