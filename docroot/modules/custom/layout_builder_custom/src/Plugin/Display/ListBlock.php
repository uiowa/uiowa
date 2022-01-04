<?php

namespace Drupal\layout_builder_custom\Plugin\Display;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\Url;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\uiowa_core\HeadlineHelper;
use Drupal\views\Plugin\Block\ViewsBlock;
use Drupal\ctools_views\Plugin\Display\Block as BlockDisplay;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Provides a List Block display plugin override.
 *
 * Adapted from Drupal\ctools_views\Plugin\Display\Block and
 * https://www.drupal.org/project/views_block_placement_exposed_form_defaults.
 */
class ListBlock extends BlockDisplay {

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);
    $filtered_allow = array_filter($this->getOption('allow'));
    $filter_options = [
      // We have to add the original ones, otherwise we have no
      // way to display them.
      'items_per_page' => $this->t('Items per page'),
      'offset' => $this->t('Pager offset'),
      'pager' => $this->t('Pager type'),
      'hide_fields' => $this->t('Hide fields'),
      'sort_fields' => $this->t('Reorder fields'),
      'disable_filters' => $this->t('Disable filters'),
      'configure_sorts' => $this->t('Configure sorts'),
      'configure_filters' => $this->t('Customize filters in block'),
      // Add use_more option summary.
      'use_more' => $this->t('Display more link'),
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
    if ($form_state->get('section') !== 'allow') {
      return;
    }

    // Add use_more option to allow displaying a link.
    $form['allow']['#options']['use_more'] = $this->t('Display more link');

    // @todo Is this section still necessary?
    $defaults = [];
    if (!empty($form['allow']['#default_value'])) {
      $defaults = array_filter($form['allow']['#default_value']);
    }

    $form['allow']['#default_value'] = $defaults;

    // Add restrict_fields option to prevent editors from toggling certain fields.
    $field_keys = array_keys($this->view->getDisplay()->getOption('fields'));
    $fields = array_combine($field_keys, $field_keys);
    $restrict_fields = $this->getOption('restrict_fields');
    $form['restrict_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Restrict fields'),
      '#description' => $this->t('Prevent editor from show/hide fields.'),
      '#options' => $fields,
      '#default_value' => $restrict_fields ?: '',
    ];

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
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    if ($form_state->get('section') === 'allow') {
      $this->setOption('more_link_help_text', $form_state->getValue('more_link_help_text'));
      $this->setOption('restrict_fields', $form_state->getValue('restrict_fields'));
    }
  }

  /**
   * Get custom ID for a filter.
   *
   * @todo Is this still needed?
   */
  protected function getFilterCustomId(FilterPluginBase $filter) {
    // If an identifier is set for the filter, use that as the $filter_id.
    if (!empty($filter->options['expose']['identifier'])) {
      return $filter->options['expose']['identifier'];
    }

    return $filter->options['id'];
  }

  /**
   * Get a map of exposed filters keyed by custom ID.
   *
   * @todo Is this still needed?
   */
  protected function getFilterCustomIdMap() {
    foreach ($this->getHandlers('filter') as $filter_name => $filter_plugin) {
      $filter_options[$this->getFilterCustomId($filter_plugin)] = $filter_name;
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

    // Hide headline child form elements for table displays.
    $has_children = !($this->view->getStyle()->getPluginId() == 'table');

    // @todo Possibly wire this up to the views title?
    // @todo Move this to a form override.
    $form['headline'] = HeadlineHelper::getElement([
      'headline' => $block_configuration['headline']['headline'] ?? NULL,
      'hide_headline' => $block_configuration['headline']['hide_headline'] ?? 0,
      'heading_size' => $block_configuration['headline']['heading_size'] ?? 'h2',
      'headline_style' => $block_configuration['headline']['headline_style'] ?? 'default',
      'child_heading_size' => $block_configuration['headline']['child_heading_size'] ?? 'h3',
    ], $has_children);
    $form['headline']['#weight'] = 1;

    // Modify "Items per page" block settings form.
    if (!empty($allow_settings['items_per_page'])) {
      // @todo Remove once exposed filters patch is added.
      // Seems to break at high numbers :grimmacing: ..
      $form['override']['items_per_page']['#min'] = 1;
      $form['override']['items_per_page']['#max'] = 50;
      $form['override']['items_per_page']['#title'] = $this->t('Items to display');
      $form['override']['items_per_page']['#description'] = $this->t('Select the number of entries to display. Minimum of 1 and maximum of 50. Show pager to display more than 50.');
    }

    // Provide "Pager offset" block settings form.
    if (!empty($allow_settings['offset'])) {
      $form['override']['pager_offset']['#title'] = $this->t('Offset');
    }

    // Provide "Show pager" block setting.
    if (!empty($allow_settings['pager'])) {
      $form['override']['pager'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show pager'),
        '#default_value' => ($block_configuration['pager'] == 'full'),
      ];
    }

    // Place "Hide fields" block settings inside a details element.
    if (!empty($allow_settings['hide_fields'])) {
      $form['override']['hide_fields'] = [
        '#type' => 'details',
        '#title' => $this->t('Hide fields'),
        '#description' => $this->t('Choose to hide some of the fields.'),
      ];
      $form['override']['hide_fields']['order_fields'] = $form['override']['order_fields'];
      $form['override']['order_fields']['#access'] = FALSE;

      // Remove restricted fields from the hide options.
      $restrict_fields = $this->getOption('restrict_fields');
      if (!empty($restrict_fields)) {
        $fields_to_remove = [];
        foreach ($restrict_fields as $field) {
          if ($field !== 0) {
            $fields_to_remove[$field] = $field;
          }
        }
        $form["override"]["hide_fields"]["order_fields"] = array_diff_key($form["override"]["hide_fields"]["order_fields"], $fields_to_remove);
      }
    }

    // Alter "Configure filters" to add checkboxes to allow exposing.
    if (!empty($allow_settings['configure_filters'])) {
      // Loop through the existing exposed filters.
      foreach (Element::children($form['exposed']) as $id) {
        $form['exposed'][$id]['expose_' . $id] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Expose filter to site visitors.'),
          '#default_value' => $block_configuration['exposed'][$id]['expose_' . $id] ?: 0,
          '#weight' => -1,
        ];

        foreach (Element::children($form['exposed'][$id]) as $child_id) {
          if (str_starts_with($child_id, 'expose_')) {
            continue;
          }
          $form['exposed'][$id][$child_id]['#states'] = [
            'disabled' => [
              [
                "input[name='settings[exposed][" . $id . "][expose_" . $id . "]']" => [
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
      // We are providing a different UI for managing sorts that allows setting
      // the order. This overrides the implementation we are extending.

      $sorts = $this->getHandlers('sort');
      // Sort available sort plugins by their currently configured weight.
      $sorted_sorts = [];
      if (isset($block_configuration['sort'])) {

        uasort($block_configuration['sort'], '\Drupal\ctools_views\Plugin\Display\Block::sortFieldsByWeight');

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

      if (count($sorted_sorts) > 1) {
        $description = $this->t('Choose the order of the available sorts by dragging the drag handle ([icon]) and moving it up or down. For each sort, select "Ascending" to display results from first to last (e.g. A-Z), or "Descending" to display results from last to first (e.g. Z-A).');
      }
      else {
        $description = $this->t('For each sort, select "Ascending" to display results from first to last (e.g. A-Z), or "Descending" to display results from last to first (e.g. Z-A).');
      }

      $form['override']['sort'] = [
        '#type' => 'details',
        '#title' => $this->t('Sort options'),
        '#description' => $description,
      ];
      $options = [
        'ASC' => $this->t('Ascending'),
        'DESC' => $this->t('Descending'),
      ];

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
      $form['override']['sort']['sort_list']['#attributes'] = ['id' => 'order-sorts'];

      if (count($sorted_sorts) > 1) {
        $form['override']['sort']['sort_list']['#tabledrag'] = [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'sort-weight',
          ],
        ];
      }

      foreach ($sorted_sorts as $sort_name => $plugin) {
        $sort_label = $plugin->adminLabel();
        if (!empty($plugin->options['label'])) {
          $sort_label .= ' (' . $plugin->options['label'] . ')';
        }
        // Display drag handle if there is more than 1.
        if (count($sorted_sorts) > 1) {
          $form['override']['sort']['sort_list'][$sort_name]['#attributes']['class'][] = 'draggable';
        }

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

    // Provide "Configure filters" form elements.
    if (!empty($allow_settings['configure_filters'])) {
      // Loop through the existing exposed filters.
      foreach (Element::children($form['exposed']) as $id) {
        $form['exposed'][$id]['expose_' . $id] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Expose filter to site visitors.'),
          '#default_value' => $block_configuration['exposed'][$id]['expose_' . $id] ?: 0,
          '#weight' => -1,
        ];

        foreach (Element::children($form['exposed'][$id]) as $child_id) {
          if (str_starts_with($child_id, 'expose_')) {
            continue;
          }
          $form['exposed'][$id][$child_id]['#states'] = [
            'disabled' => [
              [
                "input[name='settings[exposed][" . $id . "][expose_" . $id . "]']" => [
                  'checked' => TRUE,
                ],
              ],
            ],
          ];
        }
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

      $form['override']['use_more_text'] = [
        '#type' => 'textfield',
        '#title' => 'Custom text',
        '#default_value' => isset($block_configuration['use_more_text']) ? $block_configuration['use_more_text'] : '',
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
  public function blockValidate(ViewsBlock $block, array $form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit(ViewsBlock $block, $form, FormStateInterface $form_state) {
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

      // Save display more link text.
      $block->setConfigurationValue('use_more_text', $form_state->getValue([
        'override',
        'use_more_text',
      ]));
    }

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
        'order_fields',
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
    $people_types_filter = $this->view->getDisplay()->getHandler('filter', 'field_person_types_target_id');
    $people_types_filter->value = ['All'];
    $this->view->setHandler($display_id, 'filter', 'field_person_types_target_id', $people_types_filter);

    if (!empty($config['layout_builder_styles'])) {
      $this->view->display_handler->setOption('row_styles', $config['layout_builder_styles']);
    }

    // Attach the headline, if configured.
    if (!empty($config['headline'])) {
      $headline = $config['headline'];
      if (!empty($headline['headline'])) {
        $this->view->element['headline'] = [
          '#theme' => 'uiowa_core_headline',
          '#headline' => $headline['headline'],
          '#hide_headline' => $headline['hide_headline'],
          '#heading_size' => $headline['heading_size'],
          '#headline_style' => $headline['headline_style'],
        ];
      }
      if (empty($headline['headline'])) {
        $child_heading_size = $headline['child_heading_size'] ?? 'h3';
      }
      else {
        $child_heading_size = HeadlineHelper::getHeadingSizeUp($headline['heading_size']);
      }

      $this->view->display_handler->setOption('heading_size', $child_heading_size);
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

    // Change sorts based on block configuration.
    if (!empty($allow_settings['configure_sorts'])) {
      $sorts = $this->view->getHandlers('sort', $display_id);
      // Remove existing sorts from the view.
      foreach ($sorts as $sort_name => $sort) {
        $this->view->removeHandler($display_id, 'sort', $sort_name);
      }
      if (!empty($config['sort'])) {
        uasort($config['sort'], '\Drupal\ctools_views\Plugin\Display\Block::sortFieldsByWeight');
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

    // Override some filter settings.
    if (!empty($allow_settings['configure_filters'])) {
      // Loop over the exposed filter settings in the block configuration.
      foreach ($config['exposed'] as $key => $value) {
        // Load the handler related to the exposed filter.
        [$handler_type, $handler_name] = explode('-', $key, 2);
        /** @var \Drupal\views\Plugin\views\HandlerBase $handler */
        $handler = $this->view->getDisplay()->getHandler($handler_type, $handler_name);

        // Set exposed filter input directly where they were entered in the
        // block configuration. Otherwise only set them if they haven't been set
        // already.
        if ($handler) {
          if (isset($config['exposed'][$key]['expose_' . $key]) && $config['exposed'][$key]['expose_' . $key]) {
            unset($handler->options['value_from_block_configuration']);
          }
          else {
            $handler->options['exposed'] = FALSE;
          }
        }
      }
    }

    if (!empty($allow_settings['use_more'])) {
      if (isset($config['use_more']) && $config['use_more']) {
        $this->view->display_handler->setOption('use_more', TRUE);
        $this->view->display_handler->setOption('use_more_always', TRUE);
        $this->view->display_handler->setOption('link_display', 'custom_url');
        if (!empty($config['use_more_link_url'])) {
          $this->view->display_handler->setOption('link_url', Url::fromUri($config['use_more_link_url'])->toString());
        }
        if (!empty($config['use_more_text'])) {
          $this->view->display_handler->setOption('use_more_text', $config['use_more_text']);
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
   *
   * @todo Is this still needed?
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
   *
   * Is this still needed?
   */
  public function displaysExposed(): bool {
    // Hotfix shim to not display exposed blocks, necessary because of
    // the hotfix above.
    // @todo Remove this exception when these view displays are removed.
    $display = $this->view->getDisplay();
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
    if (empty($this->options['allow']['configure_filters'])) {
      return parent::displaysExposed();
    }
    return FALSE;
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
