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
  const target = e.target;
  changeTabs(target)
}

// Function to change the text of the Tab Title and the button Text of an instance.
function changeTabText(e) {
  const target = e.target;
  const user_text = (target.value === '') ? 'People' : target.value;
  const fieldset_id = target.dataset.profilesFieldsetTitleIndex;
  const tab = document.getElementById('tab-' + fieldset_id);
  const delete_button = document.querySelector('.delete-profiles-instance[data-directory-index="' + fieldset_id + '"]');
  tab.value = user_text;
  if (delete_button) {
    delete_button.value = 'Delete ' + user_text;
  }
}
