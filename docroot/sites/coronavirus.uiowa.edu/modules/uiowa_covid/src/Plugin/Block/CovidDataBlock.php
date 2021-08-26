<?php

namespace Drupal\uiowa_covid\Plugin\Block;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Custom COVID data block.
 *
 * @Block(
 *   id = "uiowa_covid_data",
 *   admin_label = @Translation("COVID Data"),
 *   category = @Translation("UIowa COVID")
 * )
 */
class CovidDataBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['covid_data'] = [
      '#type' => 'container',
      '#markup' => $this->t('<p>This block gets data from the CIMT self-reporting database and renders it. The data will update around 10am on M/W/F.</p>'),
    ];

    $form['since_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Since Date'),
      '#description' => $this->t('Enter the start of the semester date to use for the sinceDate.'),
      '#default_value' => $this->configuration['since_date'] ?? NULL,
      '#required' => TRUE,
    ];

    $form['pause'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pause'),
      '#description' => $this->t('Checking this will pause the M/W/F update and show data from the previous reporting date <strong>until</strong> 12am the next day.'),
      '#default_value' => $this->configuration['pause'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['pause'] = $form_state->getValue('pause');
    $this->configuration['since_date'] = $form_state->getValue('since_date');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $since = strtotime($this->configuration['since_date'] ?? '2021/08/23');

    $query = UrlHelper::buildQuery([
      'pause' => $this->configuration['pause'] ?? FALSE,
      'since' => $since,
    ]);

    $endpoint = Url::fromRoute('uiowa_covid.data')->toString() . "?$query";

    $build = [
      '#attached' => [
        'library' => [
          'uiowa_covid/uiowa_covid',
        ],
        'drupalSettings' => [
          'uiowaCovid' => [
            'endpoint' => $endpoint,
          ],
        ],
      ],
      'content' => [
        'disclaimer' => [
          '#markup' => $this->t('<p><em>The table below reflects data collected as of <span id="uiowa-covid-reportDate">-</span>.</em></p>'),
          '#prefix' => '<div id="uiowa-covid-disclaimer">',
          '#suffix' => '</div>',
        ],
        'reported_heading' => [
          '#markup' => $this->t('Self-reported COVID-19 positive test results'),
          '#prefix' => '<h3 class="h5">',
          '#suffix' => '</h3>',
        ],
        'students_heading' => [
          '#markup' => $this->t('Students'),
          '#prefix' => '<h4 class="h6">',
          '#suffix' => '</h4>',
        ],
        'students' => [
          '#type' => 'table',
          '#header' => [
            $this->t('New Cases'),
            $this->t('Since @since', [
              '@since' => date('M. j', $since)
            ]),
            $this->t('Total Cases (<span class="uiowa-covid-totalDate">-</span>)'),
          ],
          '#rows' => [
            [
              $this->t('<span id="uiowa-covid-studentNew">-</span>'),
              $this->t('<span id="uiowa-covid-studentSince">-</span>'),
              $this->t('<span id="uiowa-covid-studentTotal">-</span>'),
            ],
          ],
          '#attributes' => [
            'class' => [
              'table--is-striped',
              'table',
            ],
          ],
        ],
        'employees_heading' => [
          '#markup' => $this->t('Employees'),
          '#prefix' => '<h4 class="h6">',
          '#suffix' => '</h4>',
        ],
        'employees' => [
          '#type' => 'table',
          '#header' => [
            $this->t('New Cases'),
            $this->t('Since @since', [
              '@since' => date('M. j', $since),
            ]),
            $this->t('Total Cases (Since Aug. 18, 2020)'),
          ],
          '#rows' => [
            [
              $this->t('<span id="uiowa-covid-employeeNew">-</span>'),
              $this->t('<span id="uiowa-covid-employeeSince">-</span>'),
              $this->t('<span id="uiowa-covid-employeeTotal">-</span>'),
            ],
          ],
          '#attributes' => [
            'class' => [
              'is-striped',
              'table',
            ],
          ],
        ],
        'rh_heading' => [
          '#markup' => $this->t('Resident Hall students in quarantine and self-isolation'),
          '#prefix' => '<h3 class="h5">',
          '#suffix' => '</h3>',
        ],
        'rh_quarantine' => [
          '#markup' => $this->t('Number of residence hall students in quarantine: <span id="uiowa-covid-rhStudentQuarantine">-</span>*'),
          '#prefix' => '<p>',
          '#suffix' => '</p>',
        ],
        'rh_isolation' => [
          '#markup' => $this->t('Number of residence hall students in isolation: <span id="uiowa-covid-rhStudentIsolation">-</span>**'),
          '#prefix' => '<p>',
          '#suffix' => '</p>',
        ],
      ],
    ];

    return $build;
  }

}
