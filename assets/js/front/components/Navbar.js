export default class Navbar {
    constructor(root) {
        this.root = root;
        this.toggle = root.querySelector('[data-navbar-toggle]');
        this.menu = root.querySelector('[data-navbar-menu]');
        this.closeBtn = root.querySelector('[data-navbar-close]');

        if (!this.toggle || !this.menu) {
            return;
        }

        this.onOpen = this.open.bind(this);
        this.onClose = this.close.bind(this);
        this.onOverlayClick = this.handleOverlayClick.bind(this);
        this.onKeydown = this.handleKeydown.bind(this);

        this.toggle.addEventListener('click', this.onOpen);
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', this.onClose);
        }
        this.menu.addEventListener('click', this.onOverlayClick);
        document.addEventListener('keydown', this.onKeydown);
    }

    open() {
        this.menu.classList.remove('hidden');
        this.toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.menu.classList.add('hidden');
        this.toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
    }

    handleOverlayClick(event) {
        if (event.target === this.menu) {
            this.close();
        }
    }

    handleKeydown(event) {
        if (event.key === 'Escape' && !this.menu.classList.contains('hidden')) {
            this.close();
        }
    }
}
