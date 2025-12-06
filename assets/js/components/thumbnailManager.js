/**
 * Thumbnail preview component
 * Gère l'aperçu d'une image avant upload
 */
export function initThumbnailPreview(inputSelector, dropzoneSelector) {
    const input = document.querySelector(inputSelector);
    if (!input) return;

    // Gestion du clic sur la dropzone
    const dropzone = document.querySelector(dropzoneSelector);
    if (dropzone) {
        dropzone.addEventListener('click', () => input.click());
    }

    // Gestion de la preview
    input.addEventListener('change', handlePreview);
}

function handlePreview(e) {
    const input = e.target;
    const preview = document.getElementById('thumbnail-preview');
    const placeholder = document.getElementById('thumbnail-placeholder');
    const img = document.getElementById('thumbnail-img');

    if (!preview || !placeholder || !img) return;

    if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function(e) {
            img.src = e.target.result;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        };

        reader.readAsDataURL(input.files[0]);
    }
}
