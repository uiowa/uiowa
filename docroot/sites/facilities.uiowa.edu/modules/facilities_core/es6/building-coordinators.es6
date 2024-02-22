(function (Drupal, drupalSettings, once) {
  // Attach building_coordinators_block behavior.
  Drupal.behaviors.building_coordinators_block = {
    attach: function (context, settings) {
      once('building_coordinators_block', '.view-id-building_list.view-display-id-block_building_coordinators', context).forEach(function (index) {
            console.log('asdf');
      });
    }
  };
})(Drupal, drupalSettings, once);
