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
    let meta_description = document.head.querySelector('meta[name="description"]');

    // We only need to set the canonical link on individual profile pages.
    if (path !== settings.uiowaProfiles.basePath) {
      meta_description = document.head.querySelector('meta[name="description"]');

      let parts = path.split('/').filter(function (el) {
        return el !== '';
      });

      // Get the person's name from the URL, ex: james-doe
      let person = parts.pop();

      // Get the environment from the settings.
      let environment = drupalSettings.uiowaProfiles.environment;
      // Set the endpoint to either the test or prod versions of the api based on environment.
      let endpoint = 'https://profiles' + (environment === 'test' ? '-test' : '') + '.uiowa.edu/api/people/';
      // Set query parameters, in this case the api key gotten from settings.
      let params = 'api-key=' + drupalSettings.uiowaProfiles.api_key;
      // Concatenate all relevant pieces together to create an api call url.
      let url = endpoint + person + '/metadata?' + params;

      // Create a new XMLHttpRequest() with our api call url.
      const request = new XMLHttpRequest();
      request.open("GET", url);
      // On loading of the request...
      request.onload = ()=>{
        // If the request is a success...
        if (request.status === 200) {
          // Parse the response in to readable JSON.
          let response = JSON.parse(request.response);
          // Grab the canonical URL from the response.
          let canonical = response.canonical_url;
          // console.log(response);

          // Construct the `meta_description_markup` using the response data.
          let meta_description_markup = this.personMetaElement(response.name, response.directoryTitle);

          if (meta_description !== null) {
            meta_description.parentNode.replaceChild(meta_description_markup, meta_description);
          }
          else {
            document.querySelector('head').appendChild(meta_description_markup);
          }

          // And set the canonical URL in the head.
          link.setAttribute('href', canonical);
        }
        // If the request fails...
        else {
          // Log the error code.
          console.log(`error ${request.status}`);
        }
      }
      request.send();
    }

    // Else if this is not an individual profile page...
    else {
      meta_description = document.head.querySelector('meta[name="description"]');
      let directory_title = drupalSettings.uiowaProfiles.directoryTitle;
      let site_name = drupalSettings.uiowaProfiles.siteName;
      let directory_meta_description = this.directoryMetaElement(site_name, directory_title);

      // Get the original url for the directory.
      let original = url.toString();

      // Strip any queries.
      if (url.search) {
        original = original.toString().split('?')[0];
      }

      // And reset the canonical back to it.
      link.setAttribute('href', original);

      if (meta_description !== null) {
        meta_description.parentNode.replaceChild(directory_meta_description, meta_description);
      }
      else {
        document.querySelector('head').appendChild(directory_meta_description);
      }
    }
  }

  Drupal.uiowaProfiles.personMetaElement = function (name, directoryTitle) {
    let element = document.createElement('meta');
    element.name = 'description';
    element.content = name + ' - ' + directoryTitle + ' - The University of Iowa';
    return element;
  }

  Drupal.uiowaProfiles.directoryMetaElement = function (siteName, directoryTitle) {
    let element = document.createElement('meta');
    element.name = 'description';
    element.content = siteName + ' - ' + directoryTitle;
    return element;
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
