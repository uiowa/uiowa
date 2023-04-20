(function ($, AccessibleMenu) {'use strict';
  Drupal.behaviors.accessible_menu = {
    attach: function (context, settings) {
      const menus = context.querySelectorAll('.menu-wrapper--horizontal > .menu');

      // Bail early if this isn't relevant.
      if (menus === null) {
        return false;
      }

      // Loop through the menus.
      menus.forEach(function(menuElement) {

        // Add mobile toggle button
        const menuBlock = context.querySelector('.menu-wrapper--horizontal');
        const toggleBtn = context.createElement('button');
        toggleBtn.setAttribute('id', 'main-menu-toggle');
        toggleBtn.setAttribute('aria-label', 'Toggle secondary menu');
        let block_name = 'menu_block:main';
        let block_title = drupalSettings.block_title[block_name];
        // Create a new span element and append the text to it
        const span = context.createElement('span');
        span.textContent = block_title + ' Menu';

        // Append the span element to the toggleBtn button element
        toggleBtn.appendChild(span);
        menuBlock.insertAdjacentElement('afterbegin', toggleBtn);

        // Find all menu items that can be displayed.
        const expandableMenuItems = menuElement.querySelectorAll('li.menu-item--expanded > a, li.menu-item--expanded > span');

        // Add buttons to toggle menus. Buttons are generated here to support
        // no JS and sub-menus displayed by default.
        expandableMenuItems.forEach(function(menuItem) {
          let btn = context.createElement('button');
          btn.setAttribute('aria-expanded', 'false');
          btn.type = 'button';
          btn.setAttribute('aria-controls', 'id_' + menuItem.innerText.toLowerCase() + '_menu');
          btn.ariaLabel = 'More ' + menuItem.innerText + ' pages';
          menuItem.parentNode.insertBefore(btn, menuItem.nextSibling);
          // Add ID to ul for aria-label.
          const menuId = 'id_' + menuItem.innerText.toLowerCase() + '_menu';
          // Check for 'ul.menu'.
          let potentialUl = menuItem.nextElementSibling.nextElementSibling;
          if (potentialUl.nodeName === 'UL' && potentialUl.classList.contains('menu')) {
            potentialUl.setAttribute('id', menuId);
          }
        });

        // Initialize menu.
        new AccessibleMenu.TopLinkDisclosureMenu({
          menuElement,
          // We have to add 'span' to handle <nolink>.
          menuLinkSelector: 'a, span',
          submenuItemSelector: 'li.menu-item--expanded',
          controllerElement: context.querySelector('#main-menu-toggle'),
          submenuToggleSelector: 'button',
          containerElement: context.querySelector('.menu-wrapper--horizontal'),
          optionalKeySupport: true,
          hoverType: 'off',
        });
      });
    }
  }
})(jQuery, AccessibleMenu);


