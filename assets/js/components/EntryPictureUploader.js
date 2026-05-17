import Sortable from 'sortablejs';

export class EntryPictureUploader {
    constructor(container) {
        this.container = container;
        this.uploadUrl = container.dataset.uploadUrl;
        this.reorderUrl = container.dataset.reorderUrl;
        this.deleteUrlTemplate = container.dataset.deleteUrlTemplate;
        this.maxPictures = parseInt(container.dataset.maxPictures, 10) || 5;

        this.dropZone = container.querySelector('[data-drop-zone]');
        this.fileInput = container.querySelector('[data-file-input]');
        this.grid = container.querySelector('[data-pictures-grid]');
        this.counter = container.querySelector('[data-pictures-counter]');

        this.bindEvents();
        this.initSortable();
        this.updateCounter();
    }

    bindEvents() {
        this.dropZone.addEventListener('click', () => this.fileInput.click());

        this.fileInput.addEventListener('change', (e) => {
            this.handleFiles(Array.from(e.target.files));
            this.fileInput.value = '';
        });

        ['dragenter', 'dragover'].forEach((evt) => {
            this.dropZone.addEventListener(evt, (e) => {
                e.preventDefault();
                this.dropZone.classList.add('border-purple-500', 'bg-purple-500/5');
            });
        });

        ['dragleave', 'drop'].forEach((evt) => {
            this.dropZone.addEventListener(evt, (e) => {
                e.preventDefault();
                this.dropZone.classList.remove('border-purple-500', 'bg-purple-500/5');
            });
        });

        this.dropZone.addEventListener('drop', (e) => {
            const files = Array.from(e.dataTransfer.files).filter((f) => f.type.startsWith('image/'));
            this.handleFiles(files);
        });

        this.grid.addEventListener('click', (e) => {
            const deleteBtn = e.target.closest('[data-delete-picture]');
            if (deleteBtn) {
                const card = deleteBtn.closest('[data-picture-id]');
                this.deletePicture(card);
            }
        });
    }

    initSortable() {
        new Sortable(this.grid, {
            animation: 150,
            handle: '[data-drag-handle]',
            ghostClass: 'opacity-50',
            onUpdate: () => this.persistOrder(),
        });
    }

    currentCount() {
        return this.grid.querySelectorAll('[data-picture-id]').length;
    }

    updateCounter() {
        if (this.counter) {
            this.counter.textContent = `${this.currentCount()} / ${this.maxPictures}`;
        }
    }

    handleFiles(files) {
        const remaining = this.maxPictures - this.currentCount();

        if (remaining <= 0) {
            alert(`Limite atteinte : ${this.maxPictures} images max.`);
            return;
        }

        if (files.length > remaining) {
            alert(`Tu peux ajouter ${remaining} image(s) de plus (limite : ${this.maxPictures}).`);
            files = files.slice(0, remaining);
        }

        files.forEach((file) => this.uploadFile(file));
    }

    async uploadFile(file) {
        const placeholder = this.addPlaceholder(file);

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(this.uploadUrl, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || `HTTP ${response.status}`);
            }

            this.replaceWithCard(placeholder, data);
            this.updateCounter();
        } catch (error) {
            console.error('Upload error:', error);
            this.markPlaceholderFailed(placeholder, error.message);
        }
    }

    addPlaceholder(file) {
        const li = document.createElement('div');
        li.className = 'relative aspect-square bg-slate-800 border border-slate-700 rounded-lg overflow-hidden flex items-center justify-center';
        li.dataset.placeholder = '';

        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.className = 'w-full h-full object-cover opacity-50';
        li.appendChild(img);

        const status = document.createElement('div');
        status.className = 'absolute inset-0 flex items-center justify-center bg-slate-900/60 text-white text-xs';
        status.textContent = 'Upload...';
        li.appendChild(status);

        this.grid.appendChild(li);
        return li;
    }

    markPlaceholderFailed(placeholder, message) {
        const status = placeholder.querySelector('div');
        status.classList.remove('bg-slate-900/60');
        status.classList.add('bg-red-900/80');
        status.textContent = '❌ ' + (message || 'Erreur');
        setTimeout(() => placeholder.remove(), 4000);
    }

    replaceWithCard(placeholder, data) {
        const card = this.buildCard(data);
        placeholder.replaceWith(card);
    }

    buildCard(data) {
        const div = document.createElement('div');
        div.className = 'relative aspect-square bg-slate-800 border border-slate-700 rounded-lg overflow-hidden group';
        div.dataset.pictureId = data.id;

        div.innerHTML = `
            <img src="${data.thumbnailUrl}" alt="" class="w-full h-full object-cover" />
            <button type="button" data-drag-handle title="Déplacer"
                class="absolute top-1 left-1 p-1.5 bg-slate-900/70 hover:bg-slate-900 text-white rounded cursor-grab active:cursor-grabbing opacity-0 group-hover:opacity-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                </svg>
            </button>
            <button type="button" data-delete-picture title="Supprimer"
                class="absolute top-1 right-1 p-1.5 bg-red-600/80 hover:bg-red-600 text-white rounded opacity-0 group-hover:opacity-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        `;
        return div;
    }

    async deletePicture(card) {
        const id = card.dataset.pictureId;

        if (!confirm('Supprimer cette image ?')) {
            return;
        }

        const url = this.deleteUrlTemplate.replace('__ID__', id);
        const csrfToken = card.dataset.csrfToken;

        try {
            const response = await fetch(url, {
                method: 'DELETE',
                headers: { 'X-CSRF-Token': csrfToken || '' },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            card.remove();
            this.updateCounter();
        } catch (error) {
            console.error('Delete error:', error);
            alert('Erreur lors de la suppression.');
        }
    }

    async persistOrder() {
        const ids = Array.from(this.grid.querySelectorAll('[data-picture-id]'))
            .map((el) => parseInt(el.dataset.pictureId, 10));

        try {
            const response = await fetch(this.reorderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
        } catch (error) {
            console.error('Reorder error:', error);
            alert('Erreur lors de la réorganisation.');
        }
    }
}
