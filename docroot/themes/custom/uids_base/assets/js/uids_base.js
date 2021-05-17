/**
 * @file
 * UIDS behaviors.
 */

document.documentElement.className = document.documentElement.className.replace("no-js", "js");

// Remove unnecessary attributes from SF clone menu items.
document.addEventListener('click', function (event) {

  // If the clicked element doesn't have the right selector, bail.
  if (!event.target.matches('#superfish-main-accordion a.menuparent')) return;

  let clone = event.target.nextElementSibling.querySelector('.sf-clone-parent a');
  clone.removeAttribute('role');
  clone.removeAttribute('aria-haspopup');

}, false);
