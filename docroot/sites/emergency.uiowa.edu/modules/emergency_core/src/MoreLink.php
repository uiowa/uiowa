<?php

namespace Drupal\emergency_core;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field for JSON endpoint.
 */
class MoreLink extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $this->ensurePopulated();
  }

  /**
   * Output the more_info_link.
   */
  protected function ensurePopulated(): void {
    if (!isset($this->list[0])) {
      $url = \Drupal::request()->getSchemeAndHttpHost();
      $this->list[0] = $this->createItem(0, $url);
    }
  }

}
