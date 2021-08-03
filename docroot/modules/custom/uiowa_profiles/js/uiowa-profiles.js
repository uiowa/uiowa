/**
 * @file
 * UIowa Profiles JS.
 */

// This needs to be declared globally outside the behavior.
uiProfiles = { basePath: drupalSettings.uiowaProfiles.basePath };

(function ($, Drupal) {
  'use strict';

  Drupal.uiowaProfiles = {};

  Drupal.uiowaProfiles.updateCanonical = function (settings, url) {
    url = new URL(url);
    let path = url.pathname;

    // Trim any trailing slash.
    if (path.endsWith('/')) {
      path = path.substr(0, path.length - 1);
    }





    let link = document.head.querySelector('link[rel="canonical"]');

    // We only need to set the canonical link on individual profile pages.
    if (path !== settings.uiowaProfiles.basePath) {
      let parts = path.split('/').filter(function (el) {
        return el !== '';
      });

      let person = parts.pop();

      let environment = drupalSettings.uiowaProfiles.environment;
      let endpoint = 'https://profiles' + (environment === 'test' ? '-test' : '') + '.uiowa.edu/api/people/';
      let params = 'api-key=' + drupalSettings.uiowaProfiles.api_key;
      let url = endpoint + person + '/metadata?' + params;

      const request = new XMLHttpRequest();
      request.open("GET", url);
      request.onload = ()=>{
        if (request.status === 200) {
          let response = JSON.parse(request.response);
          let canonical = response.canonical_url;
          link.setAttribute('href', canonical);
        }
        else {
          console.log(`error ${request.status}`);
        }
      }
      request.send();
    }
    else {
      let original = url.toString();

      if (url.search) {
        original = original.toString().split('?')[0];
      }

      link.setAttribute('href', original);
    }
  }

  /**
   * Profiles behavior.
   */
  Drupal.behaviors.uiowaProfiles = {
    attach: function (context, settings) {
      $(document, context).once('uiowaProfiles').each(function() {
        Drupal.uiowaProfiles.updateCanonical(settings, document.URL);

        const root = document.getElementById('profiles-root');
        const observer = new MutationObserver(function() {
          Drupal.uiowaProfiles.updateCanonical(settings, document.URL);
        });

        observer.observe(root, {
          attributes: true,
          childList: true,
          subtree: true
        });
      });
    }
  };

} (jQuery, Drupal));
