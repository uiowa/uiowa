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
        if (video) {
          btn.onclick = function() {
            if (video.paused) {
              // Per request, create cookie that expires in 99 years.
              $.cookie('herovideo', 'paused', { expires: 36135, path: '/' });
            }
            else {
              // Remove a cookie.
              $.removeCookie('herovideo', { path: '/' });
            }
          }
        }
      });
    }
  };

})(jQuery, Drupal);
