(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.bannerVideoExtend = {
    attach: function (context, settings) {
      $('.media--video', context).once('media-video-attach').each(function () {
        const video = this.querySelector("video");
        const btn = this.querySelector(".video-controls .video-btn");
        // Set the bannerVideoCookie dictionary to an empty dictionary.
        let bannerVideoCookie = {};
        // If the banner video cookie already exists, load it in to the bannerVideoCookie variable.
        if ($.cookie('bannervideo')) {
          bannerVideoCookie = JSON.parse($.cookie('bannervideo'));
        }
        // Also get the block reference uuid for later usage.
        const video_uuid = video.getAttribute('data-parent-block-uuid');

        // Check bannervideo cookie to see if user paused video previously.
        if (bannerVideoCookie !== {} && bannerVideoCookie[video_uuid] === 'paused') {
          // If they did, pause the video.
          video.removeAttribute('autoplay');
          video.pause();
          btn.innerHTML = '<span class="element-invisible">' + 'Play' + '</span>';
          btn.classList.remove('video-btn__pause');
          btn.classList.add('video-btn__play');
        }

        // Create/erase herovideo cookie.
        // If the video element exists...
        if (video) {
          // On the video controls button click...
          btn.onclick = function() {
            // Update the video cookie to make sure we have the latest one.
            if ($.cookie('bannervideo')) {
              bannerVideoCookie = JSON.parse($.cookie('bannervideo'));
            }
            // And then if the video is paused...
            if (video.paused) {
              // Set an index equal to the uuid to 'paused'.
              bannerVideoCookie[video_uuid] = 'paused';
              // And then re-stringify the JSON so it can be saved as a cookie.
              let bannerVideoCookieString = JSON.stringify(bannerVideoCookie);
              // Per request, create cookie that expires in 99 years.
              $.cookie('bannervideo', bannerVideoCookieString, { expires: 36135, path: '/' });
            }
            else {
              // Remove a cookie based upon the uuid index.
              if ($.cookie('bannervideo')) {
                delete bannerVideoCookie[video_uuid];

                // If cookie array is empty, remove cookie dictionary.
                if (Object.keys(bannerVideoCookie).length === 0) {
                  $.removeCookie('bannervideo', { path: '/' });
                }
                // Else, re-save the dictionary.
                else {
                  let bannerVideoCookieString = JSON.stringify(bannerVideoCookie);
                  $.cookie('bannervideo', bannerVideoCookieString, { expires: 36135, path: '/' });
                }
              }
            }
          }
        }
      });
    }
  };

})(jQuery, Drupal);
