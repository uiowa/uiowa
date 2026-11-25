<?php

namespace Drupal\uiowa_core\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsInterface;
use Drupal\Core\Asset\AttachedAssets;

/**
 * AJAX command that pushes webform events to the dataLayer.
 */
class WebformDataLayerPushCommand implements CommandInterface, CommandWithAttachedAssetsInterface {

  /**
   * Constructs a WebformDataLayerPushCommand object.
   *
   * @param array $events
   *   The dataLayer events to push.
   */
  public function __construct(protected array $events) {}

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'uiowaWebformDataLayerPush',
      'events' => $this->events,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachedAssets() {
    return AttachedAssets::createFromRenderArray([
      '#attached' => [
        'library' => ['uiowa_core/webform-datalayer'],
      ],
    ]);
  }

}
