'use strict';

/**
 * router.js — SPA router with page preloading.
 * Keeps the background particle canvas alive across page navigations.
 */
(() => {
    const pageContent = document.getElementById('page-content');
    if (!pageContent) return;

    const TRANSITION_MS = 180;
    const cache = {};

    function normalizePath(path) {
        if (path === '/' || path === '/index.html') return '/';
        return path.startsWith('/') ? path : '/' + path;
    }

    // Cache the current page
    const currentNorm = normalizePath(location.pathname);
    cache[currentNorm] = {
        html: pageContent.innerHTML,
        className: pageContent.className,
        title: document.title
    };

    // Discover and preload all internal links
    function discoverLinks() {
        const paths = new Set();
        document.querySelectorAll('a[href]').forEach(a => {
            const href = a.getAttribute('href');
            if (!href || href.startsWith('http') || href.startsWith('//') || href.startsWith('#') || href.startsWith('mailto:')) return;
            paths.add(href);
        });
        return paths;
    }

    function preload(href) {
        const norm = normalizePath(href);
        if (cache[norm]) return;

        const url = (href === '/') ? '/index.html' : href;
        fetch(url)
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const content = doc.getElementById('page-content');
                if (content) {
                    cache[norm] = {
                        html: content.innerHTML,
                        className: content.className,
                        title: doc.title
                    };
                }
            })
            .catch(() => {});
    }

    discoverLinks().forEach(preload);

    // Navigation
    function navigateTo(path, pushState) {
        const norm = normalizePath(path);
        const cached = cache[norm];
        if (!cached) { location.href = path; return; }
        if (norm === normalizePath(location.pathname)) return;

        const leavingHome = pageContent.classList.contains('page-home');
        const goingHome = cached.className.includes('page-home');

        // Destroy text particles if leaving home
        if (leavingHome && window.SmokeText) window.SmokeText.destroy();

        // Fade out
        pageContent.style.opacity = '0';

        setTimeout(() => {
            // Swap
            pageContent.innerHTML = cached.html;
            pageContent.className = cached.className;
            document.title = cached.title;
            pageContent.scrollTop = 0;

            // Init text particles if arriving home
            if (goingHome && window.SmokeText) {
                document.fonts.ready.then(() => window.SmokeText.init());
            }

            // Discover any new links from the swapped content
            discoverLinks().forEach(preload);

            // Fade in
            requestAnimationFrame(() => { pageContent.style.opacity = '1'; });

            if (pushState) history.pushState({ path }, '', path);
        }, TRANSITION_MS);
    }

    // Intercept link clicks
    document.addEventListener('click', e => {
        const link = e.target.closest('a[href]');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('http') || href.startsWith('//') || href.startsWith('#') || href.startsWith('mailto:')) return;
        e.preventDefault();
        navigateTo(href, true);
    });

    // Back / forward
    window.addEventListener('popstate', () => {
        navigateTo(location.pathname, false);
    });

    // Init smoke text if we loaded directly on the home page
    if (pageContent.classList.contains('page-home') && window.SmokeText) {
        document.fonts.ready.then(() => window.SmokeText.init());
    }
})();
