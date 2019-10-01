(() => {
  class ResponsiveBackgroundImage {
    constructor(element) {
      this.element = element;
      this.img = element.querySelector('img');
      this.src = '';

      this.img.addEventListener('load', () => {
        this.update();
      });

      if (this.img.complete) {
        this.update();
      }
    }

    update() {
      const src = typeof this.img.currentSrc !== 'undefined' ? this.img.currentSrc : this.img.src;

      if (this.src !== src) {
        this.src = src;
        this.element.style.backgroundImage = `url("${this.src}")`;
      }
    }
  }

  const elements = document.querySelectorAll('[data-responsive-background-image]');
  const backgroundImages = [];

  for (let i = 0; i < elements.length; i++) {
    backgroundImages.push(new ResponsiveBackgroundImage(elements[i]));
  }
})();
