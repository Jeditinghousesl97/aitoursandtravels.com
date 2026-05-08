/**
 * contact.js
 * Handles Contact form and Custom Tour form submission with basic validation.
 */

document.addEventListener('DOMContentLoaded', () => {

    // Helper: show toast notification 
    function showToast(message, type = 'success') {
        const existing = document.querySelector('.nt-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `nt-toast nt-toast--${type}`;
        toast.innerHTML = `
            <i class="fa ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        `;

        // Inline styles so no extra CSS file needed
        Object.assign(toast.style, {
            position:     'fixed',
            bottom:       '30px',
            left:         '50%',
            transform:    'translateX(-50%) translateY(20px)',
            background:   type === 'success' ? '#0077B6' : '#E53E3E',
            color:        '#fff',
            padding:      '14px 24px',
            borderRadius: '999px',
            fontFamily:   'Poppins, sans-serif',
            fontSize:     '0.9rem',
            fontWeight:   '500',
            display:      'flex',
            alignItems:   'center',
            gap:          '10px',
            boxShadow:    '0 8px 30px rgba(0,0,0,0.2)',
            zIndex:       '99999',
            opacity:      '0',
            transition:   'all 0.35s ease',
        });

        document.body.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            toast.style.opacity   = '1';
            toast.style.transform = 'translateX(-50%) translateY(0)';
        });

        // Animate out
        setTimeout(() => {
            toast.style.opacity   = '0';
            toast.style.transform = 'translateX(-50%) translateY(20px)';
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }

    // Helper: set loading state on button 
    function setLoading(btn, loading) {
        if (loading) {
            btn.dataset.original = btn.innerHTML;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending…';
            btn.disabled  = true;
        } else {
            btn.innerHTML = btn.dataset.original || btn.innerHTML;
            btn.disabled  = false;
        }
    }

    // Contact Form 
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = contactForm.querySelector('button[type="submit"]');
            setLoading(btn, true);

            // Simulate async send (replace with real API call)
            await new Promise(resolve => setTimeout(resolve, 1200));

            setLoading(btn, false);
            contactForm.reset();
            showToast('Message sent! We\'ll get back to you within 24 hours.');
        });
    }

    // Custom Tour Form 
    const customForm = document.getElementById('customTourForm');
    if (customForm) {
        customForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = customForm.querySelector('button[type="submit"]');
            setLoading(btn, true);

            await new Promise(resolve => setTimeout(resolve, 1200));

            setLoading(btn, false);
            customForm.reset();
            showToast('Tour request received! Our team will contact you shortly.');
        });
    }

});
