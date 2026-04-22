import Sortable from 'sortablejs';

function initSocialLinksSortable() {
    const container = document.getElementById('social-links-sortable');

    if (!container) {
        return;
    }

    const url = container.dataset.reorderUrl

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
                    alert('Erreur lors de la réorganisation des réseaux sociaux.');
                }
            } catch {
                alert('Erreur réseau, veuillez réessayer.');
            }
        },
    });
}

function initMediaPreview(prefix) {
    const form = document.getElementById(`${prefix}-form`);
    if (!form) {
        return;
    }

    const input = form.querySelector('input[type="file"]');
    const previewZone = document.getElementById(`${prefix}-preview-zone`);
    const previewImg = document.getElementById(`${prefix}-preview-img`);
    const placeholder = document.getElementById(`${prefix}-preview-placeholder`);
    const fileName = document.getElementById(`${prefix}-file-name`);

    if (!input || !previewZone) {
        return;
    }

    previewZone.addEventListener('click', () => input.click());

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;

        fileName.textContent = file.name;

        const reader = new FileReader();
        reader.onload = (e) => {
            previewImg.src = e.target.result;
            previewImg.classList.remove('hidden');
            placeholder.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    });
}

function initMediaDelete() {
    document.querySelectorAll('[data-media-delete]').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            const message = btn.dataset.confirm || 'Confirmer la suppression ?';
            if (!confirm(message)) {
                return;
            }

            const formData = new FormData();
            formData.append('_token', btn.dataset.token);

            try {
                const response = await fetch(btn.dataset.url, {
                    method: 'POST',
                    body: formData,
                });

                if (response.ok || response.redirected) {
                    window.location.reload();
                } else {
                    alert('Erreur lors de la suppression.');
                }
            } catch {
                alert('Erreur réseau, veuillez réessayer.');
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSocialLinksSortable();
    initMediaPreview('logo');
    initMediaPreview('hero');
    initMediaPreview('favicon');
    initMediaDelete();
});
