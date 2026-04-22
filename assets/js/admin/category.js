import Sortable from 'sortablejs';

function initCategoriesSortable() {
    const container = document.getElementById('categories-sortable');

    if (!container) {
        return;
    }

    const url = container.dataset.reorderUrl;

    new Sortable(container, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'opacity-50',
        onEnd: async () => {
            const ids = Array.from(container.querySelectorAll('[data-id]'))
                .map(el => parseInt(el.dataset.id));

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids }),
                });

                if (!response.ok) {
                    alert('Erreur lors de la réorganisation des catégories.');
                }
            } catch {
                alert('Erreur réseau, veuillez réessayer.');
            }
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initCategoriesSortable();
});
