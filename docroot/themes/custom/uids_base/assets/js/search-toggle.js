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

        this.focusTimer = setTimeout(() => this.focusSearchInput(), 750);

        // If the user starts typing before the delay ends, focus right away
        // so the keystroke lands in the input instead of being lost. Space is
        // skipped so it can still toggle the button closed.
        this.earlyFocusHandler = (event) => {
          if (event.key.length === 1 && event.key !== ' ' && !event.ctrlKey && !event.metaKey && !event.altKey) {
            this.focusSearchInput();
          }
        };
        document.addEventListener('keydown', this.earlyFocusHandler, true);
      }
      else {
        this.cancelPendingFocus();
      }
    }
  }

  focusSearchInput() {
    this.cancelPendingFocus();
    this.searchInput.focus();
  }

  cancelPendingFocus() {
    clearTimeout(this.focusTimer);
    document.removeEventListener('keydown', this.earlyFocusHandler, true);
  }
}

(function ($, Drupal, once) {
  'use strict';
  const context = document.querySelector('.search-wrapper');
  once('search_overlay', new SearchOverlay(context));
})(jQuery, Drupal, once);
