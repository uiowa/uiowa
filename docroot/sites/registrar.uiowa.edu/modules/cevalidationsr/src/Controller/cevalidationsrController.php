<?php

namespace Drupal\cevalidationsr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;

/**
 *
 */
class cevalidationsrController extends ControllerBase {

  /**
   *
   */
  public function content() {
    $myForm = $this->formBuilder()->getForm('Drupal\cevalidationsr\Form\cevalidationsr');
    $renderer = \Drupal::service('renderer');
    $myFormHtml = $renderer->render($myForm);

    return [
      '#markup' => Markup::create("{$myFormHtml}"),
    ];
  }

}
