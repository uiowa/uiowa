<?php

namespace Drupal\safety_core\Form;

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
    return new static($container->get("safety_core.clery_controller"));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "csa_report_form";
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form["#cache"] = ["max-age" => 0];
    $form["#prefix"] = '<div class="container">';
    $form["#suffix"] = "</div>";

    // Add CSS library if needed.
    $form["#attached"]["library"][] = "safety_core/csa-report-form";

    // Geography Filters Fieldset.
    $form["geography_filters"] = [
      "#type" => "fieldset",
      "#title" => $this->t("Geography Filters"),
      "#description" => $this->t(
        "Select a campus and type to load available geographies."
      ),
    ];

    $form["geography_filters"]["campus_filter"] = [
      "#type" => "hidden",
      "#value" => 3,
    ];

    $form["geography_filters"]["geography_type_filter"] = [
      "#type" => "select",
      "#title" => $this->t("Geography Type"),
      "#required" => TRUE,
      "#options" => $this->getGeographyTypeOptions(),
      "#empty_option" => $this->t("Select a type"),
      "#ajax" => [
        "callback" => "::updateGeographyCallback",
        "wrapper" => "geography-wrapper",
        "progress" => [
          "type" => "throbber",
        ],
      ],
    ];

    // Incident Details Fieldset.
    $form["incident_details"] = [
      "#type" => "fieldset",
      "#title" => $this->t("Incident Details"),
    ];

    $form["incident_details"]["date_offense_reported"] = [
      "#type" => "date",
      "#title" => $this->t("Date Reported"),
      "#required" => TRUE,
    ];

    $form["incident_details"]["time_offense_reported"] = [
      "#required" => TRUE,
      "#type" => "textfield",
      "#title" => $this->t("Time Reported"),
      "#attributes" => [
        "class" => ["time-input"],
      ],
    ];

    $form["incident_details"]["geography_wrapper"] = [
      "#type" => "container",
      "#attributes" => ["id" => "geography-wrapper"],
    ];

    $form["incident_details"]["geography_wrapper"]["geography_id"] = [
      "#type" => "select",
      "#title" => $this->t("Geography"),
      "#required" => TRUE,
      "#options" => [],
      "#empty_option" => $this->t("Select geography type first"),
      "#disabled" => TRUE,
      "#description" => $this->t(
        "Select a geography type above to load available geographies."
      ),
      // Add validation to ensure we catch empty submissions.
      "#element_validate" => [[$this, "validateGeographyId"]],
      "#states" => [
        "required" => [
          ':input[name="geography_type_filter"]' => ["!value" => ""],
        ],
        "enabled" => [
          ':input[name="geography_type_filter"]' => ["!value" => ""],
        ],
      ],
    ];

    $form["incident_details"]["specific_location"] = [
      "#type" => "textfield",
      "#title" => $this->t("Specific Location"),
      "#placeholder" => $this->t("e.g., Room 101, Main Hall"),
    ];

    $form["incident_details"]["description"] = [
      "#type" => "textarea",
      "#title" => $this->t("Description of Incident"),
      "#rows" => 4,
      "#placeholder" => $this->t(
        "Provide a detailed description of the incident..."
      ),
    ];

    // Occurrence Date & Time Fieldset.
    $form["occurrence"] = [
      "#type" => "fieldset",
      "#title" => $this->t("Occurrence Date & Time"),
      "#description" => $this->t(
        "Specify if the date and time of the occurrence are known, a range, or unknown."
      ),
    ];

    $form["occurrence"]["occurrence_date_type"] = [
      "#type" => "select",
      "#title" => $this->t("Occurrence Date"),
      "#options" => [
        "unknown" => $this->t("Unknown"),
        "exact" => $this->t("Exact Date"),
        "range" => $this->t("Date Range"),
      ],
      "#default_value" => "unknown",
      "#ajax" => [
        "callback" => "::occurrenceFieldsCallback",
        "wrapper" => "occurrence-fields-wrapper",
        "progress" => [
          "type" => "throbber",
        ],
      ],
    ];

    $form["occurrence"]["occurrence_time_type"] = [
      "#type" => "select",
      "#title" => $this->t("Occurrence Time"),
      "#options" => [
        "unknown" => $this->t("Unknown"),
        "exact" => $this->t("Exact Time"),
        "range" => $this->t("Time Range"),
      ],
      "#default_value" => "unknown",
      "#states" => [
        "disabled" => [
          ':input[name="occurrence_date_type"]' => ["value" => "unknown"],
        ],
      ],
      "#ajax" => [
        "callback" => "::occurrenceFieldsCallback",
        "wrapper" => "occurrence-fields-wrapper",
        "progress" => [
          "type" => "throbber",
        ],
      ],
    ];

    $form["occurrence"]["occurrence_fields"] = [
      "#type" => "container",
      "#attributes" => ["id" => "occurrence-fields-wrapper"],
    ];

    $this->buildOccurrenceFields($form, $form_state);

    // Reporter Information Fieldset.
    $form["reporter"] = [
      "#type" => "fieldset",
      "#title" => $this->t("Reporter Information (Optional)"),
    ];

    $form["reporter"]["reporter_first_name"] = [
      "#type" => "textfield",
      "#title" => $this->t("First Name"),
    ];

    $form["reporter"]["reporter_last_name"] = [
      "#type" => "textfield",
      "#title" => $this->t("Last Name"),
    ];

    $form["reporter"]["reporter_email"] = [
      "#type" => "email",
      "#title" => $this->t("Email"),
    ];

    $form["reporter"]["reporter_phone"] = [
      "#type" => "tel",
      "#title" => $this->t("Phone"),
    ];

    $form["reporter"]["is_reporter_csa"] = [
      "#type" => "checkbox",
      "#title" => $this->t("The reporter is a Campus Security Authority (CSA)"),
    ];

    // Incident Contacts Fieldset.
    $form["contacts"] = [
      "#type" => "fieldset",
      "#title" => $this->t("Incident Contacts (Optional)"),
    ];

    $form["contacts"]["contacts_container"] = [
      "#type" => "container",
      "#attributes" => ["id" => "contacts-wrapper"],
      "#tree" => TRUE,
    ];

    // Build existing contacts.
    $num_contacts = $form_state->get("num_contacts");
    if ($num_contacts === NULL) {
      $num_contacts = 0;
      $form_state->set("num_contacts", $num_contacts);
    }

    for ($i = 0; $i < $num_contacts; $i++) {
      $this->buildContactForm($form, $form_state, $i);
    }

    $form["contacts"]["add_contact"] = [
      "#type" => "submit",
      "#value" => $this->t("Add Contact"),
      "#submit" => ["::addContactSubmit"],
      "#ajax" => [
        "callback" => "::contactsCallback",
        "wrapper" => "contacts-wrapper",
        "progress" => [
          "type" => "throbber",
        ],
      ],
      "#limit_validation_errors" => [],
    ];

    // Submit button.
    $form["actions"] = [
      "#type" => "actions",
    ];

    $form["actions"]["submit"] = [
      "#type" => "submit",
      "#value" => $this->t("Submit Report"),
      "#attributes" => ["class" => ["btn", "btn-primary"]],
      "#submit" => [[$this, "submitForm"]],
    ];

    return $form;
  }

}
