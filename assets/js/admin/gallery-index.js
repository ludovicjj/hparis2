import Sortable from 'sortablejs';

function initGalleriesSortable() {
    const container = document.getElementById('galleries-sortable');
    if (!container) {
        return;
    }
    const url = container.dataset.reorderUrl;
    const categoryId = parseInt(container.dataset.categoryId, 10);

    new Sortable(container, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'opacity-50',
        onUpdate: async () => {
            const ids = Array.from(container.querySelectorAll('[data-id]'))
                .map(el => parseInt(el.dataset.id, 10));

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ categoryId, ids }),
                });
                if (!response.ok) {
                    alert('Erreur lors de la réorganisation des galeries.');
                }
            } catch {
                alert('Erreur réseau, veuillez réessayer.');
            }
        },
    });

    // The cards are wrapped in <a>, so a plain click on the handle would
    // navigate to the edit page. Neutralize it (drag still works).
    container.querySelectorAll('.drag-handle').forEach(handle => {
        handle.addEventListener('click', (e) => e.preventDefault());
    });
}

initGalleriesSortable();
