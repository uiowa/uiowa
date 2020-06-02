(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.heroVideoExtend = {
    attach: function (context, settings) {
      $('#video-container', context).once('hero-video-attach').each(function () {

        const video = document.getElementById("video-container");
        const btn = document.getElementById("video-btn");

        // Check herovideo cookie to see if user paused video previously.
        if (readCookie('herovideo') == 'paused') {
          video.pause();
          btn.innerHTML = "<span class='element-invisible'>" + "Play" + "</span>";
          btn.classList.remove("video-btn__pause");
          btn.classList.add("video-btn__play");
        }

        // Create/erase herovideo cookie.
        if (document.getElementById("video-container")) {
          document.getElementById('video-btn').onclick = function() {
            if (video.paused) {
              createCookie('herovideo', 'paused', 30);
            }
            else {
              eraseCookie('herovideo');
            }
          }
        }
      });
    }
  };

})(jQuery, Drupal);
