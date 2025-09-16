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
          console.log('Digital Signage: Reduced motion is enabled.');
          const messages = new Drupal.Message();
          messages.add("We have detected that your operating system has the 'prefers-reduced-motion' setting turned on. This may affect your ability to see the working slideshow. This setting will not affect how the digital sign is shown on displays.", {type: 'warning'});
        }
      });
    }
  }
})(Drupal, once);
