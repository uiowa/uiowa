/**
 * @file
 * Adds aria-atomic to CKEditor 5 live regions.
 *
 * CKEditor 5's AriaLiveAnnouncer creates live regions without aria-atomic,
 * which causes Siteimprove to flag WCAG 3.3.1/4.1.3 violations.
 *
 * CKEditor appends its announcer to a .ck-body-wrapper element on
 * document.body, outside any Drupal-managed context, so a MutationObserver
 * on document.body is needed.
 *
 * @see https://github.com/uiowa/uiowa/issues/9506
 * @see https://github.com/ckeditor/ckeditor5/issues/19888
 */

(function (Drupal) {
  let observing = false;

  function patchLiveRegions() {
    document.querySelectorAll('.ck-body-wrapper div[aria-live]:not([aria-atomic])').forEach((el) => {
      el.setAttribute('aria-atomic', 'true');
    });
  }

  Drupal.behaviors.ckeditor5AriaAtomic = {
    attach() {
      // Patch any already-rendered regions.
      patchLiveRegions();

      if (observing) {
        return;
      }
      observing = true;

      // Watch document.body for CKEditor's .ck-body-wrapper insertion.
      const observer = new MutationObserver(patchLiveRegions);
      observer.observe(document.body, { childList: true, subtree: true });
    },
  };
})(Drupal);
