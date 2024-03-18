function change_select_color_by_index(element, index) {
  if (index === 0) {
    element.style.color = '#64676b'
  }
  else {
    element.style.color = 'var(--brand-secondary)';
  }
}

(function (Drupal, once) {
  Drupal.behaviors.exposedFormEnhancements = {
    attach(context) {
      const elements = once('exposedFormEnhancements', 'select', context);
      // `elements` is always an Array.
      elements.forEach(processingCallback);
    }
  };

  // The parameters are reversed in the callback between jQuery `.each` method
  // and the native `.forEach` array method.
  function processingCallback(value, index) {
    value.onchange = function(){
      change_select_color_by_index(value, value.selectedIndex);
    };

    change_select_color_by_index(value, value.selectedIndex);
  }
}(Drupal, once));
