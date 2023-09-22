/**
 * @file
 * Request form enhancements.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Behavior description.
   */
  Drupal.behaviors.betterRequestForm = {
    attach: function (context, settings) {
      $(once('betterRequestForm', '#edit-request-type input', context)).click(function (e) {
        let val = $(this).val();

        if (val === 'New') {
          $('#edit-uri--description').html(Drupal.t('The URL you would like for the new site.'));
          $('#edit-additional-comments-or-questions--description').html(Drupal.t('Enter any questions or comments you have regarding this site request.'));
        }
        else if (val === 'Existing') {
          $('#edit-uri--description').html(Drupal.t('The URL of your existing site.'));
          $('#edit-additional-comments-or-questions--description').html(Drupal.t('Enter any questions or comments you have regarding this site request.'));
        }
        else if (val === 'Approval') {
          $('#edit-uri--description').html(Drupal.t('The URL you would like to seek UI Hostmaster approval for.'));
          $('#edit-additional-comments-or-questions--description').html(Drupal.t('Please provide details about the requested domain to help expedite the approval process.'));
        }
      })
    }
  };

} (jQuery, Drupal, once));
