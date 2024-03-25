document.addEventListener("DOMContentLoaded", function() {

  const time = 5;

  // Find the container for the slides.
  const container = document.querySelector('.view-slideshow .view-content');

  // Get all the slides in the container.
  const slides = container.querySelectorAll('.banner');

  // Shuffle the slides.
  const slideIndexArray = Object.keys(slides);
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

  // Set the delay in Ms and the number of slides.
  // 1 second = 1000ms.
  const msTime = time * 1000 * 2;
  const numSlides = slides.length - 0.5;


  // Chain delaying actions to close the slideshow and reload the page.
  //    This allows us to ensure that our slideshow doesn't
  //    index incorrect slides, break on older browsers, etc.
  delayAction(

    // Delay...
    msTime * (numSlides),

    // And then prep the slideshow for closing.
    function() {
      console.log('Transition...');
      container.classList.add('prep-close');

      // Chain another delay.
      delayAction(

        // Delay...
        100,

        // Then add the class to close the shutters.
        function() {
          console.log('Close...');
          container.classList.add('close');

          // Chain another delay...
          delayAction(

            // Delay...
            3000,

            // Then reload the page.
            function() {
              console.log('Reload...');
              location.reload();
          });
      });
  });

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
 * @param {Array.<Node>} array
 */
function shuffleArray(array) {
  for (let i = array.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [array[i], array[j]] = [array[j], array[i]];
  }
}

/**
 * Delay an `action` for `time`
 * and then run the `action` asynchronously to other JavaScript.
 *
 * @param {number} time
 * @param {function} action
 */
function delayAction(time, action) {

  // Create a promise that will delay.
  const delay = (delayInMs) => {
    return new Promise(resolve => setTimeout(resolve, delayInMs));
  }

  // Make the promise, wait for it to complete, then run the `action`.
  const asyncDelayedAction = async () => {
    let delayRes = await delay(time);
    action();
  }

  // Finally, call the function that puts it all together.
  asyncDelayedAction();
}
