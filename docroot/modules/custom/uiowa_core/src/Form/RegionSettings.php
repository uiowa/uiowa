<?php

namespace Drupal\uiowa_core\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
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
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('renderer')
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
      '#markup' => $this->t('<div class="form-item__description">Add a new region item to be managed below.</div>'),
    ];
    $query = $this->entityTypeManager->getStorage('block')->getQuery();
    $query->condition('plugin', 'region_content_block');
    $region_content_blocks = $query->execute();
    $region_config = $config->get('uiowa_core.region_content');
    $form['active_region_content_blocks'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Active region content blocks'),
    ];
    $form['active_region_content_blocks']['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<div class="form-item__description">Configure the active regions to display curated layout builder content.</div>'),
    ];
    foreach ($region_content_blocks as $key => $value) {
      $title_array = explode('_', $key);
      $title_array[0] = ucwords($title_array[0]);
      $title = implode(" ", $title_array);
      $fid = $region_config[$value] ?? NULL;
      $form['active_region_content_blocks'][$key] = [
        '#type' => 'entity_autocomplete',
        '#title' => $title,
        '#description' => $this->t('Enter the name of the region item you would like to place in the @title region of the site.', [
          '@title' => $title,
        ]),
        '#target_type' => 'fragment',
        '#default_value' => $fid != NULL ? $this->entityTypeManager->getStorage('fragment')->load($fid) : NULL,
        '#selection_settings' => [
          'target_bundles' => ['region_item'],
        ],
      ];
    }

    $form['region_items'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Region items'),
    ];
    $form['region_items']['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<div class="form-item__description">Manage the region items that have been created. You may configure the layout for your regions here.</div>'),
    ];
    $view = views_embed_view('region_items', 'region_items_block');
    $render = $this->renderer->render($view);
    $form['region_items']['region_items_view'] = [
      '#type' => 'markup',
      '#markup' => $render,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->config('uiowa_core.settings');
    $query = $this->entityTypeManager->getStorage('block')->getQuery();
    $query->condition('plugin', 'region_content_block');
    $region_content_blocks = $query->execute();
    foreach ($region_content_blocks as $key => $value) {
      $config->set('uiowa_core.region_content.' . $key, $values[$key]);
    }
    $config->save();
    parent::submitForm($form, $form_state);

    // Clear cache.
    drupal_flush_all_caches();
  }

}
