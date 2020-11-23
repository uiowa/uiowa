<?php

namespace Drupal\uiowa_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Uiowa Search routes.
 */
class UiowaSearchResultsController extends ControllerBase {

  /**
   * Constructs the controller object.
   */
  public function __construct() {}

  /**
   * Builds the response.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   The render array for the search results page.
   */
  public function build(Request $request) {
    $config = $this->config('uiowa_search.settings')->get('uiowa_search');
    $search_terms = $request->get('search');

    $search_params = [
      'q' => $search_terms,
      'client' => 'our_frontend',
      'btnG' => 'Search',
      'output' => 'xml_no_dtd',
      'proxystylesheet' => 'our_frontend',
      'sort' => 'date',
      'entqr' => '0',
      'oe' => 'UTF-8',
      'ie' => 'UTF-8',
      'ud' => '1',
      'site' => 'default_collection',
    ];

    $build['search'] = [
      '#type' => 'link',
      '#title' => $this->t('Search all University of Iowa for @terms', ['@terms' => $search_terms]),
      '#url' => Url::fromUri('https://search.uiowa.edu/search', ['query' => $search_params]),
      '#attributes' => [
        'target' => '_blank',
      ],
    ];

    $build['results_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'search-results',
      ],
    ];

    $build['#attached']['library'][] = 'uiowa_search/search-results';
    $build['#attached']['drupalSettings']['uiowaSearch']['engineId'] = $config['cse_engine_id'];
    $build['#attached']['drupalSettings']['uiowaSearch']['cseScope'] = $config['cse_scope'];
    $build['#cache']['max-age'] = 0;

    return $build;
  }

}
