window.jQuery = jQuery;

(($, {
  matchmedia
}, window) => {
  $(document).ready(() => {
    const $nav = $("#nav");
    const $menuMega = $(".navbar--main");
    const $menu_link = $(".block-superfish .sf-style-none li a");
    const bp_medium_up = "(min-width: 992px)";
    let hoverTimer = false;
    const hoverDelay = 300;

    function megaDropDown() {
      $menuMega.hoverIntent(
        () => {
          clearTimeout(hoverTimer);
          $nav.removeClass("region-primary-menu");
          $menuMega.addClass("is-hover");
        },
        () => {
          hoverTimer = setTimeout(() => {
            $menuMega.removeClass("is-hover");
          }, hoverDelay);
        }
      );
      // Add focus events for accessibility
      $menu_link.on("focus", e => {
        clearTimeout(hoverTimer);
        $menuMega.addClass("is-hover");
      });
      $menu_link.on("focusout", e => {
        hoverTimer = setTimeout(() => {
          $menuMega.removeClass("is-hover");
        }, hoverDelay);
      });
    }

    const menuSwitch = ({
      matches
    }, legacy) => {
      // Desktop
      if (matches || legacy) {
        // Enable the megamenu dropdown
        megaDropDown();
      }

      // Mobile
      else {}
    };

    // Watch for changes to the browser size
    if (matchmedia) {
      // get MediaQueryList Interface
      const mql = window.matchMedia(bp_medium_up);

      mql.addListener(menuSwitch);
      // On Load
      menuSwitch(mql);
    }
    // Legacy without the ability to detect media queries. Render desktop
    else {
      menuSwitch(false, true);
    }

    // If this is using a Mega Menu, ensure that we inform the rest of the site.
    if ($menuMega.length) {
      $("body").addClass("has-mega");
    }
  });
})(jQuery, Modernizr, window); // end jquery enclosure
