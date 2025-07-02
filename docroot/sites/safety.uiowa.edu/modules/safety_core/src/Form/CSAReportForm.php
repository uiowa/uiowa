<?php

namespace Drupal\safety_core\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\safety_core\Controller\CleryController;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CSA Report Form.
 */
class CSAReportForm extends FormBase {
  use StringTranslationTrait;

  /**
   * The Clery controller service.
   *
   * @var \Drupal\safety_core\Controller\CleryController
   */
  protected $cleryController;

  /**
   * Default campus ID.
   */
  const DEFAULT_CAMPUS_ID = 3;

  /**
   * Constructs a new CSAReportForm.
   *
   * @param \Drupal\safety_core\Controller\CleryController $clery_controller
   *   The Clery controller service.
   */
  public function __construct(CleryController $clery_controller) {
    $this->cleryController = $clery_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('safety_core.clery_controller'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'csa_report_form';
  }

  /**
   * {@inheritdoc}
   */
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $required_notice = '<p>Required fields are marked with an asterisk (<abbr class="req" title="required">*</abbr>).</p>';
    $form['#prefix'] = '<div class="container">' . $required_notice;
    $form['#suffix'] = '</div>';

    $form['#attached']['library'][] = 'safety_core/csa-report-form';

    // Incident Details Fieldset.
    $form['incident_details'] = [
      '#type' => 'fieldset',
      '#title' => '<span class="headline headline--serif headline--underline h5">' . $this->t('Incident Details') . '</span>',
    ];

    $form['incident_details']['campus_filter'] = [
      '#type' => 'hidden',
      '#value' => self::DEFAULT_CAMPUS_ID,
    ];

    $form['incident_details']['geography_type_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Location Type'),
      '#required' => TRUE,
      '#options' => $this->cleryController->getGeographyTypeOptions(),
      '#empty_option' => $this->t('Select a type'),
    ] + $this->buildAjaxSelect('::updateGeographyCallback', 'geography-wrapper');

    $form['incident_details']['geography_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'geography-wrapper'],
    ];

    $form['incident_details']['geography_wrapper']['geography_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Location'),
      '#required' => TRUE,
      '#options' => [],
      '#empty_option' => $this->t('Select location type first'),
      '#disabled' => TRUE,
      '#description' => $this->t(
        'Select a location type above to load available locations.'
      ),
      '#element_validate' => [[$this, 'validateGeographyId']],
      '#states' => [
        'required' => [
          ':input[name="geography_type_filter"]' => ['!value' => ''],
        ],
        'enabled' => [
          ':input[name="geography_type_filter"]' => ['!value' => ''],
        ],
      ],
    ];

    $form['incident_details']['date_offense_reported'] = [
      '#type' => 'date',
      '#title' => $this->t('Date Reported'),
      '#required' => TRUE,
    ];

    $form['incident_details']['time_offense_reported'] = [
      '#required' => TRUE,
      '#type' => 'textfield',
      '#title' => $this->t('Time Reported'),
      '#attributes' => [
        'class' => ['time-input'],
        'placeholder' => 'HH:MM (24-hour format)',
      ],
    ];

    $form['incident_details']['specific_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specific Location'),
      '#placeholder' => $this->t('e.g., Room 101, Main Hall'),
    ];

    $form['incident_details']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description of Incident'),
      '#rows' => 4,
      '#placeholder' => $this->t(
        'Provide a detailed description of the incident...'
      ),
    ];

    // Occurrence Date & Time Fieldset.
    $form['occurrence'] = [
      '#type' => 'fieldset',
      '#title' => '<span class="headline headline--serif headline--underline h5">' . $this->t('Occurrence Date & Time (Optional)') . '</span>',
      '#description' => $this->t(
        'Fill in the fields that apply to when the incident occurred.'
      ),
    ];

    // Date type selector.
    $form['occurrence']['occurrence_date_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Date Type'),
      '#options' => [
        'unknown' => $this->t('Unknown'),
        'exact' => $this->t('Exact Date'),
        'range' => $this->t('Date Range'),
      ],
      '#default_value' => 'unknown',
    ];

    // Time type selector.
    $form['occurrence']['occurrence_time_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Time Type'),
      '#options' => [
        'unknown' => $this->t('Unknown'),
        'exact' => $this->t('Exact Time'),
        'range' => $this->t('Time Range'),
      ],
      '#default_value' => 'unknown',
      '#states' => [
        'visible' => [
          ':input[name="occurrence_date_type"]' => ['!value' => 'unknown'],
        ],
      ],
    ];

    // Exact date (shown when date_type = 'exact').
    $form['occurrence']['date_offense_occured'] = [
      '#type' => 'date',
      '#title' => $this->t('Date Occurred'),
      '#states' => [
        'visible' => [
          ':input[name="occurrence_date_type"]' => ['value' => 'exact'],
        ],
      ],
    ];

    // Exact time (shown when time_type = 'exact').
    $form['occurrence']['exact_time_occured'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Time Occurred'),
      '#attributes' => [
        'class' => ['time-input'],
        'placeholder' => 'HH:MM (24-hour format)',
      ],
      '#states' => [
        'visible' => [
          ':input[name="occurrence_time_type"]' => ['value' => 'exact'],
        ],
      ],
    ];

    // Start date (shown when date_type = 'range').
    $form['occurrence']['date_start'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
      '#states' => [
        'visible' => [
          ':input[name="occurrence_date_type"]' => ['value' => 'range'],
        ],
      ],
    ];

    // Start time (shown when time_type = 'range').
    $form['occurrence']['time_start'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start Time'),
      '#attributes' => [
        'class' => ['time-input'],
        'placeholder' => 'HH:MM (24-hour format)',
      ],
      '#states' => [
        'visible' => [
          ':input[name="occurrence_time_type"]' => ['value' => 'range'],
        ],
      ],
    ];

    // End date (shown when date_type = 'range').
    $form['occurrence']['date_end'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      '#states' => [
        'visible' => [
          ':input[name="occurrence_date_type"]' => ['value' => 'range'],
        ],
      ],
    ];

    // End time (shown when time_type = 'range').
    $form['occurrence']['time_end'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End Time'),
      '#attributes' => [
        'class' => ['time-input'],
        'placeholder' => 'HH:MM (24-hour format)',
      ],
      '#states' => [
        'visible' => [
          ':input[name="occurrence_time_type"]' => ['value' => 'range'],
        ],
      ],
    ];

    // Reporter Information Fieldset.
    $form['reporter'] = [
      '#type' => 'fieldset',
      '#title' => '<span class="headline headline--serif headline--underline h5">' . $this->t('Reporter Information (Optional)') . '</span>',
    ];

    $form['reporter']['reporter_first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
    ];

    $form['reporter']['reporter_last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
    ];

    $form['reporter']['reporter_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
    ];

    $form['reporter']['reporter_phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
    ];

    $form['reporter']['is_reporter_csa'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('The reporter is a Campus Security Authority (CSA)'),
    ];

    // Incident Contacts Fieldset.
    $form['contacts'] = [
      '#type' => 'fieldset',
      '#title' => '<span class="headline headline--serif headline--underline h5">' . $this->t('Incident Contacts (Optional)') . '</span>',
    ];

    $form['contacts']['contacts_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'contacts-wrapper'],
      '#tree' => TRUE,
    ];

    // Build existing contacts.
    $num_contacts = $form_state->get('num_contacts');
    if ($num_contacts === NULL) {
      $num_contacts = 0;
      $form_state->set('num_contacts', $num_contacts);
    }

    for ($i = 0; $i < $num_contacts; $i++) {
      $this->buildContactForm($form, $form_state, $i);
    }

    $form['contacts']['add_contact'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Contact'),
    ] + $this->buildAjaxButton('::contactsCallback', 'contacts-wrapper', ['::addContactSubmit']);

    // Submit button.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit Report'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
      '#submit' => [[$this, 'submitForm']],
    ];

    return $form;
  }

  /**
   * Build contact form fields.
   */
  protected function buildContactForm(
    array &$form,
    FormStateInterface $form_state,
    $index,
  ) {
    $form['contacts']['contacts_container'][$index] = [
      '#type' => 'fieldset',
      '#title' => '<span class="headline headline--serif headline--underline h5">' . $this->t('Contact @num', ['@num' => $index + 1]) . '</span>',
      '#collapsible' => FALSE,
    ];

    $contact = &$form['contacts']['contacts_container'][$index];

    $contact['remove'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove Contact'),
      '#name' => 'remove_contact_' . $index,
    ] + $this->buildAjaxButton('::contactsCallback', 'contacts-wrapper', ['::removeContactSubmit']);

    $contact['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];

    $contact['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];

    $contact['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
    ];

    $contact['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
    ];

    $contact['date_of_birth'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Birth'),
    ];



    $contact['contact_roles'] = [
      '#type' => 'select',
      '#title' => $this->t('Contact Role(s)'),
      '#options' => $this->cleryController->getContactRoleOptions(),
      '#multiple' => TRUE,
    ];




  }



  /**
   * AJAX callback for updating geography options.
   */
  public function updateGeographyCallback(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $campus_id = 3;
    $geo_type_id = $form_state->getValue('geography_type_filter');
    $current_geography_id = $form_state->getValue('geography_id');

    $response = new AjaxResponse();

    if (empty($geo_type_id)) {
      $html = $this->buildGeographySelectHtml(
        [],
        'Select location type first',
        TRUE
      );
      $html .=
        '<div class="description">Select a location type above to load available locations.</div>';
    }
    else {
      try {
        $geography_options = $this->cleryController->getGeographyOptions(
          $campus_id,
          $geo_type_id
        );

        if (!empty($geography_options)) {
          $form_state->set('geography_options', $geography_options);

          $html = $this->buildGeographySelectHtml(
            $geography_options,
            'Select a location',
            FALSE,
            $current_geography_id
          );

          if (
            !empty($current_geography_id) &&
            isset($geography_options[$current_geography_id])
          ) {
            $response->addCommand(
              new InvokeCommand('#edit-geography-id', 'val', [
                $current_geography_id,
              ])
            );
            $form_state->setValue('geography_id', $current_geography_id);
          }
        }
        else {
          $html = $this->buildGeographySelectHtml(
            [],
            'No locations available',
            TRUE
          );
          $html .=
            '<div class="description">No locations found for this type.</div>';
        }
      }
      catch (\Exception $e) {
        $html = $this->buildGeographySelectHtml(
          [],
          'Error loading locations',
          TRUE
        );
        $html .=
          '<div class="description">An error occurred loading locations.</div>';
      }
    }

    $response->addCommand(new HtmlCommand('#geography-wrapper', $html));
    return $response;
  }

  /**
   * Helper method to build geography select HTML.
   */
  private function buildGeographySelectHtml(
    array $options,
    $empty_text,
    $disabled = FALSE,
    $selected_value = NULL,
  ) {
    $attributes = [
      'id' => 'edit-geography-id',
      'name' => 'geography_id',
      'class' => 'form-select',
      'required' => 'required',
    ];

    if ($disabled) {
      $attributes['disabled'] = 'disabled';
    }

    $html = '<select';
    foreach ($attributes as $attr => $value) {
      $html .= ' ' . $attr . '="' . htmlspecialchars($value) . '"';
    }
    $html .= '>';

    $html .= '<option value="">' . htmlspecialchars($empty_text) . '</option>';

    foreach ($options as $key => $label) {
      $selected =
        !empty($selected_value) && $selected_value == $key
          ? ' selected="selected"'
          : '';
      $html .=
        '<option value="' . htmlspecialchars($key) . '"' . $selected . '>';
      $html .= htmlspecialchars($label);
      $html .= '</option>';
    }

    $html .= '</select>';

    return $html;
  }

  /**
   * Submit handler for adding a contact.
   */
  public function addContactSubmit(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $num_contacts = $form_state->get('num_contacts');
    $form_state->set('num_contacts', $num_contacts + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a contact.
   */
  public function removeContactSubmit(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $trigger = $form_state->getTriggeringElement();
    $contact_index = (int) str_replace(
      'remove_contact_',
      '',
      $trigger['#name']
    );

    $num_contacts = $form_state->get('num_contacts');
    if ($num_contacts > 0) {
      $form_state->set('num_contacts', $num_contacts - 1);
    }

    $form_state->setRebuild();
  }

  /**
   * AJAX callback for contacts.
   */
  public function contactsCallback(
    array &$form,
    FormStateInterface $form_state,
  ) {
    return $form['contacts']['contacts_container'];
  }



  /**
   * Enhanced form validation.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $triggering_element = $form_state->getTriggeringElement();

    // Skip validation for AJAX requests.
    if ($triggering_element && isset($triggering_element['#ajax'])) {
      return;
    }

    // Only validate on actual form submission.
    if (
      $triggering_element &&
      ($triggering_element['#value'] ?? '') === 'Submit Report'
    ) {
      $form_values = $form_state->getValues();

      // Use controller's validation method.
      $validation_errors = $this->cleryController->validateIncidentData($form_values);

      if (!empty($validation_errors)) {
        foreach ($validation_errors as $error) {
          $form_state->setErrorByName('', $this->t($error));
        }
        return;
      }

    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $user_input = $form_state->getUserInput();
      $form_values = $form_state->getValues();

      if (
        !empty($user_input['geography_id']) &&
        empty($form_values['geography_id'])
      ) {
        $form_state->setValue('geography_id', $user_input['geography_id']);
        $form_values = $form_state->getValues();
      }

      $request_body = $this->cleryController->buildIncidentRequestData($form_values, self::DEFAULT_CAMPUS_ID);

      // Add this for debugging.
      \Drupal::logger('csa_report')->notice('Request body: @body', [
        '@body' => json_encode($request_body, JSON_PRETTY_PRINT),
      ]);

      // API submission.
      // $result = $this->cleryController->submitIncidentReport($request_body);
      $this->messenger()->addMessage(
        $this->t(
          'Incident reported successfully! (API submission disabled for testing)'
        )
      );

      // Redirect to form for complete reset.
      $form_state->setRedirect('<current>');
    }
    catch (\Exception $e) {
      $this->messenger()->addError(
        $this->t('Failed to report incident: @error', [
          '@error' => $e->getMessage(),
        ])
      );
    }
  }

  /**
   * Generic helper to create AJAX button configuration.
   *
   * @param string $callback
   *   The callback method name.
   * @param string $wrapper
   *   The wrapper ID for AJAX updates.
   * @param array $submit_handlers
   *   Array of submit handler method names.
   *
   * @return array
   *   AJAX button configuration array.
   */
  protected function buildAjaxButton($callback, $wrapper, array $submit_handlers = []) {
    return [
      '#ajax' => [
        'callback' => $callback,
        'wrapper' => $wrapper,
        'progress' => [
          'type' => 'throbber',
        ],
      ],
      '#submit' => $submit_handlers,
      '#limit_validation_errors' => [],
    ];
  }

  /**
   * Generic helper to create AJAX select configuration.
   *
   * @param string $callback
   *   The callback method name.
   * @param string $wrapper
   *   The wrapper ID for AJAX updates.
   *
   * @return array
   *   AJAX configuration array.
   */
  protected function buildAjaxSelect($callback, $wrapper) {
    return [
      '#ajax' => [
        'callback' => $callback,
        'wrapper' => $wrapper,
        'progress' => [
          'type' => 'throbber',
        ],
      ],
    ];
  }

  /**
   * Custom validation for geography ID field.
   */
  public function validateGeographyId(
    $element,
    FormStateInterface $form_state,
    $form,
  ) {
    $value = $element['#value'];

    $triggering_element = $form_state->getTriggeringElement();

    // Skip validation during AJAX calls.
    if ($triggering_element && isset($triggering_element['#ajax'])) {
      return;
    }

    // Validate only on actual form submission.
    if (
      $triggering_element &&
      isset($triggering_element['#value']) &&
      $triggering_element['#value'] === 'Submit Report'
    ) {
      if (empty($value) || $value === '' || $value === '0') {
        $form_state->setError($element, $this->t('Please select a location.'));
      }
    }
  }

}
