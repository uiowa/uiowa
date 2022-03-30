(function ($, AccessibleMenu) {'use strict';
  Drupal.behaviors.accessible_menu = {
    attach: function (context, settings) {
      const menu = new AccessibleMenu.TopLinkDisclosureMenu({
        menuElement: context.querySelector('.menu-wrapper--horizontal > .menu'),
        // We have to add 'span' to handle <nolink>.
        menuLinkSelector: 'a, span',
        submenuItemSelector: 'li.menu-item--expanded',
        submenuToggleSelector: 'button',
      });

      // Add buttons to toggle menus.
      const expandableMenuItems = context.querySelectorAll('.menu-wrapper--horizontal li.menu-item--expanded > a, .menu-wrapper--horizontal li.menu-item--expanded > span');

      expandableMenuItems.forEach(function(menuItem) {
        /* buttons are generated on init, to support no JS and have the submenus displayed by default */
        let btn = document.createElement('button');
        btn.setAttribute('aria-expanded', 'false');
        btn.type = 'button';
        btn.setAttribute('aria-controls', 'id_' + menuItem.innerText.toLowerCase() + '_menu');
        btn.ariaLabel = 'More ' + menuItem.innerText + ' pages';
        menuItem.parentNode.insertBefore(btn, menuItem.nextSibling);
        // add id to ul for arial label
        const menuId = 'id_' + menuItem.innerText.toLowerCase() + '_menu';
        // check for ul.menu
        let potentialUl = menuItem.nextElementSibling.nextElementSibling;
        if (potentialUl.nodeName === 'UL' && potentialUl.classList.contains('menu')) {
          potentialUl.setAttribute('id', menuId);
        }
      });
    }
  }
})(jQuery, AccessibleMenu);


