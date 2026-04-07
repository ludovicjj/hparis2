import Sortable from 'sortablejs';
import imageCompression from 'browser-image-compression';

export class PicturesManager {

    // Compression options
    static COMPRESSION_OPTIONS = {
        maxSizeMB: 1,              // Taille max après compression (1MB)
        maxWidthOrHeight: 1200,    // Dimension max (égale au lightbox PHP)
        useWebWorker: true,        // Utiliser un Web Worker (non-bloquant)
        fileType: 'image/jpeg',    // Convertir en JPEG
    };

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
     * Compress an image file before upload
     * @param {File} file - Original file
     * @returns {Promise<File>} - Compressed file
     */
    async compressImage(file) {
        // Skip compression for non-image files or small files (< 500KB)
        if (!file.type.startsWith('image/') || file.size < 500 * 1024) {
            return file;
        }

        try {
            const compressedFile = await imageCompression(file, PicturesManager.COMPRESSION_OPTIONS);
            console.log(`Compression: ${(file.size / 1024 / 1024).toFixed(2)}MB → ${(compressedFile.size / 1024 / 1024).toFixed(2)}MB`);
            return compressedFile;
        } catch (error) {
            console.warn('Compression failed, using original file:', error);
            return file;
        }
    }

    // Number of files uploaded in parallel. Capped at 6 because most browsers
    // limit concurrent connections per origin to ~6.
    static UPLOAD_CONCURRENCY = 4;

    /**
     * Upload files in parallel using the direct-to-S3 flow:
     *   1. compress client-side
     *   2. POST /prepare → backend persists Picture (processing) and returns a presigned PUT URL
     *   3. PUT the binary directly to S3 (PHP not in the path)
     *   4. POST /uploaded → backend dispatches the async resize worker
     *
     * Files are processed by a pool of workers (UPLOAD_CONCURRENCY) pulling from
     * a shared cursor. Completion order is not guaranteed; the grid is Sortable
     * anyway so the admin can reorder afterwards.
     *
     * If any step fails after step 2, the orphan Picture entity is rolled back via DELETE.
     */
    async handleUpload(fileList) {
        const prepareUrl = this.dropzone.dataset.prepareUrl;

        // Convert FileList to Array
        const files = Array.from(fileList);
        const totalFiles = files.length;

        if (totalFiles === 0) {
            return;
        }

        // Show progress bar container
        this.progress.classList.remove('hidden');
        // init progress bar
        this.bar.style.width = '0%';
        this.count.textContent = `0/${totalFiles}`;
        this.status.textContent = `Upload en cours (${totalFiles} fichier${totalFiles > 1 ? 's' : ''})...`;

        // Disable submit button during upload
        this.setSubmitEnabled(false);

        // Create abort controller for this upload batch
        this.abortController = new AbortController();

        // Shared state across the worker pool
        let cursor = 0;
        let uploadedCount = 0;

        const worker = async () => {
            while (true) {
                const index = cursor++;
                if (index >= totalFiles) return;

                const file = files[index];
                const compressedFile = await this.compressImage(file);
                const blobUrl = URL.createObjectURL(compressedFile);

                try {
                    await this.uploadOne(prepareUrl, file, compressedFile, blobUrl);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        console.error('Upload error:', error);
                    }
                    URL.revokeObjectURL(blobUrl);
                }

                uploadedCount++;
                this.bar.style.width = `${(uploadedCount / totalFiles) * 100}%`;
                this.count.textContent = `${uploadedCount}/${totalFiles}`;
            }
        };

        const poolSize = Math.min(PicturesManager.UPLOAD_CONCURRENCY, totalFiles);
        await Promise.all(Array.from({ length: poolSize }, () => worker()));

        // Display finish message
        this.status.textContent = 'Upload terminé !';

        // Re-enable submit button
        this.setSubmitEnabled(true);

        // Hide progress bar container
        setTimeout(() => {
            this.progress.classList.add('hidden');
        }, 2000);
    }

    /**
     * Run the prepare → PUT → confirm sequence for a single file.
     * Throws on any failure; the caller cleans up the blob URL.
     */
    async uploadOne(prepareUrl, originalFile, compressedFile, blobUrl) {
        const contentType = compressedFile.type || 'image/jpeg';

        // Init picture and generate pre-signed URL
        const prepareResponse = await fetch(prepareUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                filename: originalFile.name,
                contentType: contentType,
            }),
            signal: this.abortController.signal,
        });

        const prepareData = await prepareResponse.json();
        if (!prepareData.success) {
            throw new Error(prepareData.error || 'Echec de la préparation');
        }

        const { pictureId, uploadUrl, originalName } = prepareData;

        // PUT the binary directly to S3.
        try {
            const putResponse = await fetch(uploadUrl, {
                method: 'PUT',
                headers: { 'Content-Type': contentType },
                body: compressedFile,
                signal: this.abortController.signal,
            });

            if (!putResponse.ok) {
                throw new Error(`S3 PUT failed: ${putResponse.status}`);
            }
        } catch (error) {
            // Roll back the orphan Picture entity (and any temp file that may exist).
            await this.rollbackPicture(pictureId);
            throw error;
        }

        // CDispatches the async resize worker
        try {
            const confirmResponse = await fetch(`/admin/api/picture/${pictureId}/uploaded`, {
                method: 'POST',
                signal: this.abortController.signal,
            });

            const confirmData = await confirmResponse.json();
            if (!confirmData.success) {
                throw new Error(confirmData.error || 'Echec de la confirmation');
            }
        } catch (error) {
            await this.rollbackPicture(pictureId);
            throw error;
        }

        // 4. UI: show the local preview and track the id
        this.addPictureToGrid(pictureId, blobUrl, originalName);
        this.pictureIds.push(pictureId);
        this.updateHiddenInput();
    }

    /**
     * Best-effort cleanup of an orphan Picture entity. Failures are swallowed because
     * the periodic cleanup command will pick up anything we miss.
     */
    async rollbackPicture(pictureId) {
        try {
            await fetch(`/admin/api/picture/${pictureId}`, { method: 'DELETE' });
        } catch (error) {
            console.warn('Rollback failed for picture', pictureId, error);
        }
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

    addPictureToGrid(id, blobUrl, originalName) {
        const div = document.createElement('div');
        div.className = 'relative group aspect-square cursor-grab active:cursor-grabbing picture-item';
        div.dataset.pictureId = id;
        div.innerHTML = `
            <img src="${blobUrl}" alt="${originalName}" class="w-full h-full object-cover rounded-lg">
            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition rounded-lg flex items-center justify-center">
                <button type="button"
                        class="btn-delete-picture bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition"
                        data-picture-id="${id}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;

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
