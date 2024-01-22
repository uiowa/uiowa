<?php

namespace Drupal\facilities_core;

use Drupal\uiowa_core\EntityItemProcessorBase;

/**
 * A processor for syncing building nodes.
 */
class ProjectItemProcessor extends EntityItemProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected static $fieldMap = [
    'title' => 'projectTitle',
    'field_project_number' => 'buiProjectId',
  ];

}
