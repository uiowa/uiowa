(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.crimeLogAnnounce = {
    attach: function (context, settings) {
      // Only run once per page load.
      if (context !== document) {
        return;
      }

      // Check if settings.
      if (!settings.crimeLog) {
        return;
      }

      const crimeLogData = settings.crimeLog;

      // Only announce if this was a search.
      if (crimeLogData.isSearch) {
        let message;

        if (crimeLogData.error) {
          message = Drupal.t('Error loading crime log data. Please try again later.');
          Drupal.announce(message, 'assertive');
        } else {
          const count = crimeLogData.crimeCount;
          const startDate = crimeLogData.startDate;
          const endDate = crimeLogData.endDate;

          if (count > 0) {
            message = Drupal.formatPlural(
              count,
              'Found 1 crime log entry from @start to @end.',
              'Found @count crime log entries from @start to @end.',
              {
                '@start': startDate,
                '@end': endDate,
                '@count': count
              }
            );
          } else {
            message = Drupal.t('No crime log entries found from @start to @end.', {
              '@start': startDate,
              '@end': endDate
            });
          }

          Drupal.announce(message, 'polite');
        }
      }
    }
  };

})(Drupal, drupalSettings);
