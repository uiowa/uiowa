// Adjust off-canvas menu when used with the admin toolbar.
window.addEventListener('load', function() {
  if (document.body.classList.contains("header-not-sticky")) {
    const menuDrawer = document.querySelector('.o-canvas__drawer');
    const headerIowa = document.querySelector('[data-uids-header]');
    let bodyPadding = parseFloat(getComputedStyle(document.body).paddingTop);
    let cumulativeIowaHeight = headerIowa.offsetHeight;

    // Set initial positioning of menuDrawer
    menuDrawer.style.top = `${cumulativeIowaHeight + bodyPadding}px`;

    // Create a MutationObserver instance
    const observer = new MutationObserver(function (mutationsList) {
      for (const mutation of mutationsList) {
        if (mutation.attributeName === 'style' && mutation.target === document.body) {
          // Body style attribute has changed, update bodyPadding and menuDrawer position
          bodyPadding = parseFloat(getComputedStyle(document.body).paddingTop);
          menuDrawer.style.top = `${cumulativeIowaHeight + bodyPadding}px`;
        }
      }
    });

    // Start observing changes in the body style attribute
    observer.observe(document.body, {attributes: true});

    // Stop observing when needed (e.g., when the component is unloaded)
    // observer.disconnect();
  }
});
