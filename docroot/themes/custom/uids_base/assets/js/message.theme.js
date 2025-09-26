/**
 * @file
 * Overriding core's message functions to match UIDS alert component structure.
 */

((Drupal) => {
  /**
   * Overrides message theme function.
   *
   * @param {object} message
   *   The message object.
   * @param {string} message.text
   *   The message text.
   * @param {object} options
   *   The message context.
   * @param {string} options.type
   *   The message type.
   * @param {string} options.id
   *   ID of the message, for reference.
   * @param {boolean} options.dismissible
   *   Whether the message should be dismissible (defaults to true).
   *
   * @return {HTMLElement}
   *   A DOM Node.
   */
  Drupal.theme.message = ({ text }, { type, id, dismissible = true }) => {
    const messageWrapper = document.createElement('div');

    // Set up alert classes based on message type.
    const alertClasses = [
      'alert',
      'alert--icon',
      'alert--vertically-centered'
    ];

    // Add dismissible class if dismissible is true.
    if (dismissible) {
      alertClasses.push('alert--dismissible');
    }

    // Map Drupal message types to UIDS alert types.
    if (type === 'status') {
      alertClasses.push('alert--success');
    } else if (type === 'warning') {
      alertClasses.push('alert--warning');
    } else if (type === 'info') {
      alertClasses.push('alert--info');
    } else if (type === 'error') {
      alertClasses.push('alert--danger');
    }

    messageWrapper.setAttribute('class', alertClasses.join(' '));
    messageWrapper.setAttribute(
      'role',
      type === 'error' || type === 'warning' ? 'alert' : 'alert',
    );
    messageWrapper.setAttribute('aria-label', `${type} message`);
    messageWrapper.setAttribute('data-drupal-message-id', id);
    messageWrapper.setAttribute('data-drupal-message-type', type);

    // Determine the icon based on message type.
    let icon = '';
    if (type === 'status') {
      icon = 'fa-check';
    } else if (type === 'warning') {
      icon = 'fa-exclamation-triangle';
    } else if (type === 'info') {
      icon = 'fa-info';
    } else if (type === 'error') {
      icon = 'fa-exclamation';
    }

    // Build the HTML structure to match the alert.html.twig template.
    const closeButton = dismissible
      ? `
      <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span role="presentation" class="fas fa-times"></span>
      </button>`
      : "";

    messageWrapper.innerHTML = `
      <div class="alert__icon">
        <span class="fa-stack fa-1x">
          <span role="presentation" class="fas fa-circle fa-stack-2x"></span>
          <span role="presentation" class="fas fa-stack-1x fa-inverse ${icon}"></span>
        </span>
      </div>
      <div>${text}</div>${closeButton}
    `;

    return messageWrapper;
  };
})(Drupal);
