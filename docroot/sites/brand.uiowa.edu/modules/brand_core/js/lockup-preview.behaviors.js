/**
 * @file
 * Lockup Preview.
 */
(function ($, Drupal) {
    Drupal.behaviors.lockupPreview = {
        attach: function () {
            var lockupPreview = $("#lockup-preview");
            var primaryUnit = $("#edit-field-lockup-primary-unit-0-value");
            var subUnit = $("#edit-field-lockup-sub-unit-0-value");

            // Hide preview markup initially.
            lockupPreview.hide();

            // Show preview and inject content based on existing input or live input.
            if (primaryUnit.val() !== "") {
                $("#lockup-preview").show();
                $(".lockup-stacked .primary-unit").text(primaryUnit.val());
                $(".lockup-horizontal .primary-unit").text(primaryUnit.val());
            }
            if (subUnit.val() !== "") {
                $("#lockup-preview").show();
                $(".lockup-stacked .sub-unit").text(subUnit.val());
                $(".lockup-horizontal .sub-unit").text(subUnit.val());
            }
            primaryUnit.on("input", function (event) {
                $("#lockup-preview").show();
                $(".lockup-stacked .primary-unit").text(event.target.value);
                $(".lockup-horizontal .primary-unit").text(event.target.value);
            });
            subUnit.on("input", function (event) {
                $("#lockup-preview").show();
                $(".lockup-stacked .sub-unit").text(event.target.value);
                $(".lockup-horizontal .sub-unit").text(event.target.value);
            });
        }
    };
})(jQuery, Drupal);
