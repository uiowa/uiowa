document.addEventListener("DOMContentLoaded", function() {
  // find all the slides
  const container = document.querySelector('.view-slideshow .view-content');
  const slides = container.querySelectorAll('.banner');
  slides.forEach(function(slide, index) {
    slide.style.setProperty(
      '--data-slide-index', index.toString()
    );
    slide.style.setProperty(
      '--banner-animation-delay',
      'calc(var(--time) * var(--data-slide-index) * 2)'
    );
  });

  const keyframeString =
    '@keyframes showHideSlide {\
        0% {\
          opacity: 1;\
          transform: translate3d(0, 0, 10px);\
        }\
    \
        ' + (100 / slides.length).toString() + ' {\
          opacity: 0;\
          transform: translate3d(-9999px, 0, -10px);\
        }\
    \
        100% {\
          opacity: 0;\
          transform: translate3d(-9999px, 0, -10px);\
        }\
      }'
  ;




  container.style.setProperty(
    '--slides',
    slides.length.toString()
  );

  container.style.setProperty(
    '--banner-animation',
    'showHideSlide \
    infinite calc(var(--time) * var(--slides) * 2) \
    steps(1)'
  );
});
