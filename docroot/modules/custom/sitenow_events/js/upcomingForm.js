function change_select_color_by_index(element, index) {
  if (index === 0) {
    element.style.color = '#64676b'
  }
  else {
    element.style.color = 'var(--brand-secondary)';
  }
}

(function (Drupal, once) {
  Drupal.behaviors.upcomingForm = {
    attach(context) {
      const elements = once('upcomingForm', 'select', context);
      // `elements` is always an Array.
      elements.forEach(processingCallback);
    }
  };

  // The parameters are reversed in the callback between jQuery `.each` method
  // and the native `.forEach` array method.
  function processingCallback(value, index) {
    console.log(value);
  }
}(Drupal, once));
