(function() {
  // Get all the toggletip buttons
  var toggletipTexts = document.querySelectorAll('[data-toggletip]');

  // Iterate over them
  Array.prototype.forEach.call(toggletipTexts, function(toggletipText) {
    // Create the container element
    var container = document.createElement('span');
    container.setAttribute('class', 'toggletip-container');

    // Put it before the original element in the DOM
    toggletipText.parentNode.insertBefore(container, toggletipText);

    // Create the button element
    var toggletip = document.createElement('button');
    toggletip.setAttribute('type', 'button');
    toggletip.setAttribute('aria-label', 'more info');
    toggletip.setAttribute('data-toggletip-content', toggletipText.textContent);
    toggletip.textContent = 'i';

    // Place the button element in the container
    container.appendChild(toggletip);

    // Create the live region
    var liveRegion = document.createElement('span');
    liveRegion.setAttribute('role', 'status');

    // Place the live region in the container
    container.appendChild(liveRegion);

    // Remove the original element
    toggletipText.parentNode.removeChild(toggletipText);

    // Build `data-tooltip-content`
    var message = toggletip.getAttribute('data-toggletip-content');
    toggletip.setAttribute('data-toggletip-content', message);
    toggletip.removeAttribute('title');

    // Get the message from the data-content element
    var message = toggletip.getAttribute('data-toggletip-content');

    // Get the live region element
    var liveRegion = toggletip.nextElementSibling;

    // Toggle the message
    toggletip.addEventListener('click', function() {
      liveRegion.innerHTML = '';
      window.setTimeout(function() {
        liveRegion.innerHTML = '<span class="toggletip-bubble">'+ message +'</span>';
      }, 100);
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
      if (toggletip !== e.target) {
        liveRegion.innerHTML = '';
      }
    });

    // Remove toggletip on ESC
    toggletip.addEventListener('keydown', function(e) {
      if ((e.keyCode || e.which) === 27)
        liveRegion.innerHTML = '';
    });

    // Remove on blur
    toggletip.addEventListener('blur', function (e) {
      liveRegion.innerHTML = '';
    });
  });
}());
