<?php

namespace Drupal\uiowa_core;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pushes a dataLayer event when a webform submission is confirmed.
 *
 * @see \Drupal\uiowa_core\EventSubscriber\WebformDataLayerSubscriber
 * @see uiowa_core_page_attachments()
 */
class WebformDataLayerHelper {

  /**
   * The dataLayer event name.
   */
  const EVENT = 'webform_confirmed';

  /**
   * The session key that holds queued events.
   */
  const SESSION_KEY = 'uiowa_core_webform_datalayer';

  /**
   * The webform actions that can complete a submission.
   */
  const COMPLETING_ACTIONS = ['submit', 'wizard_next', 'preview_next'];

  /**
   * Adds the submit handler to webform actions that can complete a submission.
   *
   * @param array $form
   *   The webform submission form.
   */
  public static function formAlter(array &$form): void {
    foreach (static::COMPLETING_ACTIONS as $action) {
      if (isset($form['actions'][$action]['#submit'])) {
        $form['actions'][$action]['#submit'][] = [static::class, 'submit'];
      }
    }
  }

  /**
   * Queues the dataLayer event for a newly completed submission.
   *
   * @param array $form
   *   The webform submission form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function submit(array &$form, FormStateInterface $form_state): void {
    if ($form_state->hasAnyErrors()) {
      return;
    }

    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    $submission = $form_state->getFormObject()->getEntity();

    // Unsaved covers webforms with results disabled.
    $new_states = [
      WebformSubmissionInterface::STATE_UNSAVED,
      WebformSubmissionInterface::STATE_COMPLETED,
      WebformSubmissionInterface::STATE_CONVERTED,
    ];

    if (!$submission->isCompleted() || !in_array($submission->getState(), $new_states, TRUE)) {
      return;
    }

    // The event can't follow a redirect off this site, so don't leave it
    // queued for the user's next visit.
    $request = \Drupal::request();
    $response = $form_state->getResponse();
    if ($response instanceof RedirectResponse && static::isOffSite($response->getTargetUrl(), $request)) {
      return;
    }

    $session = $request->getSession();
    $events = $session->get(static::SESSION_KEY, []);

    // Grab the webform_id to send.
    // Submitted values are deliberately left out to avoid sending PII.
    $events[] = [
      'event' => static::EVENT,
      'webform_id' => $submission->getWebform()->id(),
    ];

    $session->set(static::SESSION_KEY, $events);
  }

  /**
   * Removes and returns queued events.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   The queued dataLayer events.
   */
  public static function pop(Request $request): array {
    // Avoid starting a session for visitors who don't have one.
    if (!$request->hasPreviousSession()) {
      return [];
    }

    $session = $request->getSession();
    $events = $session->get(static::SESSION_KEY, []);

    if ($events) {
      $session->remove(static::SESSION_KEY);
    }

    return $events;
  }

  /**
   * Determines whether a URL points off this site.
   *
   * @param string $url
   *   The URL.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return bool
   *   TRUE if the URL is external to this site.
   */
  protected static function isOffSite(string $url, Request $request): bool {
    return UrlHelper::isExternal($url) && !UrlHelper::externalIsLocal($url, $request->getSchemeAndHttpHost());
  }

}
