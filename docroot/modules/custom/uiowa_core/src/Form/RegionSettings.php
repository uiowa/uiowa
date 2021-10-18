<?php

namespace Drupal\uiowa_core\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Uiowa Core settings for this site.
 */
class RegionSettings extends ConfigFormBase {

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_core_region_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uiowa_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('uiowa_core.settings');

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>These settings allow you to configure static regions of your website.</p>'),
    ];

    $fid = $config->get('uiowa_core.pre_footer');
    $form['pre_footer'] = [
      '#type' => 'entity_autocomplete',
      '#title' => 'Pre Footer Fragment',
      '#target_type' => 'fragment',
      '#default_value' => $fid != null ? $this->entityTypeManager->getStorage('fragment')->load($fid) : null,
      '#selection_settings' => [
        'target_bundles' => ['region_item'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('uiowa_core.settings')
      ->set('uiowa_core.pre_footer', $values['pre_footer'])
      ->save();
    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
