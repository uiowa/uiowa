<?php

namespace Drupal\layout_builder_custom\Plugin\Display;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\views\Plugin\Block\ViewsBlock;
use Drupal\views\Plugin\views\display\Block as CoreBlock;

/**
 * Provides a List Block display plugin.
 *
 * Adapted from Drupal\ctools_views\Plugin\Display\Block and
 * https://www.drupal.org/project/views_block_placement_exposed_form_defaults.
 */
class ListBlock extends CoreBlock {

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    if ($form_state->get('section') !== 'allow') {
      return;
    }

    $form['allow']['#options']['offset'] = $this->t('Pager offset');
    $form['allow']['#options']['hide_fields'] = $this->t('Hide fields');
    $form['allow']['#options']['sort_sorts'] = $this->t('Adjust the order of sorts');
    $form['allow']['#options']['display_more_link'] = $this->t('Display more link');

    $defaults = [];
    if (!empty($form['allow']['#default_value'])) {
      $defaults = array_filter($form['allow']['#default_value']);
      if (!empty($defaults['items_per_page'])) {
        $defaults['items_per_page'] = 'items_per_page';
      }
    }

    $form['allow']['#default_value'] = $defaults;

    // Show a text area to add custom help text to the display more link.
    $more_link_help_text = $this->getOption('more_link_help_text');
    $form['more_link_help_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('More link help text'),
      '#description' => $this->t('Set help text to display below more link.'),
      '#default_value' => $more_link_help_text ?: '',
      '#states' => [
        'visible' => [
          [
            "input[name='allow[display_more_link]']" => [
              'checked' => TRUE,
            ],
          ],
        ],
      ],
    ];

    // Show exposed filters that can be set in the block form.
    $customized_filters = $this->getOption('filter_in_block');
    $form['filter_in_block'] = [
      '#type' => 'checkboxes',
      '#options' => $this->getListOfExposedFilters(),
      '#title' => $this->t('Filter in block'),
      '#description' => $this->t('Select the filters which users should be able to customize default values for when placing the views block into a layout.'),
      '#default_value' => !empty($customized_filters) ? $customized_filters : [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    if ($form_state->get('section') === 'allow') {
      $this->setOption('filter_in_block', Checkboxes::getCheckedCheckboxes($form_state->getValue('filter_in_block')));
      $this->setOption('more_link_help_text', $form_state->getValue('more_link_help_text'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);
    $filtered_allow = array_filter($this->getOption('allow'));
    $filter_options = [
      'items_per_page' => $this->t('Items per page'),
      'offset' => $this->t('Pager offset'),
      'display_more_link' => $this->t('Display more link'),
      'hide_fields' => $this->t('Hide fields'),
      'sort_sorts' => $this->t('Adjust the order of sorts'),
    ];
    $filter_intersect = array_intersect_key($filter_options, $filtered_allow);

    $options['allow'] = [
      'category' => 'block',
      'title' => $this->t('Allow settings'),
      'value' => empty($filtered_allow) ? $this->t('None') : implode(', ', $filter_intersect),
    ];

    $customizable_filters = $this->getOption('filter_in_block');
    $filter_count = !empty($customizable_filters) ? count($customizable_filters) : 0;
    $options['allow']['value'] .= ', ' . $this->formatPlural($filter_count, '1 filter in block', '@count filters in block');
  }

  /**
   * Get a list of exposed filters.
   *
   * @return array
   *   An array of filters keyed by machine name with label values.
   */
  protected function getListOfExposedFilters() {
    $filter_options = [];
    foreach ($this->getHandlers('filter') as $filer_name => $filter_plugin) {
      if ($filter_plugin->isExposed() && $exposed_info = $filter_plugin->exposedInfo()) {
        $filter_options[$filer_name] = $exposed_info['label'];
      }
    }
    return $filter_options;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm(ViewsBlock $block, array &$form, FormStateInterface $form_state) {
    $form = parent::blockForm($block, $form, $form_state);
    $form['exposed_filters'] = [
      '#tree' => TRUE,
    ];

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
        '#default_value' => isset($block_configuration['pager_display']) ? $block_configuration['pager_display'] : FALSE,
      ];
    }

    // Provide "Hide fields" block settings form.
    if (!empty($allow_settings['hide_fields'])) {
      // Set up the configuration table for hiding / sorting fields.
      $fields = $this->getHandlers('field');
      $header = [];
      if (!empty($allow_settings['hide_fields'])) {
        $header['hide'] = $this->t('Hide');
      }
      $header['label'] = $this->t('Label');
      if (!empty($allow_settings['sort_fields'])) {
        $header['weight'] = $this->t('Weight');
      }
      $form['override']['hide_fields'] = [
        '#type' => 'details',
        '#title' => $this->t('Hide fields'),
        '#description' => $this->t('Choose to hide some of the fields.'),
      ];
      $form['override']['hide_fields']['field_list'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => [],
      ];

      // Add each field to the configuration table.
      foreach ($fields as $field_name => $plugin) {
        $field_label = $plugin->adminLabel();
        if (!empty($plugin->options['label'])) {
          $field_label .= ' (' . $plugin->options['label'] . ')';
        }
        $form['override']['hide_fields']['field_list'][$field_name]['#weight'] = !empty($block_configuration['fields'][$field_name]['weight']) ? $block_configuration['fields'][$field_name]['weight'] : 0;
        $form['override']['hide_fields']['field_list'][$field_name]['hide'] = [
          '#type' => 'checkbox',
          '#default_value' => !empty($block_configuration['fields'][$field_name]['hide']) ? $block_configuration['fields'][$field_name]['hide'] : 0,
        ];
        $form['override']['hide_fields']['field_list'][$field_name]['label'] = [
          '#markup' => $field_label,
        ];
      }
    }

    // Provide "Configure sorts" block settings form.
    if (!empty($allow_settings['sort_sorts'])) {
      $form['override']['sort'] = [
        '#type' => 'details',
        '#title' => $this->t('Sort options'),
        '#description' => $this->t('Choose which sorts to use and in which order.'),
      ];

      $sorts = $this->getHandlers('sort');
      $options = [
        'ASC' => $this->t('Sort ascending'),
        'DESC' => $this->t('Sort descending'),
      ];
      foreach ($sorts as $sort_name => $plugin) {
        $form['override']['sort'][$sort_name] = [
          '#type' => 'details',
          '#title' => $plugin->adminLabel(),
        ];
        $form['override']['sort'][$sort_name]['plugin'] = [
          '#type' => 'value',
          '#value' => $plugin,
        ];
        $form['override']['sort'][$sort_name]['order'] = [
          '#title' => $this->t('Order'),
          '#type' => 'radios',
          '#options' => $options,
          '#default_value' => $plugin->options['order'],
        ];

        // Set default values for sorts for this block.
        if (!empty($block_configuration["sort"][$sort_name])) {
          $form['override']['sort'][$sort_name]['order']['#default_value'] = $block_configuration["sort"][$sort_name];
        }
      }
    }

    // Provide "Exposed filters" block settings form.
    $exposed_filter_values = !empty($block_configuration['exposed_filter_values']) ? $block_configuration['exposed_filter_values'] : [];

    $subform_state = SubformState::createForSubform($form['exposed_filters'], $form, $form_state);
    $subform_state->set('exposed', TRUE);

    $customizable_filters = $this->getOption('filter_in_block');
    $filter_plugins = $this->getHandlers('filter');

    foreach ($customizable_filters as $customizable_filter) {
      /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
      $filter = $filter_plugins[$customizable_filter];
      $filter->buildExposedForm($form['exposed_filters'], $subform_state);

      // Set the label and default values of the form element, based on the
      // block configuration.
      $exposed_info = $filter->exposedInfo();
      $form['exposed_filters'][$exposed_info['value']]['#title'] = $exposed_info['label'];
      if ($form['exposed_filters'][$exposed_info['value']]['#type'] == 'entity_autocomplete') {
        $form['exposed_filters'][$exposed_info['value']]['#default_value'] = EntityAutocomplete::valueCallback(
          $form['exposed_filters'][$exposed_info['value']],
          $exposed_filter_values[$exposed_info['value']],
          $form_state
        );
      }
      else {
        $form['exposed_filters'][$exposed_info['value']]['#default_value'] = !empty($exposed_filter_values[$exposed_info['value']]) ? $exposed_filter_values[$exposed_info['value']] : [];
      }
    }

    if (!empty($allow_settings['display_more_link'])) {
      $form['override']['display_more_toggle'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display More link'),
      ];

      // @todo Figure out how to make the style of this field
      //   look like other LinkIt fields.
      $form['override']['display_more_path'] = [
        '#type' => 'linkit',
        '#title' => $this->t('Path'),
        '#description' => $this->t('Start typing to see a list of results. Click to select.'),
        '#autocomplete_route_name' => 'linkit.autocomplete',
        '#autocomplete_route_parameters' => [
          'linkit_profile_id' => 'default',
        ],
        '#default_value' => isset($block_configuration['display_more_path']) ? $block_configuration['display_more_path'] : NULL,
        '#states' => [
          'visible' => [
            [
              "input[name='settings[override][display_more_toggle]']" => [
                'checked' => TRUE,
              ],
            ],
          ],
        ],
      ];
      $form['#attached']['library'][] = 'linkit/linkit.autocomplete';
      // @todo Add "Display more path" setting.
      // @todo Only show "Display more path" setting when "Display more" is checked.
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit(ViewsBlock $block, $form, FormStateInterface $form_state) {
    parent::blockSubmit($block, $form, $form_state);
    $allow_settings = array_filter($this->getOption('allow'));

    // @todo Set default value for items_per_page if left blank.
    // Save "Pager offset" settings to block configuration.
    if (!empty($allow_settings['offset'])) {
      $block->setConfigurationValue('pager_offset', $form_state->getValue([
        'override',
        'pager_offset',
      ]));
    }

    if (!empty($allow_settings['display_more_link'])) {
      // @todo Save "Display pager" setting to block configuration.
      // @todo Save "Display more path" setting to block configuration.
    }

    // Provide "Configure sorts" block settings form.
    if (!empty($allow_settings['sort_sorts'])) {
      // @todo Process configure sorts.
    }

    // Save "Hide fields" settings to block configuration.
    if (!empty($allow_settings['hide_fields'])) {
      if ($fields = array_filter($form_state->getValue([
        'override',
        'hide_fields',
        'field_list',
      ]))) {
        $block->setConfigurationValue('fields', $fields);
      }
    }

    // Save "Filter in block" settings to block configuration.
    $block->setConfigurationValue('exposed_filter_values', $form_state->getValue('exposed_filters'));
  }

  /**
   * {@inheritdoc}
   */
  public function preBlockBuild(ViewsBlock $block) {
    parent::preBlockBuild($block);

    $allow_settings = array_filter($this->getOption('allow'));
    $config = $block->getConfiguration();
    [, $display_id] = explode('-', $block->getDerivativeId(), 2);

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
    // Change fields output based on block configuration.
    if (!empty($allow_settings['hide_fields'])) {
      if (!empty($config['fields']) && $this->view->getStyle()->usesFields()) {
        $fields = $this->view->getHandlers('field');
        foreach (array_keys($fields) as $field_name) {
          // Remove each field in sequence and re-add them to sort
          // appropriately or hide if disabled.
          $this->view->removeHandler($display_id, 'field', $field_name);
          if (empty($allow_settings['hide_fields']) || (!empty($allow_settings['hide_fields']) && empty($config['fields'][$field_name]['hide']))) {
            $this->view->addHandler($display_id, 'field', $fields[$field_name]['table'], $fields[$field_name]['field'], $fields[$field_name], $field_name);
          }
        }
      }
    }

    // @todo Set view sorts based on "Sort" setting.
    // Set view filter based on "Filter" setting.
    $exposed_filter_values = !empty($config['exposed_filter_values']) ? $config['exposed_filter_values'] : [];
    $this->view->setExposedInput($exposed_filter_values);
    $this->view->exposed_data = $exposed_filter_values;
  }

}
