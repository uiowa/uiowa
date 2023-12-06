(function ($, AccessibleMenu) {
  'use strict';

  Drupal.behaviors.accessible_menu = {
    attach: function (context, settings) {
      // Ensure the script runs only once.
      if (Drupal.behaviors.accessible_menu.executed) {
        return;
      }

      // Find all elements with class 'menu-wrapper--horizontal'.
      const menuWrappers = context.querySelectorAll('.menu-wrapper--horizontal');

      // Loop through each menu wrapper.
      menuWrappers.forEach(function (menuWrapper) {
        // Find the menu element inside each menu wrapper.
        const menuElement = menuWrapper.querySelector('.menu');

        // Bail early if the menu element is not found.
        if (!menuElement) {
          return false;
        }

        // Add mobile toggle button
        const toggleBtn = context.createElement('button');
        toggleBtn.setAttribute('id', 'main-menu-toggle');
        toggleBtn.setAttribute('aria-label', 'Toggle secondary menu');
        const span = context.createElement('span');
        span.textContent = 'Section Menu';
        toggleBtn.appendChild(span);
        menuWrapper.insertAdjacentElement('afterbegin', toggleBtn);

        // Find all menu items that can be displayed.
        const expandableMenuItems = menuElement.querySelectorAll('li.menu-item--expanded > a, li.menu-item--expanded > span');

        // Add buttons to toggle menus.
        expandableMenuItems.forEach(function (menuItem) {
          let btn = context.createElement('button');
          btn.setAttribute('aria-expanded', 'false');
          btn.type = 'button';
          btn.setAttribute('aria-controls', 'id_' + menuItem.innerText.toLowerCase() + '_menu');
          btn.ariaLabel = 'More ' + menuItem.innerText + ' pages';
          menuItem.parentNode.insertBefore(btn, menuItem.nextSibling);

          // Add ID to ul for aria-label.
          const menuId = 'id_' + menuItem.innerText.toLowerCase() + '_menu';
          let potentialUl = menuItem.nextElementSibling.nextElementSibling;
          if (potentialUl.nodeName === 'UL' && potentialUl.classList.contains('menu')) {
            potentialUl.setAttribute('id', menuId);
          }
        });

        // Initialize menu.
        new AccessibleMenu.TopLinkDisclosureMenu({
          menuElement,
          menuLinkSelector: 'a, span',
          submenuItemSelector: 'li.menu-item--expanded',
          controllerElement: context.querySelector('#main-menu-toggle'),
          submenuToggleSelector: 'button',
          containerElement: menuWrapper, // Use the current menu wrapper as the container.
          optionalKeySupport: true,
          hoverType: 'off',
        });
      });

      // Set the flag to true to prevent future executions.
      Drupal.behaviors.accessible_menu.executed = true;
    }
  };
})(jQuery, AccessibleMenu);
