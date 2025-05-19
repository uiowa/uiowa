Drupal.behaviors.uiowaAlerts = {
  attach: function (context, settings) {

    // We want to use Drupal.once() in this instance because we don't want
    // to set multiple timeouts.
    once('uiowaAlertsGetAlerts', '.block-uiowa-alerts-block', context).forEach(el => {

      const messages = new Drupal.Message(el.querySelector('.hawk-alerts-wrapper'));
      request();
      // Get alerts on page load.
      // updateAlerts(messages);

      // Check for changes every 30 seconds.
      // setInterval(updateAlerts, 30000, messages);
    });

    function updateAlerts() {
      // Get the alerts feed and track IDs as "new" alerts.
//       $.ajax({
//         url: settings.uiowaAlerts.source,
//         dataType: "json",
//         success: (response) => {
//           let new_alerts = [];
//
//           $.each(response.data, (i, item) => {
//             let id = `hawk-alert-${item.attributes.date}`;
//             new_alerts.push(id);
//
//             if (!messages.select(id)) {
//
//               // MAKE THE ALERT

//               messages.add(alert, {
//                 id: id,
//                 type: 'warning'
//               });
//             }
//           });
//
//           let existing_alerts = [];
//
//           // Get the existing alerts on the page and track IDs.
//           document.querySelectorAll('.hawk-alerts-wrapper .messages').forEach( (existing_alert) => {
//             existing_alerts.push(existing_alert.getAttribute('data-drupal-message-id'));
//           });
//
//           // Return any existing alerts that are not in the feed anymore.
//           let difference = existing_alerts.filter(x => !new_alerts.includes(x));
//
//           // Remove any closed alerts.
//           difference.forEach((closed) => {
//             messages.remove(closed);
//           })
//         }
//       });
    };

    function request() {
      const xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
          handleResponse(this);
        }
      };
      xhttp.open('GET', settings.uiowaAlerts.source, true);
      xhttp.send();
    }

    function handleResponse(response) {
      const responseJSON = JSON.parse(response.responseText);

      responseJSON.data.forEach((item, i) => {
        console.log(alertMarkup(item));
      })
    }

    function alertMarkup(responseJSONItem) {
      const item = responseJSONItem;
      const date = new Date(item.attributes.date); // parse the ISO 8601 timestamp

      // Create DateTimeFormat instances with the options
      const monthFormatter = new Intl.DateTimeFormat('en-US', { month: 'long', timeZone: 'America/Chicago' });
      const timeFormatter = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', timeZone: 'America/Chicago', hour12: true });

      // Format the date and time
      const month = monthFormatter.format(date);
      const time = timeFormatter.format(date).replace(' AM', 'am').replace(' PM', 'pm');

      return `
        <div class="alert alert--icon alert--danger">
          <div class="alert__icon">
            <span class="fa-stack fa-1x">
              <span role="presentation" class="fas fa-circle fa-stack-2x"></span>
              <span role="presentation" class="fas fa-stack-1x fa-inverse fa-exclamation"></span>
            </span>
          </div>
          <div class="hawk-alert-message" role="region" aria-label="hawk alert message">
            <h2 class="headline headline--serif">
              <span class="hawk-alert-heading">
                <span class="hawk-alert-label">Hawk Alert</span>
              </span>
            </h2>
            <p><em><span class="hawk-alert-date">${month} ${date.getDate()}, ${date.getFullYear()} - ${time}</span></em><br />
              <span class="hawk-alert-body">${item.attributes.alert}</span>
              <a class="hawk-alert-link alert-link" href=${item.attributes.more_info_link}>Visit ${item.attributes.more_info_link} for more information.</a></p>
          </div>
        </div>
      `;
    }
}};
