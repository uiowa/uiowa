class SearchOverlay {
  constructor(context) {
    this.context = context || document;
    this.wrapper = this.context.querySelector('.search-overlay');
    this.body = this.context.body;
    this.button = this.context.querySelector('button.search-button');
    this.searchButton = this.context.querySelector('.search-button');

    if (this.searchButton) {
      this.searchButton.addEventListener('click', this.searchToggle.bind(this));
      this.searchButton.addEventListener('keydown', (event) => {
        if (event.key == 'Escape') {
          this.searchButton.setAttribute('aria-expanded', 'false');
          this.wrapper.setAttribute('aria-hidden', 'true');
        }
      });
    }

    this.context.addEventListener('click', (event) => {
      if (!event.target.closest('.search-wrapper')) {
        if (this.context.getElementById('search-button-label')) {
          this.body.classList.remove('search-is-open');
          this.context.getElementById('search-button-label').innerHTML = 'Search';
          this.wrapper.setAttribute('aria-hidden', 'true');
          this.button.setAttribute('aria-expanded', 'false');
        }
      }
    });
  }

  searchToggle() {
    if (this.context.getElementById('search-button-label')) {
      if (this.body.classList.contains('search-is-open')) {
        this.searchButton.setAttribute('aria-expanded', 'false');
        this.wrapper.setAttribute('aria-hidden', 'true');
        this.body.classList.remove('search-is-open');
      } else {
        this.wrapper.setAttribute('aria-hidden', 'false');
        this.searchButton.setAttribute('aria-expanded', 'true');
        this.body.classList.add('search-is-open');
      }
    }
  }
}

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.searchOverlay = {
    attach: function (context, settings) {
      // Instantiate the SearchOverlay class to attach the behavior
      new SearchOverlay(context);
    }
  };
})(jQuery, Drupal);

