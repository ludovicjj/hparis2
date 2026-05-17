import './video-form.js';
import { EntryPictureUploader } from '../components/EntryPictureUploader';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-entry-picture-uploader]').forEach((container) => {
        new EntryPictureUploader(container);
    });
});
