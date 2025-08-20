/**
 * @file
 * Reload signage signs.
 */

// Namespace jQuery to avoid conflicts.
(function (Drupal, drupalSettings, once) {

  // Attach reload behavior.
  Drupal.behaviors.reload = {
    attach: function (context) {
      once('reload', 'html', context).forEach(() => {
        Drupal.signageReload();
      });
    }
  };

  // Define the behavior.
  Drupal.signageReload = function () {
    console.log('Digital Signage: Reload interval is ' + drupalSettings.signage.signReloadInterval + ' seconds');
    // @todo Set timeout from settings.
    setInterval(Drupal.signageReload.updateWindow, drupalSettings.signage.signReloadInterval * 1000);
  };

  function hostReachable(location) {
    if (!location) {
      location = window.location.hostname + "/";
    }
    // Craft a new `XMLHttpRequest()`.
    const xhttp = new XMLHttpRequest();

    // Open new request as a HEAD to the root hostname with a random param to bust the cache
    xhttp.open('HEAD', '//' + location + '?rand=' + Math.floor((1 + Math.random()) * 0x10000), false);

    // Issue request and handle response
    try {
      xhttp.send();
      return (xhttp.status >= 200 && xhttp.status < 300 || xhttp.status === 304);
    } catch (error) {
      return false;
    }

  }

  // Update all Availability on the page.
  Drupal.signageReload.updateWindow = function () {
    if (hostReachable(window.location.hostname + drupalSettings.path.baseUrl)) {
      console.log('Digital Signage: Reloading sign.')
      document.location.reload(true);
    }
  };

})(Drupal, drupalSettings, once);
