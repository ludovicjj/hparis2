import { initThumbnailPreview } from '../components/thumbnailManager';
import { PicturesManager } from '../components/PicturesManager';

// Init Thumbnail preview
initThumbnailPreview('#gallery_thumbnailFile', '#thumbnail-dropzone');

// Init Pictures upload (for create page)
new PicturesManager('#pictures-dropzone', '#pictures-input')
