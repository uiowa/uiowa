<?php

namespace Drupal\views_tree\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\HtmlList;
use Drupal\views_tree\TreeStyleTrait;

/**
 * Style plugin to render each item as hierarchy.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "tree",
 *   title = @Translation("Tree (list)"),
 *   help = @Translation("Display the results as a nested tree"),
 *   theme = "views_tree",
 *   display_types = {"normal"}
 * )
 */
class Tree extends HtmlList {

  use TreeStyleTrait;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $this->defineCommonOptions($options);

    $options['class'] = ['default' => ''];
    $options['wrapper_class'] = ['default' => 'item-list'];
    $options['collapsible_tree'] = ['default' => 0];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $this->getCommonOptionsForm($form, $form_state);

    $events = ['click' => $this->t('On Click'), 'mouseover' => $this->t('On Mouseover')];

    $form['type']['#description'] = $this->t('Whether to use an ordered or unordered list for the retrieved items. Most use cases will prefer Unordered.');

    // Unused by the views tree list style at this time.
    unset($form['wrapper_class']);
    unset($form['class']);

    $form['collapsible_tree'] = [
      '#type' => 'radios',
      '#title' => $this->t('Collapsible view'),
      '#default_value' => $this->options['collapsible_tree'],
      '#options' => [
        0 => $this->t('Off'),
        'expanded' => $this->t('Expanded'),
        'collapsed' => $this->t('Collapsed'),
      ],
    ];
  }

}
