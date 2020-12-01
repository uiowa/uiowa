(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.bannerVideoExtend = {
    attach: function (context, settings) {
      $('.media--video', context).once('media-video-attach').each(function () {
        const video = this.querySelector("video");
        const btn = this.querySelector(".video-controls .video-btn");
        let bannerVideoCookie = {};
        if ($.cookie('bannervideo')) {
          bannerVideoCookie = JSON.parse($.cookie('bannervideo'));
        }
        const video_uuid = video.getAttribute('data-parent-block-uuid');

        // Check bannervideo cookie to see if user paused video previously.
        if (bannerVideoCookie !== {} && bannerVideoCookie[video_uuid] === 'paused') {
          video.removeAttribute('autoplay');
          video.pause();
          btn.innerHTML = '<span class="element-invisible">' + 'Play' + '</span>';
          btn.classList.remove('video-btn__pause');
          btn.classList.add('video-btn__play');
        }

        // Create/erase herovideo cookie.
        if (video) {
          btn.onclick = function() {
            if (video.paused) {
              bannerVideoCookie[video_uuid] = 'paused';
              let bannerVideoCookieString = JSON.stringify(bannerVideoCookie);
              console.log(bannerVideoCookie);
              // Per request, create cookie that expires in 99 years.
              $.cookie('bannervideo', bannerVideoCookieString, { expires: 36135, path: '/' });
            }
            else {
              // Remove a cookie.
              delete bannerVideoCookie[video_uuid];
              console.log(bannerVideoCookie);
              // If array is empty, remove cookie dict.
              if (Object.keys(bannerVideoCookie).length === 0) {
                $.removeCookie('bannervideo', { path: '/' });
              }
              // Else, re-save the dict.
              else {
                let bannerVideoCookieString = JSON.stringify(bannerVideoCookie);
                $.cookie('bannervideo', bannerVideoCookieString, { expires: 36135, path: '/' });
              }
            }
          }
        }
      });
    }
  };

})(jQuery, Drupal);
