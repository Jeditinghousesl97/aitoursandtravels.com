/**
 * main.js
 * Entry point — final initializations after all scripts load.
 */

document.addEventListener('DOMContentLoaded', () => {

    // Smooth scroll for anchor links 
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (!target) return;
            e.preventDefault();
            const offset = 85; // navbar height buffer
            const top = target.getBoundingClientRect().top + window.scrollY - offset;
            window.scrollTo({ top, behavior: 'smooth' });
        });
    });

    // Lazy load images (native) 
    document.querySelectorAll('img[data-src]').forEach(img => {
        img.setAttribute('src', img.getAttribute('data-src'));
        img.removeAttribute('data-src');
    });

    // Current year in footer (fallback
    const yearEl = document.getElementById('footerYear');
    if (yearEl && !yearEl.textContent) {
        yearEl.textContent = new Date().getFullYear();
    }

    console.log('%cASB Tours — Site loaded successfully ✓',
        'color:#0077B6; font-size:13px; font-weight:600;');
});
