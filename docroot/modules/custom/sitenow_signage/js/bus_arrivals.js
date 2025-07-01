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
            <caption>${label}</caption>
            <thead class='headers'>
              <tr>
                <th>Time</th>
                <th>Route</th>
                <th>Agency</th>
              </tr>
            </thead>`;

          try {
            const response = await fetch(url);
            const data = await response.json();
            const predictions = (data.predictions ?? []).slice(0, 10);

            if (predictions.length === 0) {
              predictionsTable += `<td colspan='3' >No upcoming arrivals.</td></table>`;
              element.innerHTML = predictionsTable;
              return;
            }

            predictionsTable += `
              <tbody>
                ${predictions.map((item) =>
                  `<tr class='prediction ${item.agency}'>
                      <td>${item.minutes} minutes</td>
                      <td>${item.title}</td>
                      <td>${item.agencyName}</td>
                  </tr>`
                ).join('')}
              <tbody>
              </table>
            `;
            element.innerHTML = predictionsTable;
          } catch (error) {
            console.error('Fetch error:', error);
            predictionsTable += `<td colspan='3' >Unable to load bus arrival information.</td></table>`;
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
})(Drupal);
