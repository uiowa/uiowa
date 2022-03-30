(function ($, AccessibleMenu) {'use strict';
  Drupal.behaviors.accessible_menu = {
    attach: function (context, settings) {
      const menu = new AccessibleMenu.TopLinkDisclosureMenu({
        menuElement: document.querySelector(".menu-wrapper--horizontal > .menu"),
        submenuItemSelector: "li.menu-item--expanded",
      });
    }
  }
})(jQuery, AccessibleMenu);
