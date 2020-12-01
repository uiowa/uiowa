(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.bannerVideoExtend = {
    attach: function (context, settings) {
      $('.media--video', context).once('media-video-attach').each(function () {
        const video = this.querySelector("video");
        const btn = this.querySelector(".video-controls .video-btn");
        const bannerVideoCookie = JSON.parse($.cookie('bannervideo'));
        console.log(bannerVideoCookie);

        // Check bannervideo cookie to see if user paused video previously.
        if (bannerVideoCookie === 'paused') {
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
              console.log($.cookie('bannervideo'));
              let banner_video_dict =  {};
              banner_video_dict["paused"] = true;
              let cookie_banner_video = JSON.stringify(banner_video_dict);
              // Per request, create cookie that expires in 99 years.
              $.cookie('bannervideo', cookie_banner_video, { expires: 36135, path: '/' });
            }
            else {
              console.log('notpaused');
              // Remove a cookie.
              $.removeCookie('bannervideo', { path: '/' });
            }
          }
        }
      });
    }
  };

})(jQuery, Drupal);
