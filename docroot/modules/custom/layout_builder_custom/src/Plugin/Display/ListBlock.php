<?php

namespace Drupal\layout_builder_custom\Plugin\Display;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\Url;
use Drupal\uiowa_core\HeadlineHelper;
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

    $form['allow']['#options']['items_per_page'] = $this->t('Items to display');
    $form['allow']['#options']['pager'] = $this->t('Show pager');
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
      'items_per_page' => $this->t('Items to display'),
      'pager' => $this->t('Show pager'),
      'offset' => $this->t('Offset'),
      'hide_fields' => $this->t('Hide fields'),
      'sort_sorts' => $this->t('Adjust the order of sorts'),
      'display_more_link' => $this->t('Display more link'),
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

    $form['headline'] = HeadlineHelper::getElement([
      'headline' => $block_configuration['headline'] ?? NULL,
      'hide_headline' => $block_configuration['hide_headline'] ?? 0,
      'heading_size' => $block_configuration['heading_size'] ?? 'h2',
      'headline_style' => $block_configuration['headline_style'] ?? 'default',
      'child_heading_size' => $block_configuration['child_heading_size'] ?? 'h3',
    ]);
    $form['headline']['#weight'] = 1;

    // Modify "Items per page" block settings form.
    if (!empty($allow_settings['items_per_page'])) {
      // Items per page.
      $form['override']['items_per_page']['#type'] = 'number';
      $form['override']['items_per_page']['#title'] = $this->t('Items to display');
      $form['override']['items_per_page']['#description'] = $this->t('Select the number of entries to display');
      unset($form['override']['items_per_page']['#options']);
    }

    // Display exposed filters to allow them to be set for the block.
    $customizable_filters = $this->getOption('filter_in_block');
    if (!empty($customizable_filters)) {
      $form['override']['exposed_filters'] = [
        '#type' => 'details',
        '#title' => $this->t('Exposed filters'),
        '#description' => $this->t('Set default filters.'),
      ];
      // Provide "Exposed filters" block settings form.
      $exposed_filter_values = !empty($block_configuration['exposed_filter_values']) ? $block_configuration['exposed_filter_values'] : [];

      $subform_state = SubformState::createForSubform($form['override']['exposed_filters'], $form, $form_state);
      $subform_state->set('exposed', TRUE);

      $filter_plugins = $this->getHandlers('filter');

      foreach ($customizable_filters as $customizable_filter) {
        /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
        $filter = $filter_plugins[$customizable_filter];
        $filter->buildExposedForm($form['override']['exposed_filters'], $subform_state);

        // Set the label and default values of the form element, based on the
        // block configuration.
        $exposed_info = $filter->exposedInfo();
        $form['override']['exposed_filters'][$exposed_info['value']]['#title'] = $exposed_info['label'];
        // The following is essentially using this patch:
        // https://www.drupal.org/project/views_block_placement_exposed_form_defaults/issues/3158789
        if ($form['override']['exposed_filters'][$exposed_info['value']]['#type'] == 'entity_autocomplete') {
          $form['override']['exposed_filters'][$exposed_info['value']]['#default_value'] = EntityAutocomplete::valueCallback(
            $form['override']['exposed_filters'][$exposed_info['value']],
            $exposed_filter_values[$exposed_info['value']],
            $form_state
          );
        }
        else {
          $form['override']['exposed_filters'][$exposed_info['value']]['#default_value'] = !empty($exposed_filter_values[$exposed_info['value']]) ? $exposed_filter_values[$exposed_info['value']] : [];
        }
      }
    }

    // Provide "Configure sorts" block settings form.
    if (!empty($allow_settings['sort_sorts'])) {
      $form['override']['sort'] = [
        '#type' => 'details',
        '#title' => $this->t('Sort options'),
        '#description' => $this->t('Choose the order of the available sorts by dragging the drag handle ([icon]) and moving it up or down. For each sort, select "Ascending" to display results from first to last (e.g. A-Z), or "Descending" to display results from last to first (e.g. Z-A).'),
      ];
      $options = [
        'ASC' => $this->t('Ascending'),
        'DESC' => $this->t('Descending'),
      ];

      $sorts = $this->getHandlers('sort');
      $header = [];
      $header['label'] = $this->t('Label');
      $header['order'] = $this->t('Order');
      $header['weight'] = $this->t('Weight');
      $form['override']['sort']['sort_list'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => [],
      ];

      $form['override']['sort']['sort_list']['#tabledrag'] = [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'sort-weight',
        ],
      ];
      $form['override']['sort']['sort_list']['#attributes'] = ['id' => 'order-sorts'];

      // Sort available sort plugins by their currently configured weight.
      $sorted_sorts = [];
      if (isset($block_configuration['sort'])) {

        foreach (array_keys($block_configuration['sort']) as $sort_name) {
          if (!empty($sorts[$sort_name])) {
            $sorted_sorts[$sort_name] = $sorts[$sort_name];
            unset($sorts[$sort_name]);
          }
        }
        if (!empty($sorts)) {
          foreach ($sorts as $sort_name => $sort_info) {
            $sorted_sorts[$sort_name] = $sort_info;
          }
        }
      }
      else {
        $sorted_sorts = $sorts;
      }

      foreach ($sorted_sorts as $sort_name => $plugin) {
        $sort_label = $plugin->adminLabel();
        if (!empty($plugin->options['label'])) {
          $sort_label .= ' (' . $plugin->options['label'] . ')';
        }
        $form['override']['sort']['sort_list'][$sort_name]['#attributes']['class'][] = 'draggable';

        $form['override']['sort']['sort_list'][$sort_name]['label'] = [
          '#markup' => $sort_label,
        ];

        $form['override']['sort']['sort_list'][$sort_name]['order'] = [
          '#type' => 'radios',
          '#options' => $options,
          '#default_value' => $plugin->options['order'],
        ];

        // Set default values for sorts for this block.
        if (!empty($block_configuration['sort'][$sort_name])) {
          $form['override']['sort']['sort_list'][$sort_name]['order']['#default_value'] = $block_configuration['sort'][$sort_name]['order'];
        }

        $form['override']['sort']['sort_list'][$sort_name]['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $sort_label]),
          '#title_display' => 'invisible',
          '#delta' => 50,
          '#default_value' => !empty($block_configuration['sort'][$sort_name]['weight']) ? $block_configuration['sort'][$sort_name]['weight'] : 0,
          '#attributes' => ['class' => ['sort-weight']],
        ];
      }
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
        $form['override']['hide_fields']['field_list'][$field_name]['hide'] = [
          '#type' => 'checkbox',
          '#default_value' => !empty($block_configuration['fields'][$field_name]['hide']) ? $block_configuration['fields'][$field_name]['hide'] : 0,
        ];
        $form['override']['hide_fields']['field_list'][$field_name]['label'] = [
          '#markup' => $field_label,
        ];
      }
    }

    // Provide "Pager offset" block settings form.
    if (!empty($allow_settings['offset'])) {
      $form['override']['pager_offset'] = [
        '#type' => 'number',
        '#title' => $this->t('Offset'),
        '#default_value' => isset($block_configuration['pager_offset']) ? $block_configuration['pager_offset'] : 0,
        '#description' => $this->t('For example, set this to 3 and the first 3 items will not be displayed.'),
      ];
    }

    // @todo Re-factor this based on a coherent set of paging choices.
    if (!empty($allow_settings['pager'])) {
      $form['override']['pager'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show pager'),
        '#default_value' => isset($block_configuration['pager_display']) ? $block_configuration['pager_display'] : FALSE,
      ];
    }

    // Display a "More" link.
    if (!empty($allow_settings['display_more_link'])) {

      $more_link_help_text = $this->getOption('more_link_help_text');
      if (empty($more_link_help_text)) {
        $more_link_help_text = $this->t('Start typing to see a list of results. Click to select.');
      }

      $form['override']['display_more_toggle'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display More link'),
        '#description' => $more_link_help_text,
      ];

      // @todo Figure out how to make the style of this field
      //   look like other LinkIt fields.
      $form['override']['display_more_path'] = [
        '#type' => 'linkit',
        '#title' => $this->t('Path'),
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
      // @todo Add more link help text from view block settings.
    }

    $form['override']['#weight'] = 5;

    return $form;
  }

  public function blockValidate(ViewsBlock $block, array $form, FormStateInterface $form_state) {
    parent::blockValidate($block, $form, $form_state); // TODO: Change the autogenerated stub
    // @todo Add link validation for display 'more' link.
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit(ViewsBlock $block, $form, FormStateInterface $form_state) {
    // Alter the headline field settings for configuration.
    foreach ($form_state->getValues()['headline']['container'] as $name => $value) {
      $this->configuration[$name] = $value;
    }

    // Set default value for items_per_page if left blank.
    if (empty($form_state->getValue(['override', 'items_per_page']))) {
      $form_state->setValue(['override', 'items_per_page'], 'none');
    }

    parent::blockSubmit($block, $form, $form_state);
    $allow_settings = array_filter($this->getOption('allow'));

    // Save "Pager type" settings to block configuration.
    $configuration['pager'] = 'some';
    if ($pager = $form_state->getValue(['override', 'pager'])) {
      $configuration['pager'] = 'full';
    }
    $block->setConfigurationValue('pager', $configuration['pager']);

    // Save "Pager offset" settings to block configuration.
    if (!empty($allow_settings['offset'])) {
      $block->setConfigurationValue('pager_offset', $form_state->getValue([
        'override',
        'pager_offset',
      ]));
    }

    if (!empty($allow_settings['display_more_link'])) {
      // @todo Save "Display more path" setting to block configuration.
    }

    // Provide "Configure sorts" block settings form.
    if (!empty($allow_settings['sort_sorts'])) {
      // @todo Process configure sorts.
      if ($sorts = array_filter($form_state->getValue([
        'override',
        'sorts',
        'sort_list',
      ]))) {
        $block->setConfigurationValue('sorts', $sorts);
      }
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

    // Change pager style settings based on block configuration.
    if (!empty($config['pager'])) {
      $pager = $this->view->display_handler->getOption('pager');
      $pager['type'] = $config['pager'];
      $this->view->display_handler->setOption('pager', $pager);
    }

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

    // Change sorts based on block configuration.
    if (!empty($allow_settings['sort_sorts'])) {
      $sorts = $this->view->getHandlers('sort', $display_id);
      // Remove existing sorts from the view.
      foreach ($sorts as $sort_name => $sort) {
        $this->view->removeHandler($display_id, 'sort', $sort_name);
      }
      if (!empty($config['sort'])) {
        uasort($config['sort'], '\Drupal\layout_builder_custom\Plugin\Display\Block::sortByWeight');
        foreach ($config['sort'] as $sort_name => $sort) {
          if (!empty($config['sort'][$sort_name])) {
            $sort['order'] = $config['sort'][$sort_name];
            // Re-add sorts in the order that was selected for the block.
            $this->view->setHandler($display_id, 'sort', $sort_name, $sort);
          }
        }
      }
    }
    // Set view filter based on "Filter" setting.
    $exposed_filter_values = !empty($config['exposed_filter_values']) ? $config['exposed_filter_values'] : [];
    $this->view->setExposedInput($exposed_filter_values);
    $this->view->exposed_data = $exposed_filter_values;

    // @todo Figure out how to display a more link based on "Display more path" setting.
    if (!empty($allow_settings['display_more_link'])) {
      $this->view->element['more_link'] = [
        '#type' => 'link',
        '#title' => 'View more ',
        // @todo Replace with actual more link.
        '#url' => Url::fromUri('https://uiowa.edu'),
        '#attributes' => [
          'class' => ['bttn', 'bttn--primary', 'bttn--caps'],
        ],
      ];
    }
    // @todo Add condition to only show this if it was set.

  }

//  public function buildRenderable(array $args = [], $cache = TRUE) {
//    return parent::buildRenderable($args, $cache); // TODO: Change the autogenerated stub
//  }

  /**
   * {@inheritdoc}
   */
  public function usesExposed() {
    // We don't want this to be available on this type of block.
    return FALSE;
  }

  /**
   * Sort array by weight.
   *
   * @param int $a
   *   The field a.
   * @param int $b
   *   The field b.
   *
   * @return int
   *   Return the more weight
   */
  public static function sortByWeight($a, $b) {
    $a_weight = isset($a['weight']) ? $a['weight'] : 0;
    $b_weight = isset($b['weight']) ? $b['weight'] : 0;
    if ($a_weight == $b_weight) {
      return 0;
    }
    return ($a_weight < $b_weight) ? -1 : 1;
  }

}
