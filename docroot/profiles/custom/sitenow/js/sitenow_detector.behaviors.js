/**
 * @file
 * Sitenow Detector.
 */

(function ($, Drupal) {
    Drupal.behaviors.sitenowDetector = {
      attach: function (context) {
        $(document, context).once('sitenowDetector').each(function () {

          // I've broken this up so it is easy to change.
          // This is the first line of the log.
          var sitenow_message_line_1 = "This is a Sitenow v2 Site";

          // This is the second line of the log.
          var sitenow_message_line_2 = "For more information, please visit https://sitenow.uiowa.edu";

          // This styles the first line of the log.
          var sitenow_message_style_1 = "\
            color: #444;\
            font-size: 1.5rem;\
            font-family: 'Helvetica Neue','Arial',sans-serif;\
            font-weight: 200;\
          ";

          // This styles the second line of the log.
          var sitenow_message_style_2 = "\
            color: #444;\
            font-size: 1rem;\
            font-family: 'Helvetica Neue','Arial',sans-serif;\
            font-weight: 400;\
          ";

          // This prints the message.
          console.log("%c" + sitenow_message_line_1 + "\n%c " + sitenow_message_line_2 + " ", sitenow_message_style_1, sitenow_message_style_2);
        });
      }
    };
  })(jQuery, Drupal);
