// S1 gallery field (ACF): pick, order and remove images from the media library.
jQuery(function ($) {
  if (!window.wp || !wp.media) return;

  $('.s1-gallery-box').each(function () {
    const box = $(this);
    const list = box.find('.s1-gallery-list');
    const input = box.find('.s1-gallery-input');
    let frame = null;

    const sync = () => {
      const ids = list.children('li').map((i, el) => el.dataset.id).get();
      input.val(ids.join(',')).trigger('change');
      box.toggleClass('has-images', ids.length > 0);
    };

    list.sortable({ items: '> li', tolerance: 'pointer', update: sync });
    list.on('click', '.s1-gallery-remove', (event) => {
      $(event.currentTarget).closest('li').remove();
      sync();
    });

    box.find('.s1-gallery-add').on('click', (event) => {
      event.preventDefault();
      if (!frame) {
        frame = wp.media({
          title: 'Lägg till bilder',
          button: { text: 'Lägg till i bildspelet' },
          library: { type: 'image' },
          multiple: 'add'
        });
        frame.on('select', () => {
          frame.state().get('selection').each((attachment) => {
            const data = attachment.toJSON();
            if (list.children('li[data-id="' + Number(data.id) + '"]').length) return;
            const sizes = data.sizes || {};
            const src = (sizes.thumbnail || sizes.medium || data).url;
            const item = $('<li>').attr('data-id', Number(data.id));
            item.append($('<img>').attr({ src, alt: '' }));
            item.append($('<button type="button" class="s1-gallery-remove" aria-label="Ta bort bild">&times;</button>'));
            list.append(item);
          });
          sync();
        });
      }
      frame.open();
    });
  });
});
