<?php

namespace Drupal\layout_builder_custom\Plugin\Display;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\Url;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\uiowa_core\HeadlineHelper;
use Drupal\views\Plugin\Block\ViewsBlock;
use Drupal\views\Plugin\views\display\Block as CoreBlock;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Provides a List Block display plugin override.
 *
 * Adapted from Drupal\ctools_views\Plugin\Display\Block and
 * https://www.drupal.org/project/views_block_placement_exposed_form_defaults.
 */
class ListBlock extends CoreBlock {

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);
    $filtered_allow = array_filter($this->getOption('allow'));
    $filter_options = [
      // We are just changing the label here to be consistent current use.
      'items_per_page' => $this->t('Items to display'),
      'pager' => $this->t('Show pager'),
      'offset' => $this->t('Offset'),
      'hide_fields' => $this->t('Hide fields'),
      'configure_sorts' => $this->t('Adjust the order of sorts'),
      'use_more' => $this->t('Display more link'),
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
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    if ($form_state->get('section') !== 'allow') {
      return;
    }

    // Making the label more user-friendly.
    $form['allow']['#options']['items_per_page'] = $this->t('Items to display');
    $form['allow']['#options']['offset'] = $this->t('Pager offset');
    $form['allow']['#options']['pager'] = $this->t('Show pager');
    $form['allow']['#options']['hide_fields'] = $this->t('Hide fields');
    $form['allow']['#options']['configure_sorts'] = $this->t('Configure sorts');
    $form['allow']['#options']['use_more'] = $this->t('Display more link');

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
            "input[name='allow[use_more]']" => [
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

    $allow_settings = array_filter($this->getOption('allow'));
    $block_configuration = $block->getConfiguration();

    // @todo Possibly wire this up to the views title?
    $form['headline'] = HeadlineHelper::getElement([
      'headline' => $block_configuration['headline']['headline'] ?? NULL,
      'hide_headline' => $block_configuration['headline']['hide_headline'] ?? 0,
      'heading_size' => $block_configuration['headline']['heading_size'] ?? 'h2',
      'headline_style' => $block_configuration['headline']['headline_style'] ?? 'default',
      'child_heading_size' => $block_configuration['headline']['child_heading_size'] ?? 'h3',
    ]);
    $form['headline']['#weight'] = 1;

    // Modify "Items per page" block settings form.
    if (!empty($allow_settings['items_per_page'])) {
      $form['override']['items_per_page']['#type'] = 'number';
      $form['override']['items_per_page']['#min'] = 0;
      $form['override']['items_per_page']['#title'] = $this->t('Items to display');
      $form['override']['items_per_page']['#description'] = $this->t('Select the number of entries to display');
      unset($form['override']['items_per_page']['#options']);
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

    // Provide "Show pager" block setting.
    if (!empty($allow_settings['pager'])) {
      $form['override']['pager'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show pager'),
        '#default_value' => ($block_configuration['pager'] == 'full'),
      ];
    }

    // Display exposed filters to allow them to be set for the block.
    $customizable_filters = $this->getOption('filter_in_block');
    if (!empty($customizable_filters)) {
      $form['override']['exposed_filters'] = [
        '#type' => 'details',
        '#title' => $this->t('Exposed filters'),
        '#description' => $this->t('Set default filters.'),
        '#tree' => TRUE,
      ];

      // Provide "Exposed filters" block settings form.
      $exposed_filter_values = !empty($block_configuration['exposed_filter_values']) ? $block_configuration['exposed_filter_values'] : [];

      $subform_state = SubformState::createForSubform($form['override']['exposed_filters'], $form, $form_state);
      $subform_state->set('exposed', TRUE);

      $filter_plugins = $this->getHandlers('filter');

      foreach ($customizable_filters as $filter_id) {
        /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
        $filter = $filter_plugins[$filter_id];

        $filter_id = $this->getFilterCustomId($filter);

        // Set the label and default values of the form element, based on the
        // block configuration.
        $exposed_info = $filter->exposedInfo();

        // Add checkboxes to allow exposed filter to be shown
        // to the end user.
        $filter_id_expose = $filter_id . '_expose';
        $form['override']['exposed_filters'][$filter_id_expose] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Expose @filter_label filter to site visitors.', [
            '@filter_label' => $exposed_info['label'],
          ]),
          '#default_value' => isset($block_configuration['expose_form'][$filter_id_expose]) ? $block_configuration['expose_form'][$filter_id_expose] : 0,
        ];

        $filter->buildExposedForm($form['override']['exposed_filters'], $subform_state);

        // The following is essentially using this patch:
        // https://www.drupal.org/project/views_block_placement_exposed_form_defaults/issues/3158789
        if ($form['override']['exposed_filters'][$filter_id]['#type'] == 'entity_autocomplete') {
          $form['override']['exposed_filters'][$filter_id]['#default_value'] = EntityAutocomplete::valueCallback(
            $form['override']['exposed_filters'][$filter_id],
            $exposed_filter_values[$filter_id],
            $form_state
          );
        }
        else {
          $form['override']['exposed_filters'][$filter_id]['#default_value'] = !empty($exposed_filter_values[$filter_id]) ? $exposed_filter_values[$filter_id] : [];
        }

        // If the filter has an exposed operator, it will render in a wrapper.
        if ($filter->options['expose']['use_operator']) {
          $wrapper_id = $filter_id . '_wrapper';
          foreach (Element::children($form['override']['exposed_filters'][$wrapper_id]) as $filter_name) {
            // Add states to disable a filter if it is exposed to visitors.
            $form['override']['exposed_filters'][$wrapper_id][$filter_name]['#states'] = [
              'disabled' => [
                [
                  "input[name='settings[override][exposed_filters][" . $filter_id_expose . "]']" => [
                    'checked' => TRUE,
                  ],
                ],
              ],
            ];
          }
        }
        else {
          $form['override']['exposed_filters'][$filter_id]['#title'] = $exposed_info['label'];
          // Add states to disable a filter if it is exposed to visitors.
          $form['override']['exposed_filters'][$filter_id]['#states'] = [
            'disabled' => [
              [
                "input[name='settings[override][exposed_filters][" . $filter_id_expose . "]']" => [
                  'checked' => TRUE,
                ],
              ],
            ],
          ];
        }
      }
    }

    // Provide "Configure sorts" block settings form.
    if (!empty($allow_settings['configure_sorts'])) {
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
      $header = [
        'label' => $this->t('Label'),
        'order' => $this->t('Order'),
        'weight' => $this->t('Weight'),
      ];
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

        uasort($block_configuration['sort'], '\Drupal\layout_builder_custom\Plugin\Display\Block::sortByWeight');

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

    // Provides settings related to displaying a "More" link.
    if (!empty($allow_settings['use_more'])) {

      $more_link_help_text = $this->getOption('more_link_help_text');
      if (empty($more_link_help_text)) {
        $more_link_help_text = $this->t('Start typing to see a list of results. Click to select.');
      }

      $form['override']['use_more'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display More link'),
        '#description' => $more_link_help_text,
        '#default_value' => !empty($block_configuration['use_more']),
      ];

      $form['override']['use_more_link_url'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Path'),
        '#description' => $this
          ->t('Start typing the title of a piece of content to select it. You can also enter an internal path such as %add-node or an external URL such as %url. Enter %front to link to the front page.', [
            '%front' => '<front>',
            '%add-node' => '/node/add',
            '%url' => 'http://example.com',
          ]),
        '#default_value' => isset($block_configuration['use_more_link_url']) ? static::getUriAsDisplayableString($block_configuration['use_more_link_url']) : NULL,
        '#element_validate' => [
          [
            LinkWidget::class,
            'validateUriElement',
          ],
        ],
        // @todo The user should be able to select an entity type. Will be fixed
        //   in https://www.drupal.org/node/2423093.
        '#target_type' => 'node',
        // Disable autocompletion when the first character is '/', '#' or '?'.
        '#attributes' => [
          'data-autocomplete-first-character-blacklist' => '/#?',
        ],
        '#process_default_value' => FALSE,
        '#states' => [
          'visible' => [
            [
              "input[name='settings[override][use_more]']" => [
                'checked' => TRUE,
              ],
            ],
          ],
        ],
      ];
    }

    // Set overrides to show up in the middle of the form.
    $form['override']['#weight'] = 5;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit(ViewsBlock $block, $form, FormStateInterface $form_state) {

    // Set default value for items_per_page if left blank.
    if (empty($form_state->getValue(['override', 'items_per_page']))) {
      $form_state->setValue(['override', 'items_per_page'], 'none');
    }

    parent::blockSubmit($block, $form, $form_state);
    $allow_settings = array_filter($this->getOption('allow'));

    // Alter the headline field settings for configuration.
    $block->setConfigurationValue('headline', $form_state->getValue([
      'headline',
      'container',
    ]));

    // Save "Pager type" settings to block configuration.
    $pager = 'some';
    if ($form_state->getValue(['override', 'pager'])) {
      $pager = 'full';
    }
    $block->setConfigurationValue('pager', $pager);

    // Save "Pager offset" settings to block configuration.
    if (!empty($allow_settings['offset'])) {
      $block->setConfigurationValue('pager_offset', $form_state->getValue([
        'override',
        'pager_offset',
      ]));
    }

    if (!empty($allow_settings['use_more'])) {

      $block->setConfigurationValue('use_more', $form_state->getValue([
        'override',
        'use_more',
      ]));

      // Save display more link setting.
      $block->setConfigurationValue('use_more_link_url', $form_state->getValue([
        'override',
        'use_more_link_url',
      ]));
    }

    // Check if we are exposing any filters to the site visitor.
    $exposed_inputs = $form_state->getValue([
      'override',
      'exposed_filters',
    ]);
    $expose_form = [];
    $exposed_filter_values = [];
    foreach ($exposed_inputs as $input_name => $input_value) {
      // Check if it's an "expose form" checkbox,
      // and add its value to our expose form array.
      if (str_ends_with($input_name, '_expose')) {
        $expose_form[$input_name] = ($input_value);
      }
      else {
        // If it's not an "expose form" field,
        // then we need to fetch the field's value for later use.
        $exposed_filter_values[$input_name] = $input_value;
      }
    }
    // Save "Filter in block" settings to block configuration.
    $block->setConfigurationValue('exposed_filter_values', $exposed_filter_values);
    $block->setConfigurationValue('expose_form', $expose_form);

    // Save "Configure sorts" setting.
    if (!empty($allow_settings['configure_sorts'])) {
      if ($sorts = array_filter($form_state->getValue([
        'override',
        'sort',
        'sort_list',
      ]))) {
        $block->setConfigurationValue('sort', $sorts);
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

    if ($form_state instanceof SubformStateInterface) {
      $styles = $this->getLayoutBuilderStyles($form, $form_state->getCompleteFormState());
    }
    else {
      $styles = $this->getLayoutBuilderStyles($form, $form_state);
    }

    $block->setConfigurationValue('layout_builder_styles', $styles);
  }

  /**
   * {@inheritdoc}
   */
  public function preBlockBuild(ViewsBlock $block) {
    parent::preBlockBuild($block);
    $this->setOption('exposed_block', FALSE);

    $allow_settings = array_filter($this->getOption('allow'));
    $config = $block->getConfiguration();
    [, $display_id] = explode('-', $block->getDerivativeId(), 2);

    if (!empty($config['layout_builder_styles'])) {
      $this->view->display_handler->setOption('row_styles', $config['layout_builder_styles']);
    }

    // Attach the headline, if configured.
    if (!empty($config['headline'])) {
      $headline = $config['headline'];
      $this->view->element['headline'] = [
        '#theme' => 'uiowa_core_headline',
        '#headline' => $headline['headline'],
        '#hide_headline' => $headline['hide_headline'],
        '#heading_size' => $headline['heading_size'],
        '#headline_style' => $headline['headline_style'],
      ];
      if (empty($headline['headline'])) {
        $child_heading_size = $headline['child_heading_size'];
      }
      else {
        $child_heading_size = HeadlineHelper::getHeadingSizeUp($headline['heading_size']);
      }

      $this->view->display_handler->setOption('heading_size', $child_heading_size);
    }

    // Change pager offset settings based on block configuration.
    if (!empty($allow_settings['offset'])) {
      $this->view->setOffset($config['pager_offset']);
    }

    // Change pager style settings based on block configuration.
    if (!empty($config['pager'])) {
      $pager = $this->view->display_handler->getOption('pager');
      $pager['type'] = $config['pager'];
      $pager['options']['expose']['items_per_page'] = FALSE;
      $pager['options']['expose']['offset'] = FALSE;
      $this->view->display_handler->setOption('pager', $pager);
    }

    // Change fields output based on block configuration.
    if ($this->view->getStyle()->usesFields() &&
      !empty($allow_settings['hide_fields']) &&
      !empty($config['fields'])) {
      $fields = $this->view->getHandlers('field');
      foreach (array_keys($fields) as $field_name) {
        // Remove each field in sequence and re-add them if not hidden.
        $this->view->removeHandler($display_id, 'field', $field_name);
        if (empty($config['fields'][$field_name]['hide'])) {
          $this->view->addHandler($display_id, 'field', $fields[$field_name]['table'], $fields[$field_name]['field'], $fields[$field_name], $field_name);
        }
      }
    }

    // Check if we need to expose form filter to site visitors.
    if (isset($config['expose_form']) &&
      is_array($config['expose_form']) &&
      in_array(TRUE, $config['expose_form'])) {
      $this->view->display_handler->setOption('expose_form', TRUE);
      // Run through our exposed filters and turn their handlers on or off.
      foreach ($config['expose_form'] as $key => $value) {
        // Remove the '_expose' suffix, and if necessary
        // manually adjust for various naming inconsistencies.
        $key = basename($key, '_expose');
        switch ($key) {

          case 'research':
            $key = 'field_person_research_areas_target_id';
            // Adjust to a single-select field.
            $this->view->setHandlerOption($display_id, 'filter', $key, 'type', 'select');
            $expose = $this->view->getHandler($display_id, 'filter', $key)['expose'];
            $expose['multiple'] = FALSE;
            $this->view->setHandlerOption($display_id, 'filter', $key, 'expose', $expose);
            break;

          case 'tag':
            $key = 'field_tags_target_id';
            // Adjust to a single-select field.
            $this->view->setHandlerOption($display_id, 'filter', $key, 'type', 'select');
            $expose = $this->view->getHandler($display_id, 'filter', $key)['expose'];
            $expose['multiple'] = FALSE;
            $this->view->setHandlerOption($display_id, 'filter', $key, 'expose', $expose);
            break;

          case 'type':
            $key = 'field_person_types_target_id';
            break;

          case 'type_status':
            $key = 'field_person_type_status_value';
            break;
        }

        $this->view->setHandlerOption($display_id, 'filter', $key, 'exposed', $value);
      }
    }
    // Set to false in case it was previously exposed but no longer.
    else {
      $this->view->display_handler->setOption('expose_form', FALSE);
    }

    // Change sorts based on block configuration.
    if (!empty($allow_settings['configure_sorts'])) {
      $sorts = $this->view->getHandlers('sort', $display_id);
      // Remove existing sorts from the view.
      foreach ($sorts as $sort_name => $sort) {
        $this->view->removeHandler($display_id, 'sort', $sort_name);
      }
      if (!empty($config['sort'])) {
        uasort($config['sort'], '\Drupal\layout_builder_custom\Plugin\Display\ListBlock::sortByWeight');
        foreach ($config['sort'] as $sort_name => $sort) {
          if (!empty($config['sort'][$sort_name]) && !empty($sorts[$sort_name])) {
            $sort = $sorts[$sort_name];
            $sort['order'] = $config['sort'][$sort_name]['order'];
            // Re-add sorts in the order that was selected for the block.
            $this->view->setHandler($display_id, 'sort', $sort_name, $sort);
          }
        }
      }
    }

    // We need to get both the values from the exposed filter form
    // as well as any pre-set filter values from the block form.
    $inputs = $this->view->getExposedInput();
    $exposed_filter_values = !empty($config['exposed_filter_values']) ? $config['exposed_filter_values'] : [];
    // Inputs are the second arg here, so if we have an exposed input,
    // it will replace any value from the config array.
    $exposed_filter_values = array_merge($exposed_filter_values, $inputs);
    $this->view->setExposedInput($exposed_filter_values);

    if (!empty($allow_settings['use_more'])) {
      if (isset($config['use_more']) && $config['use_more']) {
        $this->view->display_handler->setOption('use_more', TRUE);
        $this->view->display_handler->setOption('use_more_always', TRUE);
        $this->view->display_handler->setOption('link_display', 'custom_url');
        if (!empty($config['use_more_link_url'])) {
          $this->view->display_handler->setOption('link_url', Url::fromUri($config['use_more_link_url'])->toString());
        }
      }
      else {
        // Don't display the more link.
        $this->view->display_handler->setOption('use_more', FALSE);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function usesExposed(): bool {
    $filters = $this->getHandlers('filter');
    foreach ($filters as $filter) {
      if ($filter->isExposed() && !empty($filter->exposedInfo())) {
        return TRUE;
      }
    }
    // Hotfix shim to keep these pagers working for now.
    // @todo Remove this exception when these view displays are removed.
    $display = $this->view->getDisplay();
    $exceptions = [
      'block_people_slf',
      'block_people_sfl',
      'block_articles',
    ];
    if (in_array($display->display['id'], $exceptions)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function displaysExposed(): bool {
    $display = $this->view->getDisplay();
    // If we need to expose filters, return true
    // and we're done.
    if ($display->getOption('expose_form')) {
      return TRUE;
    }
    // Hotfix shim to not display exposed blocks, necessary because of the hotfix above.
    // @todo Remove this exception when these view displays are removed.
    $exceptions = [
      'block_people_slf',
      'block_people_sfl',
      'block_articles',
    ];
    if (in_array($display->display['id'], $exceptions)) {
      return FALSE;
    }
    // If we are not utilizing the filter in block option,
    // then use the default behavior. Otherwise, do not display
    // exposed filters.
    if (empty($this->options['filter_in_block'])) {
      return parent::displaysExposed();
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Exposed widgets typically only work with ajax in Drupal core, however
   * #2605218 totally breaks the rest of the functionality in this display and
   * in Core's Block display as well, so we allow non-ajax block views to use
   * exposed filters and manually set the #action to the current request uri.
   */
  public function elementPreRender(array $element): array {
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $element['#view'];
    if (!empty($view->exposed_widgets['#action']) && !$view->ajaxEnabled()) {
      $uri = \Drupal::request()->getRequestUri();
      $view->exposed_widgets['#action'] = $uri;
    }
    return parent::elementPreRender($element);
  }

  /**
   * Get Layout Builder Styles from the form state.
   *
   * @see _layout_builder_styles_prepare_styles_for_saving()
   *
   * @return array
   *   Returns layout builder styles for this block form.
   */
  protected function getLayoutBuilderStyles(array $form, FormStateInterface $form_state): array {
    $styles = [];
    foreach ($form as $id => $el) {
      if (strpos($id, 'layout_builder_style_') === 0) {
        $value = $form_state->getValue($id);
        if ($value) {
          if (is_array($value)) {
            $styles += $value;
          }
          else {
            $styles[] = $value;
          }
        }
      }
    }
    return $styles;
  }

  /**
   * Get custom ID for a filter.
   */
  protected function getFilterCustomId(FilterPluginBase $filter) {
    // If an identifier is set for the filter, use that as the $filter_id.
    if (!empty($filter->options['expose']['identifier'])) {
      return $filter->options['expose']['identifier'];
    }

    return $filter->options['id'];
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
  public static function sortByWeight($a, $b): int {
    $a_weight = isset($a['weight']) ? $a['weight'] : 0;
    $b_weight = isset($b['weight']) ? $b['weight'] : 0;
    if ($a_weight == $b_weight) {
      return 0;
    }
    return ($a_weight < $b_weight) ? -1 : 1;
  }

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * This method is copied from
   * Drupal\link\Plugin\Field\FieldWidget\LinkWidget::getUriAsDisplayableString()
   * since I can't figure out another way to use a protected
   * method from that class.
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *   The displayable string.
   *
   * @see Drupal\link\Plugin\Field\FieldWidget\LinkWidget::getUriAsDisplayableString()
   */
  protected static function getUriAsDisplayableString($uri): string {
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
      // Show the 'entity:' URI as the entity autocomplete would.
      // @todo Support entity types other than 'node'. Will be fixed in
      //   https://www.drupal.org/node/2423093.
      if ($entity_type == 'node' && $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    elseif ($scheme === 'route') {
      $displayable_string = ltrim($displayable_string, 'route:');
    }

    return $displayable_string;
  }

}
