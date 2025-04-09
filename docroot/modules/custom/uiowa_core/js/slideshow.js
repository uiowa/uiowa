(function (Drupal, once) {

  Drupal.behaviors.slideshow = {
    attach(context) {
      const elements = once('slideshow', '.block-inline-blockslideshow', context);
      // `elements` is always an Array.
      elements.forEach(function(slide){
        const slides = initSections(context);
        // TODO: figure out a way to stop multiple timeout loops if an editor moves the block around.
        showSlides(slides);
      });
    }
  };

  function initSections(context) {
    // title = document.querySelector('.layout--title--with-background, .layout__container.layout--title');
    // title ? title.classList.add('signage-title') : '';

    const slides = [];
    const slide_els = context.querySelectorAll('.node--type-slide');
    slide_els.forEach(function(slide, index) {
      // console.log('hit!');
      slides.push(slide);
      // const spacingContainer = slide.querySelector('.layout__spacing_container');
      // const childrenCount = spacingContainer.childElementCount;
      // console.log(spacingContainer);
      // console.log(childrenCount);
      //
      // for (let i = 0; i < childrenCount; i++) {
      //   if (!(i in signData)) {
      //     signData[i] = [];
      //   }
      //   signData[i].push(spacingContainer.children[i]);
      // }
      // console.log(signData);
      // console.log('______________');
      //
      // slide.classList.add('signage-slide');
      if (index === 0) {
        slide.classList.add('show-slide');
        slide.classList.add('first-slide');
      }
      // if (title && title !== null) {
      //   slide.classList.add('signage--has-title');
      // }
    });

    return slides;
  }

  function showSlides(slides, lastSlideIndex = 0) {
    // console.log('firing show slides!');
    const transitionTime = 6;

    slideIndex = lastSlideIndex + 1;
    for (let i = 0; i < slides.length; i++) {
      if (slideIndex-1 !== i) {
        slides[i].classList.remove('show-slide');
      }
    }
    if (slideIndex > slides.length) {slideIndex = 1}
    slides[slideIndex-1].classList.add('show-slide');
    setTimeout(function() {
      showSlides(slides, slideIndex);
    }, (transitionTime * 1000)); // Change image every transitionTime seconds.
  }
}(Drupal, once));


