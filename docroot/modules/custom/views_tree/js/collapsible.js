/**
 * @file
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.views_tree = {
    attach: function (context, settings) {

      var views_tree_settings = settings.views_tree_settings;
      for (var views_tree_settings_id in views_tree_settings) {
        var views_tree_name = views_tree_settings[views_tree_settings_id][0];

        $.each($(".view-id-" + views_tree_name + " .view-content li"), function () {
          var count = $(this).find("li").length;
          if (count > 0) {
            $(this).addClass('views_tree_parent');
            $(this).children('ul').addClass("item-list");
            if (views_tree_settings[views_tree_settings_id][1] != "collapsed") {
              $(this).addClass('views_tree_expanded');
              // @todo Use Drupal.theme()
              $(this).prepend('<div class="views_tree_link views_tree_link_expanded"><a href="#">' + Drupal.t('Operation') + '</a></div>');
            }
            else {
              $(this).addClass('views_tree_collapsed');
              $(this).prepend('<div class="views_tree_link views_tree_link_collapsed"><a href="#">' + Drupal.t('Operation') + '</a></div>');
              $(this).children(".item-list").hide();
            }
          }
        });

      }
      $('.views_tree_link a', context).on('click', function (e) {
        e.preventDefault();

        if ($(this).parent().hasClass('views_tree_link_expanded')) {
          $(this).parent().parent().children(".item-list").slideUp();
          $(this).parent().addClass('views_tree_link_collapsed');
          $(this).parent().removeClass('views_tree_link_expanded');
        }
        else {
          $(this).parent().parent().children(".item-list").slideDown();
          $(this).parent().addClass('views_tree_link_expanded');
          $(this).parent().removeClass('views_tree_link_collapsed');
        }

      });
    }
  };

})(jQuery, Drupal);
