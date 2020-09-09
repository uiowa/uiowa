/**
 * @file
 * Sitenow global scripts. Attached to every page.
 */

(function ($, Drupal) {
    Drupal.behaviors.sitenow = {
      attach: function (context, setting) {
        $(document, context).once('sitenow').each(function () {
          let version = setting.sitenow.version;

          // This is the first line of the log.
          let sitenow_message_line_1 = 'This is a Sitenow ' + version +  ' Site.';

          // This is the second line of the log.
          let sitenow_message_line_2 = "For more information, please visit https://sitenow.uiowa.edu.";

          // This styles the first line of the log.
          let sitenow_message_style_1 = "\
            color: #444;\
            font-size: 1.5rem;\
            font-family: 'Helvetica Neue','Arial',sans-serif;\
            font-weight: 200;\
            background-color: rgba(255,255,255,1);\
            padding: 10px 10px 10px 10px;\
            position: relative;\
            z-index: 100;\
            border-radius: 3px;\
            margin-top: 20px;\
          ";

          // This styles the second line of the log.
          let sitenow_message_style_2 = "\
            color: #444;\
            font-size: 1rem;\
            font-family: 'Helvetica Neue','Arial',sans-serif;\
            font-weight: 400;\
            background-color: rgba(255,255,255,1);\
            padding: 10px 10px 10px 10px;\
            margin: -14px 0px 20px 0px;\
            position: relative;\
            z-index: 99;\
            border-radius: 3px;\
          ";

          // This prints the message.
          console.log("%c" + sitenow_message_line_1 + "%c\n%c " + sitenow_message_line_2, sitenow_message_style_1, 'padding:0px;', sitenow_message_style_2);
        });
      }
    };
  })(jQuery, Drupal);
