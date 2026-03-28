import GLightbox from 'glightbox';
import 'glightbox/dist/css/glightbox.min.css';

let lightbox = null;

function initLightbox() {
    if (lightbox) {
        lightbox.reload(); // reload all picture after one scroll
    } else {
        lightbox = GLightbox({
            selector: '.glightbox',
            touchNavigation: true,
            loop: true,
        });
    }
}

function initInfiniteScroll() {
    const grid = document.getElementById('pictures-grid');
    const loadingIndicator = document.getElementById('loading-indicator');

    if (!grid) return;

    // Initialiser la lightbox pour les images déjà présentes
    initLightbox();

    if (!loadingIndicator) return;

    const apiUrl = grid.dataset.apiUrl;
    let nextOffset = parseInt(grid.dataset.initialOffset, 10);
    let hasMore = grid.dataset.hasMore === 'true';
    let isLoading = false;

    function createPictureElement(picture) {
        const link = document.createElement('a');
        link.href = picture.lightboxPath; // Image lightbox (1200px)
        link.className = 'glightbox aspect-square overflow-hidden bg-gray-200 animate-pulse block relative group';

        const img = document.createElement('img');
        img.alt = picture.originalName || '';
        img.className = 'w-full h-full object-cover cursor-pointer opacity-0 transition-opacity';

        const overlay = document.createElement('div');
        overlay.className = 'absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity duration-200';
        img.loading = 'lazy';
        img.decoding = 'async';

        // Affiche l'image une fois chargée
        img.onload = () => {
            img.classList.remove('opacity-0');
            img.classList.add('opacity-100');
            link.classList.remove('bg-gray-200', 'animate-pulse');
            link.classList.add('bg-gray-100');
        };

        img.src = picture.thumbnailPath; // Thumbnail (400px)
        link.appendChild(img);
        link.appendChild(overlay);
        return link;
    }

    async function loadMorePictures() {
        if (isLoading || !hasMore) return;

        isLoading = true;

        try {
            const response = await fetch(`${apiUrl}?offset=${nextOffset}`);
            const data = await response.json();

            // Utilise un fragment pour éviter les reflows multiples
            const fragment = document.createDocumentFragment();
            data.pictures.forEach(picture => {
                fragment.appendChild(createPictureElement(picture));
            });
            grid.appendChild(fragment);

            nextOffset = data.nextOffset;
            hasMore = data.hasMore;

            // Rafraîchir la lightbox pour inclure les nouvelles images
            initLightbox();

            if (!hasMore) {
                loadingIndicator.style.display = 'none';
            }
        } catch (error) {
            console.error('Erreur lors du chargement des images:', error);
        }

        isLoading = false;
    }

    // Throttle pour éviter les appels trop fréquents
    let throttleTimer = null;
    const throttledLoad = () => {
        if (throttleTimer) return;
        throttleTimer = setTimeout(() => {
            throttleTimer = null;
            loadMorePictures();
        }, 150);
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                throttledLoad();
            }
        });
    }, {
        rootMargin: '300px'
    });

    observer.observe(loadingIndicator);
}

document.addEventListener('DOMContentLoaded', initInfiniteScroll);
