(function (Drupal, once) {

  'use strict';

  // Behaviors for banner video and background options.
  Drupal.behaviors.bannerBlock = {
    attach: function (context) {
      // Video autoplay handling
      // We target the .media-library-widget class because there are not a lot of
      // good choices for classes/IDs to target in the inline block form.
      once('media-form-attach', '.media-library-widget', context).forEach(function (element) {
        // Check that we can access the next field.
        const checkbox_wrapper = context.querySelector('div[data-drupal-selector$="autoplay-wrapper"]');
        if (checkbox_wrapper) {
          // Check if the referenced media is a video.
          const mediaTypeVideo = context.querySelector('.media--video');

          if (mediaTypeVideo) {
            // Show the autoplay field.
            checkbox_wrapper.classList.remove('js-hide');
            checkbox_wrapper.removeAttribute('tabindex');
            checkbox_wrapper.removeAttribute('aria-hidden');
          } else {
            // Hide the autoplay field.
            checkbox_wrapper.classList.add('js-hide');
            checkbox_wrapper.tabIndex = -1;
            checkbox_wrapper.setAttribute('aria-hidden', 'true');
          }
        }
      });

      // Background type handling.
      // Target the background type radio buttons.
      once('background-type-handler', 'input[name="settings[block_form][background_type]"]', context).forEach(function () {
        const backgroundTypeInputs = context.querySelectorAll('input[name="settings[block_form][background_type]"]');
        const mediaOverlay = context.querySelector('select[name="layout_builder_style_media_overlay_duplicate"]');
        const overlayCheckboxes = context.querySelectorAll('input[name^="layout_builder_style_banner_gradient"]');
        const adjustGradientCheckbox = context.querySelector('input[name="adjust_gradient_midpoint"]');
        const gradientMidpointRadios = context.querySelectorAll('input[name="settings[block_form][field_styles_gradient_midpoint]"]');
        const backgroundStyleSelect = context.querySelector('select[name="layout_builder_style_background"]');

        // Handle changes in the background type.
        function handleBackgroundChange() {
          const checkedInput = context.querySelector(
            'input[name="settings[block_form][background_type]"]:checked',
          );

          if (checkedInput && checkedInput.value !== 'media') {
            // Clear the overlay dropdown.
            if (mediaOverlay) {
              mediaOverlay.value = '';
            }
            // Uncheck all gradient checkboxes.
            overlayCheckboxes.forEach(function (checkbox) {
              checkbox.checked = false;
            });
            // Uncheck adjust gradient midpoint checkbox and trigger change for States API.
            if (adjustGradientCheckbox) {
              adjustGradientCheckbox.checked = false;
              adjustGradientCheckbox.dispatchEvent(
                new Event('change', { bubbles: true }),
              );
            }
            // Clear gradient midpoint radio buttons.
            gradientMidpointRadios.forEach(function (radio) {
              radio.checked = false;
            });
          } else if (checkedInput && checkedInput.value === 'media') {
            // Clear the background style when media is selected.
            if (backgroundStyleSelect) {
              backgroundStyleSelect.value = "";
            }
          }
        }

        // Handle media overlay changes to auto-set gradient midpoint.
        function handleMediaOverlayChange() {
          if (mediaOverlay && gradientMidpointRadios.length > 0) {
            const overlayValue = mediaOverlay.value;

            // Check if any radio is currently selected.
            const currentlySelected = context.querySelector(
              'input[name="settings[block_form][field_styles_gradient_midpoint]"]:checked',
            );

            // Only auto-set if no option is currently selected.
            if (!currentlySelected && overlayValue) {
              let midpointValue = "40%";

              if (overlayValue === "media_overlay_left_to_right") {
                midpointValue = "70%";
              }

              // Find and select the radio button.
              const targetRadio = context.querySelector(
                `input[name="settings[block_form][field_styles_gradient_midpoint]"][value="${midpointValue}"]`,
              );
              if (targetRadio) {
                targetRadio.checked = true;
              }
            } else if (!overlayValue) {
              // Clear midpoint when overlay is cleared.
              gradientMidpointRadios.forEach(function (radio) {
                radio.checked = false;
              });
            }
          }
        }

        // Bind change event to media overlay dropdown.
        if (mediaOverlay) {
          mediaOverlay.addEventListener("change", handleMediaOverlayChange);
        }

        // Bind change event to all background type radio buttons.
        backgroundTypeInputs.forEach(function (input) {
          input.addEventListener('change', handleBackgroundChange);
        });

        handleBackgroundChange();
        handleMediaOverlayChange();
      });
    },
  };

})(Drupal, once);
