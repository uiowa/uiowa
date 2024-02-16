/**
 * @file
 * Description.
 */

(function (Drupal, once) {
  Drupal.behaviors.myfeature = {
    attach(context) {
      const elements = once('fontawesomeIconPickerVanillaIconPicker', '.fontawesomeIconPickerVanillaIconPicker', context);
      // `elements` is always an Array.
      elements.forEach(processingCallback);
    }
  };
  // The parameters are reversed in the callback between jQuery `.each` method
  // and the native `.forEach` array method.
  function processingCallback(iconElementInput, index) {
    const option = JSON.parse(iconElementInput.getAttribute('data-option'));
    new IconPicker(iconElementInput, option);
  }
}(Drupal, once));
