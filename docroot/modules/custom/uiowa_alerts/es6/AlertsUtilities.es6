class AlertsUtilities {
  constructor(el, settings) {
    this.el = el;
    this.settings = settings;
  }

  // Send a GET request to the alerts endpoint and hand off to handleResponse.
  async updateAlerts(source = this.settings.uiowaAlerts.source) {
    try {
      const response = await AlertsUtilities.fetchAlerts(source);
      this.handleResponse(response);
    } catch (error) {
      console.warn('uiowa_alerts: failed to fetch alerts', error);
    }
  }

  static async fetchAlerts(source) {
    const response = await fetch(source);
    if (!response.ok) {
      throw new Error('Unable to load active alerts. Please try again later.');
    }
    return response.json();
  }

  handleResponse(response) {
    const messagesWrapper = this.el.querySelector('.hawk-alerts-wrapper');
    const messages = new Drupal.Message(messagesWrapper);
    const existingAlerts = AlertsUtilities.getExistingAlerts(messagesWrapper);
    const newAlerts = [];

    response.data.forEach((item) => {
      const id = `hawk-alert-${item.attributes.date}`;
      newAlerts.push(id);

      const existingMessage = messages.select(id);
      if (existingMessage) {
        existingMessage.setAttribute('aria-label', 'Alert');
        return;
      }

      messages.add(AlertsUtilities.hawkAlertContent(item), {
        id,
        type: 'error',
        dismissible: false,
        announce: '',
      });

      const addedMessage = messages.select(id);
      if (addedMessage) {
        addedMessage.setAttribute('aria-label', 'Alert');
      }
    });

    // Remove alerts no longer in the response.
    existingAlerts
      .filter((existing) => !newAlerts.includes(existing))
      .forEach((closed) => messages.remove(closed));
  }

  static getExistingAlerts(messagesWrapper) {
    return Array.from(
      messagesWrapper.querySelectorAll('[data-drupal-message-id]'),
    ).map((node) => node.getAttribute('data-drupal-message-id'));
  }

  static hawkAlertContent(item) {
    const date = new Date(item.attributes.date);
    const monthFormatter = new Intl.DateTimeFormat('en-US', {
      month: 'long',
      timeZone: 'America/Chicago',
    });
    const timeFormatter = new Intl.DateTimeFormat('en-US', {
      hour: 'numeric',
      minute: '2-digit',
      timeZone: 'America/Chicago',
      hour12: true,
    });
    const month = monthFormatter.format(date);
    const time = timeFormatter
      .format(date)
      .replace('AM', 'a.m.')
      .replace('PM', 'p.m.');

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
}
