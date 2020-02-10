<?php

/**
 * @file
 * API documentation for Automatic Entity Label module.
 */

/**
 * Provide post-processing of auto generated titles (labels).
 *
 * @param string $label
 *   The auto-generated label to be altered.
 * @param object $entity
 *   The entity that the label is from.
 *
 * @see \Drupal\auto_entitylabel\AutoEntityLabelManager::generateLabel()
 */
function hook_auto_entitylabel_label_alter(&$label, $entity) {
  // Trim the label.
  $label = trim($label);
}
