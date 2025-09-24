/**
 * @file
 * JavaScript for the slideshow block.
 */

(function (Drupal, once) {
  Drupal.behaviors.signageReducedMotionWarning = {
    attach: function (context, settings) {
      once('reduced-motion-warning', 'html', context).forEach(() => {
        const isReduced = window.matchMedia(`(prefers-reduced-motion: reduce)`) === true || window.matchMedia(`(prefers-reduced-motion: reduce)`).matches === true;
        if (isReduced) {
          const messages = new Drupal.Message();
          messages.add("We have detected that your operating system has the 'reduced motion' setting turned on and the slideshow may not function as expected. For more information, see the <a href=\"https://its.uiowa.edu/services/digital-signage/sign-slideshow-not-rotating-between-slides\">support article</a>.", {type: 'warning'});
        }
      });
    }
  }
})(Drupal, once);
