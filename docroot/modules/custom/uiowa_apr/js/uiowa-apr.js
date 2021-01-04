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
        let pageSize = parseInt(settings.uiowaApr.pageSize);

        const observer = new MutationObserver(function (mutationList, observer) {
          if (mutationList.length === pageSize) {
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

// @todo: Requiring this seems wrong. Remove after debugging why its required.
function hook_modifyTableSelector(selector) {
  return '.uids-responsive-tables';
}
