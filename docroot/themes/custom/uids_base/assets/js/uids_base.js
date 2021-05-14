/**
 * @file
 * UIDS behaviors.
 */

document.documentElement.className = document.documentElement.className.replace("no-js", "js");

// @todo: Scope click event listener to only a.menuparents.
document.addEventListener('click', function (event) {

  // If the clicked element doesn't have the right selector, bail.
  if (!event.target.matches('#superfish-main-accordion a.menuparent')) return;

  // @todo: Only remove attributes for descendant sf-clone-parent.
  let clones = document.querySelectorAll('.sf-clone-parent a');

  clones.forEach(function (clone) {
    clone.removeAttribute('role');
    clone.removeAttribute('aria-haspopup');
  });

}, false);
