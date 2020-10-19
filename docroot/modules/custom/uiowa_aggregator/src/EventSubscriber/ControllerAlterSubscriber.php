<?php

namespace Drupal\uiowa_aggregator\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Alter the aggregator.admin_overview route controller render array.
 */
class ControllerAlterSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
   *   The date formatter service.
   */
  public function __construct(ConfigFactoryInterface $config, DateFormatter $dateFormatter) {
    $this->config = $config;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Add a disclaimer about feed import/clear timings to aggregator overview.
   */
  public function onView(GetResponseForControllerResultEvent $event) {
    $request = $event->getRequest();
    $route = $request->attributes->get('_route');

    if ($route == 'aggregator.admin_overview') {
      $build = $event->getControllerResult();

      if (is_array($build)) {
        $expire = $this->config->get('aggregator.settings')->get('items.expire');
        $interval = $this->dateFormatter->formatInterval($expire);

        $build['disclaimer'] = [
          '#markup' => $this->t('<em>Note that feed items will only be imported if their post date is within @interval or less. Items already imported will be deleted after @interval</em>.', [
            '@interval' => $interval,
          ]),
        ];

        $event->setControllerResult($build);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::VIEW => ['onView', 50],
    ];
  }

}
