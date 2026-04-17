<?php

namespace Drupal\emergency_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\uiowa_emergency\ActiveAlertsApiClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for active alerts.
 */
class ActiveAlertsController extends ControllerBase {

  /**
   * Constructs a ActiveAlertsController object.
   *
   * @param \Drupal\uiowa_emergency\ActiveAlertsApiClientInterface $activeAlertsApiClient
   *   The active alerts API client.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(
    protected ActiveAlertsApiClientInterface $activeAlertsApiClient,
    protected RendererInterface $renderer,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uiowa_emergency.active_alerts_api_client'),
      $container->get('renderer'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Returns rendered active alerts HTML.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response containing rendered alert cards.
   */
  public function getAlerts(Request $request): Response {
    $allowed = ['h2', 'h3', 'h4', 'h5', 'h6'];
    $heading_size = $request->query->get('heading_size', 'h2');
    if (!in_array($heading_size, $allowed)) {
      $heading_size = 'h2';
    }

    $days = (int) $this->config('uiowa_emergency.apis')->get('active_alerts.days') ?: 14;
    $data = $this->activeAlertsApiClient->getAlerts($days);

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
              '#prefix' => '<div class="active-alert__date fa-field-item">',
              '#markup' => '<span role="presentation" class="far fa-calendar"></span> ' . $date . '',
              '#suffix' => '</div>',
            ];
          }
        }

        $meta['badge'] = [
          '#prefix' => '<div class="active-alert__badge">',
          '#markup' => '<span class="badge ' . $badge_class . '">' . htmlspecialchars($alert->alert_type) . '</span>',
          '#suffix' => '</div>',
        ];

        $build[] = [
          '#type' => 'card',
          '#title' => $alert->outage_types ?? '',
          '#title_heading_size' => $heading_size,
          '#meta' => $meta,
          '#content' => [
            '#markup' => $alert->alert_text ?? '',
          ],
          '#attributes' => [
            'class' => [
              'headline--serif',
              'borderless',
              'element--margin__bottom--extra',
            ],
          ],
        ];
      }
    }
    else {
      $build[] = [
        '#markup' => '<p class="active-alerts__empty">' . $this->t('No active alerts at this time.') . '</p>',
      ];
    }

    $html = $this->renderer->renderRoot($build);

    return new Response($html, 200, [
      'Content-Type' => 'text/html',
    ]);
  }

}
