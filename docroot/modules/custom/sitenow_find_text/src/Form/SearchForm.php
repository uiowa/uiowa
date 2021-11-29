<?php

namespace Drupal\sitenow_find_text\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Sitenow Search settings for this site.
 */
class SearchForm extends ConfigFormBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->renderer = $container->get('renderer');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

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
        Node fields and content blocks are included, but some areas such as menu links will not be searched.</p>
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
                    `f%r` will match `fr`, `for`, `four`, and `flounder`.</td>
                </tr>
                <tr>
                    <td>_</td>
                    <td>Wildcard that matches exactly one character.<br/>
                    `f_r` will match `for`, but not `fr`, `four`, or `flounder`.</td>
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
    $markup = $this->renderer->render($table);
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
    // Rearrange and clear out the excess to make printing easier.
    // Starting out, our results are separated by entity type.
    foreach ($results as $type => $typed_results) {
      // This first key will be the entity id.
      foreach (array_keys($typed_results) as $key) {
        // The secondary key will be a simple delta.
        foreach (array_keys($typed_results[$key]) as $secondary_key) {
          // Created a modified key to help us avoid collisions
          // while rearranging our results.
          $mod_key = implode('-', [$type, $key]);
          $results[$mod_key][] = $results[$type][$key][$secondary_key]->value;
        }
      }
      unset($results[$type]);
    }
    $rows = [];
    $node_manager = $this->entityTypeManager
      ->getStorage('node');
    foreach ($results as $mod_key => $matches) {
      $exploded = explode('-', $mod_key);
      list($type, $id) = $exploded;
      switch ($type) {
        case 'block_content':
        case 'paragraph':
        case 'node':
          $node = $node_manager->load($id);
          // Check if we have an overridden layout or not.
          // If we didn't successfully load a node, go ahead
          // and treat it as a non-overridden node so that
          // the user will still see it in the results as a failsafe.
          $has_lb = $node && $node->hasField('layout_builder__layout');
          if ($has_lb) {
            $entity_value = new FormattableMarkup('Node: @nid (<a href="/node/@nid/edit">edit</a>) (<a href="/node/@nid/layout">layout</a>)', [
              '@nid' => $id,
            ]);
          }
          else {
            $entity_value = new FormattableMarkup('Node: @nid (<a href="/node/@nid/edit">edit</a>)', [
              '@nid' => $id,
            ]);
          }
          break;

        case 'menu_link_content':
          $entity_value = new FormattableMarkup('Menu: @mid (<a href="/admin/structure/menu/item/@mid/edit">edit</a>)', [
            '@mid' => $id,
          ]);
          break;

        default:
          $entity_value = FALSE;
      }
      if (!$entity_value) {
        continue;
      }
      $rows[] = [
        'id' => [
          'data' => $entity_value,
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
        'nid' => 'Entity',
        'field' => 'Field: Contents',
      ],
      '#rows' => $rows,
      '#attributes' => NULL,
    ];
  }

}
