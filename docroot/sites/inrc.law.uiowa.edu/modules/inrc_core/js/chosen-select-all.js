(function (Drupal) {
  'use strict';

  Drupal.behaviors.grantCountiesChosen = {
    attach: function (context, settings) {
      once('grant-counties', '#edit-field-grant-counties-wrapper', context).forEach(function(wrapper) {
        const select = wrapper.querySelector('select');

        // Function to update Chosen.
        function updateChosen() {
          const event = new Event('chosen:updated');
          select.dispatchEvent(event);
        }

        // Select All button.
        wrapper.querySelector('.grant-counties-select-all').addEventListener('click', function(e) {
          e.preventDefault();
          var options = select.options;
          for (var i = 0; i < options.length; i++) {
            options[i].selected = true;
          }
          updateChosen();
        });

        // Deselect All button.
        wrapper.querySelector('.grant-counties-deselect-all').addEventListener('click', function(e) {
          e.preventDefault();
          var options = select.options;
          for (var i = 0; i < options.length; i++) {
            options[i].selected = false;
          }
          updateChosen();
        });
      });
    }
  };

})(Drupal);
