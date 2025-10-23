(function (Drupal, once, drupalSettings) {
  'use strict';

  // Attach csv_graph behavior.
  Drupal.behaviors.csv_graph = {
    attach: function (context, settings) {
      const graphContainers = once('csv_graph', '.graph-container', context);

      graphContainers.forEach(function (graph_container) {
        // This function waits for Highcharts to load completely, then executes our functions.
        continuous_wait_for_highcharts();

        async function getTableData(element) {
          let graphTable = element;

          let tableDataByRow = [];
          let tableDataByColumn = {};

          // Get each row.
          let rows = graphTable.querySelectorAll('tr');
          // For each row...
          rows.forEach(function (row) {
            let rowData = [];
            // Get the elements in that row.
            let rowEls = row.querySelectorAll('th,td');
            // For each element in that row...
            rowEls.forEach(function (element) {
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
          if (
            (function () {
              let equalLength = true;

              tableDataByRow.forEach(function (row) {
                equalLength =
                  equalLength && row.length === tableDataByRow[0].length;
              });

              return equalLength;
            })()
          ) {
            tableDataByRow[0].forEach(function (header, headerIndex) {
              tableDataByColumn[header] = [];
              for (let row = 1; row < tableDataByRow.length; row++) {
                let value = tableDataByRow[row][headerIndex];
                let originalValue = null;

                // Parse value: handle dollars, percentages, commas, negatives, and regular numbers.
                // BUT keep the first column (index 0) as strings for category labels.
                if (value !== null && value !== '' && headerIndex !== 0) {
                  // Convert to string and trim whitespace.
                  let strValue = value.toString().trim();

                  // Store original value for reference.
                  originalValue = strValue;

                  // Handle negative numbers in accounting format: ($1,234) or -$1,234.
                  let isNegative = false;
                  if (strValue.startsWith('(') && strValue.endsWith(')')) {
                    isNegative = true;
                    strValue = strValue.slice(1, -1); // Remove parentheses.
                  } else if (strValue.startsWith('-')) {
                    isNegative = true;
                    strValue = strValue.slice(1); // Remove minus sign.
                  }

                  // Remove dollar signs, euro signs, pound signs, and commas.
                  let cleanValue = strValue.replace(/[$€£,]/g, '');

                  // Handle greater-than-or-equal symbol and similar prefixes
                  cleanValue = cleanValue.replace(/^[≥>≤<~±]\s*/g, '');

                  // Check if it's a percentage.
                  let isPercentage = cleanValue.includes('%');
                  if (isPercentage) {
                    cleanValue = cleanValue.replace('%', '');
                  }

                  // Try to convert to number if it's numeric.
                  if (cleanValue !== '' && !isNaN(cleanValue)) {
                    value = parseFloat(cleanValue);
                    if (isNegative) {
                      value = -value;
                    }
                  }
                  // Otherwise keep the original value (for category labels, etc.).
                } else if (value !== null && value !== '') {
                  // Keep first column values as strings (category labels).
                  value = value.toString().trim();
                }

                // Store both parsed value and original string for formatting
                tableDataByColumn[header].push({
                  value: value,
                  original: originalValue,
                });
              }
            });
          } else {
            console.error(
              'The data in the given table is formatted incorrectly. Please check your data and make sure that there are the same number of values in each row.'
            );
            return;
          }

          return {
            headers: tableDataByRow[0],
            data: tableDataByColumn,
          };
        }

        /**
         * Detect the data type of a series based on column header and values
         */
        function detectDataType(header, values) {
          // Check header for indicators.
          let headerLower = header.toLowerCase();

          // Check for escape sequences to override detection.
          if (
            headerLower.includes('[currency]') ||
            headerLower.includes('\\currency')
          ) {
            return 'currency';
          }
          if (
            headerLower.includes('[decimal]') ||
            headerLower.includes('\\decimal')
          ) {
            return 'decimal';
          }
          if (
            headerLower.includes('[percentage]') ||
            headerLower.includes('\\percentage')
          ) {
            return 'percentage';
          }
          if (
            headerLower.includes('[number]') ||
            headerLower.includes('\\number')
          ) {
            return 'number';
          }

          if (headerLower.includes('percent') || headerLower.includes('rate')) {
            return 'percentage';
          }

          // Check for percentile indicators (like "25th %", "50th %", "median %") which are NOT percentages.
          // Use regex to detect ordinal numbers followed by % or percentile keywords.
          let percentilePattern =
            /\d+(st|nd|rd|th)\s*(%|percentile)|percentile|quartile|median/i;
          if (
            headerLower.includes('%') &&
            percentilePattern.test(headerLower)
          ) {
            return 'currency';
          } else if (headerLower.includes('%')) {
            return 'percentage';
          }

          if (
            headerLower.includes('$') ||
            headerLower.includes('salary') ||
            headerLower.includes('wage') ||
            headerLower.includes('income') ||
            headerLower.includes('revenue') ||
            headerLower.includes('cost') ||
            headerLower.includes('price')
          ) {
            return 'currency';
          }

          // Check values for indicators.
          let maxValue = 0;
          let hasDecimals = false;
          let valueCount = 0;

          values.forEach(function (val) {
            if (val !== null && !isNaN(val)) {
              valueCount++;
              maxValue = Math.max(maxValue, Math.abs(val));
              if (val % 1 !== 0) {
                hasDecimals = true;
              }
            }
          });

          // Only treat as percentage if header explicitly indicates it or values look like percentages
          // (between 0-100 with decimals AND header suggests percentage context).
          if (maxValue <= 100 && hasDecimals && valueCount > 0) {
            // Additional check: only return percentage if header context suggests it
            if (
              headerLower.includes('rating') ||
              headerLower.includes('score') ||
              headerLower.includes('average') ||
              headerLower.includes('competenc')
            ) {
              return 'decimal';
            }
            // For other cases between 0-100 with decimals, assume percentage only if very specific
            if (maxValue <= 10 && hasDecimals) {
              return 'decimal'; // Likely a rating scale
            }
            return 'percentage';
          }

          // If values are >= 1000, likely currency.
          if (maxValue >= 1000) {
            return 'currency';
          }

          // Otherwise, regular number.
          return hasDecimals ? 'decimal' : 'number';
        }

        async function setupGraph(element) {
          const canvas = element.querySelector('.graph-canvas');
          const graphData = await getTableData(
            element.querySelector('.graph-table')
          );

          // Get chart type from data attribute (default to line).
          const chartType = element.getAttribute('data-chart-type') || 'line';

          // Map 'donut' to 'pie' since Highcharts doesn't have a separate donut type.
          const isDonut = chartType === 'donut';
          const actualChartType = isDonut ? 'pie' : chartType;

          let series = [];

          // Define accessible color palette using Iowa brand colors.
          const colors = [
            '#00558C', // Brand Blue
            '#00664F', // Brand Green
            '#BD472A', // Brand Red/Orange
            '#63666A', // Brand Gray
            '#000000', // Brand Black
          ];

          // Detect data types for all series.
          let dataTypes = [];
          let primaryDataType = null;

          // Handle pie and donut charts differently.
          if (actualChartType === 'pie') {
            // Pie/donut charts use first column as categories, second column as values.
            let pieData = [];
            let categories = graphData.data[graphData.headers[0]];
            let values = graphData.data[graphData.headers[1]];

            // Extract numeric values for data type detection
            let numericValues = values.map((v) => v.value);
            // Detect data type from values.
            primaryDataType = detectDataType(
              graphData.headers[1],
              numericValues
            );

            categories.forEach(function (cat, index) {
              let catValue = cat.value || cat;
              let numericValue = values[index].value;
              let originalValue = values[index].original;
              if (numericValue !== null && !isNaN(numericValue)) {
                pieData.push({
                  name: catValue ? catValue.toString() : 'Unknown',
                  y: numericValue,
                  color: colors[index % colors.length],
                  originalValue: originalValue,
                });
              }
            });

            series = [
              {
                type: 'pie',
                name: graphData.headers[1],
                data: pieData,
                innerSize: isDonut ? '50%' : '0%',
                dataType: primaryDataType,
              },
            ];
          } else {
            // Standard line/bar/column charts.
            for (let i = 1; i < graphData.headers.length; i++) {
              let color = colors[(i - 1) % colors.length];
              let seriesData = graphData.data[graphData.headers[i]];

              // Extract numeric values for data type detection
              let numericValues = seriesData.map((v) => v.value);
              // Detect data type for this series.
              let dataType = detectDataType(
                graphData.headers[i],
                numericValues
              );
              dataTypes.push(dataType);

              // Set primary data type (used for Y-axis formatting).
              if (!primaryDataType) {
                primaryDataType = dataType;
              }

              // Extract numeric values for chart data with original values
              let chartData = seriesData.map((v) => ({
                y: v.value,
                originalValue: v.original,
              }));
              series.push({
                type: actualChartType,
                name: graphData.headers[i],
                data: chartData,
                color: color,
                marker: {
                  enabled: actualChartType === 'line' ? true : false,
                },
                dataType: dataType,
              });
            }
          }

          // Build chart options based on chart type.
          let chartOptions = {
            chart: {
              backgroundColor: 'transparent',
            },
            title: {
              text: null,
            },
            accessibility: {
              enabled: true,
              description:
                element.querySelector('#' + element.id + '-summary')
                  ?.textContent || 'Data visualization chart',
              keyboardNavigation: {
                enabled: true,
              },
              point: {
                valueDescriptionFormat: '{index}. {xDescription}, {value}.',
              },
            },
            legend: {
              enabled: true,
              layout: 'horizontal',
              align: 'center',
              verticalAlign: 'bottom',
            },
            plotOptions: {
              series: {
                animation: true,
              },
              line: {
                marker: {
                  radius: 4,
                },
              },
              column: {
                borderWidth: 0,
              },
              pie: {
                allowPointSelect: true,
                cursor: 'pointer',
                dataLabels: {
                  enabled: true,
                  format: '<b>{point.name}</b>: {point.percentage:.1f}%',
                },
                showInLegend: true,
              },
            },
            series: series,
            credits: {
              enabled: false,
            },
            responsive: {
              rules: [
                {
                  condition: {
                    maxWidth: 500,
                  },
                  chartOptions: {
                    legend: {
                      layout: 'horizontal',
                      align: 'center',
                      verticalAlign: 'bottom',
                    },
                  },
                },
              ],
            },
          };

          // Add axes and tooltips for non-pie charts.
          if (actualChartType !== 'pie') {
            // Get categories from first column, ensure they're strings.
            let categories = graphData.data[graphData.headers[0]].map(
              function (cat) {
                let catValue = cat.value || cat;
                return catValue !== null && catValue !== undefined
                  ? catValue.toString()
                  : '';
              }
            );

            chartOptions.xAxis = {
              categories: categories,
              title: {
                text: graphData.headers[0],
              },
            };

            chartOptions.yAxis = {
              title: {
                text: 'Value',
              },
              labels: {
                formatter: function () {
                  return formatValue(this.value, primaryDataType);
                },
              },
            };

            chartOptions.tooltip = {
              shared: true,
              useHTML: true,
              formatter: function () {
                // Get the actual category name from the xAxis.
                var categoryName = this.x;
                if (
                  typeof this.x === 'number' &&
                  this.points &&
                  this.points.length > 0
                ) {
                  // If x is a number (index), get the actual category from xAxis.
                  categoryName = this.points[0].series.xAxis.categories[this.x];
                }

                var s = '<b>' + categoryName + '</b>';
                if (this.points) {
                  // Shared tooltip.
                  this.points.forEach(function (point) {
                    let dataType =
                      point.series.userOptions.dataType || primaryDataType;
                    let originalValue = point.point.originalValue || null;
                    s +=
                      '<br/>' +
                      point.series.name +
                      ': <b>' +
                      formatValue(point.y, dataType, originalValue) +
                      '</b>';
                  });
                } else {
                  // Single point tooltip.
                  let dataType =
                    this.series.userOptions.dataType || primaryDataType;
                  let originalValue = this.point.originalValue || null;
                  s +=
                    '<br/>' +
                    this.series.name +
                    ': <b>' +
                    formatValue(this.y, dataType, originalValue) +
                    '</b>';
                }
                return s;
              },
            };
          } else {
            // Pie/donut chart tooltip.
            chartOptions.tooltip = {
              useHTML: true,
              formatter: function () {
                let dataType =
                  this.series.userOptions.dataType || primaryDataType;
                let originalValue = this.point.originalValue || null;
                return (
                  '<b>' +
                  this.point.name +
                  '</b><br/>' +
                  'Value: <b>' +
                  formatValue(this.y, dataType, originalValue) +
                  '</b><br/>' +
                  'Percentage: <b>' +
                  Highcharts.numberFormat(this.percentage, 1) +
                  '%</b>'
                );
              },
            };
          }

          // Create Highcharts chart.
          const chart = Highcharts.chart(canvas, chartOptions);

          // Handle window resize.
          let canvas__container = canvas.parentElement;
          let resizeTimeout;

          window.addEventListener('resize', function () {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function () {
              let layout__region =
                canvas__container.parentElement.parentElement.parentElement;

              if (layout__region.classList.contains('layout__region')) {
                canvas__container.style.width =
                  layout__region.clientWidth + 'px';
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

        /**
         * Format a value based on its data type
         */
        function formatValue(value, dataType, originalValue) {
          if (value === null || value === undefined || isNaN(value)) {
            return 'n/a';
          }

          switch (dataType) {
            case 'currency':
              // Check if original value had a prefix symbol.
              let prefix = '';
              if (originalValue && /^[≥>≤<~±]/.test(originalValue.toString())) {
                prefix = originalValue.toString().match(/^[≥>≤<~±]/)[0] + ' ';
              }

              // Handle negative values.
              if (value < 0) {
                return (
                  '(' +
                  prefix +
                  '$' +
                  Highcharts.numberFormat(Math.abs(value), 0, '.', ',') +
                  ')'
                );
              }
              return prefix + '$' + Highcharts.numberFormat(value, 0, '.', ',');

            case 'percentage':
              return Highcharts.numberFormat(value, 2) + '%';

            case 'decimal':
              return Highcharts.numberFormat(value, 2);

            case 'number':
            default:
              // Integers with commas for large numbers.
              if (Math.abs(value) >= 1000) {
                return Highcharts.numberFormat(value, 0, '.', ',');
              }
              return Highcharts.numberFormat(value, 0);
          }
        }

        function setHeightCanvas(canvas__container) {
          // Let Highcharts control the height through its responsive settings.
          // Remove any inline height styling to allow natural sizing.
          canvas__container.style.height = '';
        }

        function continuous_wait_for_highcharts(count = 0) {
          let timeout = 300;
          let limit = 50;
          setTimeout(function () {
            if (typeof Highcharts !== 'undefined') {
              setupGraph(graph_container);
            } else if (count < limit) {
              continuous_wait_for_highcharts(count + 1);
            } else {
              console.error(
                'The Highcharts library did not seem to load properly.'
              );
            }
          }, timeout);
        }

        // .----end behavior container----.
      });
    },
  };
})(Drupal, once, drupalSettings);
