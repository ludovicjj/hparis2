import Masonry from 'masonry-layout';
import imagesLoaded from 'imagesloaded';

export class GalleryMasonry {
    constructor(root) {
        this.root = root;
        this.apiUrl = root.dataset.apiUrl;
        this.category = root.dataset.category || null;
        this.nextOffset = parseInt(root.dataset.nextOffset, 10) || 0;
        this.hasMore = root.dataset.hasMore === 'true';
        this.isLoading = false;
        this.sentinel = document.getElementById('gallery-sentinel');

        this.masonry = new Masonry(this.root, {
            itemSelector: '.masonry-item',
            columnWidth: '.masonry-sizer',
            percentPosition: true,
            transitionDuration: '0',
            stagger: 0,
        });

        // Initial layout after images load (height is unknown until then)
        imagesLoaded(this.root).on('progress', () => this.masonry.layout());

        if (this.hasMore && this.sentinel) {
            this.initInfiniteScroll();
        }
    }

    initInfiniteScroll() {
        this.observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting) {
                    this.loadMore();
                }
            },
            { rootMargin: '300px' }
        );
        this.observer.observe(this.sentinel);
    }

    async loadMore() {
        if (this.isLoading || !this.hasMore) return;
        this.isLoading = true;

        try {
            const url = new URL(this.apiUrl, window.location.origin);
            url.searchParams.set('offset', String(this.nextOffset));
            if (this.category) {
                url.searchParams.set('category', this.category);
            }

            const response = await fetch(url.toString());
            const data = await response.json();

            const newItems = data.galleries.map((gallery) => this.renderItem(gallery));
            newItems.forEach((item) => this.root.appendChild(item));

            this.masonry.appended(newItems);
            imagesLoaded(newItems).on('progress', () => this.masonry.layout());

            this.nextOffset = data.nextOffset;
            this.hasMore = data.hasMore;

            if (!this.hasMore) {
                this.observer?.disconnect();
                this.sentinel?.remove();
            }
        } catch (error) {
            console.error('Erreur lors du chargement des galleries :', error);
        } finally {
            this.isLoading = false;
        }
    }

    renderItem(gallery) {
        const div = document.createElement('div');
        div.className = 'masonry-item w-full sm:w-1/2 lg:w-1/3 px-2 mb-4';

        const title = this.escapeHtml(gallery.title);
        const thumbnail = gallery.thumbnailUrl
            ? `<img src="${gallery.thumbnailUrl}" alt="${title}" loading="lazy" class="w-full h-auto transition-transform duration-300 group-hover:scale-110">`
            : `<div class="aspect-square flex items-center justify-center">
                   <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                   </svg>
               </div>`;

        div.innerHTML = `
            <a href="${gallery.url}" class="group block">
                <div class="overflow-hidden relative bg-gray-100">
                    ${thumbnail}
                    <div class="md:hidden absolute inset-0 bg-black/40 flex flex-col items-center justify-center text-center px-4">
                        <h3 class="text-white text-base font-semibold">${title}</h3>
                        <span class="mt-4 inline-block border border-white/70 text-white bg-white/10 font-medium text-sm px-6 py-2 rounded-lg transition-all">Voir</span>
                    </div>
                    <div class="hidden md:flex absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-500 items-center justify-center text-center px-4">
                        <h3 class="text-white text-xl font-semibold">${title}</h3>
                    </div>
                </div>
            </a>
        `;
        return div;
    }

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}
