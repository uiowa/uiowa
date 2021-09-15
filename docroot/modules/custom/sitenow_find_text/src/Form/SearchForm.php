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
    $form['needle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Text'),
      '#default_value' => '',
      '#description' => $this->t('The string to search against in the pre-rendered text area markup (some characters may be different; for instance, "&amp;" may match where "&" will not). Basic SQL LIKE operator modifiers may be used, including _ and % wildcards, [a-z]/[^a-z] ranges, and [AB]/[^AB] character options. % wildcards are prepended and appended automatically when not using regex.')
    ];
    $form['regexed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('REGEXP?'),
      '#description' => $this->t('Is the Search Text entered as a regular expression?'),
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
    // Grab all the fields.
    $fields = get_all_text_fields();
    $needle = $form_state->getValue('needle');
    $regexed = $form_state->getValue('regexed');
    $results = search_fields($fields, $needle, $regexed);
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
    $rows = [];
    foreach ($results as $nid => $matches) {
      // Form our edit link with the node id.
      $node_value = new FormattableMarkup('@nid (<a href="/node/@nid/edit">edit</a>) (<a href="/node/@nid/layout">layout</a>)', [
        '@nid' => $nid,
      ]);
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
        'field' => 'Field',
      ],
      '#rows' => $rows,
      '#attributes' => NULL,
    ];
  }

}
