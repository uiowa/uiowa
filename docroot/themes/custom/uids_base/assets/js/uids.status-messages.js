/**
 * @file
 * UIDS status messages behaviors.
 */

(function (Drupal, once) {
  "use strict";

  /**
   * Close any dismissible alerts on button click.
   */
  Drupal.behaviors.uidsStatusMessages = {
    attach(context, settings) {
      const alerts = once("uidsStatusMessages", ".alert--dismissible", context);

      alerts.forEach((alert) => {
        const dismissButton = alert.querySelector("button[data-dismiss='alert']");
        if (dismissButton) {
          dismissButton.addEventListener("click", (e) => {
            e.preventDefault();
            alert.style.transition = "opacity 0.3s ease";
            alert.style.opacity = "0";
            setTimeout(() => {
              alert.remove();
            }, 300);
          });
        }
      });
    },
  };
})(Drupal, once);
