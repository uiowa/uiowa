/**
 * @file
 * Display palliative-grad-map.
 */

(($, Drupal, drupalSettings) => {
  // Attach palliative-grad-map behavior.
  Drupal.behaviors.palliativeGradMap = {
    attach: (context, settings) => {
      $('.block-pharmacy-core-palliative-care-map', context).once('palliativeGradMap').each(() => {})
    }
  };
})(jQuery, Drupal, drupalSettings);
