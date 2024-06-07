<?php

namespace Drupal\cevalidationsr\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures forms module settings.
 */
class cevalidationsrConfigurationForm extends ConfigFormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ceValidationsr_admin_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [
            'cevalidationsr.settings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL)
    {
        $config = $this->config('cevalidationsr.settings');
        //$state  = \Drupal::state();
        $form["#attributes"]["autocomplete"] = "off";
        $form['cevalidationsr'] = array(
            '#type'  => 'fieldset',
            '#title' => $this->t('CeValidation settings'),
        );
        $form['cevalidationsr']['url'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('CeValidation API Base URL'),
            '#default_value' => $config->get('cevalidationsr.url'),
            '#description'   => t('https://test.secure.cecredentialtrust.com:8086/api/webapi/v3/cecredentialvalidate'),
        );
        $form['cevalidationsr']['clientid'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Client Id'),
            '#default_value' => $config->get('cevalidationsr.clientid'),
            '#description'   => t('80DBC6A0-6CCF-4BA3-AAD8-89B2AE22FFA9'),
        );
        $form['cevalidationsr']['apostilleemail'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Apostille Email'),
            '#default_value' => $config->get('cevalidationsr.apostilleemail'),
            '#description'   => t('graduation@sampleuniversity.edu'),
        );
        $form['cevalidationsr']['neutralresponseemail'] = array(
            '#type'          => 'textfield',
            '#title'         => $this->t('Neutral Response Email'),
            '#default_value' => $config->get('cevalidationsr.neutralresponseemail'),
            '#description'   => t('helpdesk@sampleuniversity.edu'),
        );
        $form['cevalidationsr']['displayCHEALogo'] = array(
            '#type'          => 'checkbox',
            '#title'         => $this->t('Display CHEA Logo'),
            '#default_value' => $config->get('cevalidationsr.displayCHEALogo'),
            '#description'   => t('Set if you are a member of CHEA'),
        );

        return parent::buildForm($form, $form_state);
    }

    /**
    * {@inheritdoc}
    */
    public function validateForm(array &$form, FormStateInterface $form_state) {
       parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();
        $config = $this->config('cevalidationsr.settings');
        $state  = \Drupal::state();
        $config->set('cevalidationsr.url', $values['url']);
        $config->set('cevalidationsr.clientid', $values['clientid']);
        $config->set('cevalidationsr.apostilleemail', $values['apostilleemail']);
        $config->set('cevalidationsr.neutralresponseemail', $values['neutralresponseemail']);
        $config->set('cevalidationsr.displayCHEALogo', isset($values['displayCHEALogo']) ? $values['displayCHEALogo'] : true);
        $config->save();
        parent::submitForm($form, $form_state);
    }
}
