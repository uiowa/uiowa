document.addEventListener('DOMContentLoaded', doSomething, false);


let slideIndex = 0;
let title;
let sections;
let transitionTime = 6; // In seconds.
let signData = {};

function doSomething () {
  const queryString = window.location.search;
  const urlParams = new URLSearchParams(queryString);
  const isSignage = urlParams.get('signage');
  if (isSignage !== null) {
    initSections();
    document.querySelector('html').classList.add('signage-view');
    showSections();
  }
  else {
    // console.log(title, sections);
  }
}

function showSections() {
  let i;
  slideIndex++;
  for (i = 0; i < sections.length; i++) {
    if (slideIndex-1 !== i) {
      sections[i].classList.remove('show-slide');
    }
  }
  if (slideIndex > sections.length) {slideIndex = 1}
  sections[slideIndex-1].classList.add('show-slide');
  setTimeout(showSections, (transitionTime * 1000)); // Change image every 2 seconds
}

function initSections() {
  title = document.querySelector('.layout--title--with-background, .layout__container.layout--title');
  title ? title.classList.add('signage-title') : '';

  sections = document.querySelectorAll('.layout__container:not(.layout--title)');
  sections.forEach(function(slide, index) {
    const spacingContainer = slide.querySelector('.layout__spacing_container');
    const childrenCount = spacingContainer.childElementCount;
    console.log(spacingContainer);
    console.log(childrenCount);

    for (let i = 0; i < childrenCount; i++) {
      if (!(i in signData)) {
        signData[i] = [];
      }
      signData[i].push(spacingContainer.children[i]);
    }
    console.log(signData);
    console.log('______________');

    slide.classList.add('signage-slide');
    if (index === 0) {
      slide.classList.add('show-slide');
    }
    if (title && title !== null) {
      slide.classList.add('signage--has-title');
    }
  });

}
