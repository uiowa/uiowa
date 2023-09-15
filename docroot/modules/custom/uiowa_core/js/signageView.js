document.addEventListener('DOMContentLoaded', doSomething, false);


let slideIndex = 0;
let title;
let slides;
let transitionTime = 10; // In seconds.

function doSomething () {
  const queryString = window.location.search;
  const urlParams = new URLSearchParams(queryString);
  const isSignage = urlParams.get('signage');
  initSlides();
  if (isSignage !== null) {
    document.querySelector('html').classList.add('signage-view');
    setTimeout(showSlides, (transitionTime * 1000));
  }
  else {
    // console.log(title, slides);
  }
}

function showSlides() {
  let i;
  slideIndex++;
  for (i = 0; i < slides.length; i++) {
    if (slideIndex-1 !== i) {
      slides[i].classList.remove('show-slide');
    }
  }
  if (slideIndex > slides.length) {slideIndex = 1}
  slides[slideIndex-1].classList.add('show-slide');
  setTimeout(showSlides, (transitionTime * 1000)); // Change image every 2 seconds
}

function initSlides() {
  title = document.querySelector('.layout--title--with-background, .layout__container.layout--title');
  title ? title.classList.add('signage-title') : '';

  slides = document.querySelectorAll('.layout__container:not(.layout--title)');
  slides.forEach(function(slide, index) {
    slide.classList.add('signage-slide');
    if (index === 0) {
      slide.classList.add('show-slide');
    }
    if (title && title !== null) {
      slide.classList.add('signage--has-title');
    }
  });

}
