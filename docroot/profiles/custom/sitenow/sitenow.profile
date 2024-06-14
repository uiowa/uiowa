<?php

/**
 * @file
 * Profile code.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Link;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\layout_builder\InlineBlockUsage;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\Form\MenuLinkContentForm;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\system\Entity\Menu;

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
 * Implements hook_preprocess_HOOK().
 */
function sitenow_preprocess_breadcrumb(&$variables) {
  $admin_context = \Drupal::service('router.admin_context');
  if (!$admin_context->isAdminRoute()) {
    $routes = [];
    foreach ($variables['links'] as $key => $link) {
      $url = $link->getURL();
      // Test for external paths.
      if ($url->isRouted()) {
        $routes[$key] = $link->getUrl()->getRouteName();
      }
    }
    // For webforms, remove all system routes and the webform route.
    if (($key = array_search("entity.webform.collection", $routes)) !== FALSE) {
      unset($variables['breadcrumb'][$key]);
      foreach ($routes as $key => $value) {
        if (substr($value, 0, strlen('system')) === 'system') {
          unset($variables['breadcrumb'][$key]);
        }
      }
    }
  }
}

/**
 * Implements hook_preprocess_select().
 */
function sitenow_preprocess_select(&$variables) {
  $admin_context = \Drupal::service('router.admin_context');
  if ($admin_context->isAdminRoute()) {
    if ($variables['element']['#multiple'] === TRUE) {
      // Use chosen for multi-selects.
      $variables['#attached']['library'][] = 'sitenow/chosen';
      // Remove none option.
      // Not the best solution, possibly look at:
      // https://www.drupal.org/files/issues/2117827-21.patch.
      if (isset($variables['options'], $variables['options'][0], $variables['options'][0]['value'])) {
        if ($variables['options'][0]['value'] === '_none' || $variables['options'][0]['value'] === '') {
          unset($variables['options'][0]);
        }
      }
    }
  }
}

/**
 * Implements hook_js_alter().
 */
function sitenow_js_alter(&$javascript, AttachedAssetsInterface $assets, LanguageInterface $language) {
  // Remove fontawesome js if ckeditor5 is present.
  if (array_key_exists('core/modules/ckeditor5/js/ckeditor5.js', $javascript) || array_key_exists('core/modules/ckeditor5/js/ckeditor5.dialog.fix.js', $javascript)) {
    if (array_key_exists('libraries/fontawesome/js/all.min.js', $javascript)) {
      unset($javascript['libraries/fontawesome/js/all.min.js']);
    }
  }
}

/**
 * Implements hook_module_implements_alter().
 */
function sitenow_module_implements_alter(&$implementations, $hook) {
  // Unset administerusersbyrole query alter which over-filters the people page.
  // @todo Refactor this to move sitenow last and then alter the altered query.
  //   See https://github.com/uiowa/uiowa/issues/5023
  if ($hook === 'query_alter' && isset($implementations['administerusersbyrole'])) {
    unset($implementations['administerusersbyrole']);
  }
}

/**
 * Implements hook_query_TAG_alter().
 *
 * Override the administerusersbyrole query alter to only exclude admins.
 */
function sitenow_query_administerusersbyrole_edit_access_alter(AlterableInterface $query) {

  /** @var Drupal\uiowa_core\Access\UiowaCoreAccess $check */
  $check = \Drupal::service('uiowa_core.access_checker');

  /** @var Drupal\Core\Access\AccessResultInterface $access */
  $access = $check->access(\Drupal::currentUser()->getAccount());

  if ($access->isForbidden()) {
    // Exclude the root user.
    if ($query instanceof SelectInterface) {
      $query->condition('users_field_data.uid', 1, '<>');

      // Get a list of uids with the administrator role.
      $subquery = \Drupal::database()->select('user__roles', 'ur2');
      $subquery->fields('ur2', ['entity_id']);
      $subquery->condition('ur2.roles_target_id', 'administrator');

      // Exclude those uids from the result list.
      $query->condition('users_field_data.uid', $subquery, 'NOT IN');
    }
  }
}

/**
 * Implements hook_form_BASE_FORM_ID_alter().
 */
function sitenow_form_menu_edit_form_alter(&$form, FormStateInterface $form_state, $form_id) {

  if ($form['id']['#default_value'] === 'top-links') {
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
 * Custom submit handler for sitenow_form_block_form_alter().
 */
function sitenow_block_form_submit($form, FormStateInterface $form_state) {
  // Get block config object.
  $config = \Drupal::service('config.factory')->getEditable('block.block.' . $form['id']['#default_value']);
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
  $template = $variables['elements']['#configuration']['block_template'] ?? FALSE;
  if ($template) {
    $suggestions[] = 'block__' . str_replace('-', '_', $template);
  }
}

/**
 * Implements hook_preprocess_block().
 */
function sitenow_preprocess_block(&$variables) {
  $classes = $variables['elements']['#configuration']['block_classes'] ?? FALSE;
  if ($classes) {
    $variables['attributes']['class'] = array_merge($variables['attributes']['class'], $classes);
  }
  switch ($variables['elements']['#plugin_id']) {
    // Visually hide page title if page option is set.
    case 'field_block:node:page:title':
    case 'page_title_block':
      $admin_context = \Drupal::service('router.admin_context');
      if (!$admin_context->isAdminRoute()) {
        $node = \Drupal::routeMatch()->getParameter('node');
        $node = ($node ?? \Drupal::routeMatch()->getParameter('node_preview'));
        if ($node instanceof NodeInterface) {
          if ($node->hasField('field_publish_options') && !$node->get('field_publish_options')->isEmpty()) {
            $publish_options = $node->get('field_publish_options')->getValue();
            if (array_search('title_hidden', array_column($publish_options, 'value')) !== FALSE) {
              $variables['attributes']['class'][] = 'element-invisible';
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
  $form_object = $form_state->getFormObject();
  if ($form_object instanceof EntityFormInterface) {
    if ($form_object->getOperation() !== 'delete') {
      return;
    }
    /** @var Drupal\Core\Entity\EntityInterface $node */
    $node = $form_object->getEntity();

    // Get and dissect front page path.
    $front = \Drupal::config('system.site')->get('page.front');
    $url = Url::fromUri("internal:" . $front);

    if ($url->isRouted()) {
      $params = $url->getRouteParameters();

      if (isset($params['node']) && $params['node'] === $node->id()) {
        // Disable the 'Delete' button.
        $form['actions']['submit']['#disabled'] = TRUE;
        _sitenow_prevent_front_delete_message($node->label());
      }
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
      if ($params['node'] === $item[0]) {
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

  if ($view && $view->id() === 'administerusersbyrole_people') {
    /** @var Drupal\uiowa_core\Access\UiowaCoreAccess $check */
    $check = \Drupal::service('uiowa_core.access_checker');

    /** @var Drupal\Core\Access\AccessResultInterface $access */
    $access = $check->access(\Drupal::currentUser()->getAccount());

    if ($access->isForbidden()) {
      unset($form['role']['#options']['administrator']);
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_views_form_administerusersbyrole_people_page_1_alter(&$form, FormStateInterface $form_state, $form_id) {
  /** @var Drupal\uiowa_core\Access\UiowaCoreAccess $check */
  $check = \Drupal::service('uiowa_core.access_checker');

  /** @var Drupal\Core\Access\AccessResultInterface $access */
  $access = $check->access(\Drupal::currentUser()->getAccount());

  if ($access->isForbidden()) {
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
 * Custom node content type form defaults.
 */
function _sitenow_node_form_defaults(&$form, $form_state) {
  // @todo Remove this after the transition to body summary
  //   has been completed.
  if (isset($form['field_teaser'])) {
    // Create node_teaser group in the advanced container.
    $form['node_teaser'] = [
      '#type' => 'details',
      '#title' => $form['field_teaser']['widget'][0]['#title'],
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

    // If we're in v3 or a non-page content type in v2 (article, person),
    // then disable the field_teaser and add help text.
    if (sitenow_get_version() === 'v3' || !str_starts_with($form['#id'], 'node-page')) {
      $form['node_teaser']['#description'] = t('<strong>This teaser field has been deprecated, and replaced by the Summary field.</strong>');
      $form['field_teaser']['#disabled'] = TRUE;
    }
  }

  if (isset($form['field_image'])) {
    // Create node_image group in the advanced container.
    $form['node_image'] = [
      '#type' => 'details',
      '#title' => $form['field_image']['widget']['#title'],
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

  if (isset($form['field_featured_image_display'])) {
    $form['field_featured_image_display']['#group'] = 'node_image';
    $form['field_featured_image_display']['widget']['#options']['_none'] = 'Site-wide default';

    $form_object = $form_state->getFormObject();

    if ($form_object && $node = $form_object->getEntity()) {
      $type = $node->getType() . 's';
      $form['field_featured_image_display']['widget']['#description'] .= t('&nbsp;If "Site-wide default" is selected, this setting can be changed on the <a href="@settings_url">SiteNow @types settings</a>.', [
        '@settings_url' => Url::fromRoute("sitenow_$type.settings_form")->toString(),
        '@types' => ucfirst($type),
      ]);
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
    if (!empty($form['field_publish_options']['widget']['#options'])) {
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
      $form['field_publish_options']['#access'] = FALSE;
    }
  }
  return $form;
}

/**
 * Implements hook_form_alter().
 */
function sitenow_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  $form_object = $form_state->getFormObject();

  if (is_a($form_object, ContentEntityForm::class)) {
    /** @var \Drupal\Core\Entity\ContentEntityForm $form_object */
    if ($form_object->getEntity()->getEntityType()->id() === 'media') {
      // Hide revision information on media entity add/edit forms
      // to prevent new revisions from being created. This aids our
      // file replace functionality.
      if (isset($form['revision_information'])) {
        $form['revision_information']['#access'] = FALSE;
      }

      // Prevent deletion if there is entity usage.
      // This is accompanied by a message from the entity_usage module.
      if ($form_object->getOperation() == 'delete') {
        $usage_data = \Drupal::service('entity_usage.usage')->listSources($form_object->getEntity());
        if (!empty($usage_data)) {
          // Check to see if usage is tied to a revisionable parent entity.
          $connection = \Drupal::database();
          foreach ($usage_data as $type => $source) {
            if ($type === 'node') {
              $form['actions']['submit']['#disabled'] = TRUE;
              return;
            }
            foreach ($source as $vid => $item) {
              $query = $connection->select('entity_usage', 'u');
              $query->fields('u', ['source_type']);
              $query->condition('u.target_id', $vid, '=');
              $query->condition('u.target_type', $type, '=');
              $result = $query->execute()
                ->fetchField();
              if ($result) {
                if ($result === 'node' || $result === 'fragment') {
                  $form['actions']['submit']['#disabled'] = TRUE;
                  return;
                }
              }
            }
          }
        }
      }
    }
  }

  switch ($form_id) {
    // Restrict theme settings form for non-admins.
    case 'system_theme_settings':
      /** @var Drupal\uiowa_core\Access\UiowaCoreAccess $check */
      $check = \Drupal::service('uiowa_core.access_checker');

      /** @var Drupal\Core\Access\AccessResultInterface $access */
      $access = $check->access(\Drupal::currentUser()->getAccount());

      if ($access->isForbidden()) {
        $form['theme_settings']['#access'] = FALSE;
        $form['logo']['#access'] = FALSE;
        $form['favicon']['#access'] = FALSE;
        $form['layout']['#access'] = FALSE;
      }
      break;

    // Node form modifications.
    case 'node_page_edit_form':
    case 'node_page_form':
    case 'node_article_edit_form':
    case 'node_article_form':
    case 'node_person_edit_form':
    case 'node_person_form':
      _sitenow_node_form_defaults($form, $form_state);
      break;

    // Restrict certain webform component options.
    case 'webform_ui_element_form':
      /** @var Drupal\uiowa_core\Access\UiowaCoreAccess $check */
      $check = \Drupal::service('uiowa_core.access_checker');

      /** @var Drupal\Core\Access\AccessResultInterface $access */
      $access = $check->access(\Drupal::currentUser()->getAccount());

      if ($access->isForbidden()) {
        // Remove access to wrapper, element, label attributes.
        $form['properties']['wrapper_attributes']['#access'] = FALSE;
        $form['properties']['element_attributes']['#access'] = FALSE;
        $form['properties']['label_attributes']['#access'] = FALSE;

        // Remove access to message close fields. Conflicts with BS alert close.
        $form['properties']['markup']['message_close']['#access'] = FALSE;
        $form['properties']['markup']['message_close_effect']['#access'] = FALSE;
        $form['properties']['markup']['message_storage']['#access'] = FALSE;
        $form['properties']['markup']['message_id']['#access'] = FALSE;
      }

      // Custom validation for webform components.
      $form['#validate'][] = '_sitenow_webform_validate';
      break;

    // Remove access to headline field in footer contact block.
    case 'block_content_uiowa_text_area_edit_form':
      if ($form_object instanceof EntityFormInterface) {
        /** @var \Drupal\block\BlockInterface $block */
        $block = $form_object->getEntity();
        $uuid = $block->uuid();
        // For Footer Contact Information, limit non-admins
        // to minimal and remove headline field.
        if ($uuid === '0c0c1f36-3804-48b0-b384-6284eed8c67e') {
          $form['field_uiowa_headline']['#access'] = FALSE;
          /** @var Drupal\uiowa_core\Access\UiowaCoreAccess $check */
          $check = \Drupal::service('uiowa_core.access_checker');

          $access = $check->access(\Drupal::currentUser()->getAccount());

          if ($access->isForbidden()) {
            $form['field_uiowa_text_area']['widget'][0]['#allowed_formats'] = [
              'minimal',
              'plain_text',
            ];
          }
        }
      }
      break;

  }
}

/**
 * Custom validation for webform_ui_element_form.
 *
 * @param array $form
 *   The form element.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state.
 */
function _sitenow_webform_validate(array &$form, FormStateInterface $form_state) {
  // Validate the managed_file webform component.
  if ($form_state->getValue(['properties', 'type']) === 'managed_file') {
    // Prevent non-default extensions from being added.
    $default_extensions = \Drupal::configFactory()->getEditable('webform.settings')->get('file.default_managed_file_extensions');
    $default_extensions_array = explode(' ', $default_extensions);
    $current_extensions = explode(' ', trim($form_state->getValue(
      [
        'properties',
        'file_extensions',
      ]
    )));
    foreach ($current_extensions as $extension) {
      if (!in_array($extension, $default_extensions_array)) {
        $form_state->setErrorByName('properties[file_extensions]',
          t('File extension, <em>@extension</em>, is not allowed. <em>Allowed extensions (@default_extensions)</em>',
            [
              '@extension' => $extension,
              '@default_extensions' => $default_extensions,
            ]
          )
        );
      }
    }
  }
}

/**
 * Implements hook_webform_element_alter().
 */
function sitenow_webform_element_alter(array &$element, FormStateInterface $form_state, array $context) {
  if (isset($element['#webform_key'])) {
    // Pass query string parameters allowed for pre-populating webforms via
    // drupalSettings to javascript and attach a script to parse them.
    if (isset($element['#prepopulate'])) {
      $webform_prepopulate_query_keys = [
        'fbclid',
        'gclid',
        'utm_campaign',
        'utm_source',
        'utm_medium',
        'utm_content',
      ];
      if (in_array($element['#webform_key'], $webform_prepopulate_query_keys)) {
        $element['#attributes']['prepopulate'] = 'true';
        $element['#attached']['drupalSettings']['sitenow']['webformPrepopulateQueryKeys'] = $webform_prepopulate_query_keys;
        $element['#attached']['library'][] = 'sitenow/get_clickid';
      }
    }
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

      if ($nrd_limit = $config->get('third_party_settings.node_revision_delete.amount.settings.amount')) {
        \Drupal::messenger()->addWarning(t('There is a @limit revision limit for this content type. The oldest revisions in excess of @limit are deleted during system background processes.', [
          '@limit' => $nrd_limit,
        ]));
      }
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sitenow_form_system_site_information_settings_alter(&$form, FormStateInterface $form_state, $form_id) {
  $menus = Menu::loadMultiple();
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

    if (array_key_exists($social_media_menu, $menus)) {
      $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $social_media_menu])->toString();
      $form['uiowa_footer_menus']['uiowa_footer_social_media_menu_help'] = [
        '#type' => 'item',
        '#markup' => t('Links in the social media section are managed via the <a href="@menu_link">@menu_name menu</a>.', [
          '@menu_link' => $menu_link,
          '@menu_name' => $menus[$social_media_menu]->label(),
        ]),
      ];
    }

    if (array_key_exists($custom_menu, $menus)) {
      $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $custom_menu])->toString();
      $form['uiowa_footer_menus']['uiowa_footer_custom_menu_help'] = [
        '#type' => 'item',
        '#markup' => t('Links in the left column are managed via the <a href="@menu_link">@menu_name menu</a>.', [
          '@menu_link' => $menu_link,
          '@menu_name' => $menus[$custom_menu]->label(),
        ]),
      ];
    }

    if (array_key_exists($custom_menu_2, $menus)) {
      $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $custom_menu_2])->toString();
      $form['uiowa_footer_menus']['uiowa_footer_custom_menu_2_help'] = [
        '#type' => 'item',
        '#markup' => t('Links in the middle column are managed via the <a href="@menu_link">@menu_name menu</a>.', [
          '@menu_link' => $menu_link,
          '@menu_name' => $menus[$custom_menu_2]->label(),
        ]),
      ];
    }

    if (array_key_exists($custom_menu_3, $menus)) {
      $menu_link = Url::fromRoute('entity.menu.edit_form', ['menu' => $custom_menu_3])->toString();
      $form['uiowa_footer_menus']['uiowa_footer_custom_menu_3_help'] = [
        '#type' => 'item',
        '#markup' => t('Links in the right column are managed via the <a href="@menu_link">@menu_name menu</a>.', [
          '@menu_link' => $menu_link,
          '@menu_name' => $menus[$custom_menu_3]->label(),
        ]),
      ];
    }

    // Hide the site slogan field, as it's not used in uids_base theme.
    $form['site_information']['site_slogan']['#access'] = FALSE;
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
 * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
 *   The field definition.
 * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
 *   The entity being created if applicable.
 * @param bool $cacheable
 *   Boolean indicating if the results are cacheable.
 *
 * @return array
 *   An array of possible key and value options.
 *
 * @see options_allowed_values()
 */
function publish_options_allowed_values(FieldStorageDefinitionInterface $definition, FieldableEntityInterface $entity = NULL, bool &$cacheable = TRUE): array {
  $options = [
    'title_hidden' => 'Visually hide title',
    'no_sidebars' => 'Remove sidebar regions',
  ];

  if (!is_null($entity)) {
    $bundle = $entity->getEntityTypeId();

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
    $node = ($node ?? \Drupal::routeMatch()->getParameter('node_preview'));
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
    $node = $variables['node'];
    // Get moderation state of node.
    $revision_id = $node->getRevisionId();
    if ($revision_id) {
      $revision = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadRevision($revision_id);
      if ($revision instanceof NodeInterface) {
        $moderation_state = $revision->get('moderation_state')->getString();
        $status = $revision->get('status')->value;
        if ((int) $status === 0) {
          if ($moderation_state) {
            $pre_vowel = (in_array($moderation_state[0], [
              'a',
              'e',
              'i',
              'o',
              'u',
            ]) ? 'n' : '');
            $state = $moderation_state;
          }
          else {
            $pre_vowel = 'n';
            $state = 'unpublished';
          }
          $warning_text = t('This content is currently in a@pre_vowel @state state.', [
            '@pre_vowel' => $pre_vowel,
            '@state' => $state,
          ]);

          switch ($variables['view_mode']) {
            case 'teaser':
              $variables['content']['unpublished'] = [
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
}

/**
 * Implements hook_form_BASE_FORM_ID_alter() for menu_link_content_form.
 *
 * @throws \Drupal\Core\TypedData\Exception\MissingDataException
 */
function sitenow_form_menu_link_content_form_alter(array &$form, FormStateInterface $form_state, $form_id) {
  $form_object = $form_state->getFormObject();
  if ($form_object instanceof MenuLinkContentForm) {
    $menu_link = $form_object->getEntity();
    if ($menu_link instanceof MenuLinkContent) {
      $link = $menu_link->link;
      if ($link instanceof FieldItemList) {
        /** @var \Drupal\Core\Field\FieldItemList $first_item */
        $first_item = $link->first();
        $menu_link_options = $first_item->get('options')->getValue() ?: [];
        $menu = $menu_link->getMenuName();
        if ($menu === 'social') {
          $option = [
            'theme' => 'default',
            'iconSource' => [
              [
                "key" => "fa6-brands",
                "prefix" => "fa-brands fa-",
                "url" => "https://raw.githubusercontent.com/iconify/icon-sets/master/json/fa6-brands.json",
              ],
              [
                "key" => "fa6-regular",
                "prefix" => "fa-regular fa-",
                "url" => "https://raw.githubusercontent.com/iconify/icon-sets/master/json/fa6-regular.json",
              ],
              [
                "key" => "fa6-solid",
                "prefix" => "fa-solid fa-",
                "url" => "https://raw.githubusercontent.com/iconify/icon-sets/master/json/fa6-solid.json",
              ],
            ],
            'closeOnSelect' => TRUE,
            'i18n' => [
              'input:placeholder' => t('Search icon…'),
              'text:title' => t('Select icon'),
              'text:empty' => t('No results found…'),
              'btn:save' => t('Save'),
            ],
          ];

          $form['fa_icon'] = [
            '#type' => 'textfield',
            '#title' => t('FontAwesome Icon'),
            '#default_value' => !empty($menu_link_options['fa_icon']) ? $menu_link_options['fa_icon'] : '',
            '#attributes' => [
              'data-option' => json_encode($option),
              'data-theme' => 'default',
              'class' => [
                'fontawesomeIconPickerVanillaIconPicker',
              ],
            ],
            '#description' => t('Pick an icon to represent this link by clicking on this field. To see a list of available icons and their class names, <a href="https://fontawesome.com/icons?d=gallery&m=free">visit the FontAwesome website</a>.'),
            '#attached' => [
              'library' => [
                'sitenow/vanilla-icon-picker',
              ],
            ],
          ];

          $form['actions']['submit']['#submit'][] = 'sitenow_form_menu_link_content_form_submit';
        }
      }
    }
  }
}

/**
 * Custom validation function for sitenow_form_menu_link_content_form_alter.
 *
 * @throws \Drupal\Core\TypedData\Exception\MissingDataException
 */
function sitenow_form_menu_link_content_form_submit(array &$form, FormStateInterface $form_state) {
  $icon_field = $form_state->getValue('fa_icon');

  $options = [
    'fa_icon' => !empty($icon_field) ? Html::escape($icon_field) : '',
  ];
  $form_object = $form_state->getFormObject();
  if ($form_object instanceof MenuLinkContentForm) {
    $menu_link = $form_object->getEntity();
    if ($menu_link instanceof MenuLinkContent) {
      $link = $menu_link->link;
      if ($link instanceof FieldItemList) {
        /** @var \Drupal\Core\Field\FieldItemList $first_item */
        $first_item = $link->first();
        $menu_link_options = $first_item->get('options')->getValue();

        $merged = array_merge($menu_link_options, $options);

        $first_item->set('options', $merged);
        $menu_link->save();
      }
    }
  }
}

/**
 * Implements hook_link_alter().
 */
function sitenow_link_alter(&$variables) {
  if (!empty($variables['options']['fa_icon'])) {
    $variables['options']['attributes']['class'][] = 'fa-icon';

    $variables['text'] = t('<span role="presentation" class="fa @icon" aria-hidden="true"></span> <span class="menu-link-title">@title</span>', [
      '@icon' => $variables['options']['fa_icon'],
      '@title' => $variables['text'],
    ]);
  }
}

/**
 * Implements hook_preprocess_HOOK().
 */
function sitenow_preprocess_field(&$variables) {
  switch ($variables['element']['#field_name']) {
    case 'title':
      if ($variables['element']['#view_mode'] === 'teaser') {
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

  if ($admin_context->isAdminRoute() && $admin_theme === 'claro') {
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
 * Determine the version of SiteNow based on what config is active.
 *
 * @todo Return additional information like if any other splits are active that might impact functionality.
 * See https://github.com/uiowa/uiowa/issues/5021
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
    if ($node instanceof NodeInterface) {
      /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $layout */
      $layout = $node->get('layout_builder__layout');

      // Loop through our sections and make sure that
      // any inline blocks have proper usage set.
      foreach ($layout->getSections() as $section) {
        // Pull out individual components.
        foreach ($section->getComponents() as $component) {
          // Grab the associated block's revision id.
          $plugin = $component->getPlugin();
          if ($plugin instanceof InlineBlock) {
            $config = $plugin->getConfiguration();
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
  }
}

/**
 * Set dynamic allowed values for the alignment field.
 *
 * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
 *   The field definition.
 * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
 *   The entity being created if applicable.
 * @param bool $cacheable
 *   Boolean indicating if the results are cacheable.
 *
 * @return array
 *   An array of possible key and value options.
 *
 * @see options_allowed_values()
 */
function featured_image_size_values(FieldStorageDefinitionInterface $definition, FieldableEntityInterface $entity = NULL, bool &$cacheable = TRUE): array {
  $options = [
    'do_not_display' => 'Do not display',
    'small' => 'Small',
    'medium' => 'Medium',
    'large' => 'Large',
  ];

  return $options;
}
