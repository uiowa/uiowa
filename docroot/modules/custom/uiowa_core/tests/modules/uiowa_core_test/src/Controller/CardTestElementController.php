<?php

namespace Drupal\uiowa_core_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides route responses for card test element.
 */
class CardTestElementController extends ControllerBase {

  /**
   * Returns the card element test page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array containing a card.
   */
  public function index(Request $request): array {
    $build = [];

    // Render the contact form.
    $build['card_test'] = [
      '#type' => 'card',
    ];

    // Populate webform properties using query string parameters.
    $properties = ['title', 'url', 'link_text', 'link_indicator'];
    $fake_data = $this->getFakeData();
    foreach ($properties as $property) {
      if ($request->query->get($property) && isset($fake_data["#$property"])) {
        $build['card_test']["#$property"] = $fake_data["#$property"];
      }
    }

    // Add query args to cache context.
    $build['#cache']['contexts'][] = 'url.query_args';

    return $build;
  }

  protected function getFakeData() {
    return [
      '#title' => 'Continue Your Story at Iowa',
      '#url' => 'https://uiowa.edu',
      '#link_text' => 'Get started',
      '#link_indicator' => TRUE,
    ];
  }

}
