<?php

namespace Drupal\layout_builder_custom;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Form\ConfigureBlockFormBase;

/**
 * Handles form alterations for the uiowa_banner block.
 */
class BannerBlockFormHandler {

  /**
   * Alters the banner block form.
   *
   * Weights:
   * -20: Banner heading (replaces admin_label)
   * -10: Headline group
   * 0: Background group
   * 50: Gradient options
   * 61: Excerpt group
   * 70: Button group
   * 94: Layout group heading
   * 97: Layout settings
   * 102: Style options
   * 200: Unique ID
   * 210: Actions (submit buttons).
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_id
   *   The form ID.
   */
  public static function formAlter(array &$form, FormStateInterface $form_state, $form_id) {
    // Attach library.
    $form['#attached']['library'][] = 'layout_builder_custom/banner-block-form';

    /*
     * Create all form groups and containers.
     */

    // Block heading.
    $form['admin_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $form['settings']['admin_label']['#plain_text'],
      '#weight' => $form['settings']['admin_label']['#weight'],
      '#attributes' => ['class' => ['heading-a']],
    ];

    // Hide admin label in favor of custom heading.
    unset($form['settings']['admin_label']);

    // Classes we want to apply to all containers.
    $container_classes = [
      'off-canvas-background',
      'padding--inline--md',
      'padding--block-start--md',
      'padding--block-end--md',
      'margin--block-start--md',
    ];

    // Headline group.
    $form['headline_group'] = [
      '#type' => 'container',
      '#weight' => -10,
      '#attributes' => [
        'class' => $container_classes,
      ],
    ];

    $form['headline_group']['headline_group_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => t('Headline'),
      '#attributes' => ['class' => ['heading-a']],
    ];

    // Background group - add heading inside block_form container.
    if (isset($form['settings']['block_form'])) {
      $form['settings']['block_form']['background_group_heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => t('Background'),
        '#weight' => 0,
        '#attributes' => ['class' => ['heading-a']],
        '#prefix' => '<div class="off-canvas-background padding--inline--md padding--block-start--md padding--block-end--md margin--block-start--md">',
      ];
    }

    // Gradient options details element.
    $form['gradient_options'] = [
      '#type' => 'details',
      '#title' => t('Overlay options'),
      '#weight' => 50,
      '#open' => FALSE,
      '#attributes' => [
        'class' => [
          'off-canvas-form-group__collapsible',
          'off-canvas-form-group__collapsible--overlay',
        ],
      ],
      '#suffix' => '</div>',
    ];

    // Add adjust gradient checkbox.
    self::addGradientMidpointCheckbox($form, $form_state);

    // Excerpt group.
    $form['excerpt_group'] = [
      '#type' => 'container',
      '#weight' => 61,
      '#attributes' => [
        'class' => $container_classes,
      ],
    ];

    $form['excerpt_group']['excerpt_group_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => t('Excerpt'),
      '#attributes' => ['class' => ['heading-a']],
    ];

    // Button group.
    $form['button_group'] = [
      '#type' => 'container',
      '#weight' => 70,
      '#attributes' => [
        'class' => $container_classes,
      ],
    ];

    $form['button_group']['button_group_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#weight' => -70,
      '#value' => t('Buttons'),
      '#attributes' => ['class' => ['heading-a']],
    ];

    // Layout group heading.
    $form['layout_group_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => t('Layout'),
      '#weight' => 94,
      '#attributes' => ['class' => ['heading-a']],
      '#prefix' => '<div class="off-canvas-background padding--inline--md padding--block-start--md margin--block-start--md">',
    ];

    // Layout settings details element.
    $form['layout_settings'] = [
      '#type' => 'details',
      '#title' => t('<span class="element-invisible">Layout</span> Options'),
      '#weight' => 97,
      '#attributes' => ['class' => ['off-canvas-form-group__collapsible']],
      '#open' => FALSE,
      '#suffix' => '</div>',
    ];

    // Styles group heading.
    $form['style_options_group_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => t('Styles'),
      '#weight' => 102,
      '#attributes' => ['class' => ['heading-a']],
      '#prefix' => '<div class="off-canvas-background padding--inline--md padding--block-start--md margin--block-start--md margin--block-end--md">',
    ];

    // Style options details element.
    $form['style_options'] = [
      '#type' => 'details',
      '#title' => t('<span class="element-invisible">Style</span> Options'),
      '#attributes' => ['class' => ['off-canvas-form-group__collapsible']],
      '#weight' => 102,
      '#open' => FALSE,
      '#suffix' => '</div>',
    ];

    /*
     * Configure all field duplications.
     */

    // Duplicate gradient fields into gradient options container.
    self::createDuplicateField($form, 'layout_builder_style_media_overlay', 'gradient_options');
    self::createDuplicateField($form, 'layout_builder_style_banner_gradient', 'gradient_options');
    self::createDuplicateField($form, 'layout_builder_style_banner_gradient_midpoint', 'gradient_options');

    // Duplicate layout fields into layout settings container.
    self::createDuplicateField($form, 'layout_builder_style_container', 'layout_settings');
    self::createDuplicateField($form, 'layout_builder_style_banner_height', 'layout_settings');

    // Duplicate style fields into style options container.
    self::createDuplicateField($form, 'layout_builder_style_margin', 'style_options');
    self::createDuplicateField($form, 'layout_builder_style_default', 'style_options');

    // Duplicate headline fields into headline group.
    self::createDuplicateField($form, 'layout_builder_style_headline_type', 'headline_group');
    self::createDuplicateField($form, 'layout_builder_style_headline_size', 'headline_group');

    // Duplicate button fields into button group.
    self::createDuplicateField($form, 'layout_builder_style_button_style', 'button_group');
    self::createDuplicateField($form, 'layout_builder_style_button_font', 'button_group');

    /*
     * Set all visibility states.
     */

    // Gradient options visible when background type is media.
    $form['gradient_options']['#states'] = [
      'visible' => [
        ':input[name="settings[block_form][background_type]"]' => ['value' => 'media'],
      ],
    ];

    // Background style field visible when background type is color-pattern.
    if (isset($form['layout_builder_style_background'])) {
      $form['layout_builder_style_background']['#states'] = [
        'visible' => [
          ':input[name="settings[block_form][background_type]"]' => ['value' => 'color-pattern'],
        ],
      ];
    }

    /*
     * Set weights and final adjustments.
     */

    // Set weights for headline fields.
    if (isset($form['layout_builder_style_headline_type'])) {
      $form['layout_builder_style_headline_type']['#weight'] = 60;
    }

    if (isset($form['layout_builder_style_headline_size'])) {
      $form['layout_builder_style_headline_size']['#weight'] = 60;
    }

    // Set weight for background style field.
    if (isset($form['layout_builder_style_background'])) {
      $form['layout_builder_style_background']['#weight'] = -50;
    }

    // Set weights for button fields.
    if (isset($form['layout_builder_style_button_style'])) {
      $form['layout_builder_style_button_style']['#weight'] = 71;
    }

    if (isset($form['layout_builder_style_button_font'])) {
      $form['layout_builder_style_button_font']['#weight'] = 72;
    }

    // Configure gradient option duplicate fields.
    if (isset($form['gradient_options']['layout_builder_style_media_overlay_duplicate'])) {
      $form['gradient_options']['layout_builder_style_media_overlay_duplicate']['#weight'] = 1;
      $form['gradient_options']['layout_builder_style_media_overlay_duplicate']['#empty_option'] = t('No gradient (default)');
    }

    if (isset($form['gradient_options']['layout_builder_style_banner_gradient_duplicate'])) {
      $form['gradient_options']['layout_builder_style_banner_gradient_duplicate']['#weight'] = 2;
      $form['gradient_options']['layout_builder_style_banner_gradient_duplicate']['#title_display'] = 'invisible';
    }

    if (isset($form['gradient_options']['layout_builder_style_adjust_gradient_midpoint_duplicate'])) {
      $form['gradient_options']['layout_builder_style_adjust_gradient_midpoint_duplicate']['#weight'] = 3;
    }

    // Handle alignment and style field radio defaults.
    $style_fields = [
      'horizontal_alignment' => [
        'weight' => 95,
        'field_path' => 'layout_builder_style_horizontal_alignment',
      ],
      'vertical_alignment' => [
        'weight' => 96,
        'field_path' => 'layout_builder_style_vertical_alignment',
      ],
      'headline_type' => [
        'weight' => NULL,
        'field_path' => 'headline_group/layout_builder_style_headline_type_duplicate',
      ],
      'button_style' => [
        'weight' => NULL,
        'field_path' => 'button_group/layout_builder_style_button_style_duplicate',
      ],
    ];

    $form_object = $form_state->getFormObject();
    if ($form_object instanceof ConfigureBlockFormBase) {
      $component = $form_object->getCurrentComponent();
      $config = $component->toArray();
      $styles = $config['additional']['layout_builder_styles_style'] ?? [];
      $extra_settings = LayoutBuilderStylesHelper::getExtraSettings();

      foreach ($style_fields as $style_type => $field_config) {
        // Get field reference.
        $field_path = explode('/', $field_config['field_path']);
        $field_reference = &$form;
        foreach ($field_path as $path_part) {
          if (isset($field_reference[$path_part])) {
            $field_reference = &$field_reference[$path_part];
          }
          else {
            $field_reference = NULL;
            break;
          }
        }

        if ($field_reference !== NULL) {
          // Set weight if specified.
          if ($field_config['weight'] !== NULL) {
            $field_reference['#weight'] = $field_config['weight'];
          }

          // Check if style is already set.
          $has_style = FALSE;
          foreach ($styles as $style) {
            if (str_starts_with($style, "{$style_type}_")) {
              $has_style = TRUE;
              break;
            }
          }

          // Set default if no style is set.
          if (!$has_style && isset($extra_settings[$style_type]['default'])) {
            $field_reference['#default_value'] = $extra_settings[$style_type]['default'];
          }
        }
      }
    }

    // Move unique_id to the bottom.
    $form['unique_id']['#weight'] = 200;

    // Make sure the actions (buttons) come after everything.
    if (isset($form['actions'])) {
      $form['actions']['#weight'] = 210;
    }
  }

  /**
   * Creates a duplicate field in a container and hides the original.
   *
   * @param array $form
   *   The form array.
   * @param string $original_field_name
   *   The name of the original field.
   * @param string $container_name
   *   The name of the container to place the duplicate in.
   */
  private static function createDuplicateField(array &$form, $original_field_name, $container_name) {
    $duplicate_field_name = $original_field_name . '_duplicate';

    if (isset($form[$original_field_name])) {
      $form[$container_name][$duplicate_field_name] = $form[$original_field_name];
      $form[$container_name][$duplicate_field_name]['#parents'] = [$duplicate_field_name];
      // Hide the original field.
      $form[$original_field_name]['#access'] = FALSE;
    }
  }

  /**
   * Syncs duplicate field values back to their original fields.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $field_names
   *   Array of field names to sync (original field name as key).
   */
  private static function syncDuplicateFields(FormStateInterface $form_state, array $field_names) {
    foreach ($field_names as $original_field) {
      $duplicate_field = $original_field . '_duplicate';
      $duplicate_value = $form_state->getValue($duplicate_field);
      if ($duplicate_value !== NULL) {
        $form_state->setValue($original_field, $duplicate_value);
      }
    }
  }

  /**
   * Validates the banner block form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateForm(array &$form, FormStateInterface $form_state) {
    // Sync duplicated fields to original fields.
    $fields_to_sync = [
      'layout_builder_style_container',
      'layout_builder_style_banner_height',
      'layout_builder_style_margin',
      'layout_builder_style_default',
      'layout_builder_style_media_overlay',
      'layout_builder_style_banner_gradient',
      'layout_builder_style_headline_type',
      'layout_builder_style_headline_size',
      'layout_builder_style_button_style',
      'layout_builder_style_button_font',
    ];

    self::syncDuplicateFields($form_state, $fields_to_sync);

    $link_set = FALSE;
    $link_text = FALSE;

    // First check if there is a link set.
    foreach ($form_state->getValue([
      'settings',
      'block_form',
      'field_uiowa_banner_link',
    ]) as $key => $link) {
      if ($key === 'add_more' || empty($link['uri'])) {
        // If there is no uri, then we don't care about anything else.
        continue;
      }
      else {
        $link_set = TRUE;
      }

      if (!empty($link['title'])) {
        $link_text = TRUE;
      }
    }
    // If there is a link and no text, check if there is a title.
    if ($link_set && !empty($form_state->getValue([
      'settings',
      'block_form',
      'field_uiowa_banner_title',
      0,
      'container',
      'text',
    ]))) {
      $link_text = TRUE;
    }

    // If there is a link and no text we can use, we have a problem.
    if ($link_set && !$link_text) {
      $form_state->setErrorByName('settings][block_form][field_uiowa_banner_link][0][title', t('Link text must be set if no title is present.'));
    }
  }

  /**
   * Handles submission of the banner block form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function submitForm(array &$form, FormStateInterface $form_state) {
    // Heading style radio selection.
    $heading_style = $form_state->getValue('heading_style');
    if ($heading_style) {
      $form_state->setValue('layout_builder_style_headline_type', $heading_style);
    }

    // Gradient midpoint checkbox.
    $adjust_gradient = $form_state->getValue('layout_builder_style_adjust_gradient_midpoint');

    // Save checkbox state as a third-party setting.
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof ConfigureBlockFormBase) {
      $component = $form_object->getCurrentComponent();
      $component->setThirdPartySetting('layout_builder_custom', 'adjust_gradient_midpoint', $adjust_gradient ? 1 : 0);
    }

    if (!$adjust_gradient) {
      // Clear the gradient midpoint value if checkbox is unchecked.
      $form_state->setValue('layout_builder_style_banner_gradient_midpoint', '');
      // Also clear the field_styles_gradient_midpoint value if checkbox is
      // unchecked.
      $form_state->setValue(['settings', 'block_form', 'field_styles_gradient_midpoint'], []);
    }

    $background_type = $form_state->getValue([
      'settings',
      'block_form',
      'background_type',
    ]);

    if ($background_type) {
      $form_media_selection = [
        'settings',
        'block_form',
        'field_uiowa_banner_image',
        'selection',
      ];

      if ($background_type === 'media') {
        // Check if media was selected.
        if ($form_state->getValue($form_media_selection)) {
          // Clear any background style when media is selected.
          $form_state->setValue('layout_builder_style_background', '');
        }
        else {
          // If no media selected, switch to color-pattern with black
          // background.
          $form_state->setValue('layout_builder_style_background', 'block_background_style_black');
          $background_type = 'color-pattern';
        }
        // @todo Add feedback for user that they didn't upload an image.
        // @todo Add validation to check whether image uploaded or not. See
        // https://github.com/uiowa/uiowa/issues/5012
      }
      elseif ($background_type === 'color-pattern') {
        // For color-pattern, clear any media reference if it was previously
        // set.
        $form_state->unsetValue($form_media_selection);
        // @todo Trigger file deletion if the media item is unused elsewhere. See
        // https://github.com/uiowa/uiowa/issues/5013
      }
    }

    // Save background_type as a third-party setting.
    if ($background_type) {
      $form_object = $form_state->getFormObject();
      if ($form_object instanceof ConfigureBlockFormBase) {
        $component = $form_object->getCurrentComponent();
        $component->setThirdPartySetting('layout_builder_custom', 'background_type', $background_type);
      }
    }
  }

  /**
   * Adds the gradient midpoint checkbox to the form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected static function addGradientMidpointCheckbox(array &$form, FormStateInterface $form_state) {
    // Get the saved checkbox state from third-party settings.
    $default_value = FALSE;
    $form_object = $form_state->getFormObject();

    if ($form_object instanceof ConfigureBlockFormBase) {
      /** @var \Drupal\layout_builder\SectionComponent $component */
      $component = $form_object->getCurrentComponent();

      // Check third-party settings for the checkbox state.
      $stored_checkbox_value = $component->getThirdPartySetting('layout_builder_custom', 'adjust_gradient_midpoint');
      if ($stored_checkbox_value !== NULL) {
        $default_value = (bool) $stored_checkbox_value;
      }
      else {
        // If no saved state exists, fallback to checking if gradient midpoint
        // field has a value.
        $has_midpoint_value = !empty($form['layout_builder_style_banner_gradient_midpoint']['#default_value']);
        $default_value = $has_midpoint_value;
      }
    }

    $form['gradient_options']['layout_builder_style_adjust_gradient_midpoint'] = [
      '#type' => 'checkbox',
      '#title' => t('Customize gradient midpoint'),
      '#default_value' => $default_value,
      '#weight' => 94,
      '#states' => [
        'visible' => [
          ':input[name="layout_builder_style_media_overlay_duplicate"]' => ['!value' => ''],
        ],
      ],
    ];

  }

  /**
   * Processes the banner block form element.
   *
   * @param array $element
   *   The current block element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The processed block element.
   */
  public static function processElement(array $element, FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ConfigureBlockFormBase) {
      return $element;
    }

    /** @var \Drupal\layout_builder\SectionComponent $component */
    $component = $form_object->getCurrentComponent();

    $complete_form = &$form_state->getCompleteForm();
    $plugin = $component->getPlugin();
    $configuration = [];
    if (method_exists($plugin, 'getConfiguration')) {
      // @phpstan-ignore-next-line
      $configuration = $plugin->getConfiguration();
    }

    /*
     * Assign fields to groups.
     */
    // Assign block fields to groups.
    if (isset($element['field_uiowa_banner_pre_title'])) {
      $element['field_uiowa_banner_pre_title']['#group'] = 'headline_group';
      $element['field_uiowa_banner_pre_title']['#weight'] = 61;
    }

    if (isset($element['field_uiowa_banner_title'])) {
      $element['field_uiowa_banner_title']['#group'] = 'headline_group';
      $element['field_uiowa_banner_title']['#weight'] = 62;
      // Update the label for the Heading sizes to remove Size label.
      if (isset($element['field_uiowa_banner_title']['widget'][0]['container']['size']['#title'])) {
        $element['field_uiowa_banner_title']['widget'][0]['container']['size']['#title'] = t('Level');
      }
    }

    if (isset($element['field_uiowa_banner_excerpt'])) {
      $element['field_uiowa_banner_excerpt']['#group'] = 'excerpt_group';
      $element['field_uiowa_banner_excerpt']['#weight'] = 62;
      $element['field_uiowa_banner_excerpt']['widget'][0]['#title_display'] = 'invisible';
    }

    if (isset($element['field_uiowa_banner_link'])) {
      $element['field_uiowa_banner_link']['#group'] = 'button_group';
      $element['field_uiowa_banner_link']['#weight'] = 70;
      $element['field_uiowa_banner_link']['#attributes']['class'][] = 'padding--inline--md';
    }

    /*
     * Configure background type.
     */

    // Determine default background type based on existing values.
    $default_bg_type = 'media';

    // Check third-party settings first, then fallback to layout builder styles.
    $stored_background_type = $component->getThirdPartySetting('layout_builder_custom', 'background_type');
    if (!empty($stored_background_type)) {
      $default_bg_type = $stored_background_type;
    }
    else {
      // Check if there's a background style set that indicates color-pattern.
      if (!empty($configuration['layout_builder_style_background'])) {
        // Any background style (including black) is color-pattern type.
        $default_bg_type = 'color-pattern';
      }
    }

    // Add radio buttons for background type selection.
    $element['background_type'] = [
      '#type' => 'radios',
      '#title' => t('Background type'),
      '#options' => [
        'media' => t('Image / Video'),
        'color-pattern' => t('Color / Pattern'),
      ],
      '#default_value' => $default_bg_type,
      '#weight' => 10,
    ];

    // Move layout_builder_style_background into block_form for proper
    // positioning.
    if (isset($complete_form['layout_builder_style_background'])) {
      $element['layout_builder_style_background'] = $complete_form['layout_builder_style_background'];
      $element['layout_builder_style_background']['#weight'] = 20;
      $element['layout_builder_style_background']['#tree'] = FALSE;
      $element['layout_builder_style_background']['#states'] = [
        'visible' => [
          ':input[name="settings[block_form][background_type]"]' => ['value' => 'color-pattern'],
        ],
      ];
      // Hide the original field.
      $complete_form['layout_builder_style_background']['#access'] = FALSE;
    }

    /*
     * Configure media fields.
     */
    $element['field_uiowa_banner_image'] = [
      '#states' => [
        'visible' => [
          ':input[name="settings[block_form][background_type]"]' => ['value' => 'media'],
        ],
      ],
      '#weight' => 30,
    ] + $element['field_uiowa_banner_image'];

    $element['field_uiowa_banner_autoplay'] = [
      '#states' => [
        'visible' => [
          ':input[name="settings[block_form][background_type]"]' => ['value' => 'media'],
        ],
      ],
      '#weight' => 40,
    ] + $element['field_uiowa_banner_autoplay'];

    unset($element['field_uiowa_banner_image']['widget']['#title']);
    unset($element['field_uiowa_banner_autoplay']['widget']['#title']);

    /*
     * Configure gradient fields.
     */
    $complete_form['layout_builder_style_media_overlay']['#states'] = [
      'visible' => [
        ':input[name="settings[block_form][background_type]"]' => ['value' => 'media'],
      ],
    ];

    $complete_form['layout_builder_style_banner_gradient']['#states'] = [
      'visible' => [
        ':input[name="settings[block_form][background_type]"]' => ['value' => 'media'],
      ],
    ];

    // Handle field_styles_gradient_midpoint field placement and behavior.
    if (isset($element['field_styles_gradient_midpoint'])) {
      // Remove the _none option from the original element.
      if (isset($element['field_styles_gradient_midpoint']['widget']['#options']['_none'])) {
        unset($element['field_styles_gradient_midpoint']['widget']['#options']['_none']);
      }

      // Move the field to gradient options if the container exists in form.
      $form = &$form_state->getCompleteForm();
      if (isset($form['gradient_options'])) {
        $form['gradient_options']['field_styles_gradient_midpoint'] = $element['field_styles_gradient_midpoint'];
        $form['gradient_options']['field_styles_gradient_midpoint']['#weight'] = 4;
        $form['gradient_options']['field_styles_gradient_midpoint']['#states'] = [
          'visible' => [
            ':input[name="layout_builder_style_adjust_gradient_midpoint_duplicate"]' => ['checked' => TRUE],
            ':input[name="layout_builder_style_media_overlay_duplicate"]' => ['!value' => ''],
          ],
        ];

        // Remove the _none option from the moved field as well.
        if (isset($form['gradient_options']['field_styles_gradient_midpoint']['widget']['#options']['_none'])) {
          unset($form['gradient_options']['field_styles_gradient_midpoint']['widget']['#options']['_none']);
        }

        // Visually hide the fieldset legend span.
        $form['gradient_options']['field_styles_gradient_midpoint']['widget']['#title_display'] = 'invisible';

        // Hide the original field.
        $element['field_styles_gradient_midpoint']['#access'] = FALSE;
      }
    }

    /*
     * Configure link fields.
     */
    if (isset($element['field_uiowa_banner_link'])) {
      $element['field_uiowa_banner_link']['#weight'] = -69;
    }

    // Check the max_delta to see how many banner links have been added
    // and unset the add more button if we've reached the third link.
    if (isset($element['field_uiowa_banner_link']) &&
      $element['field_uiowa_banner_link']['widget']['#max_delta'] >= 2) {
      unset($element['field_uiowa_banner_link']['widget']['add_more']);
      // If we're editing a banner with 3 existing links
      // we also need to unset the fourth pre-added link field.
      if (isset($element['field_uiowa_banner_link']['widget'][3])) {
        unset($element['field_uiowa_banner_link']['widget'][3]);
      }
    }

    /*
     * Misc. field configuration.
     */
    if (isset($element['langcode'])) {
      $element['langcode']['#weight'] = 100;
    }

    return $element;
  }

}
