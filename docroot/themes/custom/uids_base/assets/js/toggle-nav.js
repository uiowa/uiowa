class ToggleNavBehavior {
  constructor(context) {
    this.context = context || document;
    this.iowaHeader = this.context.querySelector('[data-uids-header]');

    // Only do something if we have a match for our element.
    if (this.iowaHeader) {
      this.drawerContainer = this.context.querySelector('.o-canvas__wrapper');
      this.toggleButtons = this.context.querySelectorAll('button.toggle-nav__bttn');
      this.canvasDrawer = this.context.querySelector('.o-canvas__drawer');

      // Set positioning of canvasDrawer based on iowaBar height
      this.iowaBarHeight = this.iowaHeader.offsetHeight;
      this.canvasDrawer.style.top = `${this.iowaBarHeight}px`;

      // Bind event handlers to the class instance
      this.handleButtonClick = this.handleButtonClick.bind(this);
      this.handleClickOutside = this.handleClickOutside.bind(this);
      this.handleEscapeKey = this.handleEscapeKey.bind(this);
      this.updateCanvasDrawerPosition = Drupal.debounce(this.updateCanvasDrawerPosition.bind(this), 100); // Debounce with 100ms delay
      this.handleResize = this.handleResize.bind(this);
      this.setupEventListeners();
    }
  }

  // Function to handle the toggleNav behavior
  handleButtonClick(e) {
    if (!e.target.classList.contains('toggle-nav__bttn')) return;
    // Add the active/open class
    e.target.classList.toggle('active');
    e.target.parentNode.classList.toggle('o-canvas--open');

    const isActive = e.target.classList.contains('active');
    e.target.setAttribute('aria-expanded', isActive ? 'true' : 'false');
    e.target.classList.toggle('inactive', !isActive);
    e.target.nextElementSibling.setAttribute('aria-hidden', !isActive);

    // go through each link
    this.toggleButtons.forEach((button) => {
      if (button !== e.target) {
        button.classList.remove('active', 'inactive');
        button.setAttribute('aria-expanded', 'false');
        button.parentNode.classList.remove('o-canvas--open');
        button.nextElementSibling.setAttribute('aria-hidden', 'true');
      }
    });

    if (this.context.body) {
      this.context.body.classList.toggle('o-canvas--complete', !isActive);
      this.context.body.classList.toggle('o-canvas--lock', isActive);
    }
  }

  handleClickOutside(event) {
    if (!event.target.closest('.o-canvas__group')) {
      if (this.context.body) {
        this.context.body.classList.remove('o-canvas--lock');
        this.context.body.classList.add('o-canvas--complete');
        this.drawerContainer.classList.remove('o-canvas--open');
        this.toggleButtons.forEach((button) => {
          button.classList.remove('active');
          button.setAttribute('aria-expanded', 'false');
          button.nextElementSibling.setAttribute('aria-hidden', 'true');
        });
      }
    }
  }

  handleEscapeKey(event) {
    if (event.key === 'Escape') {
      this.context.body.classList.remove('o-canvas--lock');
      this.context.body.classList.add('o-canvas--complete');
      this.drawerContainer.classList.remove('o-canvas--open');
      this.toggleButtons.forEach((button) => {
        button.classList.remove('active');
      });
    }
  }

  // Function to calculate canvasDrawer position based on headerPosition
  updateCanvasDrawerPosition() {
    const headerPosition = this.iowaHeader.getBoundingClientRect().top;
    this.canvasDrawer.style.top = `${Math.max(this.iowaBarHeight + headerPosition, 0)}px`;
  }

  // Function to handle resizing and updating iowaBarHeight and canvasDrawer position
  handleResize() {
    this.iowaBarHeight = this.iowaHeader.offsetHeight;
    this.updateCanvasDrawerPosition();
  }

// Setup event listeners
  setupEventListeners() {
    this.context.addEventListener('click', this.handleButtonClick);
    this.context.addEventListener('click', this.handleClickOutside);
    window.addEventListener('keydown', this.handleEscapeKey);
    window.addEventListener('resize', this.handleResize);

    // Create a MutationObserver to detect changes in the header position
    const observer = new MutationObserver(this.updateCanvasDrawerPosition);

    // Observe changes in the header's attributes or subtree
    observer.observe(this.iowaHeader, { attributes: true, subtree: true });
  }

}

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.toggleNav = {
    attach: function (context, settings) {
      const [header] = once('uids_base_toggle_nav', context.querySelector('[data-uids-header]'));
      if (header) {
        // Instantiate the ToggleNavBehavior class to attach the behavior
        new ToggleNavBehavior(context);
      }
    }
  };
})(jQuery, Drupal, once);
