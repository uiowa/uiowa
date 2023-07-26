class ScrollHandler {
  constructor(context) {
    this.context = context || document;
    this.header = this.context.querySelector('[data-uids-header]');

    // Only do something if we have a match for our element.
    if (this.header) {
      this.scrollUp = 'scroll-up';
      this.scrollDown = 'scroll-down';
      this.menuDrawer = this.context.querySelector('.header-not-sticky .o-canvas__drawer');
      this.menuDrawerMobile = this.context.querySelector('.o-canvas__drawer');
      this.menuMq = window.matchMedia('(min-width: 855px)');
      this.height = this.header.clientHeight;
      this.lastScroll = 0;

      window.addEventListener('scroll', this.handleScroll.bind(this));
      window.addEventListener('orientationchange', this.handleOrientationChange.bind(this));
    }
  }

  handleScroll() {
    const currentScroll = window.pageYOffset;
    if (currentScroll <= this.height) {
      if (this.context.body) {
        this.context.body.classList.remove(this.scrollUp);
        this.context.body.classList.remove(this.scrollDown);
      }
      if (this.menuMq.matches) {
        if (this.menuDrawer) {
          this.menuDrawer.style.top = Math.max(this.height - currentScroll) + 'px';
        }
      } else {
        this.menuDrawerMobile.style.top = Math.max(this.height - currentScroll) + 'px';
      }
      return;
    }

    if (this.context.body) {
      if (currentScroll > this.lastScroll && !this.context.body.classList.contains('o-canvas--lock')) {
        // down
        if (currentScroll > this.height) {
          this.context.body.classList.remove(this.scrollUp);
          this.context.body.classList.add(this.scrollDown);
        }
      } else if (currentScroll < this.lastScroll && this.context.body.classList.contains(this.scrollDown)) {
        // up
        this.context.body.classList.remove(this.scrollDown);
        this.context.body.classList.add(this.scrollUp);
      }
    }

    this.lastScroll = currentScroll;
  }

  handleOrientationChange() {
    const afterOrientationChange = () => {
      this.menuDrawerMobile.style.top = Math.max(this.header.offsetHeight - window.scrollY) + 'px';
      window.removeEventListener('resize', afterOrientationChange);
    };

    window.addEventListener('resize', afterOrientationChange);
  }
}

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.scrollHandler = {
    attach: function (context, settings) {
      // Instantiate the ScrollHandler class to attach the behavior
      new ScrollHandler(context);
    }
  };
})(jQuery, Drupal);
