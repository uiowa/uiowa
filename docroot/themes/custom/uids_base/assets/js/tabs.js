/*
*   This content is licensed according to the W3C Software License at
*   https://www.w3.org/Consortium/Legal/2015/copyright-software-and-document
*   This software or document includes material copied from or derived from
*   https://www.w3.org/TR/wai-aria-practices-1.1/examples/tabs/tabs-1/tabs.html
*/
(function () {
  // For easy reference.
  const keys = {
    end: 35,
    home: 36,
    left: 37,
    up: 38,
    right: 39,
    down: 40,
    delete: 46
  };

  // Add or subtract depending on key pressed.
  const direction = {
    37: -1,
    38: -1,
    39: 1,
    40: 1
  };

  // This delay could be set by a function later if ever needed.
  const delay = 0;

  function Tabs(element) {
    if (element) {
      // Set references to tab elements.
      this.tablist = element.querySelectorAll('[role="tablist"]')[0];
      this.tabs = element.querySelectorAll('[role="tab"]');
      this.panels = element.querySelectorAll('[role="tabpanel"]');

      // If all the necessary references are present, proceed.
      if (this.tablist && this.tabs && this.panels) {

        // Warn user if expected IDs are not present.
        if (!element.hasAttribute('id')) {
          console.warn('[UIDS] Tabs (<div class="tab">) needs unique ID to function correctly.')
        }

        // If JS is activated, hide the unnecessary tabs.
        for (let i = 0; i < this.panels.length; i++) {
          if (i != 0) {
            this.panels[i].hidden = true;
          }
        }

        // Activate a tab based upon the hash parameters in the URL.
        this.activateTabByHash();

        // Bind listeners
        this.addListeners();
      }
    }
  }

  // This function adds listeners for events to every tab.
  Tabs.prototype.addListeners = function() {
    // Define thisTabs as the Tabs object for later use.
    let thisTabs = this;

    // Set listeners for all three necessary event types.
    for (let i = 0; i < this.tabs.length; ++i) {
      this.tabs[i].addEventListener('click', event => {
        this.clickEventListener(event);
      });
      this.tabs[i].addEventListener('keydown', event => {
        this.keydownEventListener(event);
      });
      this.tabs[i].addEventListener('keyup', event => {
        this.keyupEventListener(event);
      });

      // Build an array with all tabs (<button>s) in it.
      this.tabs[i].index = i;
    }

    // Add a listener that listens for when the URL is changed.
    window.addEventListener('popstate', function (event) {

      // Activate a tab based upon the hash parameters in the URL.
      thisTabs.activateTabByHash();
    });
  };

  // When a tab is clicked, activateTab is fired to activate it.
  Tabs.prototype.clickEventListener = function(event) {
    const tab = event.target;
    this.activateTab(tab, true);
  }

  // This function activates any given tab panel.
  Tabs.prototype.activateTab = function(tab, setFocus) {
    // Deactivate all other tabs.
    this.deactivateTabs();

    // Remove tabindex attribute.
    tab.removeAttribute('tabindex');

    // Set the tab as selected.
    tab.setAttribute('aria-selected', 'true');

    // Get the value of aria-controls (which is an ID).
    let controls = tab.getAttribute('aria-controls');

    // Remove hidden attribute from tab panel to make it visible.
    document.getElementById(controls).removeAttribute('hidden');

    // Get the tab's id.
    let tabid = tab.id;

    // Define historyString here to be used later.
    let historyString = '#' + tabid;

    // Change window location to add URL params
    if (window.history && history.pushState && historyString !== '#') {
      // NOTE: doesn't take into account existing params
      history.replaceState("", "", historyString);
    }

    // Set focus when required.
    if (setFocus) {
      tab.focus();
    }
  }

  // Activate tab defined in the hash parameter.
  Tabs.prototype.activateTabByHash = function() {

    // Get the hash parameter.
    let hash = window.location.hash.substr(1);

    // If the hash parameter is not empty...
    if ( hash !== '') {

      // Get the tab to focus.
      let tabToFocus = document.getElementById(hash);

      // If the defined hash parameter finds an element...
      if ( tabToFocus !== null) {

        // Get the tablist id's of the hash parameter and this tablist to compare later.
        let tabToFocusTablistID = tabToFocus.parentElement.parentElement.id;
        let tablistID = this.tablist.parentElement.id;

        // If the tablist defined by the hash and this tablist are the same...
        if (tabToFocusTablistID === tablistID) {

          // Activate the tab defined in the hash parameters.
          this.activateTab(tabToFocus, false);
        }
      }
    }
  }

  // Deactivate all tabs and tab panels.
  Tabs.prototype.deactivateTabs = function() {
      // Get the necessary tabs nodelist.
      let tabs = this.tabs;

      // For each tab in tabs...
      for (let t = 0; t < tabs.length; t++) {
        // Remove the necessary accessibility attributes.
        tabs[t].setAttribute('tabindex', '-1');
        tabs[t].setAttribute('aria-selected', 'false');
        tabs[t].removeEventListener('focus', this.focusEventHandler);
      }

      // Get the necessary panels nodelist.
      // For each panel in panels...
      for (let p = 0; p < this.panels.length; p++) {
        // Set the hidden attribute so that it cant be seen.
        this.panels[p].hidden = true;
      }
  }

  // This function will, on an event, check if the event target needs to be focused.
  Tabs.prototype.focusEventHandler = function(event) {
    let target = event.target;
    const that = this;
    setTimeout(function() {
      that.checkTabFocus(target);
    }, delay);
  }

  // Only activate tab on focus if it still has focus after the delay.
  Tabs.prototype.checkTabFocus = function(target) {
    let focused = document.activeElement;

    if (target === focused) {
      this.activateTab(target, false);
    }
  }

  // Handle keydown on tabs.
  Tabs.prototype.keydownEventListener = function(event) {
    let key = event.keyCode;
    // Get the correct tabs nodelist.
    let tabs = this.tabs;

    switch (key) {
      case keys.end:
        event.preventDefault();
        // Activate last tab.
        this.activateTab(tabs[tabs.length - 1]);
        break;
      case keys.home:
        event.preventDefault();
        // Activate first tab.
        this.activateTab(tabs[0]);
        break;

      // Up and down are in keydown.
      // Because we need to prevent page scroll.
      case keys.up:
      case keys.down:
        this.determineOrientation(event);
        break;
    }
  }

  // Handle keyup on tabs.
  Tabs.prototype.keyupEventListener = function(event) {
    let key = event.keyCode;

    switch (key) {
      case keys.left:
      case keys.right:
        this.determineOrientation(event);
        break;
    }
  }

  // When a tablist's aria-orientation is set to vertical,
  // only up and down arrow should function.
  // In all other cases only left and right arrow function.
  Tabs.prototype.determineOrientation = function(event) {
    let key = event.keyCode;

    // Get the correct tablist nodelist.
    let tablist = this.tablist;

    // Determine the tab orientation.
    let vertical = tablist.getAttribute('aria-orientation') === 'vertical';
    let proceed = false;

    if (vertical) {
      if (key === keys.up || key === keys.down) {
        event.preventDefault();
        proceed = true;
      }
    }
    else {
      if (key === keys.left || key === keys.right) {
        proceed = true;
      }
    }

    if (proceed) {
      this.switchTabOnArrowPress(event);
    }
  }

  // Either focus the next, previous, first, or last tab
  // depending on key pressed
  Tabs.prototype.switchTabOnArrowPress = function(event) {
    let pressed = event.keyCode;
    // Get the correct tabs nodelist.

    // For each tab in tabs...
    for (let x = 0; x < this.tabs.length; x++) {
      // Add a focus event handler.
      this.tabs[x].addEventListener('focus', event => {
        this.focusEventHandler(event);
      });
    }

    // If a pressed key is in the direction array.
    if (direction[pressed]) {
      // Focus the necessary tab...
      let target = event.target;
      if (target.index !== undefined) {
        // Left or right if just the left or right arrow key.
        if (this.tabs[target.index + direction[pressed]]) {
          this.tabs[target.index + direction[pressed]].focus();
        }
        // Or the last tab if...
        // The left-most tab was focused and the left arrow was pressed...
        // OR...
        // The top-most tab was focused and the up arrow was pressed...
        else if (pressed === keys.left || pressed === keys.up) {
          focusLastTab(this.tabs);
        }
        // Or the first tab if...
        // The right-most tab was focused and the right arrow was pressed...
        // OR...
        // The bottom-most tab was focused and the down arrow was pressed...
        else if (pressed === keys.right || pressed === keys.down) {
          focusFirstTab(this.tabs);
        }
      }
    }
  }

  // This function focuses the first tab in a tabs nodelist.
  function focusFirstTab(tabs) {
    tabs[0].focus();
  }

  // This function focuses the last tab in a tabs nodelist.
  function focusLastTab(tabs) {
    tabs[tabs.length - 1].focus();
  }

  // Create an object to avoid collision.
  window.UidsTabs = Tabs;

  // Instantiate videos on the page.
  const items = document.getElementsByClassName('tabs-collection');

  for (let i = 0; i < items.length; i++) {
    new UidsTabs(items[i], i);
  }

}());
