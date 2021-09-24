<?php

namespace Drupal\sitenow_find_text\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Sitenow Search settings for this site.
 */
class SearchForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_find_text';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $wrapper_id = $this->getFormId() . '-wrapper';

    $form = [
      '#prefix' => '<div id="' . $wrapper_id . '" aria-live="polite">',
      '#suffix' => '</div>',
    ];
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => <<< 'EOD'
        <p>Search text fields for a provided string. The search is not case-sensitive.</p>
        <p>Pre-rendered text area markup is searched, so some characters may be different; for instance, `&amp ;` may match ampersands where `&` will not.
        Node fields and content blocks are included, but Some areas such as menu links will not be searched.</p>
        <p>Basic SQL LIKE wildcards may be used.</p>
        <table class="responsive-enabled" data-striping="1">
            <thead>
                <tr>
                    <th>Operator</th>
                    <th>Use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>%</td>
                    <td>Wildcard that matches any zero, one, or many characters.<br/>
                    `f%r` will match `fr`, `for`, `four`, and `forever`.</td>
                </tr>
                <tr>
                    <td>_</td>
                    <td>Wildcard that matches exactly one character.<br/>
                    `f_r` will match `for`, but not `fr`, `four`, or `forever`.</td>
                </tr>
            </tbody>
        </table>
      EOD,
    ];
    $form['needle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Text'),
      '#default_value' => '',
      '#description' => $this->t('The string to search against. % wildcards are prepended and appended automatically when not using regex. % and _ are always treated as wildcards, and cannot be searched for directly at this time. A search for `100%` will return matches for `100`, `100%`, and `100.0`, for instance.'),
    ];
    $form['regexed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('REGEXP?'),
      '#description' => $this->t('% wildcards will not be prepended or appended, and the search will be performed as a REGEXP search rather than LIKE. Use only if a full regular expression is required.'),
      '#default_value' => 0,
    ];
    $form['search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
      '#name' => 'search',
      '#submit' => [
        [$this, 'searchButton'],
      ],
      '#ajax' => [
        'callback' => [$this, 'searchButton'],
        'wrapper' => $wrapper_id,
        'method' => 'html',
        'disable-refocus' => TRUE,
        'effect' => 'fade',
      ],
    ];

    // Unset the original, currently unused submit button.
    // It might be used at another time if settings are needed.
    unset($form['actions']['submit']);
    return $form;
  }

  /**
   * Perform a search with the given needle..
   */
  public function searchButton(array &$form, FormStateInterface $form_state) {
    $needle = $form_state->getValue('needle');
    $regexed = $form_state->getValue('regexed');
    $results = search_fields($needle, $regexed);
    $table = $this->buildResultsTable($results);
    $markup = \Drupal::service('renderer')->render($table);
    $form['results'] = [
      '#type' => 'markup',
      '#markup' => $markup,
    ];
    return $form;
  }

  /**
   * Helper function to form our table array.
   *
   * @param array $results
   *   The search form results to prepare.
   *
   * @return array
   *   The renderable table array.
   */
  public function buildResultsTable(array $results) {
    if (empty($results)) {
      return [
        '#type' => 'markup',
        '#markup' => '<p class="text-align-center">No results found.</p>',
      ];
    }
    // Clear out the excess to make printing easier.
    foreach (array_keys($results) as $key) {
      foreach (array_keys($results[$key]) as $secondary_key) {
        $results[$key][$secondary_key] = $results[$key][$secondary_key]->value;
      }
    }
    $rows = [];
    $node_manager = \Drupal::service('entity_type.manager')
      ->getStorage('node');
    foreach ($results as $nid => $matches) {
      // @todo Clean this up. Right now, we're checking for field existence
      //   to determine if we allow layout builder editing. It's better than
      //   hardcoding it to entity types we've allowed, but...only just.
      $has_lb = $node_manager->load($nid)->hasField('layout_builder__layout');
      if ($has_lb) {
        $node_value = new FormattableMarkup('@nid (<a href="/node/@nid/edit">edit</a>) (<a href="/node/@nid/layout">layout</a>)', [
          '@nid' => $nid,
        ]);
      }
      else {
        $node_value = new FormattableMarkup('@nid (<a href="/node/@nid/edit">edit</a>)', [
          '@nid' => $nid,
        ]);
      }
      $rows[] = [
        'nid' => [
          'data' => $node_value,
          // Stretch the node row to cover all its matches.
          'rowspan' => count($matches),
        ],
        // Shift the first element out of the ray to match
        // to the node row.
        'field' => array_shift($matches),
      ];
      // If we have more matches after the shift, we need to
      // add each to its own row.
      foreach ($matches as $match) {
        $rows[] = ['field' => $match];
      }
    }
    return [
      '#type' => 'table',
      '#header' => [
        'nid' => 'Node',
        'field' => 'Field: Contents',
      ],
      '#rows' => $rows,
      '#attributes' => NULL,
    ];
  }

}
