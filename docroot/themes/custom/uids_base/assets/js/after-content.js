Drupal.behaviors.after_content = {
  attach: function (context, settings) {
    jQuery('.content__container.after_content', context).once('after_content').each(function () {
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

      // This is all the dismissal button stuff.
      setTimeout(() => {
        const layout_container = el.querySelector('.layout__container');
        const dismiss_button = document.createElement('div');
        dismiss_button.classList.add('after_content__dismiss');
        dismiss_button.innerHTML = '<i role="presentation" class="fas fa-times"></i>';
        layout_container.prepend(dismiss_button);

        document.addEventListener('click', function (event) {

          // If the clicked element doesn't have the right selector, bail
          if (!event.target.matches('.after_content__dismiss, .after_content__dismiss svg, .after_content__dismiss path')) return;

          el.classList.add('after_content__dismissed');

        }, false);
      }, 300);
    });
  }
}
