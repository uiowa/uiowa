class ToggleNavBehavior {
  constructor() {
    this.drawerContainer = document.querySelector(".o-canvas__wrapper");
    this.toggleButtons = document.querySelectorAll("button.toggle-nav__bttn");
    this.canvasDrawer = document.querySelector('.o-canvas__drawer');
    this.iowaHeader = document.querySelector('[data-uids-header]');

    // Set positioning of canvasDrawer based on iowaBar height
    this.iowaBarHeight = this.iowaHeader.offsetHeight;
    this.canvasDrawer.style.top = `${this.iowaBarHeight}px`;

    // Bind event handlers to the class instance
    this.handleButtonClick = this.handleButtonClick.bind(this);
    this.handleClickOutside = this.handleClickOutside.bind(this);
    this.handleEscapeKey = this.handleEscapeKey.bind(this);
    this.updateCanvasDrawerPosition = this.updateCanvasDrawerPosition.bind(this);
    this.handleResize = this.handleResize.bind(this);
    this.setupEventListeners();
  }

  // Function to handle the toggleNav behavior
  handleButtonClick(e) {
    if (!e.target.classList.contains("toggle-nav__bttn")) return;
    // Add the active/open class
    e.target.classList.toggle("active");
    e.target.parentNode.classList.toggle("o-canvas--open");

    const isActive = e.target.classList.contains("active");
    e.target.setAttribute("aria-expanded", isActive ? "true" : "false");
    e.target.classList.toggle("inactive", !isActive);
    e.target.nextElementSibling.setAttribute("aria-hidden", !isActive);

    // go through each link
    this.toggleButtons.forEach((button) => {
      if (button !== e.target) {
        button.classList.remove("active", "inactive");
        button.setAttribute("aria-expanded", "false");
        button.parentNode.classList.remove("o-canvas--open");
        button.nextElementSibling.setAttribute("aria-hidden", "true");
      }
    });

    document.body.classList.toggle("o-canvas--complete", !isActive);
    document.body.classList.toggle("o-canvas--lock", isActive);
  }

  handleClickOutside(event) {
    if (!event.target.closest(".o-canvas__group")) {
      document.body.classList.remove("o-canvas--lock");
      document.body.classList.add("o-canvas--complete");
      this.drawerContainer.classList.remove("o-canvas--open");
      this.toggleButtons.forEach((button) => {
        button.classList.remove("active");
        button.setAttribute("aria-expanded", "false");
        button.nextElementSibling.setAttribute("aria-hidden", "true");
      });
    }
  }

  handleEscapeKey(event) {
    if (event.key === "Escape") {
      document.body.classList.remove("o-canvas--lock");
      document.body.classList.add("o-canvas--complete");
      this.drawerContainer.classList.remove("o-canvas--open");
      this.toggleButtons.forEach((button) => {
        button.classList.remove("active");
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
    document.addEventListener("click", this.handleButtonClick);
    document.addEventListener("click", this.handleClickOutside);
    window.addEventListener("keydown", this.handleEscapeKey);
    window.addEventListener('resize', this.handleResize);

    // Create a MutationObserver to detect changes in the header position
    const observer = new MutationObserver(this.updateCanvasDrawerPosition);

    // Observe changes in the header's attributes or subtree
    observer.observe(this.iowaHeader, { attributes: true, subtree: true });
  }
}

(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.toggleNav = {
    attach: function (context, settings) {
      // Instantiate the ToggleNavBehavior class to attach the behavior
      new ToggleNavBehavior();
    }
  };
})(jQuery, Drupal);
