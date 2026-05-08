/**
 * navbar.js
 * Handles: sticky scroll effect, hamburger menu toggle, smooth active-link
 * tracking on scroll (for single-page), and closing menu on link click.
 *
 * Called by components.js after navbar HTML is injected → initNavbar()
 * Also called automatically on DOMContentLoaded for safety.
 */

function initNavbar() {
    const navbar    = document.getElementById('navbar');
    const hamburger = document.getElementById('hamburger');
    const navMenu   = document.getElementById('navMenu');
    const topbar    = document.getElementById('topbar');

    if (!navbar || !hamburger || !navMenu) return;

    // Scroll: hide topbar, make navbar solid 
    function onScroll() {
        if (window.scrollY > 60) {
            navbar.classList.add('scrolled');
            if (topbar) topbar.classList.add('hidden');
        } else {
            if (!navbar.classList.contains('solid')) {
                navbar.classList.remove('scrolled');
            }
            if (topbar) topbar.classList.remove('hidden');
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll(); // run once on init

    //  Hamburger Toggle 
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        navMenu.classList.toggle('open');
        document.body.style.overflow = navMenu.classList.contains('open') ? 'hidden' : '';
    });

    // Close menu when a link is clicked 
    navMenu.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            navMenu.classList.remove('open');
            document.body.style.overflow = '';
        });
    });

    // Close menu on outside click 
    document.addEventListener('click', (e) => {
        if (!navbar.contains(e.target) && navMenu.classList.contains('open')) {
            hamburger.classList.remove('active');
            navMenu.classList.remove('open');
            document.body.style.overflow = '';
        }
    });

    // Active link on scroll (single-page only) 
    const sections = document.querySelectorAll('section[id]');
    if (sections.length === 0) return;

    const navLinks = navMenu.querySelectorAll('.nav-link');

    function highlightActiveSection() {
        const scrollY = window.scrollY + 120;

        sections.forEach(section => {
            const top    = section.offsetTop;
            const height = section.offsetHeight;
            const id     = section.getAttribute('id');

            if (scrollY >= top && scrollY < top + height) {
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') && link.getAttribute('href').includes(`#${id}`)) {
                        link.classList.add('active');
                    }
                });
            }
        });
    }

    window.addEventListener('scroll', highlightActiveSection, { passive: true });
}

// Safety: also call on DOMContentLoaded in case components.js already loaded
document.addEventListener('DOMContentLoaded', () => {
    // Small delay to let components.js inject the navbar first
    setTimeout(initNavbar, 100);
});
