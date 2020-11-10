let responsiveTablePrefixes = [
    // This is the manual opt in class.
    '.uids-responsive-tables',
    // These are the classes that we want t
    '.block-inline-blockuiowa-text-area',
    '.block-inline-blockuiowa-collection',
    '.text-formatted',
    '.view-header'
];

document.addEventListener("DOMContentLoaded", function () {

    // Detect if we are on a layout builder preview page.
    if (document.querySelector('#block-uids-base-local-tasks a[data-drupal-link-system-path$="layout"].is-active')) {
        // if it is, grab the element that holds the layout builder content and its preview.
        let layoutContent = document.querySelector('#block-uids-base-content');
        // Then set up a mutation observer to observe if if the content within it changes.
        let layoutContentMO = new window.MutationObserver(function (e) {
            // For each change 'e', check to see if there are removed nodes and if there are, if one of them was the 'layout-builder' node.
            for (let i = 0; i < e.length; i++) {
                if (e[i].removedNodes[0] && e[i].removedNodes[0].id == 'layout-builder') {
                    // If it was removed, that means the HTML was regenerated, and we need to regenerate the Responsive tables.
                    setTimeout(function () {
                        //Because the editor drawer closes so slow, we have a delay before we resize the tables.
                        generateResponsiveTables();
                    }, 500);
                }
            }
        });
        // This is where we tell the mutation observer what element to observe.
        layoutContentMO.observe(layoutContent, { childList: true, subtree: true, characterData: true });
    }

    // Make sure triggerTableRespond is defined just in case.
    if (typeof triggerTableRespond === "function") {
        // Get all tables in accordions.
        let accordionTables = document.querySelectorAll('.accordion__content .table__responsive-container');
        // If we find tables that exist inside accordions...
        if (accordionTables.length > 0) {
            // For each one.
            accordionTables.forEach(function (table) {
                // Add an event listener so that whenever one is expanded, we trigger the tables to respond to the waking of the accordion item.
                let accordionHeaderButton = table.closest('.accordion__content').previousElementSibling.querySelector('.accordion__button');
                accordionHeaderButton.addEventListener('click', function () {
                    triggerTableRespond();
                });

            });
        }
    }
});

// This function, when defined, allows user defined functionality to be injected in the beginning of a triggerTableRespond() call.
function hook_triggerTableRespond(responsive_tables) {
    for (let i = 0; i < responsive_tables.length; i++) {
        // Reset Tables container sizes if they are contained in layout containers.
        if (responsive_tables[i].closest(responsiveTablePrefixes.join(', '))) {
            resetTableContainers(responsive_tables[i]);
        }
    }

    for (let i = 0; i < responsive_tables.length; i++) {
        // Resize Tables container sizes if they are contained in layout containers.
        if (responsive_tables[i].closest(responsiveTablePrefixes.join(', '))) {
            resizeTableContainers(responsive_tables[i]);
        }
    }
}

// This function, when defined, allows user defined changes to be made to the table selector at the beginning of a generateResponsiveTables() call.
// This function will modify the single selector 'table:not(.table--static)'.
function hook_modifyTableSelector(selector) {
    let joinedPrefixes = [];
    responsiveTablePrefixes.forEach(function(item, index) {
        joinedPrefixes[index] = item + ' ' + selector;
    });

    return joinedPrefixes.join(', ');
}

// This function resets the table wrapper size.
function resetTableContainers(table) {
    table.style.width = '1px';
}

// This function resizes the table wrapper.
function resizeTableContainers(table) {
    let tid = table.id.split('--')[1];
    let layout_width = document.getElementById('table__responsive-measurer--' + tid).getBoundingClientRect().width;
    table.style.width = layout_width + 'px';
}
