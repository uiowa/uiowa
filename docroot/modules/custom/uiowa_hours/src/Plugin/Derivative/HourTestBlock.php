<?php
/**
 * @file
 * Contains \Drupal\mymodule\Plugin\Derivative\MyModuleBlock.
 */
 
namespace Drupal\uiowa_hours\Plugin\Derivative;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeInterface;
 
/**
 * Provides block plugin definitions for uiowa_hours blocks.
 *
 * @see \Drupal\uiowa_hours\Plugin\Block\HoursTestBlock
 */
class HoursTestBlock extends DerivativeBase implements ContainerDerivativeInterface {
  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions(array $base_plugin_definition) {
    $myblocks = array(
      'uiowa_hour_api' => t('uiowa_hours Block: First'),
    );
    foreach ($myblocks as $block_id => $block_label) {
      $this->derivatives[$block_id] = $base_plugin_definition;
      $this->derivatives[$block_id]['admin_label'] = $block_label;
      $this->derivatives[$block_id]['cache'] = DRUPAL_NO_CACHE;
    }
    return $this->derivatives;
  }
}
