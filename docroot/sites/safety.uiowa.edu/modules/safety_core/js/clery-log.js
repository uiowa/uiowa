(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.logAnnounce = {
    attach(context, settings) {
      const announceLogResults = (logData, logType) => {
        if (!logData.isSearch) {
          return;
        }

        let message;

        if (logData.error) {
          message = Drupal.t(
            'Error loading @type log data. Please try again later.',
            { '@type': logType }
          );
          Drupal.announce(message, 'assertive');
          return;
        }

        const count = logData[`${logType}Count`];
        const { startDate, endDate } = logData;

        message = count > 0
          ? Drupal.formatPlural(
            count,
            'Found 1 @type log entry from @start to @end.',
            'Found @count @type log entries from @start to @end.',
            {
              '@type': logType,
              '@start': startDate,
              '@end': endDate,
              '@count': count,
            }
          )
          : Drupal.t(
            'No @type log entries found from @start to @end.',
            {
              '@type': logType,
              '@start': startDate,
              '@end': endDate,
            }
          );

        Drupal.announce(message, 'polite');
      };

      const ensureLiveRegionReady = (callback) => {
        const liveRegion = document.querySelector('#drupal-live-announce');

        if (liveRegion) {
          liveRegion.textContent = '';
          liveRegion.setAttribute('aria-busy', 'false');
          setTimeout(callback, 200);
        } else {
          setTimeout(callback, 500);
        }
      };

      const makeAnnouncements = () => {
        if (settings.crimeLog) {
          announceLogResults(settings.crimeLog, 'crime');
        }

        if (settings.fireLog) {
          announceLogResults(settings.fireLog, 'fire');
        }
      };

      if (document.readyState === 'complete') {
        ensureLiveRegionReady(() => {
          requestAnimationFrame(makeAnnouncements);
        });
      } else {
        window.addEventListener('load', () => {
          ensureLiveRegionReady(() => {
            requestAnimationFrame(makeAnnouncements);
          });
        });
      }
    },
  };
})(Drupal, drupalSettings);
