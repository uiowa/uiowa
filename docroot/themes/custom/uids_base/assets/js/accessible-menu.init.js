(function ($, AccessibleMenu) {
  'use strict';
  Drupal.behaviors.accessible_menu = {
    // Initialize executed flag to false.
    executed: false,
    attach: function (context, settings) {
      const menuWrappers = context.querySelectorAll('.menu-wrapper--horizontal');

      // Bail early if no menu wrappers are found.
      if (menuWrappers.length === 0) {
        return false;
      }

      // Loop through each menu wrapper.
      menuWrappers.forEach(function(menuWrapper, index) {
        const menuElement = menuWrapper.querySelector('.menu');

        // Generate a unique ID for the toggle button based on the index
        const toggleBtnId = `main-menu-toggle-${index}`;

        // Check if the toggle button already exists for this menu
        if (!menuWrapper.querySelector(`#${toggleBtnId}`)) {
          // Add mobile toggle button
          const toggleBtn = context.createElement('button');
          toggleBtn.setAttribute('id', toggleBtnId);
          toggleBtn.setAttribute('aria-label', 'Toggle secondary menu');
          let block_name = 'menu_block:main';
          // Get the unique block identifier from the data-block-name attribute
          let block_identifier = menuWrapper.getAttribute('data-block-name');

          // Get the block title from drupalSettings using the unique identifier
          let block_title = drupalSettings.block_title[block_identifier];
          // Create a new span element and append the text to it
          const span = context.createElement('span');
          span.textContent = block_title + ' Menu';

          // Append the span element to the toggleBtn button element
          toggleBtn.appendChild(span);
          menuWrapper.insertAdjacentElement('afterbegin', toggleBtn);
        }

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
          controllerElement: menuWrapper.querySelector(`#${toggleBtnId}`),
          submenuToggleSelector: 'button',
          containerElement: menuWrapper,
          optionalKeySupport: true,
          hoverType: 'off',
        });
      });

      // Set the flag to true to prevent future executions.
      this.executed = true;
    }
  }
})(jQuery, AccessibleMenu);
