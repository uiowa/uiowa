(function ($, Drupal, once, cookies) {

  'use strict';

  const UIOWA_VIDEO_COOKIE = 'uiowa-video';

  Drupal.behaviors.uidsVideoCookie = {
    attach: function (context, settings) {
      $(once('media-video-attach', 'media--video', context)).each(function () {
        const video = this.querySelector('video');
        // We only need to execute the rest of this if the video element exists.
        if (video) {
          const btn = this.querySelector('.video-controls .video-btn');
          if (btn) {
            // Set videoCookieCollection to be an empty dictionary.
            let videoCookieCollection = {};
            // If the video cookie already exists, load it in to the videoCookieCollection variable.
            if (cookies.get(UIOWA_VIDEO_COOKIE)) {
              videoCookieCollection = JSON.parse(cookies.get(UIOWA_VIDEO_COOKIE));
            }
            // Also get the cookie id for later usage.
            const videoCookieId = video.getAttribute('data-video-cookie-id');

            // Check cookie id entry to see if the video was paused previously.
            if (videoCookieCollection !== {} && videoCookieCollection[videoCookieId] === 'paused') {
              // If they did, pause the video.
              video.removeAttribute('autoplay');
              video.pause();
              btn.innerHTML = '<span class="element-invisible">' + 'Play' + '</span>';
              btn.classList.remove('video-btn__pause');
              btn.classList.add('video-btn__play');
            }

            // On the video controls button click...
            btn.onclick = function() {
              // Update the video cookie to make sure we have the latest one.
              if (cookies.get(UIOWA_VIDEO_COOKIE)) {
                videoCookieCollection = JSON.parse(cookies.get(UIOWA_VIDEO_COOKIE));
              }
              // And then if the video is paused...
              if (video.paused) {
                // Set an index equal to the cookie id to 'paused'.
                videoCookieCollection[videoCookieId] = 'paused';
                saveVideoCookie(videoCookieCollection);
              }
              else {
                // Remove a cookie based upon the cookie id index.
                if (cookies.get(UIOWA_VIDEO_COOKIE)) {
                  delete videoCookieCollection[videoCookieId];

                  // If cookie array is empty, remove cookie dictionary.
                  if (Object.keys(videoCookieCollection).length === 0) {
                    cookies.remove(UIOWA_VIDEO_COOKIE, { path: '/' });
                  }
                  // Else, re-save the dictionary.
                  else {
                    saveVideoCookie(videoCookieCollection);
                  }
                }
              }
            }
          }
        }
      });
    }
  };

  function saveVideoCookie(cookie) {
    // String-ify the cookie JSON so it can be saved.
    const cookieString = JSON.stringify(cookie);
    // Cookie is set to expire in 99 years.
    cookies.set(UIOWA_VIDEO_COOKIE, cookieString, { expires: 36135, path: '/' });
  }

})(jQuery, Drupal, once, window.Cookies);
