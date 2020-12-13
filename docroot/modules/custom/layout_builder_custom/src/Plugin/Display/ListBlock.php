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
      'filter_in_block' => $this->t('Set exposed filters in block settings.'),
    ];
    $filter_intersect = array_intersect_key($filter_options, $filtered_allow);

    $options['allow'] = [
      'category' => 'block',
      'title' => $this->t('Allow settings'),
      'value' => empty($filtered_allow) ? $this->t('None') : implode(', ', $filter_intersect),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['allow']['#options']['offset'] = $this->t('Pager offset');
    $form['allow']['#options']['filter_in_block'] = $this->t('Set filters in block');

    $defaults = [];
    if (!empty($form['allow']['#default_value'])) {
      $defaults = array_filter($form['allow']['#default_value']);
      if (!empty($defaults['items_per_page'])) {
        $defaults['items_per_page'] = 'items_per_page';
      }
    }

    $form['allow']['#default_value'] = $defaults;
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

    if (!empty($allow_settings['pager_display'])) {
      $form['override']['pager'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display pager'),
        '#default_value' => isset($block_configuration['pager_display']) ? $block_configuration : FALSE,
      ];
    }

    // @todo Add "Display more" toggle.
    // @todo Add "Display more path" setting.
    // @todo Only show "Display more path" setting when "Display more" is checked.
    // @todo Add "Sort" setting. How to populate this from the view?

    // Provide "Exposed filters" block settings form.
    if (!empty($allow_settings['filter_in_block'])) {
      $items = [];
      foreach ((array) $this->getOption('filters') as $filter_name => $item) {
        $item['value'] = isset($block_configuration["filter"][$filter_name]['value']) ? $block_configuration["filter"][$filter_name]['value'] : '';
        $items[$filter_name] = $item;
      }
      $this->setOption('filters', $items);
      $filters = $this->getHandlers('filter');

      // Add a settings form for each exposed filter to configure or hide it.
      foreach ($filters as $filter_name => $plugin) {
        if ($plugin->isExposed() && $exposed_info = $plugin->exposedInfo()) {
          $form['override']['filters'][$filter_name] = [
            '#type' => 'details',
            '#title' => $exposed_info['label'],
          ];

          $form['override']['filters'][$filter_name]['plugin'] = [
            '#type' => 'value',
            '#value' => $plugin,
          ];

          $form['override']['filters'][$filter_name][$filter_name] = [
            '#title' => $exposed_info['label'],
            '#default_value' => !empty($block_configuration['filter'][$filter_name]['value']) ? $block_configuration['filter'][$filter_name]['value'] : 'any',
          ];

          $plugin->buildExposedForm($form['override']['filters'][$filter_name], $form_state);
          // @todo Probably should unset 'Reduce duplicates' checkbox.
        }
      }
    }

    return $form;
  }

  public function blockSubmit(ViewsBlock $block, $form, FormStateInterface $form_state) {
    parent::blockSubmit($block, $form, $form_state);
    $configuration = $block->getConfiguration();
    $allow_settings = array_filter($this->getOption('allow'));

    // Set default value for items_per_page if left blank.
    if (empty($form_state->getValue(['override', 'items_per_page']))) {
      $form_state->setValue(['override', 'items_per_page'], "none");
    }

    // Save "Pager offset" settings to block configuration.
    if (!empty($allow_settings['offset'])) {
      $configuration['pager_offset'] = $form_state->getValue(['override', 'pager_offset']);
    }

    // @todo Save "Display pager" setting to block configuration.
    // @todo Save "Display more path" setting to block configuration.
    // @todo Save "Sort" setting to block configuration.

    // Save "Filter in block" settings to block configuration.
    if (!empty($allow_settings['filter_in_block'])) {
      if ($filters = $form_state->getValue(['override', 'filters'])) {
        // Loop through filters.
        foreach ($filters as $filter_name => $filter) {
          /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $plugin */
          $plugin = $form_state->getValue([
            'override', 'filters', $filter_name, 'plugin',
          ]);
          if ($plugin) {
            $configuration['filter'][$filter_name]['type'] = $plugin->getPluginId();

            // Retrieve the saved value of the exposed filter.
            $filter_value = $form_state->getValue([
              'override', 'filters', $filter_name, $filter_name,
            ]);

            if ($filter_value) {
              if (is_array($filter_value)) {
                $filter_value = array_column($filter_value, 'target_id');
                $filter_value = implode('+', $filter_value);
              }
              // Save exposed filter setting as block configuration.
              $configuration['filter'][$filter_name]['value'] = $filter_value;
            }
          }
        }
      }
    }

    $block->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function preBlockBuild(ViewsBlock $block) {
    parent::preBlockBuild($block);

    $allow_settings = array_filter($this->getOption('allow'));
    $config = $block->getConfiguration();
    list(, $display_id) = explode('-', $block->getDerivativeId(), 2);
    $filters = $this->view->getHandlers('filter', $display_id);
    $filters_changed = TRUE;

    if (!empty($allow_settings['items_per_page']) && !empty($config['items_per_page'])) {
      $this->view->setItemsPerPage($config['items_per_page']);
      $this->view->setExposedInput([
        'items_per_page' => $config['items_per_page'],
      ]);
    }

    // Change pager offset settings based on block configuration.
    if (!empty($allow_settings['offset'])) {
      $this->view->setOffset($config['pager_offset']);
    }

    // @todo Set view pager based on "Display pager" setting.
    // @todo Figure out how to display a more link based on "Display more path" setting.
    // @todo Set view sorts based on "Sort" setting.
    // @todo Set view filter based on "Filter" setting.
    if (!empty($allow_settings['filter_in_block'])) {
      foreach ($filters as $filter_name => $value) {
        if (!empty($config['filter'][$filter_name])) {
          // Override exposed filter value from block settings.
          $filters[$filter_name]['value'] = $config['filter'][$filter_name]['value'];
          // Set exposed filter to not show.
          $filters[$filter_name]['exposed'] = FALSE;
          $changed = TRUE;
          continue;
        }
      }
    }

    if ($filters_changed) {
      $this->view->display_handler->overrideOption('filters', $filters);
    }
  }
}
