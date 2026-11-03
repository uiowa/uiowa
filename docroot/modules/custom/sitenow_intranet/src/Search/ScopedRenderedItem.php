<?php

namespace Drupal\sitenow_intranet\Search;

use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\processor\RenderedItem;
use Drupal\sitenow_intranet\Render\RecursionGuard;

/**
 * Renders each indexed item with the recursion guards scoped to that item.
 *
 * The index stores rendered node output, so indexing renders page after page
 * in one process. This is the boundary the guards should be scoped to: one
 * call, one item, one render tree.
 *
 * Swapped in for the stock processor by ScopedRenderedItemSubscriber; it is
 * deliberately outside the plugin directory so it is not discovered as a
 * processor of its own.
 */
class ScopedRenderedItem extends RenderedItem {

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    RecursionGuard::reset();

    parent::addFieldValues($item);
  }

}
