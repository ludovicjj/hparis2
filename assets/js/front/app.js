import Navbar from './components/Navbar';

document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.querySelector('[data-navbar]');
    if (navbar) {
        new Navbar(navbar);
    }
});
