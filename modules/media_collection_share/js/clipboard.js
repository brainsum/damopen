/**
 * @file
 * clipboard.js
 *
 * Provides click to copy textfield valie into clipboard.
 */

(function (Drupal, once) {
  Drupal.behaviors.media_share_clipboard = {
    attach: function (context) {
      once('media_share_clipboard', '.copy-clipboard-wrapper', context).forEach(function(wrapper) {
        const field = wrapper.querySelector('input');
        if (field) {
          field.addEventListener('click', function (event) {
            event.preventDefault();
            event.target.select();
            try {
              navigator.clipboard.writeText(event.target.value).then(() => {
                console.log(wrapper);
                wrapper.classList.add('copied');
                setTimeout(function() {
                  wrapper.classList.remove('copied');
                  // todo: tweak this
                }, 2000);
              });
            } catch (err) {
              console.error('Failed to copy!', err);
            }
          });
        }
      });
    }
  };
})(Drupal, once);
