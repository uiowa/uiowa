<?php

namespace Drupal\uiowa_covid\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Custom COVID data block.
 *
 * @Block(
 *   id = "uiowa_covid_data",
 *   admin_label = @Translation("COVID Data"),
 *   category = @Translation("uiowa_covid")
 * )
 */
class CovidDataBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {
  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * CovidDataBlock constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \GuzzleHttp\Client
   * @param \Drupal\Core\Config\ConfigFactoryInterface
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $client, ConfigFactory $configFactory)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
    $this->configFactory = $configFactory;
  }

  /**
   * @param ContainerInterface $container
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['covid_data_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#description' => $this->t('Enter the date for which to retrieve data from.'),
      '#default_value' => isset($config['covid_data_date']) ? $config['covid_data_date'] : NULL,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['covid_data_date'] = $values['covid_data_date'];
    parent::blockSubmit($form, $form_state);
  }

  public function blockValidate($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $timestamp = strtotime($values['covid_data_date']);
    $data = $this->getData($timestamp);

    $null = [];

    foreach ($data as $name => $datum) {
      if (is_null($datum)) {
        $null[] = $name;
      }
    }

    if (!empty($null)) {
      $form_state->setErrorByName('covid_data_date', $this->t('COVID data contains empty values. Please try again later. Empty data: @data', [
        '@data' => implode(', ', $null),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $timestamp = strtotime($this->configuration['covid_data_date']);
    $data = $this->getData($timestamp);

    $build = [];

    if (isset($data)) {
      $build['content'] = [
        'disclaimer' => [
          '#markup' => $this->t('The data below reflects new cases since @date.', [
            '@date' => date('F j, Y', $timestamp),
          ]),
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
          '#caption' => $this->t('Students'),
          '#header' => [
            $this->t('New Cases'),
            $this->t('Semester to Date'),
          ],
          '#rows' => [
            [
              $this->t('@new', [
                '@new' => number_format($data->studentNew)
              ]),
              $this->t('@total', [
                '@total' => number_format($data->studentTotal)
              ]),
            ]
          ],
          '#attributes' => [
            'class' => [
              'is-striped',
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
          '#caption' => $this->t('Employees'),
          '#header' => [
            $this->t('New Cases'),
            $this->t('Semester to Date'),
          ],
          '#rows' => [
            [
              $this->t('@new', [
                '@new' => number_format($data->employeeNew)
              ]),
              $this->t('@total', [
                '@total' => number_format($data->employeeTotal)
              ]),
            ]
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
          '#markup' => $this->t('Number of residence hall students in quarantine: @quarantine*', [
            '@quarantine' => number_format($data->rhStudentQuarantine),
          ]),
          '#prefix' => '<p>',
          '#suffix' => '</p>',
        ],
        'rh_isolation' => [
          '#markup' => $this->t('Number of residence hall students in isolation: @isolation**', [
            '@isolation' => number_format($data->rhStudentIsolation),
          ]),
          '#prefix' => '<p>',
          '#suffix' => '</p>',
        ],
      ];
    }
    else {
      $build['content'] = [
        '#markup' => $this->t('There is no data at this time.'),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
    }

    return $build;
  }

  /**
   * Get API data.
   *
   * @param $timestamp
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function getData($timestamp) {
    $endpoint = $this->configFactory->get('uiowa.covid')->get('endpoint');
    $user = $this->configFactory->get('uiowa.covid')->get('user');
    $key = $this->configFactory->get('uiowa.covid')->get('key');
    $date = date('m-d-Y', $timestamp);

    /** @var \GuzzleHttp\Client $client */
    $client = \Drupal::httpClient();

    try {
      $response = $client->request('GET', "{$endpoint}/{$date}", [
        'auth' => [
          $user,
          $key,
        ]
      ]);

      // @todo: Verify status/messages/JSON.
      $data = json_decode($response->getBody()->getContents());
      return $data;
    } catch (RequestException $e) {
      watchdog_exception('uiowa_covid', $e);
    }

  }

}
