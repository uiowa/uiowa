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
    let day = '';
    const month = monthFormatter.format(date);
    const time = timeFormatter
      .format(date)
      .replace('AM', 'a.m.')
      .replace('PM', 'p.m.');

    const is_emergency = window.location.host.startsWith('emergency');
    let title = 'Hawk Alert';
    let hawk_alert_body = '';
    let emergency_link = '';
    if (!is_emergency) {
      hawk_alert_body = `<span class="hawk-alert-body">${item.attributes.alert}</span>`;
      emergency_link = `<a class="hawk-alert-link alert-link" href="${item.attributes.more_info_link}">Visit ${item.attributes.more_info_link} for more information.</a></p>\n`
    }
    else {
      title = `${item.attributes.alert}`;
      const dayFormatter = new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        timeZone: 'America/Chicago',
      });
      day = dayFormatter.format(date) + ', ';
      if (item?.relationships?.field_hawk_alert_situation?.data !== undefined) {
        const update_data = item?.relationships?.field_hawk_alert_situation?.data;
        if (update_data.length > 0) {
          hawk_alert_body = `<div class="hawk-alert-body updates"></div>`;
        }
      }
    }
    return `
      <div class="hawk-alert-message" role="region" aria-label="hawk alert message">
        <h2 class="headline headline--serif">
          <span class="hawk-alert-heading">
            <span class="hawk-alert-label">${title}</span>
          </span>
        </h2>
        <p><em><span class="hawk-alert-date">${day}${month} ${date.getDate()}, ${date.getFullYear()} - ${time}</span></em><br /></p>
          ${hawk_alert_body}
          ${emergency_link}
      </div>
    `;
  }

  static hawkAlertStatusUpdateContent(item) {
    const rawDate = item.attributes.date;                  // ISO string from API
    const description = item.attributes.description?.processed ?? ''; // rendered HTML (incl. media)

    const date = new Date(rawDate);
    const dateFormatter = new Intl.DateTimeFormat('en-US', {
      year: 'numeric', month: '2-digit', day: '2-digit',
      timeZone: 'America/Chicago',
    });
    const timeFormatter = new Intl.DateTimeFormat('en-US', {
      hour: 'numeric', minute: '2-digit',
      timeZone: 'America/Chicago', hour12: true,
    });
    const display = `${dateFormatter.format(date)} - ${timeFormatter.format(date)}`;

    return `
    <div class="block-margin__top borderless block--word-break card" data-uids-no-link="">
      <div class="card__body">
        <div class="card__details">
          <div class="card__subtitle">
            <div class="field field--name-field-hawk-alert-situation-date field--type-datetime field--label-hidden field__item">
              <time datetime="${rawDate}" class="datetime">${display}</time>
            </div>
          </div>
        </div>
        <div class="clearfix text-formatted field field--name-field-hawk-alert-situation-descr field--type-text-long field--label-hidden field__item">
          ${description}
        </div>
      </div>
    </div>
  `;
  }

  static hawkAlertSituationUpdateSectionTitle() {
    return `<p><small><strong>Situation update(s):</strong></small></p>`;
  }

  static async getSituationUpdates(item) {
    if (item?.relationships?.field_hawk_alert_situation?.links?.related?.href !== undefined) {
      const updates_url = item.relationships.field_hawk_alert_situation.links.related.href;
      return await AlertsUtilities.fetchAlerts(updates_url);
    }
    else {
      return '';
    }
  }

  static fullHawkAlertMarkup(content) {
    return `
      <div class="alert alert--danger alert--icon">
        <div class="alert__icon">
          <span class="fa-stack fa-1x">
            <span role="presentation" class="fas fa-circle fa-stack-2x"></span>
            <span
              role="presentation"
              class="fas fa-stack-1x fa-inverse fa-exclamation"
            ></span>
          </span>
        </div>
        ${content}
      </div>`;
  }

  static createElementFromHTML(htmlString) {
    const div = document.createElement('div');
    div.innerHTML = htmlString.trim();

    return div.firstChild;
  }
}
