<?php

namespace Drupal\grad_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\node\Plugin\migrate\source\d7\Node as D7Node;
use Drupal\sitenow_migrate\Plugin\migrate\source\BaseNodeSource;
use Drupal\sitenow_migrate\Plugin\migrate\source\ProcessMediaTrait;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "grad_article",
 *  source_provider = "node"
 * )
 */
class Article extends D7Node {

  use ProcessMediaTrait;

}
