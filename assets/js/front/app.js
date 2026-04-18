import Navbar from './components/Navbar';
import { CategoryCarousel } from '../components/CategoryCarousel';

document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.querySelector('[data-navbar]');
    if (navbar) {
        new Navbar(navbar);
    }

    document.querySelectorAll('[data-carousel]').forEach((root) => new CategoryCarousel(root));
});
