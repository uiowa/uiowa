(function (Drupal) {
  Drupal.behaviors.busArrivals = {
    attach(context) {
      context.querySelectorAll('.bus-arrivals').forEach((element) => {
        const stopId = element.dataset.stopId;
        if (!stopId) {
          return;
        }

        const url = `https://api.icareatransit.org/prediction?stopid=${stopId}`;

        const updateArrivals = async () => {
          try {
            const response = await fetch(url);
            const data = await response.json();
            const predictions = data.predictions ?? [];

            if (predictions.length === 0) {
              element.innerHTML = '<p class="bus-arrivals-message">No upcoming arrivals.</p>';
              return;
            }

            element.innerHTML = `
              <ul class="bus-arrival-list">
                ${predictions.map((item) =>
              `<li style="color: ${item.routeTextColor}; background: ${item.routeColor};">
                    <strong>${item.title}</strong> — ${item.minutes} min - ${item.agencyName}
                  </li>`
            ).join('')}
              </ul>
            `;
          } catch (error) {
            console.error('Fetch error:', error);
            element.innerHTML = '<p class="bus-arrivals-message">Unable to load bus arrival information.</p>';
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
