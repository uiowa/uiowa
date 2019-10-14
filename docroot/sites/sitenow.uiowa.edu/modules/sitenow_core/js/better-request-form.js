/**
 * @file
 * Request form enhancements.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Behavior description.
   */
  Drupal.behaviors.betterRequestForm = {
    attach: function (context, settings) {
      $('#edit-request-type input', context).once('betterRequestForm').click(function(e) {
        let val = $(this).val();

        if (val === 'New') {
          $('#edit-uri--description').html( Drupal.t('The URL you would like for the new site.'));
        }
        else if (val === 'Existing') {
          $('#edit-uri--description').html(Drupal.t('The URL of your existing site.'));
        }
      })
    }
  };

} (jQuery, Drupal));
