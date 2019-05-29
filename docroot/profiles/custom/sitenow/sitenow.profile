<?php

/**
 * @file
 * Profile code.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\views\ViewExecutable;

/**
 * Implements hook_toolbar_alter().
 */
function sitenow_toolbar_alter(&$items) {
  if (isset($items['acquia_connector'])) {
    unset($items['acquia_connector']);
  }

  if (isset($items['tour'])) {
    $items['tour']['#attached']['library'][] = 'seven/tour-styling';
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
      if ($variables['options'][0]['value'] == '_none' || $variables['options'][0]['value'] == '') {
        unset($variables['options'][0]);
      }
    }
  }
}

/**
 * Implements hook_views_pre_render().
 */
function sitenow_views_pre_render(ViewExecutable $view) {
  if ($view->id() == 'administerusersbyrole_people' && $view->current_display == 'page_1') {
    $user_roles = \Drupal::currentUser()->getRoles();

    // Do not show administrator accounts to non-admins.
    if (\Drupal::currentUser()->id() != 1 && !(in_array('administrator', $user_roles))) {
      $non_admins = [];

      foreach ($view->result as $result) {
        if ($result) {
          $user = User::load($result->uid);

          if ($user->hasRole('administrator') === FALSE) {
            $non_admins[] = $result;
          }
        }
      }

      $view->result = $non_admins;
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_block_form_alter(&$form, FormStateInterface $form_state) {
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
          '_none' => 'None',
          'card' => 'Card',
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
    $roles = \Drupal::currentUser()->getRoles();
    if (!in_array('administrator', $roles)) {
      unset($form['role']['#options']['administrator']);
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_views_form_administerusersbyrole_people_page_1_alter(&$form, FormStateInterface $form_state, $form_id) {
  $roles = \Drupal::currentUser()->getRoles();

  if (!in_array('administrator', $roles)) {
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
 * Implements hook_form_alter().
 */
function sitenow_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  switch ($form_id) {
    case 'node_page_edit_form':
    case 'node_page_form':
    case 'node_article_edit_form':
    case 'node_article_form':
      if (isset($form['field_teaser'])) {
        // Create node_teaser group in the advanced container.
        $form['node_teaser'] = [
          '#type' => 'details',
          '#title' => t('Content Description'),
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
          '#title' => t('Featured Image'),
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
      if (isset($form['field_reference'])) {
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
        // Set field_reference to node_reference group.
        $form['field_reference']['#group'] = 'node_relations';
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
          // Set field_reference to node_reference group.
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
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_system_site_information_settings_alter(&$form, FormStateInterface $form_state, $form_id) {
  $config = \Drupal::config('uiowa_footer.settings');
  $menus = menu_ui_get_menus();
  $social_media_menu = $config->get('social_media_menu');
  $custom_menu = $config->get('custom_menu');
  $custom_menu_2 = $config->get('custom_menu_2');

  $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_social_media_menu']['#access'] = FALSE;
  if (!empty($social_media_menu) && $social_media_menu != 'none') {
    $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $social_media_menu])->toString();
    $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_social_media_menu_help'] = [
      '#type' => 'item',
      '#markup' => t('Links in the social media section are managed via the <a href="@menu_link">@menu_name menu</a>.', ['@menu_link' => $menu_link, '@menu_name' => $menus[$social_media_menu]]),
    ];
  }

  $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_custom_menu']['#access'] = FALSE;
  if (!empty($custom_menu) && $custom_menu != 'none') {
    $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $custom_menu])->toString();
    $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_custom_menu_help'] = [
      '#type' => 'item',
      '#markup' => t('Links in the left column are managed via the <a href="@menu_link">@menu_name menu</a>.', ['@menu_link' => $menu_link, '@menu_name' => $menus[$custom_menu]]),
    ];
  }

  $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_custom_menu_2']['#access'] = FALSE;
  if (!empty($custom_menu_2) && $custom_menu_2 != 'none') {
    $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $custom_menu_2])->toString();
    $form['uiowa_footer']['uiowa_footer_menus']['uiowa_footer_custom_menu_2_help'] = [
      '#type' => 'item',
      '#markup' => t('Links in the right column are managed via the <a href="@menu_link">@menu_name menu</a>.', ['@menu_link' => $menu_link, '@menu_name' => $menus[$custom_menu_2]]),
    ];
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
  $bundle = $entity->bundle();

  switch ($bundle) {
    case 'page':
      $options['title_hidden'] = 'Visually hide title';
      $options['no_sidebars'] = 'Remove sidebar regions';
      break;
  }

  // Allow modules to alter options.
  \Drupal::moduleHandler()->alter('publish_options', $options, $entity, $bundle);

  return $options;
}

/**
 * Implements hook_preprocess_HOOK().
 */
function sitenow_preprocess_page_title(&$variables) {
  $admin_context = \Drupal::service('router.admin_context');
  if (!$admin_context->isAdminRoute()) {
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      if ($node->hasField('field_publish_options') && !$node->get('field_publish_options')->isEmpty()) {
        $publish_options = $node->get('field_publish_options')->getValue();
        if (array_search('title_hidden', array_column($publish_options, 'value')) !== FALSE) {
          $variables["title_attributes"]['class'][] = 'sr-only';
        }
      }
    }
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function sitenow_preprocess_page(&$variables) {
  $admin_context = \Drupal::service('router.admin_context');
  if (!$admin_context->isAdminRoute()) {
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      if ($node->hasField('field_publish_options') && !$node->get('field_publish_options')->isEmpty()) {
        $publish_options = $node->get('field_publish_options')->getValue();
        if (array_search('no_sidebars', array_column($publish_options, 'value')) !== FALSE) {
          // Remove sidebar regions.
          $variables['page']['sidebar_first'] = [];
          $variables['page']['sidebar_second'] = [];
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
          'class' => [
            'fa-iconpicker',
          ],
        ],
        '#description' => t('Pick an icon to render after the menu item.'),
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
  if (!empty($variables['options']['fa_icon'])) {
    $variables['options']['attributes']['class'][] = 'fa-icon';

    $variables['text'] = t('<i class="fa @icon" aria-hidden="true"></i> <span class="menu-link-title">@title</span>', [
      '@icon' => $variables['options']['fa_icon'],
      '@title' => $variables['text'],
    ]);
  }
}

/**
 * Implements hook_library_info_alter().
 */
function sitenow_library_info_alter(&$libraries, $extension) {
  if ($extension == 'core') {
    if (isset($libraries['ckeditor'])) {
      $libraries['ckeditor'] = [
        'version' => '4.11.4',
        'js' => [
          '/profiles/custom/sitenow/js/ckeditor/ckeditor.js' => [
            'preprocess' => FALSE,
            'minified' => TRUE,
          ],
        ],
      ];
    }
  }
}

/**
 * Implements hook_page_attachments().
 */
function sitenow_page_attachments(array &$attachments) {
  // Attach css file on admin pages.
  $admin_context = \Drupal::service('router.admin_context');
  if ($admin_context->isAdminRoute()) {
    $variables['#attached']['library'][] = 'sitenow/admin-overrides';
  }
}
