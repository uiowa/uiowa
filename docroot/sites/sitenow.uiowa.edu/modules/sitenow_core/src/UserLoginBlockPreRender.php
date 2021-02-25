<?php

namespace Drupal\sitenow_core;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Provide pre-rendering for user login block.
 */
class UserLoginBlockPreRender implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * Pre-render callback for user_login_block.
   *
   * @param array $build
   *   The block build render array.
   *
   * @return array
   *   The render array.
   *
   * @see uiowa_auth_block_view_user_login_block_alter()
   * @see sitenow_core_block_view_user_login_block_alter()
   */
  public static function preRender(array $build) {

    $path = \Drupal::service('path.current')->getPath();

    if ($path == '/node/26'
      && isset($build['content']['hawkid'], $build['content']['hawkid']['link'])) {
      /** @var \Drupal\Core\Url $url */
      $url = $build['content']['hawkid']['link']['#url'];

      unset($build['content']['hawkid']['link']);

      $url->setOptions([
        'query' => [
          'destination' => $path,
        ],
      ]);

      $build['content']['hawkid']['message'] = [
        '#prefix' => '<div class="alert alert-warning">',
        '#suffix' => '</div>',
        '#markup' => t('The SiteNow service is restricted to current University of Iowa members. You must <a href="@link">log in</a> first to access the request form.', [
          '@link' => $url->toString(),
        ]),
      ];
    }

    return $build;
  }

}
