/**
 * @file
 * tippie_core behaviors.
 */

(function ($, Drupal, once) {

  'use strict';

  /**
   * Behavior description.
   */
  Drupal.behaviors.pardot = {
    attach: function (context, settings) {
      once('pardot', 'html', context).forEach( function (element) {
        console.log('It works!');
      })
    }
  };

} (jQuery, Drupal, once));
