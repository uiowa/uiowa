(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.heroVideoExtend = {
    attach: function (context, settings) {
      $('#video-container', context).once('hero-video-attach').each(function () {

        const video = document.getElementById("video-container");
        const btn = document.getElementById("video-btn");
        const heroVideoCookie = $.cookie('herovideo');

        // Check herovideo cookie to see if user paused video previously.
        if (heroVideoCookie === 'paused') {
          video.removeAttribute('autoplay');
          video.pause();
          btn.innerHTML = "<span class='element-invisible'>" + "Play" + "</span>";
          btn.classList.remove("video-btn__pause");
          btn.classList.add("video-btn__play");
        }

        // Create/erase herovideo cookie.
        if (document.getElementById("video-container")) {
          document.getElementById('video-btn').onclick = function() {
            if (video.paused) {
              // Create cookie.
              $.cookie('herovideo', 'paused', { expires: 30 });
            }
            else {
              // Remove a cookie.
              $.removeCookie('herovideo');
            }
          }
        }
      });
    }
  };

})(jQuery, Drupal);
