<?php

namespace Drupal\uiowa_aggregator;

use Drupal\aggregator\FeedInterface;
use Drupal\aggregator\ItemsImporter;
use Drupal\aggregator\Plugin\AggregatorPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 *
 */
class ItemsImporterOverride extends ItemsImporter {

  /**
   * Original service object.
   *
   * @var \Drupal\aggregator\ItemsImporter
   */
  protected $itemsImporter;

  /**
   *
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
   *
   */
  public function refresh(FeedInterface $feed) {
    $purgeItems = $feed->get('purge_items')?->value;

    if ($purgeItems) {
      $existingItems = \Drupal::entityTypeManager()->getStorage('aggregator_item')->loadByProperties(['fid' => $feed->id()]);

      foreach ($existingItems as $item) {
        $item->delete();
      }
    }
    parent::refresh($feed);
  }
}
