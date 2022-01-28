<?php

namespace Drupal\uiowa_auth;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;

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
   */
  public static function preRender(array $build) {

    if (\Drupal::currentUser()->isAnonymous()) {
      if (isset($build['content']['user_links']['#items']['request_password'])) {
        unset($build['content']['user_links']['#items']['request_password']);
      }

      $build['content']['hawkid'] = [
        'message' => [
          '#markup' => t('Click the link below to be redirected to the secure HawkID login page.'),
          '#prefix' => '<p>',
          '#suffix' => '</p>',
        ],
        'link' => [
          '#type' => 'link',
          '#title' => t('HawkID Login'),
          '#url' => Url::fromRoute('samlauth.saml_controller_login'),
        ],
      ];
    }

    return $build;
  }

}
