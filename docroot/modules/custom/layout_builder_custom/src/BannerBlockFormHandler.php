<?php

namespace Drupal\layout_builder_custom;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Form\ConfigureBlockFormBase;
use Drupal\layout_builder_styles\LayoutBuilderStyleInterface;

/**
 * Handles form alterations for the uiowa_banner block.
 */
class BannerBlockFormHandler {

  /**
   * Alters the banner block form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_id
   *   The form ID.
   */
  public static function formAlter(array &$form, FormStateInterface $form_state, $form_id) {
    // We provide a field that provides background options, so
    // this is hidden.
    if (isset($form['layout_builder_style_background'])) {
      $form['layout_builder_style_background']['#access'] = FALSE;
    }

    // Add adjust gradient checkbox.
    self::addGradientMidpointCheckbox($form, $form_state);

    // Set negative weight for entire block_form container to position at top.
    if (isset($form['settings']['block_form'])) {
      $form['settings']['block_form']['#weight'] = -55;

      // Background group - add heading inside block_form container.
      $form['settings']['block_form']['background_group_heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => t('Background'),
        '#weight' => 0,
        '#attributes' => ['class' => ['layout-builder-style-heading']],
      ];
    }

    // Create a details element for gradient options.
    $form['gradient_options'] = [
      '#type' => 'details',
      '#title' => t('Gradient options'),
      '#weight' => 90,
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="settings[block_form][background_type]"]' => ['value' => 'media'],
        ],
      ],
    ];

    // Duplicate gradient fields into gradient options container.
    self::createDuplicateField($form, 'layout_builder_style_media_overlay', 'gradient_options');
    self::createDuplicateField($form, 'layout_builder_style_banner_gradient', 'gradient_options');
    self::createDuplicateField($form, 'layout_builder_style_adjust_gradient_midpoint', 'gradient_options');
    self::createDuplicateField($form, 'layout_builder_style_banner_gradient_midpoint', 'gradient_options');

    // Set weights and special properties for gradient fields.
    if (isset($form['gradient_options']['layout_builder_style_media_overlay_duplicate'])) {
      $form['gradient_options']['layout_builder_style_media_overlay_duplicate']['#weight'] = 1;
      // Replace -none- text with no gradient label.
      $form['gradient_options']['layout_builder_style_media_overlay_duplicate']["#empty_option"] = t('No gradient (default)');
    }

    if (isset($form['gradient_options']['layout_builder_style_banner_gradient_duplicate'])) {
      $form['gradient_options']['layout_builder_style_banner_gradient_duplicate']['#weight'] = 2;
    }

    if (isset($form['gradient_options']['layout_builder_style_adjust_gradient_midpoint_duplicate'])) {
      $form['gradient_options']['layout_builder_style_adjust_gradient_midpoint_duplicate']['#weight'] = 3;
    }

    if (isset($form['gradient_options']['layout_builder_style_banner_gradient_midpoint_duplicate'])) {
      $form['gradient_options']['layout_builder_style_banner_gradient_midpoint_duplicate']['#weight'] = 4;
    }

    // Layout group.
    $form['layout_group_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => t('Layout'),
      '#weight' => 94,
      '#attributes' => ['class' => ['layout-builder-style-heading']],
    ];

    if (isset($form['layout_builder_style_horizontal_alignment'])) {
      $form['layout_builder_style_horizontal_alignment']['#type'] = 'radios';
      $form['layout_builder_style_horizontal_alignment']['#weight'] = 95;
    }

    if (isset($form['layout_builder_style_vertical_alignment'])) {
      $form['layout_builder_style_vertical_alignment']['#type'] = 'radios';
      $form['layout_builder_style_vertical_alignment']['#weight'] = 96;
    }

    // Create a details element for layout settings.
    $form['layout_settings'] = [
      '#type' => 'details',
      '#title' => t('Layout settings'),
      '#weight' => 97,
      '#open' => FALSE,
    ];

    // Duplicate fields into layout settings container.
    self::createDuplicateField($form, 'layout_builder_style_container', 'layout_settings');
    self::createDuplicateField($form, 'layout_builder_style_banner_height', 'layout_settings');

    // Headline styles - placed in block_form to appear after title field.
    if (isset($form['layout_builder_style_headline_type'])) {
      $form['layout_builder_style_headline_type']['#type'] = 'radios';
    }

    // Styles heading.
    $form['style_options_group_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => t('Styles'),
      '#weight' => 102,
      '#attributes' => ['class' => ['layout-builder-style-heading']],
    ];

    // Create a details element for style options.
    $form['style_options'] = [
      '#type' => 'details',
      '#title' => t('Style options'),
      '#weight' => 102,
      '#open' => FALSE,
    ];

    // Duplicate fields into style options container.
    self::createDuplicateField($form, 'layout_builder_style_button_style', 'style_options');
    self::createDuplicateField($form, 'layout_builder_style_button_font', 'style_options');
    self::createDuplicateField($form, 'layout_builder_style_margin', 'style_options');
    self::createDuplicateField($form, 'layout_builder_style_default', 'style_options');

    // Make sure the actions (buttons) come after everything.
    if (isset($form['actions'])) {
      $form['actions']['#weight'] = 200;
    }

    $form['#attached']['library'][] = 'layout_builder_custom/banner-block-form';
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
   * @param array $field_mappings
   *   Array of field names to sync (original field name as key).
   */
  private static function syncDuplicateFields(FormStateInterface $form_state, array $field_mappings) {
    foreach ($field_mappings as $original_field => $duplicate_field) {
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
    $field_mappings = [
      'layout_builder_style_container' => 'layout_builder_style_container_duplicate',
      'layout_builder_style_banner_height' => 'layout_builder_style_banner_height_duplicate',
      'layout_builder_style_button_style' => 'layout_builder_style_button_style_duplicate',
      'layout_builder_style_button_font' => 'layout_builder_style_button_font_duplicate',
      'layout_builder_style_margin' => 'layout_builder_style_margin_duplicate',
      'layout_builder_style_default' => 'layout_builder_style_default_duplicate',
      'layout_builder_style_media_overlay' => 'layout_builder_style_media_overlay_duplicate',
      'layout_builder_style_banner_gradient' => 'layout_builder_style_banner_gradient_duplicate',
      'layout_builder_style_adjust_gradient_midpoint' => 'layout_builder_style_adjust_gradient_midpoint_duplicate',
      'layout_builder_style_banner_gradient_midpoint' => 'layout_builder_style_banner_gradient_midpoint_duplicate',
    ];

    self::syncDuplicateFields($form_state, $field_mappings);

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
    if (!$adjust_gradient) {
      // Clear the gradient midpoint value if checkbox is unchecked.
      $form_state->setValue('layout_builder_style_banner_gradient_midpoint', '');
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

      $background_style = 'block_background_style_black';

      if ($background_type === 'media') {
        if ($form_state->getValue($form_media_selection)) {
          $background_style = 'image';
        }
        // @todo Add feedback for user that they didn't upload an image.
        // @todo Add validation to check whether image uploaded or not. See
        // https://github.com/uiowa/uiowa/issues/5012
      }
      elseif ($background_type === 'color-pattern') {
        // If a non-image background was selected, remove the reference.
        // @todo Trigger file deletion if the media item is unused elsewhere. See
        // https://github.com/uiowa/uiowa/issues/5013
        $background_option = $form_state->getValue([
          'settings',
          'block_form',
          'background_options',
        ]);

        if ($background_option) {
          $background_style = $background_option;
          $form_state->unsetValue($form_media_selection);
        }
      }

      $form_state->setValue('layout_builder_style_background', $background_style);
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
    // Check if gradient midpoint field has a value.
    $has_midpoint_value = !empty($form['layout_builder_style_banner_gradient_midpoint']['#default_value']);

    $form['layout_builder_style_adjust_gradient_midpoint'] = [
      '#type' => 'checkbox',
      '#title' => t('Adjust gradient midpoint'),
      '#default_value' => $has_midpoint_value,
      '#weight' => 94,
      '#attributes' => [
        'class' => ['adjust-gradient-checkbox'],
      ],
    ];

    // Add states to show/hide gradient midpoint based on checkbox.
    if (isset($form['gradient_options']['layout_builder_style_banner_gradient_midpoint_duplicate'])) {
      $form['gradient_options']['layout_builder_style_banner_gradient_midpoint_duplicate']['#states'] = [
        'visible' => [
          ':input[name="layout_builder_style_adjust_gradient_midpoint_duplicate"]' => ['checked' => TRUE],
        ],
      ];

      $form['gradient_options']['layout_builder_style_banner_gradient_midpoint_duplicate']['#description'] = t('Override where the gradient is positioned on the image.');
    }

    // Only show checkbox when media overlay is selected.
    if (isset($form['gradient_options']['layout_builder_style_adjust_gradient_midpoint_duplicate'])) {
      $form['gradient_options']['layout_builder_style_adjust_gradient_midpoint_duplicate']['#states'] = [
        'visible' => [
          ':input[name="layout_builder_style_media_overlay_duplicate"]' => ['!value' => ''],
        ],
      ];
    }
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

    if (isset($element['field_uiowa_banner_title'])) {
      // Update the label for the Heading sizes to remove Size label.
      $element['field_uiowa_banner_title']['widget'][0]['container']['size']['#title'] =
        t('Heading');
    }
    // @todo Should this be scoped to a condition checking if layout_builder_styles is enabled? See
    //   https://github.com/uiowa/uiowa/issues/5038
    $all_styles = _layout_builder_styles_retrieve_by_type(LayoutBuilderStyleInterface::TYPE_COMPONENT);

    // @phpstan-ignore-next-line
    $selectedStyles = $component->get('layout_builder_styles_style');

    // Build color/pattern options from layout builder styles.
    $color_pattern_options = [
      'block_background_style_light' => 'White',
      'block_background_style_black' => 'Black',
      'block_background_style_gold' => 'Gold',
      'block_background_style_gray' => 'Gray',
    ];

    foreach ($all_styles as $style) {
      if ($style->getGroup() === 'background') {
        $restrictions = $style->getBlockRestrictions();
        if (empty($restrictions) || in_array('inline_block:uiowa_banner', $restrictions)) {
          $color_pattern_options[$style->id()] = $style->label();
        }
      }
    }

    // Determine default background type and value.
    $default_bg_type = 'media';
    $default_bg_value = 'block_background_style_light';

    if (is_array($selectedStyles)) {
      foreach ($selectedStyles as $selectedStyle) {
        if (array_key_exists($selectedStyle, $color_pattern_options)) {
          $default_bg_type = 'color-pattern';
          $default_bg_value = $selectedStyle;
          break;
        }
      }
    }

    // Add radio buttons for background type selection.
    $element['background_type'] = [
      '#type' => 'radios',
      '#title' => t('Background'),
      '#options' => [
        'media' => t('Image / Video'),
        'color-pattern' => t('Color / Pattern'),
      ],
      '#default_value' => $default_bg_type,
      '#weight' => 10,
    ];

    // Set explicit weights for all existing fields to control order.
    if (isset($element['field_uiowa_banner_pre_title'])) {
      $element['field_uiowa_banner_pre_title']['#weight'] = -100;
    }
    if (isset($element['field_uiowa_banner_title'])) {
      $element['field_uiowa_banner_title']['#weight'] = -90;
    }

    // Move headline style fields into block_form to appear after title.
    $complete_form = &$form_state->getCompleteForm();
    if (isset($complete_form['layout_builder_style_headline_type'])) {
      $element['layout_builder_style_headline_type'] = $complete_form['layout_builder_style_headline_type'];
      $element['layout_builder_style_headline_type']['#weight'] = -85;
      // Set #tree to FALSE so the value overrides the original in form state.
      $element['layout_builder_style_headline_type']['#tree'] = FALSE;
      // Hide the original field.
      $complete_form['layout_builder_style_headline_type']['#access'] = FALSE;
    }

    if (isset($complete_form['layout_builder_style_headline_size'])) {
      $element['layout_builder_style_headline_size'] = $complete_form['layout_builder_style_headline_size'];
      $element['layout_builder_style_headline_size']['#weight'] = -84;
      // Set #tree to FALSE so the value overrides the original in form state.
      $element['layout_builder_style_headline_size']['#tree'] = FALSE;
      // Hide the original field.
      $complete_form['layout_builder_style_headline_size']['#access'] = FALSE;
    }

    if (isset($element['field_uiowa_banner_excerpt'])) {
      $element['field_uiowa_banner_excerpt']['#weight'] = -80;
    }
    if (isset($element['field_uiowa_banner_link'])) {
      $element['field_uiowa_banner_link']['#weight'] = -70;
    }
    if (isset($element['langcode'])) {
      $element['langcode']['#weight'] = 100;
    }

    // Add select dropdown for color/pattern options.
    $element['background_options'] = [
      '#type' => 'select',
      '#title' => t('Background style'),
      '#options' => $color_pattern_options,
      '#default_value' => $default_bg_value,
      '#states' => [
        'visible' => [
          ':input[name="settings[block_form][background_type]"]' => [
            'value' => 'color-pattern',
          ],
        ],
      ],
      '#weight' => 20,
    ];

    $element['field_uiowa_banner_image'] = [
      '#states' => [
        'visible' => [
          ':input[name="settings[block_form][background_type]"]' => [
            'value' => 'media',
          ],
        ],
          // @todo Conditionally require media field when 'background_options'
          //   is set to 'image'. See
          //   https://github.com/uiowa/uiowa/issues/5039
      ],
      '#weight' => 30,
    ] + $element['field_uiowa_banner_image'];

    $element['field_uiowa_banner_autoplay'] = [
      '#states' => [
        'visible' => [
          ':input[name="settings[block_form][background_type]"]' => [
            'value' => 'media',
          ],
        ],
      ],
      '#weight' => 40,
    ] + $element['field_uiowa_banner_autoplay'];

    unset($element['field_uiowa_banner_image']['widget']['#title']);
    unset($element['field_uiowa_banner_autoplay']['widget']['#title']);

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

    $form_state->getCompleteForm()['layout_builder_style_media_overlay']['#states'] = [
      'visible' => [
        ':input[name="settings[block_form][background_type]"]' => [
          'value' => 'media',
        ],
      ],
    ];

    $form_state->getCompleteForm()['layout_builder_style_banner_gradient']['#states'] = [
      'visible' => [
        ':input[name="settings[block_form][background_type]"]' => [
          'value' => 'media',
        ],
        ':input[name="layout_builder_style_media_overlay"]' => [
          '!value' => '',
        ],
      ],
    ];

    return $element;
  }

}
