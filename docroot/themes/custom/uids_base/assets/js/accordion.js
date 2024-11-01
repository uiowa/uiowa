(function () {
  function Accordion(element) {
    let thisAccordion = this;

    // Get the accordions, and if the accordion group is multiselectable.
    this.accordions = element.getElementsByClassName("accordion__heading");
    this.multiSelectible = element.getAttribute('aria-multiselectable') === 'true' || false;

    // For each of the accordions...
    for (let i = 0; i < this.accordions.length; i++) {

      // Get the accordion item's components.
      let itemComponents = this.accordionItemComponents(this.accordions[i]);

      // Check if the accordion is currently expanded at moment of click.
      let expanded = this.isAccordionOpen(itemComponents.btn);

      // If it is, un-hide its corresponding panel.
      itemComponents.panel.hidden = !expanded;

      // When the accordion's button is clicked...
      itemComponents.btn.onclick = () => {

        // Toggle the corresponding accordion.
        this.toggleAccordion(this.accordions[i]);
      }
    }

    // Add a listener that listens for when the URL is changed.
    window.addEventListener('popstate', function (event) {

      // Activate an accordion based upon the hash parameters in the URL.
      thisAccordion.activateAccordionByHash();
    });

    // Activate any accordion that is defined in the hash parameter if there is one.
    this.activateAccordionByHash();
  }

  // Gets the item components for 'accordion'.
  // Returns an object that contains 'btn' and 'panel' elements.
  Accordion.prototype.accordionItemComponents = function (accordion) {
    let btn = accordion.querySelector('button');
    let panel = accordion.nextElementSibling;

    return {
      'btn': btn,
      'panel': panel
    }
  }

  // Define whether 'accordion' is open with 'isOpen'.
  Accordion.prototype.accordionOpen = function (accordion, isOpen) {
    // Get the accordion item's components.
    let itemComponents = this.accordionItemComponents(accordion);

    // Set the relevant attributes for 'accordion' based on 'isOpen'.
    itemComponents.btn.setAttribute('aria-expanded', isOpen);
    itemComponents.btn.setAttribute('aria-selected', isOpen);
    itemComponents.panel.hidden = !isOpen;
  }

  // Activate an 'accordion'.
  Accordion.prototype.activateAccordion = function (accordion) {

    // Checks if multiple accordions can be open at once. If not, closes other accordions.
    if (!this.multiSelectible) {
      this.collapseAllAccordions();
    }

    // Open the accordion.
    this.accordionOpen(accordion, true);
  }

  // Activate any accordion that is defined in the hash parameter if there is one.
  Accordion.prototype.activateAccordionByHash = function () {

    // Get the hash parameter.
    let hash = window.location.hash.substr(1);

    // If the hash parameter is not empty...
    if (hash !== '') {

      // Get the accordion to focus.
      let accordionToFocus = document.getElementById(hash);

      // If the defined hash parameter finds an element...
      if (accordionToFocus !== null) {

        // Get the accordion wrapper of the hash parameter and this accordion wrapper to compare later.
        let accordionToFocusAccordionWrapper = accordionToFocus.parentElement
        let accordionWrapper = this.accordions[0].parentElement;

        // If the accordion wrapper defined by the hash and this accordion wrapper are the same...
        if (accordionToFocusAccordionWrapper === accordionWrapper) {

          // Activate the accordion defined in the hash parameters.
          this.activateAccordion(accordionToFocus);
        }
      }
    }
  }

  // Collapse all accordions in this accordion group.
  Accordion.prototype.collapseAllAccordions = function () {

    // For each accordion...
    for (let i = 0; i < this.accordions.length; i++) {

      // Close it.
      this.accordionOpen(this.accordions[i], false);
    }
  }

  // Check if an accordion is open by inspecting the aria attribute of the 'btn' controlling it.
  // Returns a boolean.
  Accordion.prototype.isAccordionOpen = function (btn) {
    return btn.getAttribute('aria-expanded') === 'true' || false;
  }

  // Toggle a specific 'accordion' open or closed.
  Accordion.prototype.toggleAccordion = function (accordion) {
    // Get the accordion's button element.
    let btn = accordion.querySelector('button');

    // Check if the accordion is currently expanded at moment of click.
    let expanded = this.isAccordionOpen(btn);

    // Checks if multiple accordions can be open at once. If not, closes other accordions.
    if (!this.multiSelectible && !expanded) {
      this.collapseAllAccordions();
    }

    // Toggle the accordion.
    this.accordionOpen(accordion, !expanded)

    // If the accordion is not open (but will be)...
    if (!expanded) {

      // Define historyString here to be used later.
      let historyString = '#' + btn.parentElement.id;

      // Change window location to add URL params
      if (window.history && history.pushState && historyString !== '#') {
        // NOTE: doesn't take into account existing params
        history.replaceState("", "", historyString);
      }
    }

    // Else if the accordion is closed...
    else {

      // Empty the history string.
      history.replaceState("", "", " ");
    }
  }

  // Instantiate accordions on the page.
  const accordions = document.getElementsByClassName("accordion");

  for (let i = 0; i < accordions.length; i++) {
    let accordion = new Accordion(accordions[i]);
  }
}());

