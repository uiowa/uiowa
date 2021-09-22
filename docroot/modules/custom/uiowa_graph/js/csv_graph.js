(($, Drupal, drupalSettings) => {
  // Attach csv_graph behavior.
  Drupal.behaviors.csv_graph = {
    attach: (context, settings) => {
      $('.graph-container', context).once('csv_graph').each((index) => {
        // .----start behavior container----.
        let graph_container = document.querySelectorAll('.graph-container')[index];

        // This function waits for charts.js to load completely, then executes our functions.
        // If this is not there, the library will load after this file.
        // Thus, the calls will not function because the library is not loaded.
        continuous_wait_for_charts();

        async function getTableData(element) {
          let graphTable = element;

          let tableDataByRow = [];
          let tableDataByColumn = {};

          // Get each row.
          let rows = graphTable.querySelectorAll('tr');
          // For each row...
          rows.forEach(row => {
            let rowData = [];
            // Get the elements in that row.
            let rowEls = row.querySelectorAll('th,td');
            // For each element in that row...
            rowEls.forEach(element =>  {
              // Add that elements text to the row data.
              let text = element.textContent;
              if (text == '') {
                text = null;
              }
              rowData.push(text);
            });

            // Put the row's data in the `tableDataByRow` variable.
            tableDataByRow.push(rowData);
          });

          // If all of the arrays in `tableDataByRow` are the same...
          // I know this looks weird but it works.
          if (function() {
            let equalLength = true;

            tableDataByRow.forEach( row => {
              equalLength = equalLength && (row.length = tableDataByRow[0].length);
            });

            return equalLength;
          }) {
            tableDataByRow[0].forEach( (header, headerIndex) => {
              tableDataByColumn[header] = [];
              for (row = 1; row <tableDataByRow.length; row++) {
                tableDataByColumn[header].push(tableDataByRow[row][headerIndex]);
              }
            });
          }
          else {
            console.error("The data in the given table is formatted incorrectly. Please check your data and make sure that there are the same number of values in each row.")
            return;
          }

          return {
            'headers' : tableDataByRow[0],
            'data'    : tableDataByColumn
          };
        }

        async function setupGraph(element) {
          const canvas = element.querySelectorAll('.graph-canvas')[0];
          const canvasContext = canvas.getContext('2d');
          const graphData = await getTableData(element.querySelectorAll('.graph-table')[0]);
          let datasets = [];

          for (let i = 1; i < graphData.headers.length; i++) {
            // Default to UI Gold.
            let color = random_rgb(255, 205, 0);
            // If we are on the second dataset, set the color to Dark Grey.
            if (i == 2) {
              color = random_rgb(65, 65, 65);
            }
            // Else if it is not the first two datasets, pick a random color.
            else if (i > 2) {
              color = random_rgb();
            }

            datasets.push(
              {
                type: 'line',
                label: graphData.headers[i],
                data: graphData.data[graphData.headers[i]],
                fill: false,
                borderColor: color.fullAlpha,
                backgroundColor: color.halfAlpha,
                borderWidth: 1
              }
            );
          }

          const myChart = new Chart(canvasContext, {
            data: {
              labels: graphData.data[graphData.headers[0]],
              datasets: datasets,
            },
            options: {
              responsive: true,
              maintainAspectRatio: false
            }
          });

          let canvas__container = canvas.parentElement;
          $( window ).resize(function() {
            let layout__region = canvas__container.parentElement.parentElement.parentElement;

            if (layout__region.classList.contains('layout__region')) {
              canvas__container.style.width = layout__region.clientWidth + "px";

              setHeightCanvas(canvas, canvas__container);
            }
          });

          setHeightCanvas(canvas, canvas__container);
        }

        // Generate a random RGBA() color.
        function random_rgb(givenR = null, givenG = null, givenB = null) {
          var o = Math.round, r = Math.random, s = 255;
          let R = (givenR != null) ? givenR : o(r()*s);
          let G = (givenG != null) ? givenG : o(r()*s);
          let B = (givenB != null) ? givenB : o(r()*s);
          return {
            'fullAlpha' : 'rgba(' + R + ',' + G + ',' + B + ',' + 1 + ')',
            'halfAlpha' : 'rgba(' + R + ',' + G + ',' + B + ',' + 0.5 + ')'
          }
        }

        function setHeightCanvas(canvas, canvas__container) {
          canvas__container.style.height = parseInt(canvas.style.width, 10)/2 + "px";
        }

        function continuous_wait_for_charts(count = 0) {
          let timeout = 300;
          let limit = 50;
          setTimeout(function () {
            if (typeof Chart === 'function') {
              setupGraph(graph_container);
            }
            else if (count < limit) {
              continuous_wait_for_charts(count + 1);
            }
            else {
              console.error('The chart.js library did not seem to load properly.');
            }
          }, timeout);
        }

        // .----end behavior container----.
      });
    }
  };
})(jQuery, Drupal, drupalSettings);



