/**
 * @file
 * Sizes the category facet dropdown to two thirds of the visible area, capped
 * to the room actually available so it never extends past the screen.
 */
(function ($, Drupal, once) {
  const MARGIN = 12;

  Drupal.behaviors.damCategoryBrowser = {
    attach(context) {
      once('dam-category-browser', '.dam-category-browser select', context).forEach((select) => {
        const $select = $(select);
        const ns = `.damCategoryBrowser${Math.random().toString(36).slice(2)}`;

        // select2 keeps the visible panel inside a zero-height positioning
        // wrapper, so measure and tag the panel itself.
        const panel = (instance) => instance.$dropdown.find('.select2-dropdown').addBack('.select2-dropdown').first();

        const resize = () => {
          const instance = $select.data('select2');
          if (!instance || !instance.isOpen()) {
            return;
          }
          const $panel = panel(instance);
          const viewport = window.innerHeight;
          const field = instance.$container[0].getBoundingClientRect();
          const $list = $panel.find('.select2-results__options').first();
          // Anything in the dropdown besides the list (borders, search box).
          const chrome = $panel.outerHeight() - $list.outerHeight();
          const below = viewport - field.bottom;
          const above = field.top;
          const room = Math.max(below, above) - chrome - MARGIN;
          const height = Math.max(0, Math.min((viewport * 2) / 3, room));
          $list.css('max-height', `${Math.floor(height)}px`);
        };

        $select.on('select2:open', () => {
          panel($select.data('select2')).addClass('dam-category-dropdown');
          resize();
          // Let select2 re-pick above/below placement for the new height.
          $(window).trigger('resize.select2');
          $(window).on(`resize${ns} scroll${ns}`, resize);
        });
        $select.on('select2:close', () => $(window).off(ns));
      });
    },
  };
})(jQuery, Drupal, once);
