<?php

namespace Drupal\bit\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the bit entity edit forms.
 */
class BitForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New bit %label has been created.', $message_arguments));
      $this->logger('bit')->notice('Created new bit %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The bit %label has been updated.', $message_arguments));
      $this->logger('bit')->notice('Updated new bit %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.bit.canonical', ['bit' => $entity->id()]);
  }

}
