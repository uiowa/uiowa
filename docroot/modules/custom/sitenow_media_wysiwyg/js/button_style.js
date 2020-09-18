/**
 * @file
 */

(function ($, Drupal) {
    Drupal.behaviors.buttonStyle = {
        attach: function (context, settings) {
            $('.editor-link-dialog', context).once('button-style-attach').each(function () {
                $(".button-style").click(function () {
                    var $class = $(".js-form-item-attributes-class input");
                    var $button = $(this).data("btn-type");
                    var $classes = $class.val().split(" ");
                    $class.val($class.val().trim());
                    if ($classes.includes('btn')) {
                        $class.val($class.val() + ' ' + $button);
                    }
                    else {
                        $class.val($class.val() + ' btn ' + $button);
                    }
                    $class.val($class.val().trim());
                    return false;
                });
            });
        }
    };
})(jQuery, Drupal);
