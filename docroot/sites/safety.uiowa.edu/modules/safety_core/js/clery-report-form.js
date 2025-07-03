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

  Drupal.behaviors.csaReportFormValidation = {
    attach: function (context, settings) {

      // Function to attach validation to required fields.
      function attachValidationToRequiredFields() {
        const requiredFields = context.querySelectorAll('[required="required"]');

        requiredFields.forEach(function (field) {
          // Skip if already processed to avoid duplicate listeners.
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

      // Initial attachment.
      attachValidationToRequiredFields();

      // Re-attach when CSA checkbox changes (after states API updates required attributes).
      const csaCheckbox = once('csa-validation-trigger', '[name="is_reporter_csa"]', context);
      csaCheckbox.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
          // Small delay to let Drupal's states API update the DOM.
          setTimeout(attachValidationToRequiredFields, 50);
        });
      });
    },
  };
})(Drupal, once);
