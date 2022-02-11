<?php

namespace Drupal\uiowa_graph\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Html;

/**
 * Provides the Graph block.
 *
 * @Block(
 *   id = "uiowa_graph",
 *   admin_label = @Translation("Graph"),
 *   category = @Translation("Site custom")
 * )
 */
class GraphBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();

    $csv_text = isset($config['graph_CSV_data']) ?: '';
    $graph_summary = isset($config['graph_summary']) ?: '';

    $rows = preg_split("/\r\n|\n|\r/", $csv_text);

    $unique_id = Html::getUniqueId('graph');

    $build['graph_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $unique_id,
        'class' => ['graph-container'], /* Class on the wrapping DIV element */
      ],

    ];
    $build['graph_container']['canvas'] = [
      '#type' => 'markup',
      '#markup' => '<div class="graph-canvas__container"><canvas class="graph-canvas" role="img" aria-labelledby="' . $unique_id . '-summary"></canvas></div>',
      '#allowed_tags' => array_merge(Xss::getHtmlTagList(), ['canvas', 'div']),
    ];
    $build['graph_container']['#attached']['library'][] = 'uiowa_graph/chartjs';
    $build['graph_container']['#attached']['library'][] = 'uiowa_graph/graph';

    $build['graph_container']['graph_details'] = [
      '#type' => 'details',
      '#title' => $this
        ->t('Show tabulated data.'),
      '#attributes' => ['class' => ['graph-table__details']],
    ];

    $build['graph_container']['graph_details']['graph_table'] = [
      '#theme' => 'table',
      '#attributes' => ['class' => ['graph-table']],
    ];

    $build['graph_container']['graph_details']['graph_table']['#caption'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<span id="@unique_id-summary">@summary</span>', [
        '@unique_id' => $unique_id,
        '@summary' => $graph_summary,
      ]),
      '#allowed_tags' => array_merge(Xss::getHtmlTagList(), ['caption', 'span']),
    ];

    $build['graph_container']['graph_details']['graph_table']['#header'] = [];
    $build['graph_container']['graph_details']['graph_table']['#rows'] = [];

    foreach ($rows as $row_key => $row) {
      if ($row_key == 0) {
        foreach (explode(',', $row) as $column) {
          array_push($build['graph_container']['graph_details']['graph_table']['#header'], ['data' => $column]);
        }
      }
      else {
        $build['graph_container']['graph_details']['graph_table']['#rows']['row-' . $row_key] = [];
        foreach (explode(',', $row) as $column) {
          array_push($build['graph_container']['graph_details']['graph_table']['#rows']['row-' . $row_key], ['data' => $column]);
        }
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['graph_summary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Graph summary'),
      '#description' => $this->t('Provide a short description for the graph data.'),
      '#default_value' => isset($config['graph_summary']) ?: '',
    ];

    $form['graph_CSV_data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('CSV data'),
      '#description' => $this->t('Copy and paste your properly formatted CSV file here. An example of a properly formatted csv file can be found <a href="https://sitenow.uiowa.edu/sites/sitenow.uiowa.edu/files/2021-09/airtravel.csv">here</a>.'),
      '#default_value' => isset($config['graph_CSV_data']) ?: '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['graph_summary'] = $values['graph_summary'];
    $this->configuration['graph_CSV_data'] = trim($values['graph_CSV_data']);
  }

}
