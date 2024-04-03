<?php

namespace Drupal\emergency_core;

use Drupal\uiowa_core\EntityItemProcessorBase;

/**
 * A processor for syncing hawk alert nodes.
 */
class AlertItemProcessor extends EntityItemProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected static $fieldMap = [
    'title' => 'headline',
    'body' => 'description',
  ];

}
