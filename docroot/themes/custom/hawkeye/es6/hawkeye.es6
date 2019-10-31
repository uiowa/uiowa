/**
 * @file
 * Hawkeye behaviors.
 */

(($, Drupal) => {
  Drupal.behaviors.hawkeye = {
    attach(context, settings) {
      console.log('It works!');
    }
  };

})(jQuery, Drupal);
