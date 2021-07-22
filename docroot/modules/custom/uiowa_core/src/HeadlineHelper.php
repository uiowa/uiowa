<?php

namespace Drupal\uiowa_core;

/**
 * A class to help with the rendering headlines.
 */
class HeadlineHelper {

  /**
   * Get a list of valid heading options as element => human-readable pairs.
   */
  public static function getHeadingOptions() {
    return [
      'h2' => 'Heading 2',
      'h3' => 'Heading 3',
      'h4' => 'Heading 4',
      'h5' => 'Heading 5',
    ];
  }

  /**
   * Size up a heading based on the argument.
   */
  public static function getHeadingSizeUp($size) {
    $options = [
      'h2' => 'h3',
      'h3' => 'h4',
      'h4' => 'h5',
      'h5' => 'h6',
      'h6' => 'h6',
    ];

    return $options[$size];
  }

  /**
   * Get a list of valid heading styles as machine-name => class pairs.
   */
  public static function getStyles() {
    return [
      'default' => 'headline block__headline',
      'headline_bold_serif' => 'headline bold-headline bold-headline--serif block__headline',
      'headline_bold_serif_underline' => 'headline bold-headline bold-headline--serif bold-headline--underline block__headline',
    ];
  }

  /**
   * Provide the render array structure for a headline element.
   *
   * @todo Investigate creating a custom render element for this.
   */
  public static function getElement(array $defaults, $has_children = TRUE) {
    $heading_size_options = self::getHeadingOptions();

    $element['container'] = [
      '#type' => 'container',
      '#title' => 'Headline',
      '#attributes' => [
        'class' => 'uiowa-headline--container',
      ],
    ];

    $element['container']['headline'] = [
      '#type' => 'textfield',
      '#title' => t('Headline'),
      '#description' => $defaults['description'],
      '#size' => 80,
      '#default_value' => $defaults['headline'],
      '#attributes' => [
        'id' => 'uiowa-headline-field',
      ],
    ];

    $element['container']['hide_headline'] = [
      '#type' => 'checkbox',
      '#title' => t('Visually hide title'),
      '#default_value' => $defaults['hide_headline'],
      '#attributes' => [
        'id' => 'uiowa-headline-hide-headline-field',
      ],
      '#states' => [
        'visible' => [
          ':input[id="uiowa-headline-field"]' => [
            'filled' => TRUE,
          ],
        ],
      ],
    ];

    $element['container']['heading_size'] = [
      '#type' => 'select',
      '#title' => t('Headline size'),
      '#options' => $heading_size_options,
      '#description' => t('The heading size for the block title.'),
      '#default_value' => $defaults['heading_size'],
      '#states' => [
        'visible' => [
          ':input[id="uiowa-headline-field"]' => [
            'filled' => TRUE,
          ],
        ],
      ],
    ];

    $element['container']['headline_style'] = [
      '#type' => 'select',
      '#title' => t('Headline style'),
      '#options' => [
        'default' => t('Default'),
        'headline_bold_serif' => t('Bold serif'),
        'headline_bold_serif_underline' => t('Bold serif, underlined'),
      ],
      '#default_value' => $defaults['headline_style'],
      '#states' => [
        'visible' => [
          ':input[id="uiowa-headline-field"]' => [
            'filled' => TRUE,
          ],
        ],
      ],
    ];

    if ($has_children) {
      $element['container']['heading_size']['#description'] .= ' Children headings will be set one level lower.';

      // Add an additional option for children headings.
      $heading_size_options['h6'] = 'Heading 6';

      $element['container']['child_heading_size'] = [
        '#type' => 'select',
        '#title' => t('Child content heading size'),
        '#options' => $heading_size_options,
        '#default_value' => $defaults['child_heading_size'],
        '#description' => t('The heading size for all children headings.'),
        '#states' => [
          'visible' => [
            ':input[id="uiowa-headline-field"]' => [
              'filled' => FALSE,
            ],
          ],
        ],
      ];
    }

    return $element;
  }

}
