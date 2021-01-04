/**
 * @file
 * APR behaviors.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Behavior description.
   */
  Drupal.behaviors.uiowaApr = {
    attach: function (context, settings) {
      $('#apr-directory-service table tbody', context).once('uiowaApr').each(function() {
        const observer = new MutationObserver(function (mutationList, observer) {
          if (mutationList.length === 30) {
            $('#apr-directory-service table')
              .addClass('is-striped')
              .addClass('uids-responsive-tables')
              .removeClass('table-striped')
              .removeClass('table-condensed');

            generateResponsiveTables();
          }
        });

        observer.observe(this, {
          attributes: true,
          childList: true,
          subtree: true
        });
      });
    }
  };

} (jQuery, Drupal));

