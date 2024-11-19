<?php

namespace Drupal\sitenow_media_wysiwyg\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\media_library\Form\AddFormBase;

/**
 * Form for creating brand icon instances from within the media library.
 */
class BrandIconForm extends AddFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return $this->getBaseFormId() . '_brand_icon';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state): array {
    // Add a container to group the input elements for styling purposes.
    $form['container'] = [
      '#type' => 'container',
    ];

    return $form;
  }

  /**
   * Form submit callback that processes the value from the URL field.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function addButtonSubmit(array $form, FormStateInterface $form_state) {
    $this->processInputValues([$form_state->getValue('url')], $form, $form_state);
  }

}
