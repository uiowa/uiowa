/**
 * @file
 */
(function ($, Drupal) {
    Drupal.behaviors.SI_overrides = {
        attach: function (context, settings) {
            $('.node-page-layout-builder-form', context).once('SIoverrides').each(function () {
                // Double check that this is only a layout builder editing page.
                var is_editing_layout_page = jQuery('body').hasClass('user-logged-in') && window.location.pathname.match(/\/node\/\d\/layout/g) != null;
                if (is_editing_layout_page) {
                    // This waits for the widget to exist in the dom and then hides it.
                    // This is necessary because it loads after this JS is attached.
                    var checkExist = setInterval(function () {
                        if ($('.si-toggle-container').length) {
                            // Sadly, it seems like the SI JS does not allow a click to propogate on their drag bar, so an identical hide is happening here.
                            // This means that the use will only have to double click the collapsed drag dots to restore it to full size.
                            var SI_plugin = $('.si-toggle-container.si-pos-side.si-pos-east')
                            SI_plugin.css('width', '0px');
                            SI_plugin.find('.si-drag-box').css('cursor', 'pointer');
                            SI_plugin.find('.si-boxes-container').css('width', '0px');
                            // Once this is all done, clear the waiting interval so the code does not loop infinitely.
                            clearInterval(checkExist);
                        }
                    }, 100); 
                }  
            });
        }
    };
})(jQuery, Drupal);
