// Development Golf / Tennis pages: event filter and event details dialog.
document.addEventListener('DOMContentLoaded', () => {
  const events = document.querySelector('.s1d-events');
  if (events) {
    const buttons = events.querySelectorAll('[data-s1d-filter]');
    const cards = events.querySelectorAll('[data-s1d-status]');
    const empty = events.querySelector('.s1d-empty[data-empty-upcoming]');
    const apply = (value) => {
      let shown = 0;
      buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.s1dFilter === value)));
      cards.forEach((card) => {
        const visible = value === 'all' || card.dataset.s1dStatus === value;
        card.hidden = !visible;
        if (visible) shown += 1;
      });
      if (empty) {
        empty.textContent = value === 'past' ? empty.dataset.emptyPast : empty.dataset.emptyUpcoming;
        empty.hidden = shown > 0;
      }
    };
    buttons.forEach((button) => button.addEventListener('click', () => apply(button.dataset.s1dFilter)));
    apply(events.dataset.s1dDefault || 'upcoming');
  }

  // Photo slideshow inside an event dialog. Started on first open, when the dialog has a size.
  const startGallery = (root) => {
    if (root.dataset.s1dReady || typeof window.Swiper !== 'function') return;
    root.dataset.s1dReady = '1';
    const sv = document.documentElement.lang.startsWith('sv');
    const mainEl = root.querySelector('.s1d-gallery__main');
    const thumbsEl = root.querySelector('.s1d-gallery__thumbs');
    const count = root.querySelector('.s1d-gallery__count');
    const total = mainEl.querySelectorAll('.swiper-slide').length;
    if (total < 2) return;
    const thumbs = thumbsEl ? new window.Swiper(thumbsEl, { slidesPerView: 'auto', spaceBetween: 8, watchSlidesProgress: true, slideToClickedSlide: true }) : null;
    new window.Swiper(mainEl, {
      spaceBetween: 12,
      keyboard: { enabled: true, onlyInViewport: true },
      navigation: { prevEl: root.querySelector('.s1d-gallery__prev'), nextEl: root.querySelector('.s1d-gallery__next') },
      thumbs: thumbs ? { swiper: thumbs } : undefined,
      a11y: {
        prevSlideMessage: sv ? 'Föregående bild' : 'Previous photo',
        nextSlideMessage: sv ? 'Nästa bild' : 'Next photo',
        slideLabelMessage: sv ? 'Bild {{index}} av {{slidesLength}}' : 'Photo {{index}} of {{slidesLength}}'
      },
      on: {
        slideChange(swiper) { if (count) count.textContent = (swiper.activeIndex + 1) + ' / ' + total; }
      }
    });
  };

  let opener = null;
  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-s1d-open]');
    if (!trigger) return;
    const dialog = document.getElementById(trigger.dataset.s1dOpen);
    if (!dialog || typeof dialog.showModal !== 'function') return;
    opener = trigger;
    dialog.showModal();
    document.documentElement.classList.add('s1d-lock');
    dialog.querySelectorAll('[data-s1d-gallery]').forEach(startGallery);
  });
  document.querySelectorAll('dialog.s1d-modal').forEach((dialog) => {
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => {
      document.documentElement.classList.remove('s1d-lock');
      if (opener) opener.focus();
      opener = null;
    });
  });
});
