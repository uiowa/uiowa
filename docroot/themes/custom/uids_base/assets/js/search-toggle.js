class SearchOverlay {
  constructor(context) {
    this.context = context || document;
    this.wrapper = this.context.querySelector('.search-overlay');
    this.body = document.body;
    this.button = this.context.querySelector('button.search-button');
    this.searchButton = this.context.querySelector('.search-button');
    this.searchButtonLabel = document.getElementById('search-button-label');
    this.searchInput = document.getElementsByName('search-terms')[0];

    if (this.searchButton) {
      this.searchButton.addEventListener('click', this.searchToggle.bind(this));
      this.context.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          this.searchButton.setAttribute('aria-expanded', 'false');
          this.wrapper.setAttribute('aria-hidden', 'true');
        }
      });
    }

    // This listener will close the toggle if you click off of it.
    document.addEventListener('click', (event) => {
      if (!event.target.closest('.search-wrapper')) {
        if (this.body && this.searchButtonLabel) {
          this.body.classList.remove('search-is-open');
          this.searchButtonLabel.innerHTML = 'Search';
          this.wrapper.setAttribute('aria-hidden', 'true');
          this.button.setAttribute('aria-expanded', 'false');
        }
      }
    });
  }

  searchToggle() {
    if (this.searchButtonLabel && this.body) {
      const isSearchOpen = this.body.classList.contains('search-is-open');
      const isExpanded = isSearchOpen ? 'false' : 'true';

      // Set aria-expanded first.
      this.searchButton.setAttribute('aria-expanded', isExpanded);

      // Update other attributes and classes.
      this.wrapper.setAttribute('aria-hidden', isSearchOpen ? 'true' : 'false');
      this.body.classList.toggle('search-is-open');
      Drupal.announce(isSearchOpen ? 'Search form closed.' : 'Search form expanded and focus changed.');

      // If opening the search, wait longer then move focus.
      if (isExpanded === 'true') {
        if (!this.searchInput) {
          this.searchInput = document.getElementsByName('search-terms')[0];
        }

        setTimeout(() => {
            this.searchInput.focus()
          }
          ,750);
      }
    }
  }
}

(function ($, Drupal, once) {
  'use strict';
  const context = document.querySelector('.search-wrapper');
  once('search_overlay', new SearchOverlay(context));
})(jQuery, Drupal, once);
