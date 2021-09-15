<?php

namespace Drupal\uiowa_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;

/**
 * Provides the Graph block.
 *
 * @Block(
 *   id = "uiowa_core_graph",
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

    $csv_text = isset($config['graph_CSV_data']) ? $config['graph_CSV_data'] : '';

    $rows = preg_split("/\r\n|\n|\r/", $csv_text);

    $build['graph_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['graph-container'], /* Class on the wrapping DIV element */
      ],

    ];
    $build['graph_container']['canvas'] = [
      '#type' => 'markup',
      '#markup' => '<div class="graph-canvas__container"><canvas class="graph-canvas"></canvas></div>',
      '#allowed_tags' => array_merge(Xss::getHtmlTagList(), ['canvas', 'div']),
    ];
    $build['graph_container']['#attached']['library'][] = 'uiowa_core/graph';
    $build['graph_container']['#attached']['library'][] = 'uiowa_core/chartjs';

    $build['graph_container']['graph_table'] = [
      '#theme' => 'table',
      '#attributes' => ['class' => ['graph-table', 'sr-only']],

    ];

    $build['graph_container']['graph_table']['#header'] = [];
    $build['graph_container']['graph_table']['#rows'] = [];

    foreach ($rows as $row_key => $row) {
      if ($row_key == 0) {
        foreach (explode(',', $row) as $column_key => $column) {
          array_push($build['graph_container']['graph_table']['#header'], ['data' => $column]);
        }
      }
      else {
        $build['graph_container']['graph_table']['#rows']['row-' . $row_key] = [];
        foreach (explode(',', $row) as $column_key => $column) {
          array_push($build['graph_container']['graph_table']['#rows']['row-' . $row_key], ['data' => $column]);
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

    $form['graph_CSV_data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('CSV data'),
      '#description' => $this->t('Copy and paste your properly formatted CSV file here. An example of a properly formatted csv file can be found <a href="https://sitenow.uiowa.edu/sites/sitenow.uiowa.edu/files/2021-09/airtravel.csv">here</a>'),
      '#default_value' => isset($config['graph_CSV_data']) ? $config['graph_CSV_data'] : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['graph_CSV_data'] = $values['graph_CSV_data'];
  }

}
