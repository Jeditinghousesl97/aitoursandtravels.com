/**
 * sliders.js
 * All Swiper.js instances for the site.
 */

document.addEventListener('DOMContentLoaded', () => {

    //  Hero Slider 
    if (document.querySelector('.hero-swiper')) {
        new Swiper('.hero-swiper', {
            loop:            true,
            speed:           900,
            effect:          'fade',
            fadeEffect:      { crossFade: true },
            autoplay: {
                delay:           5000,
                disableOnInteraction: false,
                pauseOnMouseEnter:    true,
            },
            pagination: {
                el:        '.hero-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.hero-next',
                prevEl: '.hero-prev',
            },
            keyboard: { enabled: true },
        });
    }

    // Packages Slider 
    if (document.querySelector('.packages-swiper')) {
        new Swiper('.packages-swiper', {
            loop:          true,
            speed:         700,
            spaceBetween:  28,
            slidesPerView: 1,
            autoplay: {
                delay:                6500,
                disableOnInteraction: false,
                pauseOnMouseEnter:    true,
            },
            pagination: {
                el:        '.packages-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.packages-next',
                prevEl: '.packages-prev',
            },
            breakpoints: {
                600:  { slidesPerView: 2, spaceBetween: 22 },
                1024: { slidesPerView: 3, spaceBetween: 28 },
                1400: { slidesPerView: 3, spaceBetween: 28 },
            },
        });
    }

    // Partners Slider (infinite auto-scroll, no user control) 
    if (document.querySelector('.partners-swiper')) {
        new Swiper('.partners-swiper', {
            loop:            true,
            speed:           3000,
            spaceBetween:    24,
            slidesPerView:   2,
            allowTouchMove:  false,
            autoplay: {
                delay:                0,
                disableOnInteraction: false,
            },
            breakpoints: {
                480:  { slidesPerView: 3, spaceBetween: 20 },
                768:  { slidesPerView: 4, spaceBetween: 24 },
                1024: { slidesPerView: 5, spaceBetween: 28 },
            },
        });
    }

    // Reviews Slider
    if (document.querySelector('.reviews-swiper')) {
        new Swiper('.reviews-swiper', {
            loop:          true,
            speed:         700,
            spaceBetween:  28,
            slidesPerView: 1,
            autoplay: {
                delay:                5500,
                disableOnInteraction: false,
                pauseOnMouseEnter:    true,
            },
            pagination: {
                el:        '.reviews-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.reviews-next',
                prevEl: '.reviews-prev',
            },
            breakpoints: {
                640:  { slidesPerView: 2, spaceBetween: 22 },
                1024: { slidesPerView: 3, spaceBetween: 28 },
            },
        });
    }

    // Destinations Slider
    if (document.querySelector('.destinations-swiper')) {
        new Swiper('.destinations-swiper', {
            loop:          true,
            speed:         700,
            spaceBetween:  28,
            slidesPerView: 1,
            autoplay: {
                delay:                6500,
                disableOnInteraction: false,
                pauseOnMouseEnter:    true,
            },
            pagination: {
                el:        '.destinations-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.destinations-next',
                prevEl: '.destinations-prev',
            },
            breakpoints: {
                600:  { slidesPerView: 2, spaceBetween: 22 },
                1024: { slidesPerView: 3, spaceBetween: 28 },
                1400: { slidesPerView: 3, spaceBetween: 28 },
            },
        });
    }

});
