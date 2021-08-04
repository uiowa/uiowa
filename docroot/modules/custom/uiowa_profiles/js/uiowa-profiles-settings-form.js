// Wait until the dom is loaded.
window.addEventListener("DOMContentLoaded", () => {
  // Attach behaviors after DOM is loaded.
  attachBehaviors();

  // select the #uiowa-profiles-settings element.
  let target = document.getElementById("uiowa-profiles-settings");
  // create an observer instance to watch for changes in the markup.
  let observer = new MutationObserver(function(mutations) {
    // If the internals of the form mutate, re-attach the JS for tabs.
    attachBehaviors();
  });

  // configuration of the observer:
  let config = { attributes: true, childList: true, characterData: true };
  // Tell the observer to observe
  // Pass in the target node, as well as the observer options.
  observer.observe(target, config);
});

// Attach relevant behaviors to elements in the form markup.
function attachBehaviors() {
  // An array of the content of each tab, holding a form.
  const tabs = document.querySelectorAll('.tabs [role="tab"]');
  // The container for the tab buttons that allow the user to swap tabs.
  const tabList = document.querySelector('.tabs [role="tablist"]');
  // An array of profiles titles.
  const profiles_titles = document.querySelectorAll('.tabs .profiles-fieldset-title');
  // An array of error links.
  const error_links = document.querySelectorAll('.messages--error .messages__content .item-list__comma-list a');
  console.log(error_links);

  // Add a click event handler to each tab.
  tabs.forEach(tab => {
    // On click shange to the tab that was clicked with changeTabsFromEvent().
    tab.addEventListener("click", changeTabsFromEvent);
  });

  // Add a click event handler to each title.
  profiles_titles.forEach(profile_title => {
    // On keydown or keyup change the text of the tab button and the delete button for each profiles instance.
    profile_title.addEventListener("keydown", changeTabText);
    profile_title.addEventListener("keyup"  , changeTabText);
  });

  // Add a click event handler to each error link in the errors message.
  error_links.forEach(error_link => {
    // On keydown or keyup change the text of the tab button and the delete button for each profiles instance.
    error_link.addEventListener("click", errorLinkTabChange);
  });

  // Enable arrow navigation between tabs in the tab list
  let tabFocus = 0;

  // Add a click event handler to the tablist.
  tabList.addEventListener("keydown", e => {
    // Move right
    if (e.keyCode === 39 || e.keyCode === 37) {
      tabs[tabFocus].setAttribute("tabindex", -1);
      if (e.keyCode === 39) {
        tabFocus++;
        // If we're at the end, go to the start
        if (tabFocus >= tabs.length) {
          tabFocus = 0;
        }
        // Move left
      } else if (e.keyCode === 37) {
        tabFocus--;
        // If we're at the start, move to the end
        if (tabFocus < 0) {
          tabFocus = tabs.length - 1;
        }
      }

      tabs[tabFocus].setAttribute("tabindex", 0);
      tabs[tabFocus].focus();
    }
  });

  // If this is the first load...
  if(!document.body.dataset["directoryProfilesAfterFirstLoad"]) {
    // Add an attribute to the body that says the first load has occured.
    document.body.setAttribute('data-directory-profiles-after-first-load', 'true');
  }
  // Else if it is not the first load...
  else if (document.body.dataset["directoryProfilesAfterFirstLoad"] === 'true') {
    // Change focus to the last tab.
    const tabs = document.querySelectorAll('.tabs [role="tab"]');
    changeTabs(tabs[tabs.length -1]);
  }
}

//Change focus to a certain tab given its element.
function changeTabs(element) {
  const parent = element.parentNode;
  const grandparent = parent.parentNode;

  // Remove all current selected tabs
  parent
    .querySelectorAll('[aria-selected="true"]')
    .forEach(t => t.setAttribute("aria-selected", false));

  // Set this tab as selected
  element.setAttribute("aria-selected", true);

  // Hide all tab panels
  grandparent
    .querySelectorAll('[role="tabpanel"]')
    .forEach(p => p.setAttribute("hidden", true));

  // Show the selected panel
  grandparent.parentNode
    .querySelector(`#${element.getAttribute("aria-controls")}`)
    .removeAttribute("hidden");
}

// Function wrapper for changeTabs() that passes an event target through.
// Useful for event listeners.
function changeTabsFromEvent(e) {
  // Get target of tab button click, pas it to changeTabs().
  const target = e.target;
  changeTabs(target)
}

// Function to change the text of the Tab Title and the button Text of an instance.
function changeTabText(e) {
  // Grab the title field that is being edited.
  const target = e.target;
  // If the title string is not empty, use that, otherwise default to 'People so that no buttons or tab text is empty.
  const user_text = (target.value === '') ? 'People' : target.value;
  // Get the fieldset id so that we can target the right elements.
  const fieldset_id = target.dataset.profilesFieldsetTitleIndex;
  // Get the right tab element using the fieldset Id.
  const tab = document.getElementById('tab-' + fieldset_id);
  // Try and get the right delete button element using the fieldset Id.
  const delete_button = document.querySelector('.delete-profiles-instance[data-directory-index="' + fieldset_id + '"]');
  // Set the tab text to the text the user has input in the title field.
  tab.value = user_text;
  // If the delete button exists...
  if (delete_button) {
    // Set the delete button text to the text the user has input in the title field.
    delete_button.value = 'Delete ' + user_text;
  }
}

// function to change tabs on error link click.
function errorLinkTabChange(e) {
  // Prevent the link from trying to move to a focusable element.
  e.preventDefault();
  // Get the targeted link of the click
  let anchor = e.target;
  // Get the href of the link clicked.
  let href = anchor.getAttribute("href");
  // Get the ID of the tab that is referenced in the error link.
  let tab_id = href.split('-')[6];
  // Get the tab we want to focus.
  let tab = document.getElementById('tab-' + tab_id);
  // Focus relevant tab.
  changeTabs(tab);
  // Finally, after the relevant tab has been focused, go to the href defined before.
  window.location.replace(href);
}
