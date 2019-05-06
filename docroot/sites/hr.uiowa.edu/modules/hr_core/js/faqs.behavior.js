/**
 * @file
 * FAQs.
 */
(function ($, Drupal) {
    Drupal.behaviors.faqs = {
        attach: function (context, setting) {
            $('.paragraph--type--faqs', context).once('faqs').each(function () {
                var accordionID = drupalSettings.hr_core.faqsJS.accordion_id;
                $(this).find('.view-content').attr('id', 'accordion-' + accordionID);
                $(this).find('.collapse').attr('data-parent', '#accordion-' + accordionID);
            });
        }
    };
})(jQuery, Drupal);