/**
 * @file
 * FAQs.
 */
(function ($, Drupal, drupalSettings, once) {
    Drupal.behaviors.faqs = {
        attach: function (context, settings) {
            $(once('faqs', '.paragraph--type--faqs', context)).each(function () {
                var accordionID = drupalSettings.hr_core.faqsJS.accordion_id;
                $(this).find('.view-content').attr('id', 'accordion-' + accordionID);
                $(this).find('.collapse').attr('data-parent', '#accordion-' + accordionID);
            });
        }
    };
})(jQuery, Drupal, drupalSettings, once);
