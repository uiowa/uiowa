function Slider(element) {
  this.slides = element.getElementsByClassName('slider__slide');

  for (let i = 0; i < this.slides.length; i++) {
    this.slides[i].onmouseover = this.slides[i].onfocus = () => {
      this.setActiveSlide(this.slides[i]);
    };
    this.slides[i].addEventListener('keyup', (event) => {
      this.handleKeyEvent(event);
    }, false);
  }
}

Slider.prototype.setAriaFalse = function() {
  for (let i = 0; i < this.slides.length; i++) {
    let el = this.slides.item(i)
    if (el) {
      el.setAttribute('aria-expanded', 'false');
    }
  }
}

Slider.prototype.setActiveSlide = function(element) {
  this.setAriaFalse();
  element.setAttribute('aria-expanded', 'true');
}

Slider.prototype.getActiveSlideIndex = function() {
  for (let i = 0; i < this.slides.length; i++) {
    if (this.slides[i].getAttribute('aria-expanded') === 'true') {
      return i;
    }
  }

  return false;
}

Slider.prototype.handleKeyEvent = function(event) {
  if (event.keyCode === 37) {
    // left - previous
    let index = this.getActiveSlideIndex();
    if (index !== false && index > 0) {
      // @todo Allow looping?
      this.setActiveSlide(this.slides[index-1]);
    }
  } else if (event.keyCode === 39) {
    // right - next
    let index = this.getActiveSlideIndex();
    if (index !== false && index < this.slides.length-1) {
      this.setActiveSlide(this.slides[index+1]);
    }
  }
}

// Instantiate sliders on the page.
const sliders = document.getElementsByClassName('slider');

for (let i = 0; i < sliders.length; i++) {
  let slider = new Slider(sliders[i]);
}
