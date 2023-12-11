<?php

namespace Drupal\uiowa_aggregator;

use Drupal\aggregator\FeedInterface;
use Drupal\aggregator\ItemsImporter;
use Drupal\aggregator\Plugin\AggregatorPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Modify the Aggregator ItemsImporter with a custom refresh.
 */
class ItemsImporterOverride extends ItemsImporter {

  /**
   * Original service object.
   *
   * @var \Drupal\aggregator\ItemsImporter
   */
  protected $itemsImporter;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ItemsImporter $itemsImporter,
    ConfigFactoryInterface $configFactory,
    AggregatorPluginManager $fetcherManager,
    AggregatorPluginManager $parserManager,
    AggregatorPluginManager $processorManager,
    LoggerInterface $logger,
    KeyValueFactoryInterface $keyValue,
  ) {
    $this->itemsImporter = $itemsImporter;
    parent::__construct($configFactory, $fetcherManager, $parserManager, $processorManager, $logger, $keyValue);
  }

  /**
   * {@inheritdoc}
   */
  public function refresh(FeedInterface $feed) {
    $purgeItems = $feed->get('field_aggregator_purge_items')?->value;

    if ($purgeItems) {
      $feed->deleteItems();
    }
    parent::refresh($feed);
  }

}
