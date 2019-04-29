<?php

namespace Drupal\uiowa_events\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the event feed entity edit forms.
 */
class EventsInstanceForm extends ContentEntityForm {

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
      drupal_set_message($this->t('New event feed %label has been created.', $message_arguments));
      $this->logger('uiowa_events')->notice('Created new event feed %label', $logger_arguments);
    }
    else {
      drupal_set_message($this->t('The event feed %label has been updated.', $message_arguments));
      $this->logger('uiowa_events')->notice('Created new event feed %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.uievents.canonical', ['uievents' => $entity->id()]);
  }

}
