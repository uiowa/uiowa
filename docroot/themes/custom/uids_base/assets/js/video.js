(function() {

  // Check if the user prefers reduced motion.
  const motionQuery = matchMedia('(prefers-reduced-motion)');

  function Video(element, index) {
    if (element) {
      this.element = element;
      this.video = this.element.querySelector('video');
      if (this.video) {
        // Give each video an id so that we can index them individually later.
        // As well, no two elements should have the same ID, so assigning them like this ensures that is the case.
        this.video.id = this.video.id + '-' + index;

        // Give each video button an id so that we can index them individually later.
        // As well, no two elements should have the same ID, so assigning them like this ensures that is the case.
        this.video_btn = this.element.querySelector('.video-controls .video-btn');
        this.video_btn.id = this.video_btn.id + '-' + index;

        // Do a reduced motion check, and attach a listener to do on every time it changes.
        Video.reducedMotionCheck(this.video, this.video_btn);
        motionQuery.addListener(function() { Video.reducedMotionCheck(this.video, this.video_btn) });

        // Add an event listener to the button of this banner video to toggle pause/play on the video.
        this.video_btn.addEventListener('click', () => {
          Video.pausePlay(this.video, this.video_btn);
        });
      }
    }
  }

  Video.reducedMotionCheck = function(video, btn) {
    if (motionQuery.matches) {
      // Pause the video.
      video.pause();
      // When the video is paused, offer the user the option to play.
      this.setButtonDataPlay(btn);
    }
  }

  // This function toggles pause and play on a specific banner video.
  Video.pausePlay = function(video, btn) {
    if (video.paused) {
      // Play the video.
      video.play();
      // When the video is playing, offer the user the option to pause.
      Video.setButtonDataPaused(btn);
    } else {
      // Pause the video.
      video.pause();
      // When the video is paused, offer the user the option to play.
      Video.setButtonDataPlay(btn);
    }
  }

  // This function sets the button to show a 'Pause' Icon.
  Video.setButtonDataPaused = function(btn) {
    btn.innerHTML = "<span class='element-invisible'>" + "Pause" + "</span>";
    btn.classList.remove("video-btn__play");
    btn.classList.add("video-btn__pause");
    btn.setAttribute("aria-label", "Pause");
  }

  // This function sets the button to show a 'Play' Icon.
  Video.setButtonDataPlay = function(btn) {
    btn.innerHTML = "<span class='element-invisible'>" + "Play" + "</span>";
    btn.classList.remove("video-btn__pause");
    btn.classList.add("video-btn__play");
    btn.setAttribute("aria-label", "Play");
  }

  window.UidsVideo = Video;

  // Instantiate videos on the page.
  const videos = document.getElementsByClassName('media--video');

  for (let i = 0; i < videos.length; i++) {
    new UidsVideo(videos[i], i);
  }

})();
