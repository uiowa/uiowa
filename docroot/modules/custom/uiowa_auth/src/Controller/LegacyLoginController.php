<?php

namespace Drupal\uiowa_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Legacy login path controller.
 */
class LegacyLoginController extends ControllerBase {

  /**
   * Redirect users to the SAML login path.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throw an exception if the module is not configured to redirect.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function build() {
    $legacy_redirect = $this->config('uiowa_auth.settings')->get('legacy_redirect');

    if ($legacy_redirect) {
      return $this->redirect('samlauth.saml_controller_login');
    }
    else {
      throw new AccessDeniedHttpException();
    }
  }

}
