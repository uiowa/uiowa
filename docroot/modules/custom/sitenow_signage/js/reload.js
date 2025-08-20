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
    const url = new URL(window.location.href);
    // Remove any existing `_reload` param.
    url.searchParams.delete('_reload');
    // Add a fresh one.
    url.searchParams.set('_reload', Date.now());
    window.location.replace(url.toString());
  };

})(Drupal, drupalSettings, once);
