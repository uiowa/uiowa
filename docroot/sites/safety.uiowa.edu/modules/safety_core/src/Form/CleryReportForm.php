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
      '#type' => 'datetime',
      '#title' => $this->t('Time Reported'),
      '#date_date_element' => 'none',
      '#date_time_element' => 'time',
      '#attributes' => [
        'step' => '60',
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

    // Exact date container (shown when date_type = 'exact').
    $form['occurrence']['exact_date_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="occurrence_date_type"]' => ['value' => 'exact'],
        ],
      ],
    ];

    $form['occurrence']['exact_date_container']['date_offense_occured'] = [
      '#type' => 'date',
      '#title' => $this->t('Date Occurred'),
    ];

    // Exact time container (shown when time_type = 'exact').
    $form['occurrence']['exact_time_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="occurrence_time_type"]' => ['value' => 'exact'],
        ],
      ],
    ];

    $form['occurrence']['exact_time_container']['exact_time_occured'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Time Occurred'),
      '#date_date_element' => 'none',
      '#date_time_element' => 'time',
      '#attributes' => [
        'step' => '60',
      ],
    ];

    // Start date container (shown when date_type = 'range').
    $form['occurrence']['start_date_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="occurrence_date_type"]' => ['value' => 'range'],
        ],
      ],
    ];

    $form['occurrence']['start_date_container']['date_start'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
    ];

    // Start time container (shown when time_type = 'range').
    $form['occurrence']['start_time_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="occurrence_time_type"]' => ['value' => 'range'],
        ],
      ],
    ];

    $form['occurrence']['start_time_container']['time_start'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Start Time'),
      '#date_date_element' => 'none',
      '#date_time_element' => 'time',
      '#attributes' => [
        'step' => '60',
      ],
    ];

    // End date container (shown when date_type = 'range').
    $form['occurrence']['end_date_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="occurrence_date_type"]' => ['value' => 'range'],
        ],
      ],
    ];

    $form['occurrence']['end_date_container']['date_end'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
    ];

    // End time container (shown when time_type = 'range').
    $form['occurrence']['end_time_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="occurrence_time_type"]' => ['value' => 'range'],
        ],
      ],
    ];

    $form['occurrence']['end_time_container']['time_end'] = [
      '#type' => 'datetime',
      '#title' => $this->t('End Time'),
      '#date_date_element' => 'none',
      '#date_time_element' => 'time',
      '#attributes' => [
        'step' => '60',
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

    // Initialize contacts data in form state if not set.
    $contacts_data = $form_state->get('contacts_data');
    if ($contacts_data === NULL) {
      $contacts_data = [];
      $form_state->set('contacts_data', $contacts_data);
    }

    // Build existing contacts.
    foreach ($contacts_data as $contact_id => $contact_data) {
      $this->buildContactForm($form, $form_state, $contact_id, $contact_data);
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
    $contact_id,
    array $contact_data = [],
  ) {
    $contact_number = array_search($contact_id, array_keys($form_state->get('contacts_data'))) + 1;

    $form['contacts']['contacts_container'][$contact_id] = [
      '#type' => 'fieldset',
      '#title' => '<span class="headline h6">' . $this->t('Individual @num', ['@num' => $contact_number]) . '</span>',
      '#collapsible' => FALSE,
    ];

    $contact = &$form['contacts']['contacts_container'][$contact_id];

    $contact['remove'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove Individual'),
      '#name' => 'remove_contact_' . $contact_id,
    ] + $this->buildAjaxButton('::contactsCallback', 'contacts-wrapper', ['::removeContactSubmit']);

    $contact['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
      '#default_value' => $contact_data['first_name'] ?? '',
    ];

    $contact['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
      '#default_value' => $contact_data['last_name'] ?? '',
    ];

    $contact['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $contact_data['email'] ?? '',
    ];

    $contact['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#default_value' => $contact_data['phone'] ?? '',
    ];

    $contact['date_of_birth'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Birth'),
      '#default_value' => $contact_data['date_of_birth'] ?? '',
    ];

  }

  /**
   * Submit handler for adding a contact.
   */
  public function addContactSubmit(
    array &$form,
    FormStateInterface $form_state,
  ) {
    // Preserve existing contact data.
    $this->preserveContactData($form_state);

    // Add new contact with unique ID.
    $contacts_data = $form_state->get('contacts_data');
    $new_contact_id = 'contact_' . time() . '_' . mt_rand();
    $contacts_data[$new_contact_id] = [];
    $form_state->set('contacts_data', $contacts_data);

    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a contact.
   */
  public function removeContactSubmit(
    array &$form,
    FormStateInterface $form_state,
  ) {
    // Preserve existing contact data.
    $this->preserveContactData($form_state);

    $trigger = $form_state->getTriggeringElement();
    $contact_id = str_replace('remove_contact_', '', $trigger['#name']);

    $contacts_data = $form_state->get('contacts_data');
    if (isset($contacts_data[$contact_id])) {
      unset($contacts_data[$contact_id]);
      $form_state->set('contacts_data', $contacts_data);
    }

    $form_state->setRebuild();
  }

  /**
   * Preserve contact data from form values into form state.
   */
  protected function preserveContactData(FormStateInterface $form_state) {
    $contacts_data = $form_state->get('contacts_data');
    $contacts_values = $form_state->getValue('contacts_container', []);

    foreach ($contacts_data as $contact_id => $stored_data) {
      if (isset($contacts_values[$contact_id])) {
        $contacts_data[$contact_id] = array_merge($stored_data, $contacts_values[$contact_id]);
      }
    }

    $form_state->set('contacts_data', $contacts_data);
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
      // $result = $this->cleryController->submitIncidentReport($request_body);
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
