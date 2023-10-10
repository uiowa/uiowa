/**
 * @file
 * UIowa search CSE results functionality.
 */

(function ($, Drupal, drupalSettings, once) {

  'use strict';

  Drupal.uiowaSearchResults = function() {
    let cseAttributes = {
      queryParameterName: 'terms',
    }

    if (drupalSettings.uiowaSearch.cseScope === 1) {
      cseAttributes.as_sitesearch = window.location.hostname + drupalSettings.path.baseUrl;
    }

    google.search.cse.element.render({
        div: 'search-results',
        tag: 'search',
        attributes: cseAttributes
    });
  };

  // Attach uiowaSearchResults behavior.
  Drupal.behaviors.uiowaSearchResults = {
    attach: function(context, settings) {
      $(once('uiowaSearchResults', 'body', context)).each(function() {
        window.__gcse = {
          parsetags: 'explicit',
          callback: Drupal.uiowaSearchResults,
        };

        let cx = drupalSettings.uiowaSearch.engineId;
        let gcse = document.createElement('script');
        gcse.type = 'text/javascript';
        gcse.async = true;
        gcse.src = 'https://cse.google.com/cse.js?cx=' + cx;
        let s = document.getElementsByTagName('script')[0];
        s.parentNode.insertBefore(gcse, s);
      });
    }
  };
})(jQuery, Drupal, drupalSettings, once);
