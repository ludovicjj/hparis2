export class VideoPictureCreate {
    static CONCURRENCY = 3;

    constructor(form) {
        this.form = form;
        this.createStubUrl = form.action;
        this.videoPictureUploadUrl = form.dataset.videoPictureUploadUrl;
        this.finalRedirectUrl = form.dataset.finalRedirectUrl;
        this.maxPictures = parseInt(form.dataset.maxPictures, 10) || 5;

        this.dropZone = form.querySelector('[data-drop-zone]');
        this.fileInput = form.querySelector('[data-file-input]');
        this.queueGrid = form.querySelector('[data-queue-grid]');
        this.counter = form.querySelector('[data-pictures-counter]');
        this.submitButton = form.querySelector('button[type="submit"]');

        this.queue = [];
        this.locked = false;

        this.bindEvents();
        this.updateCounter();
    }

    bindEvents() {
        // Show File selector
        this.dropZone.addEventListener('click', () => {
            if (this.locked) {
                return
            }
            this.fileInput.click();
        });

        // Add to queue
        this.fileInput.addEventListener('change', (e) => {
            this.addToQueue(Array.from(e.target.files));
            this.fileInput.value = '';
        });
        this.dropZone.addEventListener('drop', (e) => {
            if (this.locked) {
                return
            }
            const files = Array.from(e.dataTransfer.files).filter((f) => f.type.startsWith('image/'));
            this.addToQueue(files);
        });

        // Update style
        ['dragenter', 'dragover'].forEach((evt) => {
            this.dropZone.addEventListener(evt, (e) => {
                e.preventDefault();
                if (this.locked) {
                    return
                }
                this.dropZone.classList.add('border-purple-500', 'bg-purple-500/5');
            });
        });
        ['dragleave', 'drop'].forEach((evt) => {
            this.dropZone.addEventListener(evt, (e) => {
                e.preventDefault();
                this.dropZone.classList.remove('border-purple-500', 'bg-purple-500/5');
            });
        });

        // Remove picture tu queue
        this.queueGrid.addEventListener('click', (e) => {
            if (this.locked) {
                return
            }

            const removeBtn = e.target.closest('[data-remove-queue]');

            if (removeBtn) {
                const card = removeBtn.closest('[data-queue-id]');
                this.removeFromQueue(card.dataset.queueId);
            }
        });

        // Handle Submit
        this.form.addEventListener('submit', this.onSubmit.bind(this));
    }

    addToQueue(files) {
        const remaining = this.maxPictures - this.queue.length;

        if (remaining <= 0) {
            alert(`Limite atteinte : ${this.maxPictures} images max.`);
            return;
        }

        if (files.length > remaining) {
            alert(`Tu peux ajouter ${remaining} image(s) de plus (limite : ${this.maxPictures}).`);
            files = files.slice(0, remaining);
        }

        files.forEach((file) => {
            // Random client-side id : we don't have a DB id yet.
            const id = Math.random().toString(36).slice(2);
            const item = { id, file, status: 'pending' };
            this.queue.push(item);
            this.renderQueueItem(item);
        });

        this.updateCounter();
    }

    renderQueueItem(item) {
        const card = document.createElement('div');
        card.className = 'relative aspect-square bg-slate-800 border border-slate-700 rounded-lg overflow-hidden group';
        card.dataset.queueId = item.id;

        card.innerHTML = `
            <img src="${URL.createObjectURL(item.file)}" alt="" class="w-full h-full object-cover">
            <div class="absolute bottom-1 left-1 right-1 px-2 py-0.5 text-xs text-white bg-slate-900/70 rounded text-center" data-status>
                En attente
            </div>
            <button type="button" data-remove-queue title="Supprimer"
                    class="absolute top-1 right-1 p-1.5 bg-red-600/80 hover:bg-red-600 text-white rounded opacity-0 group-hover:opacity-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        `;

        this.queueGrid.appendChild(card);
    }

    removeFromQueue(queueId) {
        this.queue = this.queue.filter((item) => item.id !== queueId);
        const card = this.queueGrid.querySelector(`[data-queue-id="${queueId}"]`);
        if (card) card.remove();
        this.updateCounter();
    }

    updateCounter() {
        if (this.counter) {
            this.counter.textContent = `${this.queue.length} / ${this.maxPictures}`;
        }
    }

    async onSubmit(event) {
        event.preventDefault();

        // Clear Error and prevent re-submit
        this.clearErrors();
        this.lockUi();

        // Create Video Entity
        let videoId;
        try {
            const response = await fetch(this.createStubUrl, {
                method: 'POST',
                body: new FormData(this.form),
                headers: { 'Accept': 'application/json' },
            });

            // Error: Handle Violations errors
            if (response.status === 422) {
                const { errors } = await response.json();
                this.displayErrors(errors || {});

                // Unlock submit btn for next try
                this.unlockUi();
                return;
            }

            // Error: Handle Other errors
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            // Success and fetch video.id
            const data = await response.json();
            videoId = data.id;
        } catch (error) {
            console.error('video created failed:', error);
            alert('Erreur lors de la création. Réessaie.');
            this.unlockUi();
            return;
        }

        // No picture redirect directly.
        if (this.queue.length === 0) {
            window.location.href = this.finalRedirectUrl;
            return;
        }

        // Build URL for upload VideoPicture
        // replace placeholder by video.id
        const uploadUrl = this.videoPictureUploadUrl.replace('__ID__', videoId);

        // Worker pool : N workers compete for queued items.
        const workers = Array(VideoPictureCreate.CONCURRENCY)
            .fill(null)
            .map(() => this.uploadWorker(uploadUrl));

        await Promise.all(workers);

        // Report partial failures (if any), redirect either way.
        const failedCount = this.queue.filter((item) => item.status === 'failed').length;
        if (failedCount > 0) {
            alert(`${failedCount} image(s) ont échoué. Tu pourras les re-uploader depuis la page d'édition.`);
        }

        window.location.href = this.finalRedirectUrl;
    }

    async uploadWorker(uploadUrl) {
        while (true) {
            const item = this.queue.find((i) => i.status === 'pending');

            // no item in queue with await status
            if (!item) {
                return;
            }

            item.status = 'uploading';
            this.updateItemStatus(item.id, 'Upload...', 'bg-blue-900/80');

            try {
                const formData = new FormData();
                formData.append('file', item.file);

                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.error || `HTTP ${response.status}`);
                }

                item.status = 'done';
                this.updateItemStatus(item.id, 'Success', 'bg-green-900/80');
            } catch (error) {
                console.error('VideoPictureCreate phase 2 (upload) failed for item', item.id, error);
                item.status = 'failed';
                this.updateItemStatus(item.id, 'Error: ' + error.message, 'bg-red-900/80');
            }
        }
    }

    updateItemStatus(queueId, label, bgClass) {
        const card = this.queueGrid.querySelector(`[data-queue-id="${queueId}"]`);
        if (!card) return;

        const status = card.querySelector('[data-status]');
        if (status) {
            status.textContent = label;
            status.className = `absolute bottom-1 left-1 right-1 px-2 py-0.5 text-xs text-white rounded text-center ${bgClass}`;
        }

        // Remove the "×" button once the upload has started.
        const removeBtn = card.querySelector('[data-remove-queue]');
        if (removeBtn) {
            removeBtn.remove()
        }
    }

    lockUi() {
        this.locked = true;
        if (this.submitButton) {
            this.submitButton.disabled = true;
        }
        this.queueGrid.classList.add('pointer-events-none');
    }

    unlockUi() {
        this.locked = false;
        if (this.submitButton) {
            this.submitButton.disabled = false;
        }
        this.queueGrid.classList.remove('pointer-events-none');
    }

    displayErrors(errors) {
        Object.entries(errors).forEach(([field, message]) => {
            const input = this.form.querySelector(`[name$="[${field}]"]`);
            if (!input) {
                console.warn(`VideoPictureCreate : no input found for field "${field}"`);
                return;
            }

            const errorEl = document.createElement('p');
            errorEl.className = 'async-form-error text-red-400 text-xs mt-1';
            errorEl.textContent = message;
            input.parentNode.appendChild(errorEl);
        });
    }

    clearErrors() {
        this.form.querySelectorAll('.async-form-error').forEach((el) => el.remove());
    }
}
