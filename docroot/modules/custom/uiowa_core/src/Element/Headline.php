<?php

namespace Drupal\uiowa_core\Element;

use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form input element for entering a headline.
 *
 * This implementation is incomplete and not in use. It is
 * provided as a jumping off point for future work.
 *
 * Example usage:
 * @code
 * $form['headline'] = array(
 *   '#type' => 'headline',
 *   '#title' => $this->t('Headline'),
 * );
 * @end
 *
 * @see \Drupal\Core\Render\Element\Render\FormElement
 *
 * @FormElement("headline")
 *
 * @todo Finish this plugin class.
 */
class Headline extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#theme' => 'headline_input',
      '#label' => $this->t('Headline'),
      '#description' => $this->t('Set a headline and choose the header size.'),
      '#default_value' => NULL,
      // @todo Determine whether a #process implementation is necessary.
      '#process' => [
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderHeadline'],
        // @todo Determine whether this #pre_render is necessary.
        [$class, 'preRenderGroup'],
      ],
      '#heading_size' => '',
      '#theme_wrappers' => ['container'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderHeadline(&$element) {

    $element['#tree'] = TRUE;

    // Headline text.
    $element['text'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'name' => $element['#name'] . '_text',
      ],
      '#required' => $element['#required'],
      '#default_value' => NULL,
    ];

    // Headline size.
    $element['size'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'name' => $element['#name'] . '_size',
      ],
      '#required' => $element['#required'],
      '#default_value' => NULL,
    ];

    return $element;
  }

}
