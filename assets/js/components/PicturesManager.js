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

    /**
     * Upload files sequentially with client-side compression
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

        // Timing logs
        const batchStartTime = performance.now();
        console.log(`\n📦 Début du batch: ${totalFiles} images`);
        console.log('─'.repeat(50));

        let totalCompressionTime = 0;
        let totalUploadTime = 0;

        for (const file of files) {
            // Step 1: Compress the image
            this.status.textContent = `Compression de ${file.name}...`;
            this.count.textContent = `${uploadedCount}/${totalFiles}`;

            const compressionStart = performance.now();
            const compressedFile = await this.compressImage(file);
            const compressionTime = performance.now() - compressionStart;
            totalCompressionTime += compressionTime;

            // Step 2: Create blob URL for local preview
            const blobUrl = URL.createObjectURL(compressedFile);

            // Step 3: Upload the compressed image
            this.status.textContent = `Upload de ${file.name}...`;

            try {
                const formData = new FormData();
                formData.append('file', compressedFile, file.name); // Keep original filename

                const uploadStart = performance.now();
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    signal: this.abortController.signal
                });

                const data = await response.json();
                const uploadTime = performance.now() - uploadStart;
                totalUploadTime += uploadTime;

                // Log timing for this file
                const fileSizeMB = (compressedFile.size / 1024 / 1024).toFixed(2);
                console.log(`[${uploadedCount + 1}/${totalFiles}] ${file.name} (${fileSizeMB}MB) → Compression: ${compressionTime.toFixed(0)}ms | Upload: ${uploadTime.toFixed(0)}ms`);

                if (data.success) {
                    this.addPictureToGrid(data.id, blobUrl, data.originalName);
                    this.pictureIds.push(data.id);
                    this.updateHiddenInput();
                } else {
                    console.error('Upload failed:', data.error);
                    URL.revokeObjectURL(blobUrl); // Clean up on error
                }
            } catch (error) {
                console.error('Upload error:', error);
                URL.revokeObjectURL(blobUrl); // Clean up on error
            }

            // Update UI
            uploadedCount++;
            const progressPercent = (uploadedCount / totalFiles) * 100;
            this.bar.style.width = `${progressPercent}%`;
            this.count.textContent = `${uploadedCount}/${totalFiles}`;
        }

        // Final summary
        const totalTime = performance.now() - batchStartTime;
        console.log('─'.repeat(50));
        console.log(`📊 RÉSUMÉ DU BATCH:`);
        console.log(`   Total: ${(totalTime / 1000).toFixed(2)}s`);
        console.log(`   Compression (total): ${(totalCompressionTime / 1000).toFixed(2)}s`);
        console.log(`   Upload+PHP (total): ${(totalUploadTime / 1000).toFixed(2)}s`);
        console.log(`   Moyenne par image: ${(totalTime / totalFiles / 1000).toFixed(2)}s`);
        console.log(`   Moyenne Upload+PHP: ${(totalUploadTime / totalFiles).toFixed(0)}ms`);
        console.log('─'.repeat(50));

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

        // Add delete event
        div.querySelector('button').addEventListener('click', async (e) => {
            e.preventDefault();
            await this.deletePicture(id, div);
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
