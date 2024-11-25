// Get all the vertical videos.
const ctrlVideo = document.querySelectorAll(".player");

// For each one...
for (i = 0; i < ctrlVideo.length; ++i) {

  // Add a listener for when the video plays.
  ctrlVideo[i].onplay = (event) => {

    // For each video that is not the one that was clicked on...
    for (j = 0; j < ctrlVideo.length; ++j) {
      if (ctrlVideo[j] != event.target) {

        // Set the video to not active/paused.
        setVideoState(ctrlVideo[j], false);
      }
    }

    // Then set the one clicked on to active/playing.
    setVideoState(event.target, true);
  };

  // Add a listener for when the video pauses.
  ctrlVideo[i].onpause = (event) => {

    // Set the video to not active/paused.
    setVideoState(event.target, false);
  };

  // Get the video button for this video.
  let vidbttn = ctrlVideo[i].parentElement.querySelector('.vidbttn');

  // And when it is clicked, play it's associated video.
  vidbttn.addEventListener('click', function (event) {
    let video = vidbttn.parentElement.querySelector('video');
    video.play();

  }, false);
}

// This function sets the state for a video element.
// It takes a <video> element and a boolean for active/not active.
function setVideoState(video, active) {

  // Get the necessary places where classes are set.
  const container = video.parentElement;
  const button = container.querySelector('.vidbttn');
  const highlight = container.parentElement.querySelector('.highlight__wrapper');

  // If the video is active set active classes.
  if (active) {
    container.classList.add("active");
    highlight.classList.add("active");
    button.classList.add("active");
  }

  // Else, make sure the video is paused and remove the active classes.
  else {
    video.pause();

    container.classList.remove("active");
    highlight.classList.remove("active");
    button.classList.remove("active");
  }
}
