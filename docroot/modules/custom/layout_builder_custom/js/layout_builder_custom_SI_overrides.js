/**
 * @file
 */
(function ($, Drupal) {
    Drupal.behaviors.SI_overrides = {
        attach: function (context, settings) {
            $('.si-toggle-container', context).once('SIoverrides').each(function () {
                console.log('Just trying to see if it is attached.');
                // var is_editing_layout_page = jQuery('body').hasClass('user-logged-in') && window.location.pathname.match(/\/node\/\d\/layout/g) != null;
                // if (is_editing_layout_page) {
                //     jQuery('.si-drag-dots').trigger('click');
                //     var SI_plugin = jQuery('.si-toggle-container.si-pos-side.si-pos-east')
                //     SI_plugin.css('width', '0px');
                //     SI_plugin.find('.si-drag-box').css('cursor', 'pointer');
                //     SI_plugin.find('.si-boxes-container').css('width', '0px');
                // }
            });
        }
    };
})(jQuery, Drupal);
