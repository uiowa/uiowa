<?php

namespace Drupal\sitenow_p2lb\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Primary paragraphs2layoutbuilder class.
 */
class P2LbSettingsForm extends ConfigFormBase {
  /**
   * The config.storage service.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config.factory service.
   * @param \Drupal\Core\Config\StorageInterface $configStorage
   *   The config.storage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity_type.manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StorageInterface $configStorage, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($config_factory);
    $this->configStorage = $configStorage;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.storage'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_p2lb_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>These settings let you configure and use SiteNow paragraphs2layoutbuilder on this site.</p>'),
    ];

    // Grab all nodes that currently have paragraphs associated with them.
    $nids_w_paragraphs = sitenow_p2lb_paragraph_nodes();

    // Set the key=>value to use the nid for both.
    $nids_w_paragraphs = array_combine($nids_w_paragraphs, $nids_w_paragraphs);

    $form['nodes_w_paragraphs'] = [
      '#type' => 'select',
      '#title' => $this->t('Nodes with paragraph items.'),
      '#options' => $nids_w_paragraphs,
      '#multiple' => TRUE,
    ];

    $form['update'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#button_type' => 'primary',
      '#name' => 'update',
      '#submit' => [
        [$this, 'updateButton'],
      ],
    ];

    $form['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
      '#name' => 'delete',
      '#submit' => [
        [$this, 'deleteButton'],
      ],
    ];

    $form['magic'] = [
      '#type' => 'submit',
      '#button_type' => 'danger',
      '#value' => $this->t('MAGIC'),
      '#name' => 'magic',
      '#submit' => [
        [$this, 'magicButton'],
      ],
      '#attributes' => [
        'onclick' => 'if(!confirm("Are you ready to be amazed?")){return false};',
      ],
    ];

    // Unset the original, unused submit button.
    unset($form['actions']['submit']);
    return $form;
  }

  /**
   * Delete connected paragraphs from the selected nodes.
   */
  public function deleteButton(array &$form, FormStateInterface $form_state) {
    // Grab nids for all boxes that were checked (0s are filtered out).
    $nids = array_filter(array_values($form_state->getValue('nodes_w_paragraphs')));
    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($nids);
    foreach ($nodes as $node) {
      sitenow_p2lb_remove_attached_paragraphs($node);
    }
    return $form_state;
  }

  /**
   * Update paragraphs to lb blocks from the selected nodes.
   */
  public function updateButton(array &$form, FormStateInterface $form_state) {
    // Grab nids for all boxes that were checked (0s are filtered out).
    $nids = array_filter(array_values($form_state->getValue('nodes_w_paragraphs')));
    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($nids);
    foreach ($nodes as $node) {
      sitenow_p2lb_node_p2lb($node);
    }
    // @todo Option to remove paragraphs after migrate, or review first?
    return $form_state;
  }

  /**
   * Update paragraphs to lb blocks from the selected nodes.
   */
  public function magicButton(array &$form, FormStateInterface $form_state) {
    // Update config to enable layout builder for page layouts.
    $config_path = Settings::get('config_sync_directory');
    $source = new FileStorage($config_path);
    $this->configStorage->write('core.entity_view_display.node.page.default', $source->read('core.entity_view_display.node.page.default'));

    // Grab all nids for nodes with paragraphs.
    $nids = sitenow_p2lb_paragraph_nodes();
    foreach ($nids as $nid) {
      sitenow_p2lb_node_p2lb($nid, TRUE);
    }
    return $form_state;
  }

}
