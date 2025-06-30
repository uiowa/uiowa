(function (Drupal) {
  Drupal.behaviors.busArrivals = {
    attach(context) {
      context.querySelectorAll('.bus-arrivals').forEach((element) => {
        const stopId = element.dataset.stopId;
        const stopName = element.dataset.stopName;
        if (!stopId) {
          return;
        }

        const palette = {
          'coralville' : {
            'bg' : 'var(--coralville)',
            'fg' : 'var(--text-dark)'
          },
          'iowacity' : {
            'bg' : 'var(--iowa-city)',
            'fg' : 'var(--text-dark)'
          },
          'uiowa' : {
            'bg' : 'var(--uiowa)',
            'fg' : 'var(--text-dark)'
          },
        }

        const url = `https://api.icareatransit.org/prediction?stopid=${stopId}`;

        const updateArrivals = async () => {
          let predictionsTable = `<table>
            <caption>Next Arrivals for Stop ${stopName ?? 'Bus arrival information'}</caption>
            <thead>
              <tr>
                <th>Time</th>
                <th>Route</th>
                <th>Agency</th>
              </tr>
            </thead>`;

          try {
            const response = await fetch(url);
            const data = await response.json();
            const predictions = data.predictions ?? [];

            if (predictions.length === 0) {
              predictionsTable += `<td colspan='3' >No upcoming arrivals.</td></table>`;
              element.innerHTML = predictionsTable;
              return;
            }

            predictionsTable += `
              <tbody>
                ${predictions.map((item) =>
                  `<tr style="color: ${palette[item.agency].fg}; background: ${palette[item.agency].bg};">
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
