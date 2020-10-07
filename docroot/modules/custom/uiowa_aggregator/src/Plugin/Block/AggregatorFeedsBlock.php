<?php

namespace Drupal\uiowa_aggregator\Plugin\Block;

use Drupal\aggregator\FeedStorageInterface;
use Drupal\aggregator\ItemStorageInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\MetadataBubblingUrlGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "uiowa_aggregator_feeds",
 *   admin_label = @Translation("Aggregator feeds"),
 *   category = @Translation("Lists (Views)")
 * )
 */
class AggregatorFeedsBlock extends BlockBase implements ContainerFactoryPluginInterface {
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
   * The entity storage for feeds.
   *
   * @var \Drupal\aggregator\FeedStorageInterface
   */
  protected $feedStorage;

  /**
   * The entity storage for items.
   *
   * @var \Drupal\aggregator\ItemStorageInterface
   */
  protected $itemStorage;

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Render\MetadataBubblingUrlGenerator
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entityTypeManager, ConfigFactoryInterface $configFactory, FeedStorageInterface $feed_storage, ItemStorageInterface $item_storage, MetadataBubblingUrlGenerator $urlGenerator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->feedStorage = $feed_storage;
    $this->itemStorage = $item_storage;
    $this->urlGenerator = $urlGenerator;
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
      $container->get('config.factory'),
      $container->get('entity_type.manager')->getStorage('aggregator_feed'),
      $container->get('entity_type.manager')->getStorage('aggregator_item'),
      $container->get('url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $feeds = $this->feedStorage->loadMultiple();
    $options = [];

    foreach ($feeds as $feed) {
      $options[$feed->id()] = $feed->label();
    }

    // @todo: Investigate shared parent field for use here.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('An optional title to display before the feed items.'),
      '#default_value' => $this->configuration['title'],
    ];

    $form['feeds'] = [
      '#type' => 'select',
      '#title' => $this->t('Feeds'),
      '#description' => $this->t('The <a href="@link">feed(s)</a> to display items from. Sorted by most recent.', [
        '@link' => $this->urlGenerator->generateFromRoute('aggregator.admin_overview'),
      ]),
      '#default_value' => $this->configuration['feeds'],
      '#multiple' => TRUE,
      '#options' => $options,
      '#required' => TRUE,
    ];

    $min = 1;
    $max = 25;

    $form['item_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of items'),
      '#description' => $this->t('The number of items to display. Min: @min. Max: @max', [
        '@min' => $min,
        '@max' => $max,
      ]),
      '#default_value' => $this->configuration['item_count'],
      '#min' => $min,
      '#max' => $max,
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
    $this->configuration['title'] = $values['title'];
    $this->configuration['feeds'] = $values['feeds'];
    $this->configuration['item_count'] = $values['item_count'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $title = $this->getConfiguration()['title'];
    $count = $this->getConfiguration()['item_count'];
    $feeds = $this->getConfiguration()['feeds'];

    $result = $this->itemStorage->getQuery()
      ->condition('fid', $feeds, 'IN')
      ->range(0, $count)
      ->sort('timestamp', 'DESC')
      ->sort('iid', 'DESC')
      ->execute();

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'aggregator-wrapper',
          'uiowa-aggregator',
        ],
      ],
    ];

    if (!empty($title)) {
      $build['title'] = [
        '#markup' => $this->t('@title', [
          '@title' => $title,
        ]),
        '#prefix' => '<h2 class="uiowa-aggregator-title">',
        '#suffix' => '</h2>',
      ];
    }

    if ($result) {
      $build['feed_source'] = ['#markup' => ''];
      $items = $this->itemStorage->loadMultiple($result);

      if ($items) {
        $build['items'] = $this->entityTypeManager->getViewBuilder('aggregator_item')->viewMultiple($items, 'default');
      }
    }
    else {
      $build['no_results'] = [
        '#markup' => $this->t('There are on results.'),
        '#prefix' => '<div class="uiowa-aggregator-no-results">',
        '#suffix' => '</div>',
      ];
    }

    $build['#attached']['feed'][] = [
      'aggregator/rss',
      $this->configFactory->get('system.site')->get('name') . ' ' . $this->t('aggregator'),
    ];

    return $build;
  }

}
