document.addEventListener("DOMContentLoaded", function() {

  const time = 5;

  // Find the container for the slides.
  const container = document.querySelector('.view-slideshow .view-content');

  // Get all the slides in the container.
  let slides = container.querySelectorAll('.banner');

  // For each slide...
  let slideIndexArray = Object.keys(slides);
  // shuffleArray(slideIndexArray);

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

    if (index > 2) {
      slide.remove();
    }
  });

  slides = container.querySelectorAll('.banner');

  // Set the slides variable to use in later maths.
  container.style.setProperty(
    '--slides',
    slides.length.toString()
  );

  // Set the time variable to use in later maths.
  container.style.setProperty(
    '--time',
    time + 's'
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

  // const delayInMilliseconds = 1000; //1 second
  const msTime = time * 1000;
  const numSlides = slides.length + 0.25;

  delayAction(function() {
    console.log('Transition...');
    container.classList.add('prep-close');

    delayAction(function() {
      console.log('Close...');
      container.classList.add('close');

      delayAction(function() {
        console.log('Reload...');
        location.reload();
      }, 5000);
    }, 100);
  }, msTime * (numSlides - 0.6));

  /**
   * Returns a formatted string of a keyframe animation named `showHideSlide`.
   * This string is to be used as the inner html of a style variable.
   *
   * @param {number} slideCount
   * @returns {string}
   */
  function styleString(slideCount) {
    const timing = ((100 / slideCount).toFixed(2)).toString();

    let styleString =
      '@keyframes showHideSlide {\n\
        0% {\n\
          opacity: 1;\n\
          transform: translate3d(0, 0, 10px);\n\
        }\n\
        \n\
        '+ timing + '% {\n\
          opacity: 0;\n\
          transform: translate3d(-9999px, 0, -10px);\n\
        }\n\
        \n\
        99% {\n\
          opacity: 0;\n\
          transform: translate3d(-9999px, 0, - 10px);\n\
        }\n\
        \n\
        100% {\n\
          opacity: 1;\n\
          transform: translate3d(0, 0, 10px);\n\
        }\n\
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

function delayAction(action, time) {
  const delay = (delayInms) => {
    return new Promise(resolve => setTimeout(resolve, delayInms));
  }

  const asyncDelayedAction = async () => {
    let delayres = await delay(time);
    action();
  }

  asyncDelayedAction();
}
