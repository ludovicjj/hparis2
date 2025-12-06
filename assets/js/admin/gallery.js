import { initThumbnailPreview } from '../components/thumbnailManager';
import { PicturesManager } from '../components/picturesManager';

// Init Thumbnail preview
initThumbnailPreview('#gallery_thumbnailFile', '#thumbnail-dropzone');

// Init Pictures upload (for create page)
new PicturesManager('#pictures-dropzone', '#pictures-input')
// initPicturesUpload('#pictures-dropzone', '#pictures-input');
