(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.uidsScroll = {
    attach: function (context, settings) {
      const scrollElements = document.querySelectorAll(".js-scroll");
      //initialize throttleTimer as false
      let throttleTimer = false;

      const throttle = (callback, time) => {
        //don't run the function while throttle timer is true
        if (throttleTimer) return;
        //first set throttle timer to true so the function doesn't run
        throttleTimer = true;
        setTimeout(() => {
          //call the callback function in the setTimeout and set the throttle timer to false after the indicated time has passed
          callback();
          throttleTimer = false;
        }, time);
      }

      const elementInView = (el, dividend = 1) => {
        const elementTop = el.getBoundingClientRect().top;
        return (
          elementTop <=
          (window.innerHeight || document.documentElement.clientHeight) / dividend
        );
      };

      const elementOutofView = (el) => {
        const elementTop = el.getBoundingClientRect().top;
        return (
          elementTop > (window.innerHeight || document.documentElement.clientHeight)
        );
      };

      const displayScrollElement = (element) => {
        element.classList.add("scrolled");
      };

      const handleScrollAnimation = () => {
        scrollElements.forEach((el) => {
          if (elementInView(el, 2)) {
            displayScrollElement(el);
          }
        })
      }

      window.addEventListener('scroll', () => {
        throttle(handleScrollAnimation, 250);
      })
    }
  };

})(jQuery, Drupal);

