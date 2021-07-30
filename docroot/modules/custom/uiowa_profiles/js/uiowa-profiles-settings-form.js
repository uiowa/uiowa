window.addEventListener("DOMContentLoaded", () => {
  attachBehaviors();

  // select the target node
  let target = document.getElementById("uiowa-profiles-settings");
  // create an observer instance
  let observer = new MutationObserver(function(mutations) {
    // If the internals of the form mutate, re-attach the JS for tabs.
    attachBehaviors();
  });
  // configuration of the observer:
  let config = { attributes: true, childList: true, characterData: true };
  // pass in the target node, as well as the observer options
  observer.observe(target, config);
});

function attachBehaviors() {
  const tabs = document.querySelectorAll('.tabs [role="tab"]');
  const tabList = document.querySelector('.tabs [role="tablist"]');

  const profiles_titles = document.querySelectorAll('.tabs .profiles-fieldset-title');

  // Add a click event handler to each tab
  tabs.forEach(tab => {
    tab.addEventListener("click", changeTabs);
  });

  profiles_titles.forEach(profile_title => {
    profile_title.addEventListener("keydown", changeTabText);
    profile_title.addEventListener("keyup"  , changeTabText);
  });

  // Enable arrow navigation between tabs in the tab list
  let tabFocus = 0;

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
}


function changeTabs(e) {
  const target = e.target;
  const parent = target.parentNode;
  const grandparent = parent.parentNode;

  // Remove all current selected tabs
  parent
    .querySelectorAll('[aria-selected="true"]')
    .forEach(t => t.setAttribute("aria-selected", false));

  // Set this tab as selected
  target.setAttribute("aria-selected", true);

  // Hide all tab panels
  grandparent
    .querySelectorAll('[role="tabpanel"]')
    .forEach(p => p.setAttribute("hidden", true));

  // Show the selected panel
  grandparent.parentNode
    .querySelector(`#${target.getAttribute("aria-controls")}`)
    .removeAttribute("hidden");
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
