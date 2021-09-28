(function ($, Drupal, drupalSettings) {
  // Attach aos_kiosk behavior.
  Drupal.behaviors.aos_kiosk = {
    attach: function (context, settings) {
      $('.view-id-areas_of_study_kiosk', context).once('aos_kiosk').each(function (index) {
        // Create a mutation observer for the future to watch for changes to the view.
        let observeDOM = (function(){
          let MutationObserver = window.MutationObserver || window.WebKitMutationObserver;

          return function( obj, callback ){
            if( !obj || obj.nodeType !== 1 ) return;

            if( MutationObserver ){
              // Define a new observer.
              let mutationObserver = new MutationObserver(callback)

              // Have the observer observe foo for changes in children.
              mutationObserver.observe( obj, { childList:true, subtree:true })
              return mutationObserver
            }

            // Browser support fallback.
            else if( window.addEventListener ){
              obj.addEventListener('DOMNodeInserted', callback, false)
              obj.addEventListener('DOMNodeRemoved', callback, false)
            }
          }
        })();

        // Observe a the view, and when it changes...
        observeDOM( $(this)[0].parentElement, function(m){
          let view = document.querySelector('.view-areas-of-study-kiosk .views-table');
          let checkboxes = view.querySelectorAll('.form-checkbox');
          // Re check any boxes that are currently selected by the user.
          checkboxes.forEach(function (element) {
            let index = checked_nids.indexOf(element.value);
            if (index > -1) {
              element.checked = true;
            }
          });

          // Check to see if we need to show the print bar.
          checkPrintShow();
        });

        // This will hold all the checked node ids.
        let checked_nids = [];

        // Listen for clicks.
        document.addEventListener('click', function (event) {
          // When a user clicks on the print button...
          if (event.target.matches('#aos-cta__button')) {
            // Call printJS with the joined checked_nids.
            printJS(
              {
                printable:'/print/view/pdf/areas_of_study_kiosk/filtered_print_page?view_args[0]=' + checked_nids.join(' '),
                type:'pdf',
                showModal:true,
                // When the print dialog closes, call dialogClose.
                onPrintDialogClose: dialogClose
              }
            );
          }
          // Else if we click a checkbox...
          else if(event.target.matches('.view-areas-of-study-kiosk .views-table .form-checkbox')) {
            let checkbox = event.target;
            // If the checkbox is checked...
            if (checkbox.checked) {
              // If it is not in checked_nids already...
              let index = checked_nids.indexOf(checkbox.value);
              if (index < 0) {
                // Add it to checked_nids.
                checked_nids.push(checkbox.value);
              }
            }
            // Else if it is not checked...
            else if (!checkbox.checked) {
              // If it is in checked_nids already...
              let index = checked_nids.indexOf(checkbox.value);
              if (index > -1) {
                // Remove it from checked_nids.
                checked_nids.splice(index, 1);
              }
            }

            // Check to see if we need to show the print bar.
            checkPrintShow();
          }
        }, false);

        // Callback to refresh the page when the print dialog closes.
        function dialogClose() {
          setTimeout(() => {location.reload()} , 5000);
        }

        // Checks if there are checked checkboxes, and if there are, shows the print bar.
        function checkPrintShow() {
          let print_button_container = document.querySelector('.view-header .aos-cta');
          // If we have nodes that are checked...
          if (checked_nids.length > 0) {
            // Show the print bar.
            print_button_container.classList.add('aos-cta--show');
          }
          // Else, we dont have nodes that are checked.
          else {
            // So hide the print bar.
            print_button_container.classList.remove('aos-cta--show');
          }
        }

      });
    }
  };
})(jQuery, Drupal, drupalSettings);
