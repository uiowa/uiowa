<?php

namespace Drupal\facilities_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\uiowa_facilities\UtilityAlertsApiClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for utility alerts.
 */
class UtilityAlertsController extends ControllerBase {

  /**
   * Constructs a UtilityAlertsController object.
   *
   * @param \Drupal\uiowa_facilities\UtilityAlertsApiClientInterface $utilityAlertsApiClient
   *   The utility alerts API client.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(
    protected UtilityAlertsApiClientInterface $utilityAlertsApiClient,
    protected RendererInterface $renderer,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_facilities.utility_alerts_api_client'),
      $container->get('renderer'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Returns rendered utility alerts HTML.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response containing rendered alert cards.
   */
  public function getAlerts(): Response {
    $data = $this->utilityAlertsApiClient->getAlerts(30);

    $build = [];

    if ($data && !empty($data[0]->alerts)) {
      foreach ($data[0]->alerts as $alert) {
        $badge_class = match (strtolower($alert->alert_type)) {
          'emergency' => 'badge--orange',
          default => 'badge--cool-gray',
        };

        $meta = [];

        $date = '';
        if (!empty($alert->created_date)) {
          $timestamp = strtotime($alert->created_date);
          if ($timestamp !== FALSE) {
            $date = $this->dateFormatter->format($timestamp, 'long');
            $meta['date'] = [
              '#prefix' => '<div class="utility-alert__date fa-field-item">',
              '#markup' => '<span role="presentation" class="far fa-calendar"></span> ' . $date . '',
              '#suffix' => '</div>',
            ];
          }
        }

        $meta['badge'] = [
          '#prefix' => '<div class="utility-alert__badge">',
          '#markup' => '<span class="badge ' . $badge_class . '">' . htmlspecialchars($alert->alert_type) . '</span>',
          '#suffix' => '</div>',
        ];

        $build[] = [
          '#type' => 'card',
          '#title' => $alert->outage_types ?? '',
          '#title_heading_size' => 'h2',
          '#meta' => $meta,
          '#content' => [
            '#markup' => $alert->alert_text ?? '',
          ],
          '#attributes' => [
            'class' => [
              'headline--serif',
            ],
          ],
        ];
      }
    }
    else {
      $build[] = [
        '#markup' => '<p class="utility-alerts__empty">' . $this->t('No utility alerts at this time.') . '</p>',
      ];
    }

    $html = $this->renderer->renderRoot($build);

    return new Response($html, 200, [
      'Content-Type' => 'text/html',
    ]);
  }

}
