<?php

namespace Drupal\safety_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\safety_core\Controller\CleryController;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Clery Report Form.
 */
class CleryReportForm extends FormBase {
  use StringTranslationTrait;

  /**
   * The Clery controller service.
   *
   * @var \Drupal\safety_core\Controller\CleryController
   */
  protected $cleryController;

  /**
   * Constructs a new CleryReportForm.
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
    return 'clery_report_form';
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

    $form['#attached']['library'][] = 'safety_core/clery-report-form';

    // Incident Details Fieldset.
    $form['incident_details'] = [
      '#type' => 'fieldset',
      '#title' => '<span class="headline headline--serif headline--underline h5">' . $this->t('Incident Details') . '</span>',
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
      '#title' => '<span class="headline headline--serif headline--underline h5">' . $this->t('Occurrence Date & Time') . '</span>',
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
      '#title' => '<span class="headline headline--serif headline--underline h5">' . $this->t('Reporter Information') . '</span>',
    ];

    $form['reporter']['reporter_first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#states' => [
        'required' => [
          ':input[name="is_reporter_csa"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['reporter']['reporter_last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#states' => [
        'required' => [
          ':input[name="is_reporter_csa"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['reporter']['reporter_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#states' => [
        'required' => [
          ':input[name="is_reporter_csa"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['reporter']['reporter_phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#states' => [
        'required' => [
          ':input[name="is_reporter_csa"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['reporter']['is_reporter_csa'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('The reporter is a Campus Security Authority (CSA)'),
    ];

    // Incident Contacts Fieldset.
    $form['contacts'] = [
      '#type' => 'fieldset',
      '#title' => '<span class="headline headline--serif headline--underline h5">' . $this->t('Individuals involved') . '</span>',
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
      '#value' => $this->t('Add Individual'),
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
      '#title' => '<span class="headline h6">' . $this->t('Individual @num', ['@num' => $index + 1]) . '</span>',
      '#collapsible' => FALSE,
    ];

    $contact = &$form['contacts']['contacts_container'][$index];

    $contact['remove'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove Individual'),
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

      // Validate required fields.
      if (empty($form_values['date_offense_reported'])) {
        $form_state->setErrorByName('date_offense_reported', $this->t('Date offense reported is required.'));
      }

      if (empty($form_values['time_offense_reported'])) {
        $form_state->setErrorByName('time_offense_reported', $this->t('Time offense reported is required.'));
      }

      // Validate time format for time_offense_reported.
      if (!empty($form_values['time_offense_reported'])) {
        $this->validateTimeFormat($form_state, 'time_offense_reported', $form_values['time_offense_reported']);
      }

      // Validate occurrence date/time fields based on selected types.
      $this->validateOccurrenceFields($form_state, $form_values);

      // Validate contacts if present.
      if (isset($form_values['contacts_container']) && is_array($form_values['contacts_container'])) {
        foreach ($form_values['contacts_container'] as $index => $contact_data) {
          if (!empty($contact_data['first_name']) || !empty($contact_data['last_name'])) {
            if (empty($contact_data['first_name'])) {
              $form_state->setErrorByName("contacts_container[$index][first_name]", $this->t('Contact @num: First name is required.', ['@num' => $index + 1]));
            }
            if (empty($contact_data['last_name'])) {
              $form_state->setErrorByName("contacts_container[$index][last_name]", $this->t('Contact @num: Last name is required.', ['@num' => $index + 1]));
            }
          }

          // Validate email format if provided.
          if (!empty($contact_data['email']) && !filter_var($contact_data['email'], FILTER_VALIDATE_EMAIL)) {
            $form_state->setErrorByName("contacts_container[$index][email]", $this->t('Contact @num: Please enter a valid email address.', ['@num' => $index + 1]));
          }

          // Validate phone format if provided.
          if (!empty($contact_data['phone'])) {
            $this->validatePhoneFormat($form_state, "contacts_container[$index][phone]", $contact_data['phone'], $index + 1);
          }
        }
      }

      // Validate CSA reporter fields if CSA checkbox is checked.
      if (!empty($form_values['is_reporter_csa'])) {
        $required_csa_fields = [
          'reporter_first_name' => 'First Name',
          'reporter_last_name' => 'Last Name',
          'reporter_email' => 'Email',
          'reporter_phone' => 'Phone',
        ];

        foreach ($required_csa_fields as $field_name => $field_label) {
          if (empty($form_values[$field_name])) {
            $form_state->setErrorByName($field_name, $this->t('@field is required when the reporter is a Campus Security Authority (CSA).', ['@field' => $field_label]));
          }
        }

        // Validate CSA reporter email format.
        if (!empty($form_values['reporter_email']) && !filter_var($form_values['reporter_email'], FILTER_VALIDATE_EMAIL)) {
          $form_state->setErrorByName('reporter_email', $this->t('Please enter a valid email address.'));
        }

        // Validate CSA reporter phone format.
        if (!empty($form_values['reporter_phone'])) {
          $this->validatePhoneFormat($form_state, 'reporter_phone', $form_values['reporter_phone']);
        }
      }

    }
  }

  /**
   * Validate time format (HH:MM in 24-hour format).
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_name
   *   The field name.
   * @param string $time_value
   *   The time value to validate.
   */
  protected function validateTimeFormat(FormStateInterface $form_state, string $field_name, string $time_value): void {
    // Allow time format HH:MM (24-hour) or H:MM.
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time_value)) {
      $form_state->setErrorByName($field_name, $this->t('Please enter a valid time in HH:MM format (24-hour).'));
    }
  }

  /**
   * Validate phone format.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_name
   *   The field name.
   * @param string $phone_value
   *   The phone value to validate.
   * @param int|null $contact_num
   *   The contact number for error messages.
   */
  protected function validatePhoneFormat(FormStateInterface $form_state, string $field_name, string $phone_value, int $contact_num = NULL): void {
    // Remove all non-numeric characters for validation.
    $phone_digits = preg_replace('/[^0-9]/', '', $phone_value);
    
    // Check if it's a valid US phone number (10 digits).
    if (strlen($phone_digits) !== 10) {
      $error_message = $contact_num 
        ? $this->t('Contact @num: Please enter a valid 10-digit phone number.', ['@num' => $contact_num])
        : $this->t('Please enter a valid 10-digit phone number.');
      $form_state->setErrorByName($field_name, $error_message);
    }
  }

  /**
   * Validate occurrence date and time fields based on selected types.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form_values
   *   The form values.
   */
  protected function validateOccurrenceFields(FormStateInterface $form_state, array $form_values): void {
    $date_type = $form_values['occurrence_date_type'] ?? 'unknown';
    $time_type = $form_values['occurrence_time_type'] ?? 'unknown';

    // Validate based on date type.
    if ($date_type === 'exact') {
      if (empty($form_values['date_offense_occured'])) {
        $form_state->setErrorByName('date_offense_occured', $this->t('Date occurred is required when date type is set to "Exact Date".'));
      }
    }
    elseif ($date_type === 'range') {
      if (empty($form_values['date_start'])) {
        $form_state->setErrorByName('date_start', $this->t('Start date is required when date type is set to "Date Range".'));
      }
      if (empty($form_values['date_end'])) {
        $form_state->setErrorByName('date_end', $this->t('End date is required when date type is set to "Date Range".'));
      }

      // Validate that start date is before end date.
      if (!empty($form_values['date_start']) && !empty($form_values['date_end'])) {
        $start_date = new \DateTime($form_values['date_start']);
        $end_date = new \DateTime($form_values['date_end']);
        if ($start_date > $end_date) {
          $form_state->setErrorByName('date_end', $this->t('End date must be after start date.'));
        }
      }
    }

    // Validate based on time type (only if date type is not unknown).
    if ($date_type !== 'unknown') {
      if ($time_type === 'exact') {
        if (empty($form_values['exact_time_occured'])) {
          $form_state->setErrorByName('exact_time_occured', $this->t('Time occurred is required when time type is set to "Exact Time".'));
        }
        elseif (!empty($form_values['exact_time_occured'])) {
          $this->validateTimeFormat($form_state, 'exact_time_occured', $form_values['exact_time_occured']);
        }
      }
      elseif ($time_type === 'range') {
        if (empty($form_values['time_start'])) {
          $form_state->setErrorByName('time_start', $this->t('Start time is required when time type is set to "Time Range".'));
        }
        elseif (!empty($form_values['time_start'])) {
          $this->validateTimeFormat($form_state, 'time_start', $form_values['time_start']);
        }

        if (empty($form_values['time_end'])) {
          $form_state->setErrorByName('time_end', $this->t('End time is required when time type is set to "Time Range".'));
        }
        elseif (!empty($form_values['time_end'])) {
          $this->validateTimeFormat($form_state, 'time_end', $form_values['time_end']);
        }

        // Validate that start time is before end time (when on same date).
        if (!empty($form_values['time_start']) && !empty($form_values['time_end'])) {
          $start_time = \DateTime::createFromFormat('H:i', $form_values['time_start']);
          $end_time = \DateTime::createFromFormat('H:i', $form_values['time_end']);
          
          // Only validate time order if dates are the same or if it's an exact date.
          $same_date = FALSE;
          if ($date_type === 'exact') {
            $same_date = TRUE;
          }
          elseif ($date_type === 'range' && !empty($form_values['date_start']) && !empty($form_values['date_end'])) {
            $same_date = $form_values['date_start'] === $form_values['date_end'];
          }

          if ($same_date && $start_time && $end_time && $start_time >= $end_time) {
            $form_state->setErrorByName('time_end', $this->t('End time must be after start time.'));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $form_values = $form_state->getValues();

      $request_body = $this->cleryController->buildIncidentRequestData($form_values);

      // Log json for testing.
      \Drupal::logger('csa_report')->notice('Request body: @body', [
        '@body' => json_encode($request_body, JSON_PRETTY_PRINT),
      ]);

      // API submission.
      $result = $this->cleryController->submitIncidentReport($request_body);
      $this->messenger()->addMessage(
        $this->t('Incident reported successfully!')
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

}
