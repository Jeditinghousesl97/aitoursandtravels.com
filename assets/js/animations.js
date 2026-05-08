/**
 * animations.js
 * AOS (Animate On Scroll) init + counter animation for stats.
 */

document.addEventListener('DOMContentLoaded', () => {

    // AOS Init 
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration:   750,
            easing:     'ease-out-cubic',
            once:       true,
            offset:     80,
            delay:      0,
        });
    }

    // Stat Counter Animation 
    const counters = document.querySelectorAll('.stat-number[data-count]');
    if (counters.length === 0) return;

    const easeOut = (t) => 1 - Math.pow(1 - t, 3);

    function animateCounter(el) {
        const target   = parseInt(el.getAttribute('data-count'), 10);
        const duration = 1800; // ms
        const start    = performance.now();

        function tick(now) {
            const elapsed  = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const value    = Math.round(easeOut(progress) * target);
            el.textContent = value.toLocaleString();
            if (progress < 1) requestAnimationFrame(tick);
        }

        requestAnimationFrame(tick);
    }

    // Only start counters when About section enters viewport
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                counters.forEach(animateCounter);
                observer.disconnect(); // run once
            }
        });
    }, { threshold: 0.3 });

    const aboutStats = document.querySelector('.about-stats');
    if (aboutStats) observer.observe(aboutStats);

    // Back-to-Top Button 
    const backBtn = document.getElementById('backToTop');
    if (backBtn) {
        window.addEventListener('scroll', () => {
            backBtn.classList.toggle('visible', window.scrollY > 400);
        }, { passive: true });

        backBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

});
