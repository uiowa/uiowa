/**
 * @file
 * UIDS status messages behaviors.
 */

(function ($, Drupal, once) {
  'use strict';
  /**
   * Close any dismissible alerts on button click.
   */
  Drupal.behaviors.uidsStatusMessages = {
    attach: function (context, settings) {
      $(once('uidsStatusMessages', '.alert--dismissible')).each(function() {
        $("button[data-dismiss='alert']", this).click(function(e) {
          $(this).parent().hide();
        });
      });
    }
  };

} (jQuery, Drupal, once));
