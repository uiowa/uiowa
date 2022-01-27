<?php

namespace Drupal\uiowa_auth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\uiowa_auth\RoleMappings;

/**
 * Configure HawkID settings for this site.
 */
class HawkIDSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_auth';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uiowa_auth.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $mappings = $this->config('uiowa_auth.settings')->get('role_mappings');

    if (is_array($mappings)) {
      $text = RoleMappings::arrayToText($mappings);
    }
    else {
      $text = NULL;
    }

    $form['role_mappings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Role mappings'),
      '#description' => $this->t('Enter role mappings in the format
      rid|attr|value where rid is the machine name of the role, attr is the SAML
       attribute to parse and value is what to match in the attribute.
       Ex. webmaster|urn:oid:2.5.4.31|CN=MyGroup,OU=Groups,OU=MyDomain,DC=iowa,DC=uiowa,DC=edu.
       Separate multiple mappings with a return. <strong>Note</strong> that the
       value must match exactly.'),
      '#default_value' => $text,
    ];

    $form['legacy_redirect'] = [
      '#type' => 'checkbox',
      '#title' => 'Redirect legacy paths',
      '#description' => $this->t('Redirect /user/login and /hawkid_login to the SAML login path.'),
      '#return_value' => TRUE,
      '#default_value' => $this->config('uiowa_auth.settings')->get('legacy_redirect'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $mappings = RoleMappings::textToArray($form_state->getValue('role_mappings'));

    foreach ($mappings as $mapping) {
      $parts = explode('|', $mapping);

      // Each mapping should have three parts, i.e. two pipes.
      if (count($parts) != 3) {
        $form_state->setErrorByName('role_mappings', $this->t('Invalid role mapping @mapping. Ensure the mapping follows the rid|attr|value format.', ['@mapping' => $mapping]));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mappings = RoleMappings::textToArray($form_state->getValue('role_mappings'));

    $this->config('uiowa_auth.settings')
      ->set('role_mappings', $mappings)
      ->set('legacy_redirect', $form_state->getValue('legacy_redirect'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
