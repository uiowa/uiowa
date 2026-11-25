/**
 * @file
 * Pushes confirmed webform submission events to the dataLayer.
 */
(function (Drupal, drupalSettings, once) {
  function push(events) {
    window.dataLayer = window.dataLayer || [];
    events.forEach((event) => window.dataLayer.push(event));
  }

  // Events queued before a full page load.
  Drupal.behaviors.uiowaWebformDataLayer = {
    attach(context) {
      if (once('uiowaWebformDataLayer', 'html', context).length) {
        push(drupalSettings.uiowaWebformDataLayer?.events ?? []);
      }
    }
  };

  // Events delivered in an AJAX response.
  Drupal.AjaxCommands.prototype.uiowaWebformDataLayerPush = function (ajax, response) {
    push(response.events);
  };
}(Drupal, drupalSettings, once));
