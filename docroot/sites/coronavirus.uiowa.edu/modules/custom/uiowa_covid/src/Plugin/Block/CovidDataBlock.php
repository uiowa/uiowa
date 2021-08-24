<?php

namespace Drupal\uiowa_covid\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Custom COVID data block.
 *
 * @Block(
 *   id = "uiowa_covid_data",
 *   admin_label = @Translation("COVID Data"),
 *   category = @Translation("uiowa_covid")
 * )
 */
class CovidDataBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['covid_data_date'] = [
      '#type' => 'html',
      '#title' => $this->t('COVID Data'),
      '#description' => $this->t('This block gets data from the CIMT self-reporting database and renders it. It will refresh automatically around 10am on M/W/F.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#attached' => [
        'library' => [
          'uiowa_covid/uiowa_covid',
        ],
        'drupalSettings' => [
          'uiowaCovid' => [
            'endpoint' => Url::fromRoute('uiowa_covid.data')->toString(),
          ],
        ],
      ],
      'content' => [
        'disclaimer' => [
          '#markup' => $this->t('The data below reflects new cases since <span id="uiowa-covid-date">-</span>.'),
          '#prefix' => '<em>',
          '#suffix' => '</em>',
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
            $this->t('Semester to Date'),
          ],
          '#rows' => [
            [
              $this->t('<span id="uiowa-covid-studentNew">-</span>'),
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
            $this->t('Semester to Date'),
          ],
          '#rows' => [
            [
              $this->t('<span id="uiowa-covid-employeeNew">-</span>'),
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
