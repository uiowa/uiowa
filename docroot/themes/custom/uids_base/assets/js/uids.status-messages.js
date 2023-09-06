/**
 * @file
 * UIDS status messages behaviors.
 */

(function ($, Drupal) {
  'use strict';
  /**
   * Close any dismissible alerts on button click.
   */
  Drupal.behaviors.uidsStatusMessages = {
    attach: function (context, settings) {
      $('.alert--dismissible').once('uidsStatusMessages').each(function() {
        $("button[data-dismiss='alert']", this).click(function(e) {
          $(this).parent().hide();
        });
      });
    }
  };

} (jQuery, Drupal));
