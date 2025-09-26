Drupal.behaviors.uiowaAlerts = {
  attach: function (context, settings) {

    // We want to use Drupal.once() in this instance because we don't want
    // to set multiple timeouts.
    once('uiowaAlertsGetAlerts', '.block-uiowa-alerts-block', context).forEach(el => {

      const messagesWrapper = el.querySelector('.hawk-alerts-wrapper');
      const messages = new Drupal.Message(messagesWrapper);
      let existingAlerts = getExistingAlerts();

      // Get alerts on page load.
      updateAlerts();

      // Check for changes every 60 seconds.
      setInterval(updateAlerts, 60000);

      // Send out a `GET` request to the alerts endpoint,
      // and call `handleResponse()` when we get a good response.
      function updateAlerts() {

        // Craft a new `XMLHttpRequest()`.
        const xhttp = new XMLHttpRequest();

        // Define a function to be called when the readystate changes in the response.
        xhttp.onreadystatechange = function() {

          // If the readyState is `4`(which in this case means `DONE`),
          // and we got a 200 range status for a successful response...
          if (this.readyState === 4 && this.status === 200) {
            handleResponse(this);
          }
        };

        // Set up the parameters for the request and send it.
        xhttp.open('GET', settings.uiowaAlerts.source, true);
        xhttp.send();
      }

      // Handle the response gotten from the alerts endpoint API call.
      function handleResponse(response) {

        let newAlerts = [];

        // Parse the JSON string into a usable array.
        const responseJSON = JSON.parse(response.responseText);

        // For each item we find in the JSON data...
        responseJSON.data.forEach((item, i) => {

          // Create a new ID for it using the UNIX date to avoid collision
          // and add it to our new alerts
          const id = `hawk-alert-${item.attributes.date}`;
          newAlerts.push(id);

          // If it is already in the Drupal messages section, do not continue.
          if (messages.select(id)) {
            return;
          }

          // Get the hawk alert content (without the alert wrapper)
          const alertContent = hawkAlertContent(item);

          // Set all hawk alerts to danger type (red alert)
          const messageType = 'error';

          // Use Drupal.theme.message to create the themed message.
          const themedMessage = Drupal.theme.message(
            { text: alertContent },
            {
              id: id,
              type: 'warning',
              dismissible: false
            }
          );

          // Add the themed message to the messages container
          messagesWrapper.appendChild(themedMessage);

          // Look for differences in existing and new alerts and remove any closed alerts.
          const difference = existingAlerts.filter(existingAlert => !newAlerts.includes(existingAlert));
          difference.forEach((closed) => {
            messages.remove(closed);
          })

          //Then set existing alerts to the new alerts for the next cycle.
          existingAlerts = newAlerts;
        })
      }

      // Gets existing alerts tracked in the messages wrapper.
      // Returns an array of DOM elements.
      function getExistingAlerts() {
        const existing = [];
        messagesWrapper.querySelectorAll('[data-drupal-message-id]').forEach( (existingAlert) => {
          existing.push(existingAlert.getAttribute('data-drupal-message-id'));
        });

        return existing;
      }

      // Takes a JSON item and creates just the CONTENT for a hawk alert.
      // Returns a string of HTML content (no alert wrapper).
      function hawkAlertContent(responseJSONItem) {
        const item = responseJSONItem;
        const date = new Date(item.attributes.date); // parse the ISO 8601 timestamp

        // Create DateTimeFormat instances with the options
        const monthFormatter = new Intl.DateTimeFormat('en-US', { month: 'long', timeZone: 'America/Chicago' });
        const timeFormatter = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', timeZone: 'America/Chicago', hour12: true });

        // Format the date and time
        const month = monthFormatter.format(date);
        const time = timeFormatter.format(date).replace('AM', 'a.m.').replace('PM', 'p.m.');

        return `
          <div class="hawk-alert-message" role="region" aria-label="hawk alert message">
            <h2 class="headline headline--serif">
              <span class="hawk-alert-heading">
                <span class="hawk-alert-label">Hawk Alert</span>
              </span>
            </h2>
            <p><em><span class="hawk-alert-date">${month} ${date.getDate()}, ${date.getFullYear()} - ${time}</span></em><br />
              <span class="hawk-alert-body">${item.attributes.alert}</span>
              <a class="hawk-alert-link alert-link" href="${item.attributes.more_info_link}">Visit ${item.attributes.more_info_link} for more information.</a></p>
          </div>
        `;
      }
    });
  }
};
