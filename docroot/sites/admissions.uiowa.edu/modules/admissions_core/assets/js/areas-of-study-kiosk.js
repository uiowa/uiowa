(function ($, Drupal, drupalSettings) {
  // Attach aos_kiosk behavior.
  Drupal.behaviors.aos_kiosk = {
    attach: function (context, settings) {
      $('.view-id-areas_of_study_kiosk', context).once('aos_kiosk').each(function (index) {
        document.addEventListener('click', function (event) {

          // If the clicked element doesn't have the right selector, bail
          if (!event.target.matches('#kiosk-print-button')) return;

          // Do our code
          let view = document.querySelector('.view-areas-of-study-kiosk .views-table');
          let checkboxes = view.querySelectorAll('.form-checkbox');
          // .checked

          let print_node_ids = '';
          checkboxes.forEach(function (element) {
            if (element.checked) {
              print_node_ids += element.value + ' ';
            }
          })
          print_node_ids = print_node_ids.slice(0, -1);
          printJS('/print/view/pdf/areas_of_study_kiosk/filtered_print_page?view_args[0]=' + print_node_ids);

        }, false);
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
