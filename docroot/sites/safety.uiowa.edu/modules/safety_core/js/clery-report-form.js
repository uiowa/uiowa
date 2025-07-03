(function (Drupal, once) {
  Drupal.behaviors.setTimeInputType = {
    attach: function (context, settings) {
      // Turn text fields into time fields.
      const timeInputs = context.querySelectorAll(".time-input");
      timeInputs.forEach(function (input) {
        input.setAttribute("type", "time");
      });
    },
  };

  Drupal.behaviors.csaReportFormClear = {
    attach: function (context, settings) {
      // Clear date fields when date type switches to "unknown".
      const dateTypeSelects = once(
        "date-clear",
        '[name="occurrence_date_type"]',
        context,
      );
      dateTypeSelects.forEach(function (select) {
        select.addEventListener("change", function () {
          if (this.value === "unknown") {
            // Clear all date-related fields.
            const dateOffenseOccured = document.querySelector(
              '[name="date_offense_occured"]',
            );
            const dateStart = document.querySelector('[name="date_start"]');
            const dateEnd = document.querySelector('[name="date_end"]');

            if (dateOffenseOccured) dateOffenseOccured.value = "";
            if (dateStart) dateStart.value = "";
            if (dateEnd) dateEnd.value = "";

            // Also reset and clear time type and time fields.
            const timeTypeSelect = document.querySelector(
              '[name="occurrence_time_type"]',
            );
            if (timeTypeSelect) {
              timeTypeSelect.value = "unknown";
              timeTypeSelect.dispatchEvent(new Event("change"));
            }
          }
        });
      });

      // Clear time fields when time type switches to "unknown".
      const timeTypeSelects = once(
        "time-clear",
        '[name="occurrence_time_type"]',
        context,
      );
      timeTypeSelects.forEach(function (select) {
        select.addEventListener("change", function () {
          if (this.value === "unknown") {
            // Clear all time-related fields.
            const exactTimeOccured = document.querySelector(
              '[name="exact_time_occured"]',
            );
            const timeStart = document.querySelector('[name="time_start"]');
            const timeEnd = document.querySelector('[name="time_end"]');

            if (exactTimeOccured) exactTimeOccured.value = "";
            if (timeStart) timeStart.value = "";
            if (timeEnd) timeEnd.value = "";
          }
        });
      });
    },
  };

  Drupal.behaviors.csaReportFormValidation = {
    attach: function (context, settings) {

      // Function to attach validation to required fields
      function attachValidationToRequiredFields() {
        const requiredFields = context.querySelectorAll('[required="required"]');

        requiredFields.forEach(function (field) {
          // Skip if already processed to avoid duplicate listeners
          if (field.dataset.validationAttached) return;
          field.dataset.validationAttached = 'true';

          // Listen for HTML5 invalid events.
          field.addEventListener("invalid", function () {
            this.classList.add("error");
          });

          // Remove error class.
          field.addEventListener("input", function () {
            if (this.validity.valid) {
              this.classList.remove("error");
            }
          });

          field.addEventListener("change", function () {
            if (this.validity.valid) {
              this.classList.remove("error");
            }
          });
        });
      }

      // Initial attachment
      attachValidationToRequiredFields();

      // Re-attach when CSA checkbox changes (after states API updates required attributes)
      const csaCheckbox = once('csa-validation-trigger', '[name="is_reporter_csa"]', context);
      csaCheckbox.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
          // Small delay to let Drupal's states API update the DOM
          setTimeout(attachValidationToRequiredFields, 50);
        });
      });
    },
  };
})(Drupal, once);
