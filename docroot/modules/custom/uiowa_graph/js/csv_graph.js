(function (Drupal, once, drupalSettings) {
  'use strict';

  // Attach csv_graph behavior.
  Drupal.behaviors.csv_graph = {
    attach: function (context, settings) {
      const graphContainers = once('csv_graph', '.graph-container', context);

      graphContainers.forEach(function(graph_container) {
        // This function waits for Highcharts to load completely, then executes our functions.
        continuous_wait_for_highcharts();

        async function getTableData(element) {
          let graphTable = element;

          let tableDataByRow = [];
          let tableDataByColumn = {};

          // Get each row.
          let rows = graphTable.querySelectorAll('tr');
          // For each row...
          rows.forEach(function(row) {
            let rowData = [];
            // Get the elements in that row.
            let rowEls = row.querySelectorAll('th,td');
            // For each element in that row...
            rowEls.forEach(function(element) {
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

          // If all of the arrays in `tableDataByRow` are the same length...
          if (function() {
            let equalLength = true;

            tableDataByRow.forEach(function(row) {
              equalLength = equalLength && (row.length === tableDataByRow[0].length);
            });

            return equalLength;
          }()) {
            tableDataByRow[0].forEach(function(header, headerIndex) {
              tableDataByColumn[header] = [];
              for (let row = 1; row < tableDataByRow.length; row++) {
                let value = tableDataByRow[row][headerIndex];

                // Parse value: handle dollars, percentages, commas, and regular numbers
                if (value !== null && value !== '') {
                  // Convert to string and trim whitespace
                  let strValue = value.toString().trim();

                  // Remove dollar signs and commas
                  let cleanValue = strValue.replace(/[$,]/g, '');

                  // Check if it's a percentage
                  if (cleanValue.includes('%')) {
                    cleanValue = cleanValue.replace('%', '');
                  }

                  // Try to convert to number if it's numeric
                  if (cleanValue !== '' && !isNaN(cleanValue)) {
                    value = parseFloat(cleanValue);
                  }
                  // Otherwise keep the original value (for category labels, etc.)
                }

                tableDataByColumn[header].push(value);
              }
            });
          }
          else {
            console.error("The data in the given table is formatted incorrectly. Please check your data and make sure that there are the same number of values in each row.");
            return;
          }

          return {
            'headers' : tableDataByRow[0],
            'data'    : tableDataByColumn
          };
        }

        async function setupGraph(element) {
          const canvas = element.querySelector('.graph-canvas');
          const graphData = await getTableData(element.querySelector('.graph-table'));

          // Get chart type from data attribute (default to line)
          const chartType = element.getAttribute('data-chart-type') || 'line';

          let series = [];

          // Define accessible color palette (WCAG AA compliant colors)
          // These colors provide good contrast and are distinguishable for colorblind users
          const colors = [
            '#FFCD00',  // UI Gold (Iowa brand color)
            '#414141',  // Dark Grey (Iowa brand color)
            '#0074B7',  // Blue
            '#C41E3A',  // Red
            '#118B4F',  // Green
            '#6B2D84',  // Purple
            '#F58025',  // Orange
            '#007398',  // Teal
            '#8B1A4F',  // Maroon
            '#4A7729',  // Olive
          ];

          for (let i = 1; i < graphData.headers.length; i++) {
            // Use modulo to cycle through colors if we have more series than colors
            let color = colors[(i - 1) % colors.length];

            series.push({
              type: chartType,
              name: graphData.headers[i],
              data: graphData.data[graphData.headers[i]],
              color: color,
              marker: {
                enabled: chartType === 'line' ? true : false
              }
            });
          }

          // Create Highcharts chart
          const chart = Highcharts.chart(canvas, {
            chart: {
              height: null,
              backgroundColor: 'transparent'
            },
            title: {
              text: null
            },
            accessibility: {
              enabled: true,
              description: element.querySelector('#' + element.id + '-summary')?.textContent || 'Data visualization chart',
              keyboardNavigation: {
                enabled: true
              },
              point: {
                valueDescriptionFormat: '{index}. {xDescription}, {value}.'
              }
            },
            xAxis: {
              categories: graphData.data[graphData.headers[0]],
              title: {
                text: graphData.headers[0]
              }
            },
            yAxis: {
              title: {
                text: 'Value'
              }
            },
            legend: {
              enabled: true,
              layout: 'horizontal',
              align: 'center',
              verticalAlign: 'bottom'
            },
            tooltip: {
              shared: true
            },
            plotOptions: {
              series: {
                animation: true
              },
              line: {
                marker: {
                  radius: 4
                }
              },
              column: {
                borderWidth: 0
              }
            },
            series: series,
            credits: {
              enabled: false
            },
            responsive: {
              rules: [{
                condition: {
                  maxWidth: 500
                },
                chartOptions: {
                  legend: {
                    layout: 'horizontal',
                    align: 'center',
                    verticalAlign: 'bottom'
                  }
                }
              }]
            }
          });

          // Handle window resize
          let canvas__container = canvas.parentElement;
          let resizeTimeout;

          window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
              let layout__region = canvas__container.parentElement.parentElement.parentElement;

              if (layout__region.classList.contains('layout__region')) {
                canvas__container.style.width = layout__region.clientWidth + "px";
                setHeightCanvas(canvas__container);

                // Trigger Highcharts reflow
                if (chart) {
                  chart.reflow();
                }
              }
            }, 100);
          });

          setHeightCanvas(canvas__container);
        }

        function setHeightCanvas(canvas__container) {
          let width = parseInt(canvas__container.style.width || canvas__container.offsetWidth, 10);
          canvas__container.style.height = width / 2 + "px";
        }

        function continuous_wait_for_highcharts(count = 0) {
          let timeout = 300;
          let limit = 50;
          setTimeout(function () {
            if (typeof Highcharts !== 'undefined') {
              setupGraph(graph_container);
            }
            else if (count < limit) {
              continuous_wait_for_highcharts(count + 1);
            }
            else {
              console.error('The Highcharts library did not seem to load properly.');
            }
          }, timeout);
        }

        // .----end behavior container----.
      });
    }
  };
})(Drupal, once, drupalSettings);
