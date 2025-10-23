<?php

namespace Drupal\uiowa_graph\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

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

    $csv_text = $config['graph_CSV_data'] ?? '';
    $graph_summary = $config['graph_summary'] ?? '';
    $chart_type = $config['chart_type'] ?? 'line';

    $rows = preg_split("/\r\n|\n|\r/", $csv_text);

    $unique_id = Html::getUniqueId('graph');

    $build['graph_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $unique_id,
        'class' => ['graph-container'],
        'data-chart-type' => $chart_type,
      ],
    ];

    $build['graph_container']['canvas'] = [
      '#type' => 'markup',
      '#markup' => '<div class="graph-canvas__container"><div class="graph-canvas" role="img" aria-labelledby="' . $unique_id . '-summary"></div></div>',
      '#allowed_tags' => array_merge(Xss::getHtmlTagList(), ['div']),
    ];

    $build['graph_container']['#attached']['library'][] = 'uiowa_graph/highcharts';
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
      // Parse CSV row properly (handles quoted values with commas)
      $columns = $this->parseCSVRow($row);

      if ($row_key === 0) {
        foreach ($columns as $column) {
          array_push($build['graph_container']['graph_details']['graph_table']['#header'], ['data' => $column]);
        }
      }
      else {
        $build['graph_container']['graph_details']['graph_table']['#rows']['row-' . $row_key] = [];
        foreach ($columns as $column) {
          // Format the column data.
          $formatted_column = $this->formatTableCell($column);
          array_push($build['graph_container']['graph_details']['graph_table']['#rows']['row-' . $row_key], ['data' => $formatted_column]);
        }
      }
    }

    return $build;
  }

  /**
   * Parse a CSV row handling quoted values.
   *
   * @param string $row
   *   The CSV row string.
   *
   * @return array
   *   Array of column values.
   */
  protected function parseCSVRow($row) {
    // Use str_getcsv with explicit parameters:
    // delimiter: comma
    // enclosure: double quote
    // escape: backslash.
    $result = str_getcsv($row, ',', '"', '\\');

    // Trim whitespace from each value.
    return array_map('trim', $result);
  }

  /**
   * Format table cell data.
   *
   * @param string $value
   *   The cell value to format.
   *
   * @return string
   *   The formatted cell value.
   */
  protected function formatTableCell($value) {
    $value = trim($value);

    // If empty, return as is.
    if ($value === '') {
      return $value;
    }

    // Remove existing dollar signs and commas for processing.
    $clean_value = str_replace(['$', ','], '', $value);

    // Check if it's a numeric value.
    if (is_numeric($clean_value)) {
      $num = floatval($clean_value);

      // Format large numbers (>= 1000) as currency with commas.
      if ($num >= 1000) {
        return '$' . number_format($num, 0);
      }
      // Format small numbers (< 1000) with 2 decimal places if they have decimals.
      elseif ($num != floor($num)) {
        return number_format($num, 2);
      }
      // Return integers as is.
      else {
        return $value;
      }
    }

    // Check if it's a percentage.
    if (strpos($value, '%') !== FALSE) {
      $clean_value = str_replace('%', '', $value);
      if (is_numeric($clean_value)) {
        $num = floatval($clean_value);
        return number_format($num, 2) . '%';
      }
    }

    // Return non-numeric values as is.
    return $value;
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
      '#default_value' => $config['graph_summary'] ?? '',
    ];

    $form['chart_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Chart type'),
      '#description' => $this->t('Select the type of chart to display.'),
      '#options' => [
        'line' => $this->t('Line Chart'),
        'column' => $this->t('Bar Chart (Column)'),
        'bar' => $this->t('Bar Chart (Horizontal)'),
        'pie' => $this->t('Pie Chart'),
        'donut' => $this->t('Donut Chart'),
      ],
      '#default_value' => $config['chart_type'] ?? 'line',
    ];

    $form['graph_CSV_data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('CSV data'),
      '#description' => $this->t('Copy and paste your properly formatted CSV file here. Values with commas should be wrapped in double quotes. An example of a properly formatted csv file can be found <a href="https://sitenow.uiowa.edu/sites/sitenow.uiowa.edu/files/2021-09/airtravel.csv">here</a>.'),
      '#default_value' => $config['graph_CSV_data'] ?? '',
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
    $this->configuration['chart_type'] = $values['chart_type'];
    $this->configuration['graph_CSV_data'] = trim($values['graph_CSV_data']);
  }

}
