<?php

/**
 * @file
 * Profile code.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\InlineBlockUsage;

/**
 * Implements hook_preprocess_HOOK().
 */
function sitenow_preprocess_html(&$variables) {
  $version = sitenow_get_version();

  $meta_web_author = [
    '#tag' => 'meta',
    '#attributes' => [
      'name' => 'web-author',
      'content' => t('SiteNow @version (https://sitenow.uiowa.edu)', [
        '@version' => $version,
      ]),
    ],
  ];

  $variables['page']['#attached']['html_head'][] = [
    $meta_web_author,
    'web-author',
  ];
  $variables['page']['#attached']['library'][] = 'sitenow/global-scripts';
  $variables['page']['#attached']['drupalSettings']['sitenow']['version'] = $version;
}

/**
 * Implements hook_toolbar_alter().
 */
function sitenow_toolbar_alter(&$items) {
  if (isset($items['acquia_connector'])) {
    unset($items['acquia_connector']);
  }

  if (isset($items['tour'])) {
    $items['tour']['#attached']['library'][] = 'claro/tour-styling';
  }
}

/**
 * Implements hook_preprocess_select().
 */
function sitenow_preprocess_select(&$variables) {
  $admin_context = \Drupal::service('router.admin_context');
  if ($admin_context->isAdminRoute()) {
    if ($variables['element']['#multiple'] == TRUE) {
      // Use chosen for multi-selects.
      $variables['#attached']['library'][] = 'sitenow/chosen';
      // Remove none option.
      // Not the best solution, possibly look at:
      // https://www.drupal.org/files/issues/2117827-21.patch.
      if (isset($variables['options'], $variables['options'][0], $variables['options'][0]['value'])) {
        if ($variables['options'][0]['value'] == '_none' || $variables['options'][0]['value'] == '') {
          unset($variables['options'][0]);
        }
      }
    }
  }
}

/**
 * Implements hook_module_implements_alter().
 */
function sitenow_module_implements_alter(&$implementations, $hook) {
  // Unset administerusersbyrole query alter which over-filters the people page.
  // @todo: Refactor this to move sitenow last and then alter the altered query.
  if ($hook == 'query_alter' && isset($implementations['administerusersbyrole'])) {
    unset($implementations['administerusersbyrole']);
  }
}

/**
 * Implements hook_query_TAG_alter().
 *
 * Override the administerusersbyrole query alter to only exclude admins.
 */
function sitenow_query_administerusersbyrole_edit_access_alter(AlterableInterface $query) {
  if (!sitenow_is_user_admin(\Drupal::currentUser())) {
    // Exclude the root user.
    $query->condition('users_field_data.uid', 1, '<>');

    // Get a list of uids with the administrator role.
    $subquery = \Drupal::database()->select('user__roles', 'ur2');
    $subquery->fields('ur2', ['entity_id']);
    $subquery->condition('ur2.roles_target_id', 'administrator');

    // Exclude those uids from the result list.
    $query->condition('users_field_data.uid', $subquery, 'NOT IN');
  }
}

/**
 * Implements hook_form_BASE_FORM_ID_alter().
 */
function sitenow_form_menu_edit_form_alter(&$form, FormStateInterface $form_state, $form_id) {

  if ($form["id"]["#default_value"] == 'top-links') {
    $theme = \Drupal::config('system.theme')->get('default');
    if (in_array($theme, ['uids_base'])) {
      $limit = theme_get_setting('header.top_links_limit', 'uids_base');
      if ($limit) {
        $warning_text = t('Only the top @limit menu items will display.', [
          '@limit' => $limit,
        ]);
        \Drupal::messenger()->addWarning($warning_text);
      }
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_block_form_alter(&$form, FormStateInterface $form_state) {
  $theme = \Drupal::config('system.theme')->get('default');

  if (in_array($theme, ['uiowa_bootstrap', 'hr'])) {
    // Get block config settings.
    $settings = \Drupal::config('block.block.' . $form["id"]["#default_value"])->get('settings', FALSE);
    // Set classes options.
    $classes_options = ['' => 'None'];
    // Allow other modules to modify block classes options.
    \Drupal::moduleHandler()->alter('block_classes', $classes_options, $form, $form_state);
    $form['settings']['block_styles'] = [
      'style_details' => [
        '#type' => 'details',
        '#title' => t('Block Style Options'),
        '#open' => TRUE,
        'template' => [
          '#type' => 'select',
          '#title' => t('Select a block template'),
          '#default_value' => $settings['block_template'] ?? '',
          '#options' => [
            '_none' => t('None'),
            'card' => t('Card'),
          ],
        ],
        'classes' => [
          '#type' => 'select',
          '#title' => t('Set classes'),
          '#default_value' => $settings['block_classes'] ?? '',
          '#options' => $classes_options,
          '#multiple' => TRUE,
        ],
      ],
    ];

    // Add custom submit handler.
    $form["actions"]["submit"]["#submit"][] = 'sitenow_block_form_submit';
  }
}

/**
 * Custom submit handler for sitenow_form_block_form_alter().
 */
function sitenow_block_form_submit($form, FormStateInterface $form_state) {
  // Get block config object.
  $config = \Drupal::service('config.factory')->getEditable('block.block.' . $form["id"]["#default_value"]);
  // Get the config object settings.
  $settings = $config->get('settings');
  // Get block_styles from form_state.
  $style = $form_state->getValue(['settings', 'block_styles', 'style_details']);
  // Set style settings.
  $settings['block_template'] = $style['template'];
  $settings['block_classes'] = $style['classes'];
  // Save the settings.
  $config->set('settings', $settings)->save();
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function sitenow_theme_suggestions_block_alter(&$suggestions, $variables) {
  $template = $variables["elements"]["#configuration"]["block_template"] ?? FALSE;
  if ($template) {
    $suggestions[] = 'block__' . str_replace('-', '_', $template);
  }
}

/**
 * Implements hook_preprocess_block().
 */
function sitenow_preprocess_block(&$variables) {
  $classes = $variables["elements"]["#configuration"]["block_classes"] ?? FALSE;
  if ($classes) {
    $variables["attributes"]["class"] = array_merge($variables["attributes"]["class"], $classes);
  }
  switch ($variables["elements"]["#plugin_id"]) {
    // Visually hide page title if page option is set.
    case 'field_block:node:page:title':
    case 'page_title_block':
      $admin_context = \Drupal::service('router.admin_context');
      if (!$admin_context->isAdminRoute()) {
        $node = \Drupal::routeMatch()->getParameter('node');
        $node = (isset($node) ? $node : \Drupal::routeMatch()->getParameter('node_preview'));
        if ($node instanceof NodeInterface) {
          if ($node->hasField('field_publish_options') && !$node->get('field_publish_options')->isEmpty()) {
            $publish_options = $node->get('field_publish_options')->getValue();
            if (array_search('title_hidden', array_column($publish_options, 'value')) !== FALSE) {
              // For uiowa_bootstrap/classy default.
              $variables["title_attributes"]['class'][] = 'element-invisible';
              // For uids_base.
              $variables["attributes"]['class'][] = 'element-invisible';
            }
          }
        }
      }
      break;

  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_node_confirm_form_alter(&$form, FormStateInterface $form_state) {
  // Check/prevent front page from being deleted on single delete.
  // Only need to alter the delete operation form.
  if ($form_state->getFormObject()->getOperation() !== 'delete') {
    return;
  }
  $node = $form_state
    ->getFormObject()
    ->getEntity();

  // Get and dissect front page path.
  $front = \Drupal::config('system.site')->get('page.front');
  $url = Url::fromUri("internal:" . $front);

  if ($url->isRouted()) {
    $params = $url->getRouteParameters();

    if (isset($params['node']) && $params['node'] == $node->id()) {
      // Disable the 'Delete' button.
      $form['actions']['submit']['#disabled'] = TRUE;
      _sitenow_prevent_front_delete_message($node->getTitle());
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_node_delete_multiple_confirm_form_alter(&$form) {
  // Check/prevent front page from being deleted on bulk delete.
  // Get and dissect front page path.
  $front = \Drupal::config('system.site')->get('page.front');
  $params = Url::fromUri("internal:" . $front)->getRouteParameters();
  if (isset($params['node'])) {
    // Loop through until there is a front page match.
    foreach ($form['entities']['#items'] as $item => $title) {
      // Formatted as {nid}:{lang}.
      $item = explode(':', $item);
      if ($params['node'] == $item[0]) {
        // Disable the 'Delete' button.
        $form['actions']['submit']['#disabled'] = TRUE;
        _sitenow_prevent_front_delete_message($title);
        break;
      }
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_views_exposed_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  $view = $form_state->get('view');

  if ($view && $view->id() == 'administerusersbyrole_people') {
    if (!sitenow_is_user_admin(\Drupal::currentUser())) {
      unset($form['role']['#options']['administrator']);
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_views_form_administerusersbyrole_people_page_1_alter(&$form, FormStateInterface $form_state, $form_id) {
  if (!sitenow_is_user_admin(\Drupal::currentUser())) {
    foreach ($form['header']['user_bulk_form']['action']['#options'] as $key => $option) {
      if (stristr($option, 'administrator')) {
        unset($form['header']['user_bulk_form']['action']['#options'][$key]);
      }
    }
  }

  // Hide the bulk operations form from users who do not have one of these
  // permissions since it is then not that useful. Ideally, this would be
  // removed and handled in the administerusersbyrole module.
  $permissions = [
    'assign roles',
    'cancel users by role',
    'edit users by role',
  ];

  $hide = FALSE;

  foreach ($permissions as $permission) {
    if (\Drupal::currentUser()->hasPermission($permission) === FALSE) {
      $hide = TRUE;
    }
  }

  if ($hide) {
    $form['header']['#access'] = FALSE;
    $form['actions']['submit']['#access'] = FALSE;
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_config_split_edit_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  // Enable themes to be blacklisted.
  $form['blacklist_fieldset']['theme']['#access'] = TRUE;
}

/**
 * Implements hook_ENTITY_TYPE_prepare_form().
 */
function sitenow_config_split_prepare_form(EntityInterface $entity, $operation, FormStateInterface $form_state) {
  // Set a state variable to ensure config_split uses our Chosen
  // select implementation instead of checkboxes.
  if (!\Drupal::state()->get('config_split_use_select')) {
    \Drupal::state()->set('config_split_use_select', TRUE);
  }
}

/**
 * Implements hook_form_alter().
 */
function sitenow_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  switch ($form_id) {
    // Restrict theme settings form for non-admins.
    case 'system_theme_settings':
      if (!sitenow_is_user_admin(\Drupal::currentUser())) {
        $form["theme_settings"]['#access'] = FALSE;
        $form["logo"]['#access'] = FALSE;
        $form["favicon"]['#access'] = FALSE;
        $form["layout"]['#access'] = FALSE;
      }
      break;

    // Node form modifications.
    case 'node_page_edit_form':
    case 'node_page_form':
    case 'node_article_edit_form':
    case 'node_article_form':
    case 'node_person_edit_form':
    case 'node_person_form':
      if (isset($form['field_teaser'])) {
        // Create node_teaser group in the advanced container.
        $form['node_teaser'] = [
          '#type' => 'details',
          '#title' => $form["field_teaser"]["widget"][0]["#title"],
          '#group' => 'advanced',
          '#attributes' => [
            'class' => ['node-form-teaser'],
          ],
          '#attached' => [
            'library' => ['node/drupal.node'],
          ],
          '#weight' => -10,
          '#optional' => TRUE,
          '#open' => FALSE,
        ];
        // Set field_teaser to node_teaser group.
        $form['field_teaser']['#group'] = 'node_teaser';
      }
      if (isset($form['field_image'])) {
        // Create node_image group in the advanced container.
        $form['node_image'] = [
          '#type' => 'details',
          '#title' => $form["field_image"]["widget"]["#title"],
          '#group' => 'advanced',
          '#attributes' => [
            'class' => ['node-form-image'],
          ],
          '#attached' => [
            'library' => ['node/drupal.node'],
          ],
          '#weight' => -10,
          '#optional' => TRUE,
          '#open' => FALSE,
        ];
        // Set field_image to node_image group.
        $form['field_image']['#group'] = 'node_image';
        if (isset($form['field_image_caption'])) {
          // Set field_image_caption to node_image group.
          $form['field_image_caption']['#group'] = 'node_image';
        }
      }
      if (isset($form['field_tags'])) {
        // Create node_relations group in the advanced container.
        $form['node_relations'] = [
          '#type' => 'details',
          '#title' => t('Relationships'),
          '#group' => 'advanced',
          '#attributes' => [
            'class' => ['node-form-relations'],
          ],
          '#attached' => [
            'library' => ['node/drupal.node'],
          ],
          '#weight' => -10,
          '#optional' => TRUE,
          '#open' => FALSE,
        ];
        // Set field_tags to node_reference group.
        $form['field_tags']['#group'] = 'node_relations';
      }
      if (isset($form['field_publish_options'])) {
        // Place field in advanced options group.
        if (!empty($form["field_publish_options"]["widget"]["#options"])) {
          // Create node_publish group in the advanced container.
          $form['node_publish'] = [
            '#type' => 'details',
            '#title' => t('Page Options'),
            '#group' => 'advanced',
            '#attributes' => [
              'class' => ['node-form-publish'],
            ],
            '#attached' => [
              'library' => ['node/drupal.node'],
            ],
            '#weight' => 99,
            '#optional' => TRUE,
            '#open' => FALSE,
          ];
          // Set field_publish_options to node_publish group.
          $form['field_publish_options']['#group'] = 'node_publish';
          // Hide label. Redundant with group label.
          $form['field_publish_options']['widget']['#title_display'] = 'invisible';
        }
        else {
          // If no field options, set access to false.
          $form["field_publish_options"]['#access'] = FALSE;
        }
      }
      break;

    // Restrict certain webform component options.
    case 'webform_ui_element_form':
      if (!sitenow_is_user_admin(\Drupal::currentUser())) {
        // Remove access to wrapper, element, label attributes.
        $form['properties']['wrapper_attributes']['#access'] = FALSE;
        $form['properties']['element_attributes']['#access'] = FALSE;
        $form['properties']['label_attributes']['#access'] = FALSE;

        // Remove access to message close fields. Conflicts with BS alert close.
        $form['properties']['markup']['message_close']['#access'] = FALSE;
        $form['properties']['markup']['message_close_effect']['#access'] = FALSE;
        $form['properties']['markup']['message_storage']['#access'] = FALSE;
        $form['properties']['markup']['message_id']['#access'] = FALSE;

        // Remove access to change allowed file upload extensions.
        if (isset($form['properties']['file'])) {
          $form['properties']['file']['file_extensions']['#access'] = FALSE;
        }
      }
      break;
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_revision_overview_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  if (isset($form['nid'], $form['nid']['#value'])) {
    $node = Node::load($form['nid']['#value']);

    if ($node) {
      $type = $node->getType();
      $config = \Drupal::config("node.type.{$type}");

      if ($nrd = $config->get('third_party_settings.node_revision_delete')) {
        \Drupal::messenger()->addWarning(t('There is a @limit revision limit for this content type. The oldest revisions in excess of @limit are deleted during system background processes.', [
          '@limit' => $nrd['minimum_revisions_to_keep'],
        ]));
      }
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_system_site_information_settings_alter(&$form, FormStateInterface $form_state, $form_id) {
  $menus = menu_ui_get_menus();
  $social_media_menu = 'social';
  $custom_menu = 'footer-primary';
  $custom_menu_2 = 'footer-secondary';
  $custom_menu_3 = 'footer-tertiary';

  $default_theme = \Drupal::configFactory()
    ->get('system.theme')
    ->get('default');

  if ($default_theme === 'uids_base') {
    $form['uiowa_footer_block'] = [
      '#type' => 'details',
      '#title' => t('Footer Contact Information'),
      '#open' => TRUE,
    ];
    // Add link to Footer Contact Information block since block id is not
    // always "1". Contextual link does not show if block is empty.
    $footer_contact_block = \Drupal::service('entity.repository')->loadEntityByUuid('block_content', '0c0c1f36-3804-48b0-b384-6284eed8c67e');
    if ($footer_contact_block) {
      $destination = Url::fromRoute('<front>')->toString();
      $footer_contact_block_link = Url::fromRoute(
        'entity.block_content.edit_form',
        ['block_content' => $footer_contact_block->id()],
        [
          'query' => ['destination' => $destination],
          'absolute' => TRUE,
        ])->toString();

      $form['uiowa_footer_block']['uiowa_footer_contact_info_edit'] = [
        '#type' => 'item',
        '#markup' => t('<a href="@menu_link">Edit Footer Contact Information</a>.', [
          '@menu_link' => $footer_contact_block_link,
        ]),
      ];
    }

    $form['uiowa_footer_menus'] = [
      '#type' => 'details',
      '#title' => t('Footer Menus'),
      '#open' => TRUE,
    ];

    $form['uiowa_footer_menus']['uiowa_footer_social_media_menu']['#access'] = FALSE;
    if (!empty($social_media_menu) && $social_media_menu != 'none') {
      $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $social_media_menu])->toString();
      $form['uiowa_footer_menus']['uiowa_footer_social_media_menu_help'] = [
        '#type' => 'item',
        '#markup' => t('Links in the social media section are managed via the <a href="@menu_link">@menu_name menu</a>.', [
          '@menu_link' => $menu_link,
          '@menu_name' => $menus[$social_media_menu],
        ]),
      ];
    }

    $form['uiowa_footer_menus']['uiowa_footer_custom_menu']['#access'] = FALSE;
    if (!empty($custom_menu) && $custom_menu != 'none') {
      $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $custom_menu])->toString();
      $form['uiowa_footer_menus']['uiowa_footer_custom_menu_help'] = [
        '#type' => 'item',
        '#markup' => t('Links in the left column are managed via the <a href="@menu_link">@menu_name menu</a>.', [
          '@menu_link' => $menu_link,
          '@menu_name' => $menus[$custom_menu],
        ]),
      ];
    }

    $form['uiowa_footer_menus']['uiowa_footer_custom_menu_2']['#access'] = FALSE;
    if (!empty($custom_menu_2) && $custom_menu_2 != 'none') {
      $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $custom_menu_2])->toString();
      $form['uiowa_footer_menus']['uiowa_footer_custom_menu_2_help'] = [
        '#type' => 'item',
        '#markup' => t('Links in the middle column are managed via the <a href="@menu_link">@menu_name menu</a>.', [
          '@menu_link' => $menu_link,
          '@menu_name' => $menus[$custom_menu_2],
        ]),
      ];
    }

    $form['uiowa_footer_menus']['uiowa_footer_custom_menu_3']['#access'] = FALSE;
    if (!empty($custom_menu_2) && $custom_menu_2 != 'none') {
      $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $custom_menu_3])->toString();
      $form['uiowa_footer_menus']['uiowa_footer_custom_menu_3_help'] = [
        '#type' => 'item',
        '#markup' => t('Links in the right column are managed via the <a href="@menu_link">@menu_name menu</a>.', [
          '@menu_link' => $menu_link,
          '@menu_name' => $menus[$custom_menu_3],
        ]),
      ];
    }

    // Hide the site slogan field, as it's not used in uids_base theme.
    $form['site_information']['site_slogan']['#access'] = FALSE;
  }
  else {
    $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_social_media_menu']['#access'] = FALSE;
    if (!empty($social_media_menu) && $social_media_menu != 'none') {
      $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $social_media_menu])->toString();
      $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_social_media_menu_help'] = [
        '#type' => 'item',
        '#markup' => t('Links in the social media section are managed via the <a href="@menu_link">@menu_name menu</a>.', [
          '@menu_link' => $menu_link,
          '@menu_name' => $menus[$social_media_menu],
        ]),
      ];
    }

    $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_custom_menu']['#access'] = FALSE;
    if (!empty($custom_menu) && $custom_menu != 'none') {
      $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $custom_menu])->toString();
      $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_custom_menu_help'] = [
        '#type' => 'item',
        '#markup' => t('Links in the left column are managed via the <a href="@menu_link">@menu_name menu</a>.', [
          '@menu_link' => $menu_link,
          '@menu_name' => $menus[$custom_menu],
        ]),
      ];
    }

    $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_custom_menu_2']['#access'] = FALSE;
    if (!empty($custom_menu_2) && $custom_menu_2 != 'none') {
      $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $custom_menu_2])->toString();
      $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_custom_menu_2_help'] = [
        '#type' => 'item',
        '#markup' => t('Links in the right column are managed via the <a href="@menu_link">@menu_name menu</a>.', [
          '@menu_link' => $menu_link,
          '@menu_name' => $menus[$custom_menu_2],
        ]),
      ];
    }
  }
}

/**
 * Custom warning message for front page deletion detection.
 *
 * @param string $title
 *   The front page match content's title.
 *
 * @see sitenow_form_node_confirm_form_alter()
 * @see sitenow_form_node_delete_multiple_confirm_form_alter()
 */
function _sitenow_prevent_front_delete_message($title) {
  // Print warning message informing user to use basic site settings.
  $url = Url::fromRoute('system.site_information_settings', [], ['fragment' => 'edit-site-frontpage']);
  $settings_link = Link::fromTextAndUrl(t('change the front page'), $url)
    ->toString();
  $warning_text = t('The content <em>"@title"</em> is currently set as the front page for this site. You must @settings_link before deleting this content.', [
    '@settings_link' => $settings_link,
    '@title' => $title,
  ]);
  \Drupal::messenger()->addWarning($warning_text);
}

/**
 * Set dynamic allowed values for the publish_options field.
 *
 * @param \Drupal\field\Entity\FieldStorageConfig $definition
 *   The field definition.
 * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
 *   The entity being created if applicable.
 * @param bool $cacheable
 *   Boolean indicating if the results are cacheable.
 *
 * @return array
 *   An array of possible key and value options.
 *
 * @see options_allowed_values()
 */
function publish_options_allowed_values(FieldStorageConfig $definition, ContentEntityInterface $entity = NULL, &$cacheable) {
  $cacheable = FALSE;
  $options = [];

  if (method_exists($entity, 'bundle')) {
    $bundle = $entity->bundle();

    switch ($bundle) {
      case 'page':
        $options['title_hidden'] = 'Visually hide title';
        $options['no_sidebars'] = 'Remove sidebar regions';
        break;
    }

    // Allow modules to alter options.
    \Drupal::moduleHandler()
      ->alter('publish_options', $options, $entity, $bundle);
  }

  return $options;
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function sitenow_preprocess_page(&$variables) {
  $admin_context = \Drupal::service('router.admin_context');
  if (!$admin_context->isAdminRoute()) {
    $node = \Drupal::routeMatch()->getParameter('node');
    $node = (isset($node) ? $node : \Drupal::routeMatch()->getParameter('node_preview'));
    if ($node instanceof NodeInterface) {
      $variables['header_attributes'] = new Attribute();
      if ($node->hasField('field_publish_options') && !$node->get('field_publish_options')->isEmpty()) {
        $publish_options = $node->get('field_publish_options')->getValue();
        if (array_search('no_sidebars', array_column($publish_options, 'value')) !== FALSE) {
          // Remove sidebar regions.
          $variables['page']['sidebar_first'] = [];
          $variables['page']['sidebar_second'] = [];
        }
        if (array_search('title_hidden', array_column($publish_options, 'value')) !== FALSE) {
          $variables['header_attributes']->addClass('title-hidden');
        }
      }
    }
  }
}

/**
 * Implements hook_preprocess_HOOK().
 */
function sitenow_preprocess_node(&$variables) {
  $admin_context = \Drupal::service('router.admin_context');
  if (!$admin_context->isAdminRoute()) {
    $node = $variables["node"];
    // Get moderation state of node.
    $revision_id = $node->getRevisionId();
    if ($revision_id) {
      $revision = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadRevision($revision_id);
      $moderation_state = $revision->get('moderation_state')->getString();
      $status = $revision->get('status')->value;
      if ($status == 0) {
        $pre_vowel = (in_array($moderation_state[0], [
          'a',
          'e',
          'i',
          'o',
          'u',
        ]) ? 'n' : '');
        $warning_text = t('This content is currently in a@pre_vowel @moderation_state state.', [
          '@pre_vowel' => $pre_vowel,
          '@moderation_state' => $moderation_state,
        ]);

        switch ($variables['view_mode']) {
          case 'teaser':
            $variables["content"]['unpublished'] = [
              '#type' => 'markup',
              '#markup' => '<span class="badge badge--orange" aria-description="' . $warning_text . '">' . ucfirst($moderation_state) . '</span>',
              '#weight' => 99,
            ];
            break;

          case 'full':
            \Drupal::messenger()->addWarning($warning_text);
            break;

        }
      }
    }
  }
}

/**
 * Implements hook_form_BASE_FORM_ID_alter() for menu_link_content_form.
 */
function sitenow_form_menu_link_content_form_alter(array &$form, FormStateInterface $form_state, $form_id) {
  /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_link */
  $menu_link = $form_state->getFormObject()->getEntity();
  $menu_link_options = $menu_link->link->first()->options ?: [];
  $menu = $menu_link->getMenuName();

  switch ($menu) {
    case 'social':
      $form['fa_icon'] = [
        '#type' => 'textfield',
        '#title' => t('FontAwesome Icon'),
        '#default_value' => !empty($menu_link_options['fa_icon']) ? $menu_link_options['fa_icon'] : '',
        '#attributes' => [
          'autocomplete' => 'off',
          'class' => [
            'fa-iconpicker',
          ],
        ],
        '#description' => t('Pick an icon to represent this link by clicking on this field. To see a list of available icons and their class names, <a href="https://fontawesome.com/icons?d=gallery&m=free">visit the FontAwesome website</a>.'),
        '#attached' => [
          'library' => [
            'sitenow/fontawesome-iconpicker',
          ],
        ],
      ];

      $form['actions']['submit']['#submit'][] = 'sitenow_form_menu_link_content_form_submit';

      break;
  }
}

/**
 * Custom validation function for sitenow_form_menu_link_content_form_alter.
 */
function sitenow_form_menu_link_content_form_submit(array &$form, FormStateInterface $form_state) {
  $icon_field = $form_state->getValue('fa_icon');

  $options = [
    'fa_icon' => !empty($icon_field) ? Html::escape($icon_field) : '',
  ];

  /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_link */
  $menu_link = $form_state->getFormObject()->getEntity();
  $menu_link_options = $menu_link->link->first()->options;

  $merged = array_merge($menu_link_options, $options);

  $menu_link->link->first()->options = $merged;
  $menu_link->save();
}

/**
 * Implements hook_link_alter().
 */
function sitenow_link_alter(&$variables) {
  if ($variables['url']->isRouted() && $variables['url']->getRouteName() === '<nolink>') {
    $variables['options']['attributes']['tabindex'] = '0';
  }
  if (!empty($variables['options']['fa_icon'])) {
    $variables['options']['attributes']['class'][] = 'fa-icon';

    $variables['text'] = t('<span class="fa @icon" aria-hidden="true"></span> <span class="menu-link-title">@title</span>', [
      '@icon' => $variables['options']['fa_icon'],
      '@title' => $variables['text'],
    ]);
  }
}

/**
 * Implements hook_preprocess_HOOK().
 */
function sitenow_preprocess_field(&$variables) {
  switch ($variables["element"]["#field_name"]) {
    case 'title':
      if ($variables["element"]["#view_mode"] == 'teaser') {
        $variables['attributes']['class'][] = 'h5';
      }
      break;
  }
}

/**
 * Implements hook_page_attachments().
 */
function sitenow_page_attachments(array &$attachments) {
  // Attach css file on admin pages.
  $admin_context = \Drupal::service('router.admin_context');
  $admin_theme = \Drupal::config('system.theme')->get('admin');

  if ($admin_context->isAdminRoute() && $admin_theme == 'claro') {
    $attachments['#attached']['library'][] = 'sitenow/admin-overrides';
  }
}

/**
 * Implements hook_toolbar().
 */
function sitenow_toolbar() {
  $version = sitenow_get_version();
  $url = Url::fromUri('//sitenow.uiowa.edu/node/36');

  $items = [];
  $items['support'] = [
    '#type' => 'toolbar_item',
    'tab' => [
      '#type' => 'link',
      '#url' => $url,
      '#title' => t('SiteNow @version Help', [
        '@version' => $version,
      ]),
      '#options' => [
        'attributes' => [
          'title' => t('Opens help documentation in a new window.'),
          'id' => 'toolbar-item-sitenow-help',
          'class' => [
            'toolbar-item',
          ],
          'role' => 'button',
          'target' => '_blank',
        ],
      ],
    ],
  ];

  return $items;
}

/**
 * Helper function to determine if the current user is an admin.
 *
 * @param \Drupal\Core\Session\AccountProxy $current_user
 *   The current user account.
 *
 * @return bool
 *   Boolean indicating whether or not current user is an admin.
 *
 * @todo: Replace this with uiowa_core access checker service.
 */
function sitenow_is_user_admin(AccountProxy $current_user) {
  if ($current_user->id() == 1 || in_array('administrator', $current_user->getRoles())) {
    return TRUE;
  }
  else {
    return FALSE;
  }
}

/**
 * Determine the version of SiteNow based on what config is active.
 *
 * @todo: Return additional information like if any other splits are active that might impact functionality.
 */
function sitenow_get_version() {
  $version = 'v3';

  $is_v2 = \Drupal::config('config_split.config_split.sitenow_v2')->get('status');

  if ($is_v2) {
    $version = 'v2';
  }

  return $version;
}

/**
 * Implements hook_entity_insert().
 */
function sitenow_entity_insert(EntityInterface $entity) {
  // UUIDs for default content Home and About pages.
  $uuids = [
    '922b3b26-306a-457c-ba18-2c00966f81cf',
    'f44a17cb-a187-4286-ad9f-aae44a8e9f85',
  ];
  if (in_array($entity->uuid(), $uuids)) {
    $database = \Drupal::database();
    $use_controller = new InlineBlockUsage($database);
    $block_controller = \Drupal::service('entity_type.manager')
      ->getStorage('block_content');

    // Load the node and grab the layout information.
    $node = \Drupal::service('entity.repository')
      ->loadEntityByUuid('node', $entity->uuid());
    $layouts = $node->get('layout_builder__layout');

    foreach ($layouts as $layout) {
      $section = $layout->getValue()['section'];
      // Pull out individual components.
      foreach ($section->getComponents() as $component) {
        // Grab the associated block's uuid.
        $config = $component->get('configuration');
        if (isset($config['block_revision_id'])) {
          $rev_id = $config['block_revision_id'];
          $block = $block_controller->loadRevision($rev_id);
          if ($block) {
            $use_controller->addUsage($block->id(), $node);
          }
        }
      }
    }
  }
}

/**
 * Implements hook_ENTITY_TYPE_presave().
 *
 * @param \Drupal\node\Entity\Node $node
 */
function sitenow_node_presave(Node $node) {
  $tags = ['node_type:' . $node->getType()];
  Cache::invalidateTags($tags);
}

/**
 * Implements hook_ENTITY_TYPE_delete().
 *
 * @param \Drupal\node\Entity\Node $node
 */
function sitenow_node_delete(Node $node) {
  $tags = ['node_type:' . $node->getType()];
  Cache::invalidateTags($tags);
}
