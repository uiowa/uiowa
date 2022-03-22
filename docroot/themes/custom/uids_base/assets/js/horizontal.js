'use strict';

// Add buttons to toggle menus
const expandableMenuItems = document.querySelectorAll(".menu-wrapper--horizontal li.menu-item--expanded > a, .menu-wrapper--horizontal li.menu-item--expanded > span");

expandableMenuItems.forEach(function(menuItem) {
  /* buttons are generated on init, to support no JS and have the submenus displayed by default */
  let btn = document.createElement('button');
  btn.ariaExpanded = 'false';
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


/* todo implement library from NickDJM/accessible-menu repo when this version is implemented */
/* Source: https://www.w3.org/TR/wai-aria-practices-1.2/examples/disclosure/disclosure-navigation-hybrid.html#mythical-page-content */

class DisclosureNav {
  constructor(domNode) {
    this.rootNode = domNode;
    this.controlledNodes = [];
    this.openIndex = null;
    this.useArrowKeys = true;
    this.topLevelNodes = [
      ...this.rootNode.querySelectorAll(
        '.menu-item--expanded > a, button[aria-expanded][aria-controls]'
      ),
    ];

    this.topLevelNodes.forEach((node) => {
      // handle button + menu
      if (
        node.tagName.toLowerCase() === 'button' &&
        node.hasAttribute('aria-controls')
      ) {
        const menu = node.parentNode.querySelector('ul');
        if (menu) {
          // save ref controlled menu
          this.controlledNodes.push(menu);

          // collapse menus
          node.setAttribute('aria-expanded', 'false');
          this.toggleMenu(menu, false);

          // attach event listeners
          menu.addEventListener('keydown', this.onMenuKeyDown.bind(this));
          node.addEventListener('click', this.onButtonClick.bind(this));
          node.addEventListener('keydown', this.onButtonKeyDown.bind(this));
        }
      }
      // handle links
      else {
        this.controlledNodes.push(null);
        node.addEventListener('keydown', this.onLinkKeyDown.bind(this));
      }
    });

    this.rootNode.addEventListener('focusout', this.onBlur.bind(this));
  }

  controlFocusByKey(keyboardEvent, nodeList, currentIndex) {
    switch (keyboardEvent.key) {
      case 'ArrowUp':
      case 'ArrowLeft':
        keyboardEvent.preventDefault();
        if (currentIndex > -1) {
          var prevIndex = Math.max(0, currentIndex - 1);
          nodeList[prevIndex].focus();
        }
        break;
      case 'ArrowDown':
      case 'ArrowRight':
        keyboardEvent.preventDefault();
        if (currentIndex > -1) {
          var nextIndex = Math.min(nodeList.length - 1, currentIndex + 1);
          nodeList[nextIndex].focus();
        }
        break;
      case 'Home':
        keyboardEvent.preventDefault();
        nodeList[0].focus();
        break;
      case 'End':
        keyboardEvent.preventDefault();
        nodeList[nodeList.length - 1].focus();
        break;
    }
  }

  // public function to close open menu
  close() {
    this.toggleExpand(this.openIndex, false);
  }

  onBlur(event) {
    var menuContainsFocus = this.rootNode.contains(event.relatedTarget);
    if (!menuContainsFocus && this.openIndex !== null) {
      this.toggleExpand(this.openIndex, false);
    }
  }

  onButtonClick(event) {
    var button = event.target;
    var buttonIndex = this.topLevelNodes.indexOf(button);
    var buttonExpanded = button.getAttribute('aria-expanded') === 'true';
    this.toggleExpand(buttonIndex, !buttonExpanded);
  }

  onButtonKeyDown(event) {
    var targetButtonIndex = this.topLevelNodes.indexOf(document.activeElement);

    // close on escape
    if (event.key === 'Escape') {
      this.toggleExpand(this.openIndex, false);
    }

    // move focus into the open menu if the current menu is open
    else if (
      this.useArrowKeys &&
      this.openIndex === targetButtonIndex &&
      event.key === 'ArrowDown'
    ) {
      event.preventDefault();
      this.controlledNodes[this.openIndex].querySelector('a').focus();
    }

    // handle arrow key navigation between top-level buttons, if set
    else if (this.useArrowKeys) {
      this.controlFocusByKey(event, this.topLevelNodes, targetButtonIndex);
    }
  }

  onLinkKeyDown(event) {
    var targetLinkIndex = this.topLevelNodes.indexOf(document.activeElement);

    // handle arrow key navigation between top-level buttons, if set
    if (this.useArrowKeys) {
      this.controlFocusByKey(event, this.topLevelNodes, targetLinkIndex);
    }
  }

  onMenuKeyDown(event) {
    if (this.openIndex === null) {
      return;
    }

    var menuLinks = Array.prototype.slice.call(
      this.controlledNodes[this.openIndex].querySelectorAll('a')
    );
    var currentIndex = menuLinks.indexOf(document.activeElement);

    // close on escape
    if (event.key === 'Escape') {
      this.topLevelNodes[this.openIndex].focus();
      this.toggleExpand(this.openIndex, false);
    }

    // handle arrow key navigation within menu links, if set
    else if (this.useArrowKeys) {
      this.controlFocusByKey(event, menuLinks, currentIndex);
    }
  }

  toggleExpand(index, expanded) {
    let isChildOpening = false;
    // close open menu, if applicable

    // if openIndex has a child menu item that is index
    // dont close
    if (this.openIndex != null) {
      const childMenus = this.controlledNodes[this.openIndex].querySelectorAll('ul.menu');

      for (let i = 0; i < childMenus.length; i++) {
        const indexInControlledNodes = this.controlledNodes.indexOf(childMenus[i]);
        if (!expanded && indexInControlledNodes > -1 && index == this.openIndex) {
          this.toggleExpand(indexInControlledNodes, false);
        }

        if (childMenus[i] === this.controlledNodes[index] && this.openIndex !== index) {
            isChildOpening = true;
        }
      }
    }

    if (this.openIndex !== index) {
      if (!isChildOpening) {
        this.toggleExpand(this.openIndex, false);
      }
    }

    // handle menu at called index
    if (this.topLevelNodes[index]) {
      if(!isChildOpening) {
        this.openIndex = expanded ? index : null;
      }
      this.topLevelNodes[index].setAttribute('aria-expanded', expanded);
      this.toggleMenu(this.controlledNodes[index], expanded);
    }
  }

  toggleMenu(domNode, show) {
    if (domNode) {
      domNode.style.display = show ? 'block' : 'none';
    }
  }

  updateKeyControls(useArrowKeys) {
    this.useArrowKeys = useArrowKeys;
  }
}

/* Initialize Disclosure Menus */

window.addEventListener(
  'load',
  function () {
    var menus = document.querySelectorAll('.menu-wrapper--horizontal > .menu');
    var disclosureMenus = [];

    for (var i = 0; i < menus.length; i++) {
      disclosureMenus[i] = new DisclosureNav(menus[i]);
    }

    // listen to arrow key checkbox
    var arrowKeySwitch = document.getElementById('arrow-behavior-switch');
    if (arrowKeySwitch) {
      arrowKeySwitch.addEventListener('change', function () {
        var checked = arrowKeySwitch.checked;
        for (var i = 0; i < disclosureMenus.length; i++) {
          disclosureMenus[i].updateKeyControls(checked);
        }
      });
    }
  },
  false
);


