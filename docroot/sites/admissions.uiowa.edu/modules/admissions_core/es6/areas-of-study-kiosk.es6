(function ($, Drupal, drupalSettings) {
  // Attach aos_kiosk behavior.
  Drupal.behaviors.aos_kiosk = {
    attach: function (context, settings) {
      $('.view-id-areas_of_study_kiosk', context).once('aos_kiosk').each(function (index) {
        document.addEventListener('click', function (event) {

          if (event.target.matches('#kiosk-print-button')) {

            let view = document.querySelector('.view-areas-of-study-kiosk .views-table');
            let checkboxes = view.querySelectorAll('.form-checkbox');

            let print_node_ids = '';
            checkboxes.forEach(function (element) {
              if (element.checked) {
                print_node_ids += element.value + ' ';
              }
            })
            print_node_ids = print_node_ids.slice(0, -1);
            printJS(
              {
                printable:'/print/view/pdf/areas_of_study_kiosk/filtered_print_page?view_args[0]=' + print_node_ids,
                type:'pdf',
                showModal:true,
                onPrintDialogClose: dialogClose});
          }
          else if(event.target.matches('.view-areas-of-study-kiosk .views-table .form-checkbox')) {
            let view = document.querySelector('.view-areas-of-study-kiosk .views-table');
            let checkboxes = view.querySelectorAll('.form-checkbox');
            let any_checked = false;

            checkboxes.forEach(function (element) {
              if (element.checked) {
                any_checked = true;
              }
            });

            let print_button_container = document.querySelector('.view-header .cta-row__container');
            if (any_checked) {
              print_button_container.classList.add('container-show');
            }
            else {
              print_button_container.classList.remove('container-show');
            }
          }

        }, false);

        function dialogClose() {
          setTimeout(() => {location.reload()} , 5000);
        }

      });
    }
  };
})(jQuery, Drupal, drupalSettings);
