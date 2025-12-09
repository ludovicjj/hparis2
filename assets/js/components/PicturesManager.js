import Sortable from 'sortablejs';

export class PicturesManager {

    /**
     *
     * @param {string} dropzoneSelector - picture dropzone selector
     * @param {string }inputSelector - picture file input
     */
    constructor(dropzoneSelector, inputSelector) {
        this.dropzone = document.querySelector(dropzoneSelector);
        this.input = document.querySelector(inputSelector);

        // check requirement
        if (!this.dropzone || !this.input) {
            console.error('Dropzone or input file not found')
            return;
        }

        // Progress bar tools
        this.progress = document.getElementById('upload-progress');
        this.bar = document.getElementById('upload-bar');
        this.count = document.getElementById('upload-count');
        this.status = document.getElementById('upload-status');

        // Pictures container
        this.grid = document.getElementById('pictures-grid');

        // Hidden field for track picture
        this.hiddenInput = document.getElementById('picture-ids');
        this.pictureIds = this.hiddenInput.value ? this.hiddenInput.value.split(',').map(Number) : [];

        // Submit button
        this.submitBtn = document.getElementById('submit-btn');

        // Upload state
        this.isUploading = false;
        this.abortController = null;

        this.initDropzone();
        this.initBeforeUnload();
        this.initDeleteButtons();
        this.initSortable();
    }

     initDropzone() {
        // click to select files
        this.dropzone.addEventListener('click', () => this.input.click());

        // Drag Over
        this.dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.dropzone.classList.add('border-purple-500');
        });

        // Drag Leave
        this.dropzone.addEventListener('dragleave', () => {
            this.dropzone.classList.remove('border-purple-500');
        });

        // Drop
        this.dropzone.addEventListener('drop', async (e) => {
            e.preventDefault();
            this.dropzone.classList.remove('border-purple-500');
            await this.handleUpload(e.dataTransfer.files);
        });

        // File input change
        this.input.addEventListener('change', async (e) => {
            await this.handleUpload(e.target.files);
            e.target.value = '';
        });
    }

    /**
     * Upload files sequentially (one by one) - legacy version
     */
    async handleUpload(fileList) {
        const url = this.dropzone.dataset.uploadUrl;

        // Convert FileList to Array
        const files = Array.from(fileList);

        // Total and Uploaded files count
        const totalFiles = files.length;
        let uploadedCount = 0;

        if (totalFiles === 0) {
            return;
        }

        // Show progress bar container
        this.progress.classList.remove('hidden');
        // init progress bar
        this.bar.style.width = '0%';

        // Disable submit button during upload
        this.setSubmitEnabled(false);

        // Create abort controller for this upload batch
        this.abortController = new AbortController();

        for (const file of files) {
            this.status.textContent = `Upload de ${file.name}...`;
            this.count.textContent = `${uploadedCount}/${totalFiles}`;

            // let go send file
            try {
                const formData = new FormData();
                formData.append('file', file);

                const response = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    signal: this.abortController.signal
                });

                const data = await response.json();

                if (data.success) {
                    this.addPictureToGrid(data);
                    this.pictureIds.push(data.id);
                    this.updateHiddenInput();
                } else {
                    console.error('Upload failed:', data.error);
                }
            } catch (error) {
                console.error('Upload error:', error);
            }

            // Update UI
            uploadedCount++;
            const progressPercent = (uploadedCount / totalFiles) * 100;
            this.bar.style.width = `${progressPercent}%`;
            this.count.textContent = `${uploadedCount}/${totalFiles}`;
        }

        // Display finish message
        this.status.textContent = 'Upload terminé !';

        // Re-enable submit button
        this.setSubmitEnabled(true);

        // Hide progress bar container
        setTimeout(() => {
            this.progress.classList.add('hidden');
        }, 2000);
    }

    setSubmitEnabled(enabled) {
        if (this.submitBtn) {
            this.submitBtn.disabled = !enabled;
        }
        this.isUploading = !enabled;
    }

    initBeforeUnload() {
        window.addEventListener('beforeunload', (e) => {
            if (this.isUploading) {
                e.preventDefault();
                this.abortController?.abort();
            }
        });
    }

    addPictureToGrid(data) {
        // create picture
        const div = document.createElement('div');
        div.className = 'relative group aspect-square cursor-grab active:cursor-grabbing picture-item';
        div.dataset.pictureId = data.id;
        div.innerHTML = `
            <img src="${data.path}" alt="${data.originalName}" class="w-full h-full object-cover rounded-lg">
            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition rounded-lg flex items-center justify-center">
                <button type="button"
                        class="btn-delete-picture bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition"
                        data-picture-id="${data.id}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;

        // Add delete event
        div.querySelector('button').addEventListener('click', async (e) => {
            e.preventDefault();
            await this.deletePicture(data.id, div);
        });

        this.grid.appendChild(div);
    }

    updateHiddenInput() {
        if (this.hiddenInput) {
            this.hiddenInput.value = this.pictureIds.join(',');
        }
    }

    // Initialize Sortable for drag & drop reordering
    initSortable() {
        if (!this.grid) {
            console.error('Grid container is missing')
            return;
        }

        new Sortable(this.grid, {
            animation: 150,
            ghostClass: 'opacity-50',
            onEnd: () => {
                console.log('Before sort', this.pictureIds)
                // Rebuild pictureIds array based on DOM order
                this.pictureIds = Array.from(this.grid.querySelectorAll('div[data-picture-id]'))
                    .map(el => parseInt(el.dataset.pictureId));

                console.log('After sort', this.pictureIds)
                this.updateHiddenInput();
            }
        });
    }

    // Event delegation for delete buttons
    initDeleteButtons() {
        this.grid.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-delete-picture');
            if (!btn) return;

            e.preventDefault();
            const pictureId = parseInt(btn.dataset.pictureId);
            const pictureElement = btn.closest('.picture-item');

            await this.deletePicture(pictureId, pictureElement);
        });
    }

    async deletePicture(id, element) {
        try {
            const response = await fetch(`/admin/api/picture/${id}`, {
                method: 'DELETE'
            });

            const data = await response.json();

            if (data.success) {
                element.remove();
                this.pictureIds = this.pictureIds.filter(pid => pid !== id);
                this.updateHiddenInput();
            } else {
                alert(data.error || 'Erreur lors de la suppression');
            }
        } catch (error) {
            console.error('Delete error:', error);
            alert('Erreur lors de la suppression');
        }
    }
}
