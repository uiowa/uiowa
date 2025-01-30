((Drupal, drupalSettings) => {
  // Check if Leaflet is initialized.
  if (typeof L !== 'undefined' && document.querySelector('[id^="leaflet-map"]')) {
  Drupal.attachBehaviors(document);
}
})(Drupal, drupalSettings);
