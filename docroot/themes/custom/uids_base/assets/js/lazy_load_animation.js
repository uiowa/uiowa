(function ($, Drupal) {
  'use strict';
  Drupal.behaviors.nativeLazyLoadAnimation = {
    attach: function (context) {
      $(once('lazy-load-animation','img.lazyload')).on('load', function () {
        $(this).addClass('loaded');
      }).each(function () {
        if (this.complete) {
          $(this).trigger('load');
        }
      });
    }
  };

})(jQuery, Drupal);
