export class CategoryCarousel {
    constructor(root) {
        this.root = root;
        this.track = root.querySelector('[data-carousel-track]');
        this.prevBtn = root.querySelector('[data-carousel-prev]');
        this.nextBtn = root.querySelector('[data-carousel-next]');

        if (!this.track) {
            return;
        }

        this.scrollStep = 240;

        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', () => this.scroll(-this.scrollStep));
        }
        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', () => this.scroll(this.scrollStep));
        }

        this.track.addEventListener('scroll', () => this.updateArrows(), { passive: true });
        window.addEventListener('resize', () => this.updateArrows());

        // Scroll the active category into view on load, then recompute arrows
        this.scrollActiveIntoView();
        this.updateArrows();
    }

    scroll(delta) {
        this.track.scrollBy({ left: delta, behavior: 'smooth' });
    }

    scrollActiveIntoView() {
        const active = this.track.querySelector('[data-active="true"]');
        if (active) {
            active.scrollIntoView({ behavior: 'instant', block: 'nearest', inline: 'center' });
        }
    }

    updateArrows() {
        const { scrollLeft, scrollWidth, clientWidth } = this.track;
        const atStart = scrollLeft <= 1;
        const atEnd = scrollLeft + clientWidth >= scrollWidth - 1;

        if (this.prevBtn) this.prevBtn.disabled = atStart;
        if (this.nextBtn) this.nextBtn.disabled = atEnd;
    }
}
