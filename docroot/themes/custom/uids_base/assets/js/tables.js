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
});

function hook_triggerTableRespond(responsive_tables) {
    for (let i = 0; i < responsive_tables.length; i++) {
        // Reset Tables container sizes if they are contained in layout containers.
        if (responsive_tables[i].closest('.block-inline-blockuiowa-text-area')) {
            resetTableContainers(responsive_tables[i]);
        }
    }

    for (let i = 0; i < responsive_tables.length; i++) {
        // Resize Tables container sizes if they are contained in layout containers.
        if (responsive_tables[i].closest('.block-inline-blockuiowa-text-area')) {
            resizeTableContainers(responsive_tables[i]);
        }
    }
}

// This function resets the table wrapper size.
function resetTableContainers(table) {
    let table_bounding_box_selector = table;
    let lb_container = table_bounding_box_selector.closest('.layout__container');
    let lb_container_has_multiple_columns = lb_container.querySelectorAll('.layout__spacing_container>.layout__region').length;

    if (lb_container_has_multiple_columns) {
        table_bounding_box_selector.style.width = '1px';
    }
    else {
        table_bounding_box_selector.style.width = '1px';
    }
}

// This function resizes the table wrapper.
function resizeTableContainers(table) {
    let table_bounding_box_selector = table;
    let lb_container = table_bounding_box_selector.closest('.layout__container');
    let lb_container_has_multiple_columns = lb_container.querySelectorAll('.layout__spacing_container>.layout__region').length;
    let layout_width;

    if (lb_container_has_multiple_columns) {
        layout_width = table_bounding_box_selector.closest('.layout__region').offsetWidth;
    }
    else {
        layout_width = table_bounding_box_selector.closest('.layout__spacing_container').offsetWidth;
    }

    table_bounding_box_selector.style.width = layout_width + 'px';
}
