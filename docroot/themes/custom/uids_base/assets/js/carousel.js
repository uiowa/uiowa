Drupal.behaviors.carousel = {
    attach: function (context, settings) {
        jQuery('div[data-carousel="carousel"]', context).once('carousel').each(function () {
            var fade = jQuery(this).hasClass('carousel-fade');
            jQuery('.field--name-field-carousel-item').slick({
                dots: true,
                fade: fade
            });
        });
    }
};