<?php

namespace Drupal\uiowa_areas_of_study\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormState;
use Drupal\views\Views;

/**
 * Provides the Areas of Study Search block.
 *
 * @Block(
 *   id = "uiowa_areas_of_study_search",
 *   admin_label = @Translation("Areas of Study Search"),
 *   category = @Translation("Site custom")
 * )
 */
class AreasOfStudySearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Using a process described here:
    // https://drupal.stackexchange.com/a/274383/6066
    $form = [];
    $view_id = 'areas_of_study';
    $display_id = 'areas_of_study';
    $view = Views::getView($view_id);
    if ($view) {
      $view->setDisplay($display_id);
      $view->initHandlers();
      $form_state = (new FormState())
        ->setStorage([
          'view' => $view,
          'display' => &$view->display_handler->display,
          'rerender' => TRUE,
        ])
        ->setMethod('get')
        ->setAlwaysProcess()
        ->disableRedirect();
      $form_state->set('rerender', NULL);
      $form = \Drupal::formBuilder()
        ->buildForm('\Drupal\views\Form\ViewsExposedForm', $form_state);
    }
    $variables['content']['form'] = $form;

    $build['content'] = $form;

    return $build;
  }
}
