/**
 * SaltShore Systems V2 — main.js
 * Hamburger navigation toggle + active link state
 */
(function () {
    'use strict';

    var hamburger = document.querySelector('.hamburger');
    var overlay   = document.querySelector('.sidebar-overlay');
    var body      = document.body;

    function openNav() {
        body.classList.add('nav-open');
        if (hamburger) {
            hamburger.setAttribute('aria-expanded', 'true');
            hamburger.setAttribute('aria-label', 'Close navigation');
        }
    }

    function closeNav() {
        body.classList.remove('nav-open');
        if (hamburger) {
            hamburger.setAttribute('aria-expanded', 'false');
            hamburger.setAttribute('aria-label', 'Open navigation');
        }
    }

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            body.classList.contains('nav-open') ? closeNav() : openNav();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeNav);
    }

    // Close nav on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && body.classList.contains('nav-open')) {
            closeNav();
        }
    });

    // Active navigation link highlighting
    var currentFile = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
        var linkFile = link.getAttribute('href').split('/').pop().split('?')[0];
        if (linkFile === currentFile || (currentFile === '' && linkFile === 'index.html')) {
            link.classList.add('active');
        }
    });

}());
