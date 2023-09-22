/**
 * @file
 * UIowa Profiles JS.
 */

// This needs to be declared globally outside the behavior.
uiProfiles = { basePath: drupalSettings.uiowaProfiles.basePath };

function vanillaOnce() {
  if (!document.body.getAttribute('data-once')) {
    document.body.setAttribute('data-once', 'true');
    return true;
  } else {
    return false;
  }
}

(function ($, Drupal) {
  'use strict';

  Drupal.uiowaProfiles = {};

  // Updates the canonical link for a person and the metadata descriptions for profiles.
  Drupal.uiowaProfiles.updateSEOData = function (settings, url) {
    url = new URL(url);
    let path = url.pathname;

    // Trim any trailing slash.
    if (path.endsWith('/')) {
      path = path.substr(0, path.length - 1);
    }

    // Grab the canonical link element.
    let link = document.head.querySelector('link[rel="canonical"]');

    // We only need to set the canonical link on individual profile pages.
    if (path !== settings.uiowaProfiles.basePath) {

      // Split in to an array the pieces of the path that are not empty.
      let parts = path.split('/').filter(function (el) {
        return el !== '';
      });

      // Get the person's name from the URL, ex: james-doe
      let person = parts.pop();

      // Get the endpoint from the settings.
      let endpoint = drupalSettings.uiowaProfiles.endpoint;

      // Set query parameters, in this case the api key gotten from settings.
      let params = 'api-key=' + drupalSettings.uiowaProfiles.api_key;

      // Retrieve the person metadata.
      fetch(`${endpoint}/people/${person}/metadata?${params}`)
        .then(response => response.json())
        .then(data => {
          // Construct the metadata elements and set appropriately.
          let canonical = data.canonical_url;
          let meta_description_markup = this.personMetaElement(data.name, data.directoryTitle);
          let meta_description = document.head.querySelector('meta[name="description"]');

          if (meta_description !== null) {
            meta_description.parentNode.replaceChild(meta_description_markup, meta_description);
          }
          else {
            // Append the `meta_description_markup` at the end of the `head` element.
            document.querySelector('head').appendChild(meta_description_markup);
          }

          // And set the canonical URL in the head.
          link.setAttribute('href', canonical);
        })
        .catch(error => console.log(`Error retrieving person metadata:`, error));

      // Retrieve the person schema and set the element.
      fetch(`${endpoint}/people/${person}/structured?${params}`)
        .then(response => response.text())
        .then(data => {
          if (!document.head.querySelector('script[type="application/ld+json"]')) {
            let schema = document.createElement('script');
            schema.text = data;
            schema.setAttribute('type', 'application/ld+json');
            document.querySelector('head').appendChild(schema);
          }

        })
        .catch(error => console.log(`Error retrieving person schema:`, error));
    }

    // Else if this is not an individual profile page...
    else {
      // Remove any previously set schema.
      const schema = document.head.querySelector('script[type="application/ld+json"]');

      if (schema) {
        schema.remove();
      }

      // Grab some data from `drupalSettings` and make the `directory_meta_description` from it,
      let site_name = drupalSettings.uiowaProfiles.siteName;
      let directory_title = drupalSettings.uiowaProfiles.directoryTitle;
      let directory_meta_description = this.directoryMetaElement(site_name, directory_title);

      // Get the original url for the directory.
      let original = url.toString();

      // Strip any queries or hash parameters.
      if (url.search) {
        original = original.split('?')[0].split('#')[0];
      }

      // And reset the canonical back to it.
      link.setAttribute('href', original);

      // Grab the meta description element.
      let meta_description = document.head.querySelector('meta[name="description"]');

      // If the meta description exists...
      if (meta_description !== null) {

        // Replace it with the newly constructed `directory_meta_description`.
        meta_description.parentNode.replaceChild(directory_meta_description, meta_description);
      }

      // Else if the meta description does not exist...
      else {

        // Append the `directory_meta_description` at the end of the `head` element.
        document.querySelector('head').appendChild(directory_meta_description);
      }
    }
  }

  // This function creates a meta element for an individual person.
  // It takes two strings, `name` and `directoryTitle` and returns a fully constructed meta element.
  Drupal.uiowaProfiles.personMetaElement = function (name, directoryTitle) {
    let element = document.createElement('meta');
    element.name = 'description';
    element.content = name + ' - ' + directoryTitle + ' - The University of Iowa';
    return element;
  }

  // This function creates a meta element for a directory listing.
  // It takes two strings, `siteName` and `directoryTitle` and returns a fully constructed meta element.
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
      if (vanillaOnce) {
        Drupal.uiowaProfiles.updateSEOData(settings, document.URL);

        // Note that this seems to observe multiple changes per person.
        const root = document.getElementById('profiles-root');
        const observer = new MutationObserver(function() {
          Drupal.uiowaProfiles.updateSEOData(settings, document.URL);
        });

        observer.observe(root, {
          attributes: true,
          childList: true,
          subtree: true
        });
      }
    }
  };

} (jQuery, Drupal));
