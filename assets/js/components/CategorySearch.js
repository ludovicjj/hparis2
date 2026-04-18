import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.default.css';
import '../../styles/components/tom-select-dark.scss';

export function initCategorySearch(selector) {
    document.querySelectorAll(selector).forEach((el) => {
        if (el.tomselect) {
            return;
        }

        new TomSelect(el, {
            valueField: 'id',
            labelField: 'name',
            searchField: 'name',
            plugins: ['remove_button'],
            preload: 'focus',
            maxOptions: 15,
            create: false,
            load: function (query, callback) {
                const url = new URL(el.dataset.remote, window.location.origin);
                url.searchParams.set('name', query);

                fetch(url, { credentials: 'same-origin' })
                    .then((response) => response.ok ? response.json() : [])
                    .then((data) => callback(data))
                    .catch(() => callback([]));
            },
            render: {
                no_results: () => '<div class="no-results">Aucune catégorie trouvée</div>',
            },
        });
    });
}
