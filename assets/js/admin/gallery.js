import { initThumbnailPreview } from '../components/thumbnailManager';
import { PicturesManager } from '../components/PicturesManager';
import { CopyText } from '../components/CopyText';
import { ResetToken } from '../components/ResetToken';

// Init Copy URL button
new CopyText('#copy-url-btn', '#gallery-url', '#copy-url-text');

// Init Reset Token button
new ResetToken('#reset-token-btn', '#gallery-url', '#reset-token-text');

// Init Thumbnail preview
initThumbnailPreview('#gallery_thumbnailFile', '#thumbnail-dropzone');

// Init Pictures upload (for create page)
new PicturesManager('#pictures-dropzone', '#pictures-input')
