<?php

namespace Drupal\uiowa_aggregator\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "uiowa_aggregator_all_feeds",
 *   admin_label = @Translation("All aggregator feeds combined"),
 *   category = @Translation("Lists (Views)")
 * )
 */
class AllFeedsBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The ConfigFactory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entityTypeManager, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $range = range(1, 20);

    $form['block_count'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of news items in block'),
      '#default_value' => $this->configuration['block_count'],
      '#options' => array_combine($range, $range),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['block_count'] = $values['block_count'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $count = $this->getConfiguration()['block_count'] ?? 20;

    $items = $this->entityTypeManager->getStorage('aggregator_item')->loadAll($count);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['aggregator-wrapper']],
    ];

    $build['feed_source'] = ['#markup' => ''];

    if ($items) {
      $build['items'] = $this->entityTypeManager->getViewBuilder('aggregator_item')->viewMultiple($items, 'default');
      $build['pager'] = ['#type' => 'pager'];
    }

    $build['#attached']['feed'][] = [
      'aggregator/rss',
      $this->configFactory->get('system.site')->get('name') . ' ' . $this->t('aggregator'),
    ];

    return $build;

  }

}
