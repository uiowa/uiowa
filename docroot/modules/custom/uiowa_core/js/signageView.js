document.addEventListener('DOMContentLoaded', doSomething, false);

function doSomething () {
  const queryString = window.location.search;
  const urlParams = new URLSearchParams(queryString);
  const isSignage = urlParams.get('signage');
  if (isSignage !== null) {
    document.querySelector('html').classList.add('signage-view');
    showSlides(slideIndex);
  }
}

let slideIndex = 0;
function showSlides() {
  let i;
  let slides = document.getElementsByClassName("layout__container");
  for (i = 0; i < slides.length; i++) {
    slides[i].style.display = "none";
  }
  slideIndex++;
  if (slideIndex > slides.length) {slideIndex = 1}
  slides[slideIndex-1].style.display = "block";
  setTimeout(showSlides, 2000); // Change image every 2 seconds
}
