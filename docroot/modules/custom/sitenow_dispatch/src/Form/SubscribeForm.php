<?php

namespace Drupal\sitenow_dispatch\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sitenow_dispatch\DispatchApiClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure sitenow_dispatch settings for this site.
 */
class SubscribeForm extends ConfigFormBase {

  /**
   * Constructs the SubscribeForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\sitenow_dispatch\DispatchApiClientInterface $dispatch
   *   The Dispatch API client service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, protected DispatchApiClientInterface $dispatch) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('sitenow_dispatch.dispatch_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_dispatch_subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sitenow_dispatch.subscribe_form'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $population = NULL) {
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $this->config('sitenow_dispatch.subscribe_form')->get('email'),
      '#required' => TRUE,
    ];
    $form['first'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#default_value' => $this->config('sitenow_dispatch.subscribe_form')->get('first'),
    ];
    $form['last'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#default_value' => $this->config('sitenow_dispatch.subscribe_form')->get('last'),
    ];
    $form['population'] = [
      '#type' => 'hidden',
      '#value' => $population,
    ];

    $parameters = $this->dispatch->get("populations/$population");

    if ($parameters?->subscriptionList?->hidePhone === FALSE) {
      $form['phone'] = [
        '#type' => 'tel',
        '#title' => $this->t('Phone number'),
      ];
    }
    foreach ($parameters?->subscriptionList?->customFields as $custom_field) {
      $this->processCustomField($custom_field, $form);
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    $first = $form_state->getValue('first');
    $last = $form_state->getValue('last');
    $population = $form_state->getValue('population');
    $body = [
      'toAddress' => $email,
      'firstName' => $first,
      'lastName' => $last,
    ];

    $parameters = $this->dispatch->get("populations/$population");
    if ($parameters?->subscriptionList?->hidePhone === FALSE) {
      $phone = $form_state->getValue('phone');
      $body['toPhone'] = $phone;
    }
    foreach ($parameters?->subscriptionList?->customFields as $custom_field) {
      $value = $form_state->getValue($custom_field->key);
      // Checkboxes allows for multiple values, but stores them
      // as a comma delimited string in Dispatch, so first
      // implode our value(s).
      if ($custom_field->fieldType === 'CHECKBOX') {
        // Filter out any 0s in the array, which indicate
        // unchecked boxes, and pass the cleaned array to implode.
        $value = implode(',', array_filter($value));
      }
      $body[$custom_field->key] = $value;
    }

    $this->dispatch->request('POST', "populations/$population/subscribers", [
      'body' => json_encode($body),
    ]);

    $this->messenger()->addStatus($this->t('@email has been added to the subscription list.', [
      '@email' => $email,
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    $population = $form_state->getValue('population');

    $response = $this->dispatch->get("populations/$population/subscribers", [
      'query' => [
        'search' => $email,
      ],
    ]);

    if ($response->recordsReturned > 0) {
      $form_state->setErrorByName('email', 'This email is already subscribed to the related subscription list.');
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * Process a custom field from the API and add it to the form.
   *
   * @param Object $custom_field
   *   A custom field defined by the Dispatch API.
   * @param array $form
   *   The form to which field elements will be added.
   */
  protected function processCustomField(Object $custom_field, array &$form) : void {
    /*
     * The custom field is an object defined by the
     * Dispatch API with fields like the following:
     * fieldType = 'TEXT'
     * key = 'unit'
     * label = 'Unit/Organization'
     * listOptions = ''
     * required = false
     * defaultValue = NULL
     * helpText = NULL
     * sortOrder = (int) 0
     */
    $map = [
      'TEXT' => 'textfield',
      'NUMBER' => 'number',
      'CHECKBOX' => 'checkboxes',
      'DROPDOWN' => 'select',
      'RADIO' => 'radios',
    ];
    if (!isset($custom_field->fieldType) || !isset($map[$custom_field->fieldType])) {
      return;
    }
    $field_type = $map[$custom_field->fieldType];

    // We can't have a select list or radio
    // without options, so if that's empty, skip.
    if (in_array($field_type, ['select', 'radio', 'checkboxes']) && empty($custom_field->listOptions)) {
      return;
    }

    $form['custom_fields'][$custom_field->key] = [
      '#type' => $field_type,
      '#title' => $custom_field->label,
      '#default_value' => $custom_field->defaultValue,
      '#required' => $custom_field->required,
      '#description' => $custom_field->helpText,
    ];

    // Add options to the form element if needed.
    if (in_array($field_type, ['select', 'radios', 'checkboxes'])) {
      // Split the options on carriage returns.
      $dispatch_options = preg_split('%\r\n|\r|\n%', $custom_field->listOptions);
      // Form API expects a set of key => value pairs.
      // In our case, we may or may not have labels
      // in the form of value,label or value.
      $options = [];
      foreach ($dispatch_options as $option) {
        // Limit it to 2 parts so the string scan will
        // stop once it hits the first comma.
        $parts = explode(',', $option, 2);
        // If we only have one part, then we need to
        // set the value to itself as its key.
        if (count($parts) === 1) {
          $options[$option] = $option;
        }
        else {
          $options[$parts[0]] = $parts[1];
        }
      }
      $form['custom_fields'][$custom_field->key]['#options'] = $options;

      // Checkboxes take an array as their default value,
      // unlike the other field options.
      // Split on commas and replace the single string
      // that we added prior.
      if ($field_type === 'checkboxes') {
        $form['custom_fields'][$custom_field->key]['#default_value'] = explode(',', $custom_field->defaultValue);
      }
    }
  }

}
