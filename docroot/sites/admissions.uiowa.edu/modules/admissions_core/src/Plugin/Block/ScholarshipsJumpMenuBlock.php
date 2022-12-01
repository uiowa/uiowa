<?php

namespace Drupal\admissions_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Scholarships "Jump Menu" block.
 *
 * @Block(
 *   id = "admissions_core_scholarships_jump_menu",
 *   admin_label = @Translation("Scholarships Jump Menu"),
 *   category = @Translation("Site custom")
 * )
 */
class ScholarshipsJumpMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form_builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $paths = [
      'transfer' => $config['transfer_path'],
      'international' => $config['international_path'],
      'resident' => $config['resident_path'],
      'nonresident' => $config['nonresident_path'],
    ];
    $form_state = new FormState();
    $form_state->addBuildInfo('scholarship_paths', $paths);
    return $this->formBuilder->buildForm('Drupal\admissions_core\Form\ScholarshipsJumpMenu', $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();
    if (isset($config['transfer_path'])) {
      $transfer_entity = $this->entityTypeManager->getStorage('node')->load($config['transfer_path']);
    }
    $form['transfer_path'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Transfer Path'),
      '#description' => $this->t('Url for the transfer option'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['page'],
      ],
      '#default_value' => $transfer_entity ?? NULL,
      '#required' => TRUE,
    ];
    if (isset($config['international_path'])) {
      $international_entity = $this->entityTypeManager->getStorage('node')->load($config['international_path']);
    }
    $form['international_path'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('International Path'),
      '#description' => $this->t('Url for the international option'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['page'],
      ],
      '#default_value' => $international_entity ?? NULL,
      '#required' => TRUE,
    ];
    if (isset($config['resident_path'])) {
      $resident_entity = $this->entityTypeManager->getStorage('node')->load($config['resident_path']);
    }
    $form['resident_path'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Resident Path'),
      '#description' => $this->t('Url for the resident option'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['page'],
      ],
      '#default_value' => $resident_entity ?? NULL,
      '#required' => TRUE,
    ];
    if (isset($config['nonresident_path'])) {
      $nonresident_entity = $this->entityTypeManager->getStorage('node')->load($config['nonresident_path']);
    }
    $form['nonresident_path'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Non-Resident Path'),
      '#description' => $this->t('Url for the nonresident option'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['page'],
      ],
      '#default_value' => $nonresident_entity ?? NULL,
      '#required' => TRUE,
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
