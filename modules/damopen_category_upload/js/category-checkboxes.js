/**
 * @file
 * Lists the selected categories and a search field above the category
 * checkboxes, and scrolls the list within two thirds of the visible area.
 */
(function (Drupal, once) {
  Drupal.behaviors.damCategoryCheckboxes = {
    attach(context) {
      once('dam-category-checkboxes', '.dam-category-checkboxes', context).forEach((widget) => {
        const rows = Array.from(widget.querySelectorAll('input[type="checkbox"]')).map((input) => {
          const row = input.closest('.js-form-item, .form-item') || input.parentElement;
          const label = row.textContent.trim();
          return { row, input, label, text: label.toLowerCase() };
        });
        if (!rows.length) {
          return;
        }

        const list = document.createElement('div');
        list.className = 'dam-category-list';
        rows[0].row.before(list);
        rows.forEach(({ row }) => list.append(row));

        const empty = document.createElement('p');
        empty.className = 'dam-category-empty';
        empty.textContent = Drupal.t('No categories match your search.');
        empty.hidden = true;
        list.append(empty);

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'dam-category-search';
        search.placeholder = Drupal.t('Search a country or folder');
        search.setAttribute('aria-label', Drupal.t('Search categories'));
        search.autocomplete = 'off';
        list.before(search);

        // The selected categories, each with a button to remove it.
        const chips = document.createElement('ul');
        chips.className = 'dam-category-selected';
        chips.setAttribute('aria-label', Drupal.t('Selected categories'));
        search.before(chips);
        const renderChips = () => {
          chips.replaceChildren();
          rows.filter(({ input }) => input.checked).forEach(({ input, label }) => {
            const chip = document.createElement('li');
            // Locked categories cannot be removed, so they get no button.
            if (!input.disabled) {
              const remove = document.createElement('button');
              remove.type = 'button';
              remove.textContent = '×';
              remove.setAttribute('aria-label', Drupal.t('Remove @category', { '@category': label }));
              remove.addEventListener('click', () => {
                input.checked = false;
                input.dispatchEvent(new Event('change', { bubbles: true }));
              });
              chip.append(remove);
            }
            chip.append(label);
            chips.append(chip);
          });
          chips.hidden = !chips.children.length;
        };
        widget.addEventListener('change', renderChips);
        renderChips();

        search.addEventListener('input', () => {
          // Every typed word must appear somewhere in the category path.
          const words = search.value.toLowerCase().split(/\s+/).filter(Boolean);
          let shown = 0;
          rows.forEach(({ row, text }) => {
            const match = words.every((word) => text.includes(word));
            row.hidden = !match;
            shown += match ? 1 : 0;
          });
          empty.hidden = shown > 0;
          list.scrollTop = 0;
        });
        // Enter in the search field must not submit the asset form.
        search.addEventListener('keydown', (event) => {
          if (event.key === 'Enter') {
            event.preventDefault();
          }
        });
      });
    },
  };
})(Drupal, once);
