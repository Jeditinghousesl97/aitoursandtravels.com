/**
 * components.js
 * Injects navbar (with topbar) and footer directly — no fetch/server required.
 * Works with file://, Live Server, and any HTTP server.
 * Uses BASE prefix so the same HTML works from root & /pages/ depth.
 */

(function () {
    'use strict';

    /** Detect path prefix relative to site root */
    function getBase() {
        const path = window.location.pathname;
        return path.includes('/pages/') ? '../' : './';
    }

    const BASE = getBase();

    /** True when we are on the homepage (root, index.html, or index.php) */
    const isHome = !window.location.pathname.includes('/pages/');

    /** On homepage → scroll anchor; on inner pages → full link */
    function homeLink(anchor, pageHref) {
        return isHome ? anchor : `${BASE}${pageHref}`;
    }

    /** Read a settings meta tag injected by PHP */
    function getSetting(name, fallback) {
        const val = document.querySelector(`meta[name="${name}"]`)?.content || '';
        return val || (fallback || '');
    }

    function getFooterServices() {
        const fallback = [
            'Flight Booking',
            'Hotel Booking',
            'Tour Guides',
            'Visa Assistance',
            'Airport Transfer',
            'Group Tours'
        ];

        try {
            const raw = getSetting('footer-services', '[]');
            const items = JSON.parse(raw);
            if (!Array.isArray(items)) return fallback;

            const cleaned = items
                .map(item => String(item || '').trim())
                .filter(Boolean)
                .slice(0, 6);

            return cleaned.length ? cleaned : fallback;
        } catch (error) {
            return fallback;
        }
    }

    function escapeHTML(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Navbar + Topbar HTML
    function getNavbarHTML() {
        const phone = getSetting('site-phone', '+94 11 234 5678');
        const email = getSetting('site-email', 'info@asbtours.com');
        const waNum = getSetting('wa-number');

        const topbarSocials = [
            [getSetting('social-facebook'),                      'Facebook',  'fab fa-facebook-f'],
            [getSetting('social-instagram'),                     'Instagram', 'fab fa-instagram'],
            [getSetting('social-twitter'),                       'Twitter',   'fab fa-x-twitter'],
            [waNum ? `https://wa.me/${waNum}` : '',              'WhatsApp',  'fab fa-whatsapp'],
        ].filter(([url]) => url)
         .map(([url, label, icon]) => `<a href="${url}" target="_blank" rel="noopener" aria-label="${label}"><i class="${icon}"></i></a>`)
         .join('');

        return `
<div class="topbar" id="topbar">
    <div class="container topbar-inner">
        <div class="topbar-left">
            ${phone ? `<a href="tel:${phone.replace(/\s/g,'')}"><i class="fa fa-phone"></i> ${phone}</a>` : ''}
            ${email ? `<a href="mailto:${email}"><i class="fa fa-envelope"></i> ${email}</a>` : ''}
        </div>
        <div class="topbar-right">
            <span><i class="fa fa-clock"></i> Mon – Sat: 9 AM – 6 PM</span>
            <div class="topbar-socials">${topbarSocials}</div>
        </div>
    </div>
</div>

<nav class="navbar" id="navbar">
    <div class="container nav-container">

        <a href="${BASE}index.php" class="nav-logo">
            ${getSetting('site-logo')
                ? `<img src="${getSetting('site-logo')}" alt="${getSetting('site-name','ASB Tours')}" class="logo-img">`
                : `<div class="logo-icon"><i class="fa-solid fa-compass"></i></div>`
            }
            <div class="logo-text">
                <span class="logo-name">${getSetting('site-name','ASB Tours')}</span>
                <span class="logo-tagline">${getSetting('site-tagline','Come as a guest - Leave as a friend.')}</span>
            </div>
        </a>

        <ul class="nav-menu" id="navMenu">
            <li><a href="${BASE}index.php"                                           class="nav-link" data-page="home">Home</a></li>
            <li><a href="${homeLink('#about',    'index.php#about')}"                class="nav-link" data-page="about">About</a></li>
            <li><a href="${homeLink('#services', 'pages/services.php')}"             class="nav-link" data-page="services">Services</a></li>
            <li><a href="${homeLink('#packages', 'pages/packages.php')}"             class="nav-link" data-page="packages">Packages</a></li>
            <li><a href="${homeLink('#gallery',  'pages/gallery.php')}"              class="nav-link" data-page="gallery">Gallery</a></li>
            <li><a href="${homeLink('#destinations', 'pages/blog.php')}"             class="nav-link" data-page="blog">Destinations</a></li>
            <li><a href="${homeLink('#reviews',  'index.php#reviews')}"              class="nav-link" data-page="reviews">Reviews</a></li>
            <li><a href="${homeLink('#contact',  'index.php#contact')}"              class="nav-link" data-page="contact">Contact</a></li>
        </ul>

        <div class="nav-actions">
            <a href="${BASE}pages/packages.php" class="btn btn-primary nav-btn">
                <i class="fa-solid fa-paper-plane"></i> Book Now
            </a>
            <button class="hamburger" id="hamburger" aria-label="Toggle Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

    </div>
</nav>`;
    }

    // Footer HTML
    function getFooterHTML() {
        const phone   = getSetting('site-phone',   '+94 11 234 5678');
        const email   = getSetting('site-email',   'info@asbtours.com');
        const address = getSetting('site-address', 'No. 15, Galle Road, Colombo 03, Sri Lanka');
        const waNum   = getSetting('wa-number');

        const footerSocials = [
            [getSetting('social-facebook'),                 'Facebook',   'fab fa-facebook-f'],
            [getSetting('social-instagram'),                'Instagram',  'fab fa-instagram'],
            [getSetting('social-twitter'),                  'Twitter',    'fab fa-x-twitter'],
            [getSetting('social-youtube'),                  'YouTube',    'fab fa-youtube'],
            [waNum ? `https://wa.me/${waNum}` : '',         'WhatsApp',   'fab fa-whatsapp'],
        ].filter(([url]) => url)
         .map(([url, label, icon]) => `<a href="${url}" target="_blank" rel="noopener" aria-label="${label}"><i class="${icon}"></i></a>`)
         .join('');

        const tripAdvisorUrl = getSetting('social-tripadvisor');
        const tripAdvisorLogo = tripAdvisorUrl
            ? `<a href="${tripAdvisorUrl}" target="_blank" rel="noopener" aria-label="TripAdvisor" class="footer-tripadvisor-link"><img src="${BASE}assets/images/tripadvisor.png" alt="TripAdvisor" class="footer-tripadvisor-logo"></a>`
            : '';

        const footerServices = getFooterServices()
            .map(service => `<li><a href="${BASE}pages/services.php"><i class="fa fa-chevron-right"></i> ${escapeHTML(service)}</a></li>`)
            .join('');

        return `
<footer class="footer">
    <div class="container">
        <div class="footer-grid">

            <div class="footer-brand">
                <div class="footer-logo">
                    ${getSetting('site-logo')
                        ? `<img src="${getSetting('site-logo')}" alt="${getSetting('site-name','ASB Tours')}" class="logo-img" style="height:38px;max-width:150px;object-fit:contain;">`
                        : `<div class="logo-icon"><i class="fa-solid fa-compass"></i></div>`
                    }
                    <span class="logo-name">${getSetting('site-name','ASB Tours')}</span>
                </div>
                <p>Sri Lanka's passionate travel specialists, crafting unforgettable island experiences from the ancient ruins of Sigiriya to the golden shores of Mirissa. Your Pearl of the Indian Ocean journey begins here.</p>
                <div class="footer-social">${footerSocials}${tripAdvisorLogo}</div>
            </div>

            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="${BASE}index.php"><i class="fa fa-chevron-right"></i> Home</a></li>
                    <li><a href="${BASE}index.php#about"><i class="fa fa-chevron-right"></i> About Us</a></li>
                    <li><a href="${BASE}pages/packages.php"><i class="fa fa-chevron-right"></i> Packages</a></li>
                    <li><a href="${BASE}pages/gallery.php"><i class="fa fa-chevron-right"></i> Gallery</a></li>
                    <li><a href="${BASE}pages/blog.php"><i class="fa fa-chevron-right"></i> Destinations</a></li>
                    <li><a href="${BASE}index.php#contact"><i class="fa fa-chevron-right"></i> Contact</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Our Services</h4>
                <ul>${footerServices}</ul>
            </div>

            <div class="footer-col">
                <h4>Contact Us</h4>
                ${address ? `<div class="footer-contact-item"><i class="fa fa-map-marker-alt"></i><span>${address}</span></div>` : ''}
                ${phone   ? `<div class="footer-contact-item"><i class="fa fa-phone"></i><span>${phone}</span></div>` : ''}
                ${email   ? `<div class="footer-contact-item"><i class="fa fa-envelope"></i><span>${email}</span></div>` : ''}
                <div class="footer-contact-item">
                    <i class="fa fa-clock"></i>
                    <span>Mon–Sat: 9AM – 7PM<br>Sunday: 10AM – 4PM</span>
                </div>
            </div>

        </div>

        <div class="footer-bottom">
            <p>&copy; <span id="footerYear"></span> ASB Tours. All rights reserved. | Designed &amp; Developed by <a href="https://www.asseminate.com/" target="_blank" rel="noopener">Asseminate</a></p>
            <div class="footer-bottom-links">
                <a href="${BASE}pages/privacy-policy.php">Privacy Policy</a>
                <a href="${BASE}pages/terms-of-service.php">Terms of Service</a>
                <a href="${BASE}pages/cookie-policy.php">Cookie Policy</a>
            </div>
        </div>
    </div>
</footer>`;
    }

    // Inject component HTML
    function injectComponent(targetId, html, callback) {
        const el = document.getElementById(targetId);
        if (!el) return;
        el.innerHTML = html;
        if (typeof callback === 'function') callback();
    }

    // Mark the correct nav link active
    function setActiveNav() {
        const page  = window.location.pathname;
        const links = document.querySelectorAll('.nav-link');

        links.forEach(link => {
            link.classList.remove('active');
            const href = link.getAttribute('href') || '';

            if      (page.includes('blog')     && href.includes('blog'))     link.classList.add('active');
            else if (page.includes('packages') && href.includes('packages')) link.classList.add('active');
            else if (page.includes('services') && href.includes('services')) link.classList.add('active');
            else if (page.includes('gallery')  && href.includes('gallery'))  link.classList.add('active');
            else if ((page.endsWith('/') || page.endsWith('index.html') || page.endsWith('index.php') || page === '') && (href.includes('index.html') || href.includes('index.php')) && !href.includes('#')) {
                link.classList.add('active');
            }
        });
    }

    // On inner pages without a page-hero, make navbar solid immediately
    function solidifyNavOnInnerPages() {
        const nav = document.getElementById('navbar');
        if (!nav) return;
        if (document.querySelector('.page-hero')) return;
        const path = window.location.pathname;
        if (!path.endsWith('index.html') && !path.endsWith('index.php') && !path.endsWith('/') && path !== '') {
            nav.classList.add('solid');
        }
    }

    // Floating buttons (WhatsApp + Scroll-to-top) 
    function injectFloatingButtons() {
        const waNum = getSetting('wa-number');

        const html = `
${waNum ? `<a id="float-wa" href="https://wa.me/${waNum}" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="fab fa-whatsapp"></i></a>` : ''}
<button id="float-top" aria-label="Back to top" title="Back to top"><i class="fa fa-chevron-up"></i></button>
<style>
#float-wa {
    position: fixed; bottom: 28px; left: 24px; z-index: 9999;
    width: 50px; height: 50px; border-radius: 50%;
    background: #25D366; display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; text-decoration: none; color: #fff;
    box-shadow: 0 4px 16px rgba(0,0,0,0.18);
    opacity: 0; pointer-events: none; transform: translateY(10px);
    transition: opacity 0.3s, transform 0.3s, box-shadow 0.2s;
}
#float-wa.visible { opacity: 1; pointer-events: auto; transform: translateY(0); }
#float-top {
    position: fixed; bottom: 28px; right: 24px; z-index: 9999;
    width: 50px; height: 50px; border-radius: 50%;
    background: #0077B6; display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; cursor: pointer; border: none; color: #fff;
    box-shadow: 0 4px 16px rgba(0,0,0,0.18);
    opacity: 0; pointer-events: none; transform: translateY(10px);
    transition: opacity 0.3s, transform 0.3s, box-shadow 0.2s;
}
#float-top.visible { opacity: 1; pointer-events: auto; transform: translateY(0); }
#float-wa:hover, #float-top:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.22); }
</style>`;

        document.body.insertAdjacentHTML('beforeend', html);

        window.addEventListener('scroll', () => {
            const hero = document.querySelector('.hero-section, .page-hero, section:first-of-type');
            const threshold = hero ? hero.offsetTop + hero.offsetHeight : 350;
            const past = window.scrollY > threshold;
            document.getElementById('float-top')?.classList.toggle('visible', past);
            document.getElementById('float-wa')?.classList.toggle('visible', past);
        });

        document.getElementById('float-top')?.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Boot
    document.addEventListener('DOMContentLoaded', () => {

        injectComponent('navbar-placeholder', getNavbarHTML(), () => {
            setActiveNav();
            solidifyNavOnInnerPages();
            if (typeof initNavbar === 'function') initNavbar();
        });

        injectComponent('footer-placeholder', getFooterHTML(), () => {
            const yearEl = document.getElementById('footerYear');
            if (yearEl) yearEl.textContent = new Date().getFullYear();
        });

        injectFloatingButtons();

    });

})();
