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
        let visitor_id = document.cookie.split('; ').find(row => row.startsWith('visitor_id')).split('=')[1];
        $('input[name="pardot_vistitor_id"]').val(visitor_id);
      })
    }
  };

} (jQuery, Drupal, once));
