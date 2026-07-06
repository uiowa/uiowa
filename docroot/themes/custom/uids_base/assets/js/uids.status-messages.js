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
      once("uidsStatusMessages", "body", context).forEach((body) => {
        body.addEventListener("click", (e) => {
          const dismissButton = e.target.closest("button[data-dismiss='alert']");
          if (dismissButton) {
            e.preventDefault();
            const alert = dismissButton.closest(".alert--dismissible");
            if (alert) {
              alert.style.transition = "opacity 0.3s ease";
              alert.style.opacity = "0";
              setTimeout(() => {
                alert.remove();
              }, 300);
            }
          }
        });
      });
    },
  };
})(Drupal, once);
