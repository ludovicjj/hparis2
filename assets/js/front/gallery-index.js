import { GalleryMasonry } from '../components/GalleryMasonry';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-gallery-masonry]').forEach((root) => new GalleryMasonry(root));
});
