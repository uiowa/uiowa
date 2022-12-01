<?php

namespace Drupal\uiowa_covid\Plugin\Block;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom COVID data block.
 *
 * @Block(
 *   id = "uiowa_covid_data",
 *   admin_label = @Translation("COVID Data"),
 *   category = @Translation("Site custom")
 * )
 */
class CovidDataBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['covid_data'] = [
      '#type' => 'container',
      '#markup' => $this->t('<p>This block gets data from the CIMT self-reporting database and renders it. The data will update around 10am on Monday, Wednesday and Friday.</p>'),
    ];

    $form['since_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Since Date'),
      '#description' => $this->t('Used to display cases since this date. Typically, the start of the semester.'),
      '#default_value' => $this->configuration['since_date'] ?? NULL,
      '#required' => TRUE,
    ];

    $form['pause'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pause'),
      '#description' => $this->t('Checking this before 10am on M/W/F will pause that update and show data from the previous reporting date <strong>until</strong> 12am the next day. Remember to uncheck.'),
      '#default_value' => $this->configuration['pause'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    foreach (['endpoint', 'user', 'key'] as $required) {
      if (!$this->configFactory->get('uiowa.covid')->get($required)) {
        $form_state->setErrorByName('covid_data', $this->t('The required credentials for accessing the CIMT database have not been set in configuration.'));
      }
    }
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

    return [
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
        'disclaimer_wrapper' => [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'uiowa-covid-disclaimer',
          ],
          'disclaimer' => [
            '#markup' => $this->t('<em>The table below reflects data collected as of <span id="uiowa-covid-reportDate"><i role="presentation" class="fas fa-spinner fa-spin"></i></span>.</em>'),
            '#prefix' => '<p>',
            '#suffix' => '</p>',
          ],
        ],
        'report_wrapper' => [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'uiowa-covid-report',
          ],
          'report' => [
            'reported_heading' => [
              '#markup' => $this->t('Self-reported COVID-19 positive test results updated every Monday, Wednesday, and Friday'),
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
                $this->t('Since <span class="uiowa-covid-sinceDate">-</span>'),
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
                $this->t('Since <span class="uiowa-covid-sinceDate">-</span>'),
                $this->t('Total Cases (<span class="uiowa-covid-totalDate">-</span>)'),
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
        ],
      ],
    ];
  }

}
