Drupal.behaviors.after_content = {
  attach: function (context, settings) {
    jQuery('.content__container.after_content', context).once('after_content').each(function () {
      // Code here.
      const el = document.querySelector('.content__container.after_content')
      let first = true;
      const pinned_class = 'after_content--is_pinned';
      const observer = new IntersectionObserver(
        ([e]) => {
          if (first && e.intersectionRatio < 1) {
            e.target.classList.add(pinned_class);
            first = false;
          }
          e.target.classList.toggle(pinned_class, e.intersectionRatio < 1)
        },
        { threshold: [1] }
      );

      observer.observe(el);
    });
  }
}
