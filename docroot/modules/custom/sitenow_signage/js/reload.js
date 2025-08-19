/**
 * @file
 * Reload signage signs.
 */
(function (Drupal, drupalSettings, once) {

  // Attach reload behavior.
  Drupal.behaviors.reload = {
    attach: function (context) {
      once('signageReload', 'body', context).forEach(() => {
        Drupal.signageReload();
      });
    }
  };

  Drupal.signageReload = function () {
    // Prevent stacked timers across attaches.
    if (Drupal.signageReload.interval) {
      clearInterval(Drupal.signageReload.interval);
    }

    const seconds = parseInt(drupalSettings?.signage?.signReloadInterval, 10) || 0;
    console.log('Digital Signage: Reload interval is ' + seconds + ' seconds');

    if (seconds > 0) {
      Drupal.signageReload.interval = setInterval(Drupal.signageReload.updateWindow, seconds * 1000);
    }
  };

  // Update all Availability on the page.
  Drupal.signageReload.updateWindow = function () {
    console.log('Digital Signage: Reloading sign.');
    const url = window.location.pathname + window.location.search;
    // Cache-busting param while keeping existing query params.
    window.location.replace(url + (url.includes('?') ? '&' : '?') + '_reload=' + Date.now());
  };

})(Drupal, drupalSettings, once);
