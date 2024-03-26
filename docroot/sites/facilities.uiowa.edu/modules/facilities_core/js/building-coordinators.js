(function (Drupal, once) {
  // Attach building_coordinators_block behavior.
  Drupal.behaviors.building_coordinators_block = {
    attach: function () {
      const blocks = once(
        'building_coordinators_block',
        '.view-id-building_list.view-display-id-block_building_coordinators'
      );
      if (blocks.length < 1) {
        return;
      }

      // Change blocks to hold extra data.
      blocks.forEach(function (block, index) {
        blocks[index] = {
          element: block,
          detailsData: []
        };
      });

      blocks.forEach(function (block, index) {
        window.onbeforeprint = (event) => {
          detailsData(block);
          detailsOpen(block);
        };

        window.onafterprint = (event) => {
          detailsReset(block);
        };
      });
    }
  };

  // Sets the details data for each element in `block.
  // This allows us to retain the open or closed state
  // of details elements as the user defined them so that
  // when the user closes the print dialog, we can reset
  // them to what the user chose.
  function detailsData(block) {
    const context = block.element;
    let data = [];
    let details = context.querySelectorAll('.contacts-wrapper details');

    // Set the details data for each details element.
    details.forEach(function(detail){
      this.is_open = detail.hasAttribute('open');
      data.push({
        element: detail,
        is_open: this.is_open
      });
    });

    block.detailsData = data;
  }

  // Opens every details element in the table of `block`.
  function detailsOpen(block) {
    forEveryDetails(block, function(detailsDataPoint) {
      const element = detailsDataPoint.element;
      element.setAttribute('open', '');
    });
  }

  // Resets every details element in the table of `block` to
  // its previously open or closed state before the user triggered a print.
  function detailsReset(block) {
    forEveryDetails(block, function(detailsDataPoint) {
      const is_open = detailsDataPoint.is_open;

      if (!is_open) {
        const element = detailsDataPoint.element;
        element.removeAttribute('open');
      }
    });
  }

  // Helper function to have a callback execute on every details element.
  function forEveryDetails(block, callback) {
    const data = block.detailsData;
    data.forEach((detailsDataPoint) => {
      callback(detailsDataPoint)
    });
  }
})(Drupal, once);
