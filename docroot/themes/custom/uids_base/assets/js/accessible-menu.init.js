(function ($, AccessibleMenu) {'use strict';
  Drupal.behaviors.accessible_menu = {
    // Initialize executed flag to false.
    executed: false,
    attach: function (context, settings) {
      // Ensure the script runs only once.
      if (this.executed) {
        return;
      }

      const verticalMenus = context.querySelectorAll('.menu-wrapper--vertical > .menu');
      const horizontalMenus = context.querySelectorAll('.menu-wrapper--horizontal > .menu');

      // Handle vertical menus
      if (verticalMenus !== null) {
        verticalMenus.forEach(function(menuElement) {
          // Add mobile toggle button
          const menuBlock = menuElement.parentElement;
          const toggleBtn = context.createElement('button');
          toggleBtn.setAttribute('id', 'main-menu-toggle');
          toggleBtn.setAttribute('aria-label', 'Toggle secondary menu');
          let block_name = 'menu_block:main';
          let block_title = drupalSettings.block_title[block_name];
          const span = context.createElement('span');
          span.textContent = block_title + ' Menu';
          toggleBtn.appendChild(span);
          menuBlock.insertAdjacentElement('afterbegin', toggleBtn);

          // Initialize menu
          new AccessibleMenu.TopLinkDisclosureMenu({
            menuElement,
            controllerElement: context.querySelector('#main-menu-toggle'),
            containerElement: context.querySelector('.menu-wrapper--vertical'),
            optionalKeySupport: true,
            hoverType: 'off',
          });
        });
      }

      // Handle horizontal menus
      if (horizontalMenus !== null) {
        horizontalMenus.forEach(function(menuElement) {
          // Add mobile toggle button
          const menuBlock = context.querySelector('.menu-wrapper--horizontal');
          const toggleBtn = context.createElement('button');
          toggleBtn.setAttribute('id', 'main-menu-toggle');
          toggleBtn.setAttribute('aria-label', 'Toggle secondary menu');
          let block_name = 'menu_block:main';
          let block_title = drupalSettings.block_title[block_name];
          const span = context.createElement('span');
          span.textContent = block_title + ' Menu';
          toggleBtn.appendChild(span);
          menuBlock.insertAdjacentElement('afterbegin', toggleBtn);

          // Find all menu items that can be displayed
          const expandableMenuItems = menuElement.querySelectorAll('li.menu-item--expanded > a, li.menu-item--expanded > span');

          // Add buttons to toggle menus
          expandableMenuItems.forEach(function(menuItem) {
            let btn = context.createElement('button');
            btn.setAttribute('aria-expanded', 'false');
            btn.type = 'button';
            btn.setAttribute('aria-controls', 'id_' + menuItem.innerText.toLowerCase() + '_menu');
            btn.ariaLabel = 'More ' + menuItem.innerText + ' pages';
            menuItem.parentNode.insertBefore(btn, menuItem.nextSibling);
            const menuId = 'id_' + menuItem.innerText.toLowerCase() + '_menu';
            let potentialUl = menuItem.nextElementSibling.nextElementSibling;
            if (potentialUl.nodeName === 'UL' && potentialUl.classList.contains('menu')) {
              potentialUl.setAttribute('id', menuId);
            }
          });

          // Initialize menu
          new AccessibleMenu.TopLinkDisclosureMenu({
            menuElement,
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

      // Set the flag to true to prevent future executions.
      this.executed = true;
    }
  }
})(jQuery, AccessibleMenu);
