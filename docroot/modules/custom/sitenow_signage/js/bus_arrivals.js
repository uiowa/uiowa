(function (Drupal) {
  Drupal.behaviors.busArrivals = {
    attach(context) {
      context.querySelectorAll('.bus-arrivals').forEach((element) => {
        const stopId = element.dataset.stopId;
        if (!stopId) {
          return;
        }

        const url = `https://api.icareatransit.org/prediction?stopid=${stopId}`;
        const stopName = element.dataset.stopName?.trim();
        const label = stopName ? `Next Arrivals for Stop ${stopName}` : 'Bus arrival information';

        const updateArrivals = async () => {
          let predictionsTable = `<table>
            <caption class='predictions-title'>${label}</caption>
            <thead class='headers'>
              <tr>
                <th>Agency</th>
                <th>Route</th>
                <th>Time</th>
              </tr>
            </thead>`;

          try {
            const response = await fetch(url);
            const data = await response.json();
            const predictions = (data.predictions ?? []).slice(0, 10);

            if (predictions.length === 0) {
              predictionsTable += `<td class='bg-dark-gray' colspan='3' >No upcoming arrivals.</td></table>`;
              element.innerHTML = predictionsTable;
              return;
            }

            predictionsTable += `
                <tbody>
                  ${predictions.map((item) =>
                  `<tr class='prediction ${item.agency}'>
                      <td class='bg-dark-gray agency'>${item.agencyName}</td>
                      <td class='bg-dark-gray title'>${item.title}</td>
                      <td class='bg-dark-gray minutes'>${minutesTranslation(item.minutes)}</td>
                    </tr>`
                  ).join('')}
                </tbody>
              </table>
            `;
            element.innerHTML = predictionsTable;
          }

          catch (error) {
            console.error('Fetch error:', error);
            predictionsTable += `<td class='bg-dark-gray' colspan='3' >Unable to load bus arrival information.</td></table>`;
            element.innerHTML = predictionsTable;
          }
        };

        // Initial fetch.
        updateArrivals();

        // Refresh every 60 seconds.
        setInterval(updateArrivals, 60000);
      });
    }
  };

  // Translates special cases.
  function minutesTranslation(minutes) {
    if (minutes === '0') {
      return 'Now arriving';
    }

    if (minutes === '1') {
      return '1 minute';
    }

    return minutes + ' minutes';
  }
})(Drupal);
