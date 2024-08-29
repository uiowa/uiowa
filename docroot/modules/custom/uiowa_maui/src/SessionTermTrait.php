<?php

namespace Drupal\uiowa_maui;

/**
 * Trait SessionTermTrait.
 *
 * This trait provides methods for working with academic sessions and terms.
 * It includes functionality to retrieve the fall session and determine
 * the term (Fall, Spring, Summer, or Winter) from a session description.
 *
 * @package Drupal\uiowa_maui
 */
trait SessionTermTrait {

  /**
   * Get the fall session.
   *
   * @return object
   *   The fall session object.
   */
  public function getFallSession() {
    $currentSession = $this->getCurrentSession();
    $currentTerm = $this->getTerm($currentSession->shortDescription);

    if ($currentTerm == 'Fall') {
      return $currentSession;
    }

    $range = $this->getSessionsRange($currentSession->id, -1, 'FALL');
    return $range[0];
  }

  /**
   * Get the term from a session description.
   *
   * @param string $description
   *   The session description.
   *
   * @return string
   *   The term (Fall, Spring, Summer, or Winter).
   */
  protected function getTerm($description) {
    $terms = ['Fall', 'Spring', 'Summer', 'Winter'];
    foreach ($terms as $term) {
      if (stripos($description, $term) !== FALSE) {
        return $term;
      }
    }
    return '';
  }

}
