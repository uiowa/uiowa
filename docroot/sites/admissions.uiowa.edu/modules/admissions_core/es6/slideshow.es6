document.addEventListener("DOMContentLoaded", function() {

  // Find the container for the slides.
  const container = document.querySelector('.view-slideshow .view-content');

  // Get all the slides in the container.
  const slides = container.querySelectorAll('.banner');

  // For each slide...
  let slideIndexArray = Object.keys(slides);
  shuffleArray(slideIndexArray);

  slides.forEach(function(slide, index) {

    // Give it an index to be used for later maths.
    slide.style.setProperty(
      '--data-slide-index', slideIndexArray[index].toString()
    );

    // Set the animation delay after we have the proper index.
    slide.style.setProperty(
      '--banner-animation-delay',
      'calc(var(--time) * var(--data-slide-index) * 2)'
    );
  });

  // Set the slides variable to use in later maths.
  container.style.setProperty(
    '--slides',
    slides.length.toString()
  );

  // Create a style element with a dynamically generated keyframe animation.
  // This is necessary because it is very difficult to edit the keyframe
  // animations directly through JS.
  // Then, add it just before the slideshow container.
  const animationStyle = document.createElement('style');
  animationStyle.innerHTML = styleString(slides.length);
  container.parentElement.insertBefore(animationStyle, container);

  // Now that we have the animation dynamically generated,
  // We can set the banner animation value with the proper animation.
  container.style.setProperty(
    '--banner-animation',
    'showHideSlide \
    infinite calc(var(--time) * var(--slides) * 2) \
    steps(1)'
  );

  // Now we add the animate class to the container
  // so that we trigger all animations at the same time.
  container.classList.add('animate');

  /**
   * Returns a formatted string of a keyframe animation named `showHideSlide`.
   * This string is to be used as the inner html of a style variable.
   *
   * @param {number} slideCount
   * @returns {string}
   */
  function styleString(slideCount) {
    let styleString =
      '@keyframes showHideSlide {\
          0% {\
            opacity: 1;\
            transform: translate3d(0, 0, 10px);\
          }\
          \
          '+ (100 / slideCount).toString() + '% {\
          opacity: 0;\
          transform: translate3d(-9999px, 0, -10px);\
        }\
        \
        100% {\
          opacity: 0;\
          transform: translate3d(-9999px, 0, - 10px);\
        }\
      }';
    return styleString;
  }
});

/**
 * Randomize array in-place using Durstenfeld shuffle algorithm
 * https://stackoverflow.com/a/12646864
 *
 * @param array
 */
function shuffleArray(array) {
  for (let i = array.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [array[i], array[j]] = [array[j], array[i]];
  }
}
