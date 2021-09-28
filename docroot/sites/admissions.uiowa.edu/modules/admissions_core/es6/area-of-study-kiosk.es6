(function ($, Drupal, drupalSettings) {
  // Attach aos_kiosk behavior.
  Drupal.behaviors.aos_kiosk = {
    attach: function (context, settings) {
      $('.view-id-areas_of_study_kiosk', context).once('aos_kiosk').each(function (index) {
        let observeDOM = (function(){
          let MutationObserver = window.MutationObserver || window.WebKitMutationObserver;

          return function( obj, callback ){
            if( !obj || obj.nodeType !== 1 ) return;

            if( MutationObserver ){
              // define a new observer
              let mutationObserver = new MutationObserver(callback)

              // have the observer observe foo for changes in children
              mutationObserver.observe( obj, { childList:true, subtree:true })
              return mutationObserver
            }

            // browser support fallback
            else if( window.addEventListener ){
              obj.addEventListener('DOMNodeInserted', callback, false)
              obj.addEventListener('DOMNodeRemoved', callback, false)
            }
          }
        })()
        // Observe a specific DOM element:
        observeDOM( $(this)[0].parentElement, function(m){
          let view = document.querySelector('.view-areas-of-study-kiosk .views-table');
          let checkboxes = view.querySelectorAll('.form-checkbox');
          checkboxes.forEach(function (element) {
            let index = checked_nids.indexOf(element.value);
            if (index > -1) {
              element.checked = true;
            }
          });

          checkPrintShow();
        });

        let checked_nids = [];

        document.addEventListener('click', function (event) {
          if (event.target.matches('#aos-cta__button')) {
            printJS(
              {
                printable:'/print/view/pdf/areas_of_study_kiosk/filtered_print_page?view_args[0]=' + checked_nids.join(' '),
                type:'pdf',
                showModal:true,
                onPrintDialogClose: dialogClose
              }
            );
          }
          else if(event.target.matches('.view-areas-of-study-kiosk .views-table .form-checkbox')) {
            let checkbox = event.target;
            if (checkbox.checked) {
              let index = checked_nids.indexOf(checkbox.value);
              if (index < 0) {
                checked_nids.push(checkbox.value);
              }
            }
            else if (!checkbox.checked) {
              let index = checked_nids.indexOf(checkbox.value);
              if (index > -1) {
                checked_nids.splice(index, 1);
              }
            }

            checkPrintShow();

          }

        }, false);

        function dialogClose() {
          setTimeout(() => {location.reload()} , 5000);
        }

        function checkPrintShow() {
          let print_button_container = document.querySelector('.view-header .aos-cta');
          if (checked_nids.length > 0) {
            print_button_container.classList.add('aos-cta--show');
          }
          else {
            print_button_container.classList.remove('aos-cta--show');
          }
        }

      });
    }
  };
})(jQuery, Drupal, drupalSettings);
