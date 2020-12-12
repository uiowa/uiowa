<?php

namespace Drupal\layout_builder_custom\Plugin\Display;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\Block\ViewsBlock;
use Drupal\views\Plugin\views\display\Block as CoreBlock;

/**
 * Provides a List Block display plugin.
 *
 * Adapted from Drupal\ctools_views\Plugin\Display\Block.
 */
class ListBlock extends CoreBlock {

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);
    $filtered_allow = array_filter($this->getOption('allow'));
    $filter_options = [
      'items_per_page' => $this->t('Items per page'),
      'offset' => $this->t('Pager offset'),
      'pager' => $this->t('Pager type'),
      'hide_fields' => $this->t('Hide fields'),
      'sort_fields' => $this->t('Reorder fields'),
      'disable_filters' => $this->t('Disable filters'),
      'configure_sorts' => $this->t('Configure sorts'),
    ];
    $filter_intersect = array_intersect_key($filter_options, $filtered_allow);

    $options['allow'] = [
      'category' => 'block',
      'title' => $this->t('Allow settings'),
      'value' => empty($filtered_allow) ? $this->t('None') : implode(', ', $filter_intersect),
    ];
  }

  public function blockForm(ViewsBlock $block, array &$form, FormStateInterface $form_state) {
    $form = parent::blockForm($block, $form, $form_state);

    $allow_settings = array_filter($this->getOption('allow'));
    $block_configuration = $block->getConfiguration();

    // Modify "Items per page" block settings form.
    if (!empty($allow_settings['items_per_page'])) {
      // Items per page.
      $form['override']['items_per_page']['#type'] = 'number';
      unset($form['override']['items_per_page']['#options']);
    }

    // Provide "Pager offset" block settings form.
    if (!empty($allow_settings['offset'])) {
      $form['override']['pager_offset'] = [
        '#type' => 'number',
        '#title' => $this->t('Pager offset'),
        '#default_value' => isset($block_configuration['pager_offset']) ? $block_configuration['pager_offset'] : 0,
        '#description' => $this->t('For example, set this to 3 and the first 3 items will not be displayed.'),
      ];
    }

    // @todo Add additional block settings.
    // @todo Add show pager setting.
    // @todo Add display more setting.
    // @todo Add display more path setting.
    // @todo Add sort setting. How to populate this from the view?
    // @todo Add filter setting. How to populate this from the view?

    return $form;
  }

  public function blockSubmit(ViewsBlock $block, $form, FormStateInterface $form_state) {
    parent::blockSubmit($block, $form, $form_state);
    $configuration = $block->getConfiguration();
    $allow_settings = array_filter($this->getOption('allow'));

    // @todo Save additional block settings.

  }

  public function preBlockBuild(ViewsBlock $block) {
    parent::preBlockBuild($block);

    $allow_settings = array_filter($this->getOption('allow'));
    $config = $block->getConfiguration();

    // @todo Change view settings based on block settings.
  }
}
