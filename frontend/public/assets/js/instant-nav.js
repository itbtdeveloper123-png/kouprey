/**
 * Instant Navigation & Speculative Prefetcher for KouPrey
 * Eliminates mobile page-transition lag by prefetching links on touchstart/hover
 * and showing an instant top progress feedback bar on navigation.
 */
(function() {
    'use strict';

    const prefetchedUrls = new Set();
    const isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

    // 1. Speculation Rules API for supported browsers (Chrome 109+, Edge, Android Chrome)
    if (HTMLScriptElement.supports && HTMLScriptElement.supports('speculationrules')) {
        const specScript = document.createElement('script');
        specScript.type = 'speculationrules';
        specScript.textContent = JSON.stringify({
            prefetch: [
                {
                    source: "list",
                    urls: [
                        "product.php",
                        "features.php",
                        "reviews.php",
                        "about.php"
                    ]
                }
            ]
        });
        document.head.appendChild(specScript);
    }

    // 2. Prefetch helper
    function prefetchUrl(url) {
        if (!url || typeof url !== 'string') return;
        if (url.startsWith('#') || url.startsWith('javascript:') || url.startsWith('tel:') || url.startsWith('mailto:')) return;
        
        try {
            const target = new URL(url, window.location.href);
            // Only prefetch same-origin HTML pages
            if (target.origin !== window.location.origin) return;
            if (target.pathname.endsWith('.jpg') || target.pathname.endsWith('.png') || target.pathname.endsWith('.zip') || target.pathname.endsWith('.pdf')) return;
            if (prefetchedUrls.has(target.href)) return;

            prefetchedUrls.add(target.href);

            // Use link rel="prefetch"
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = target.href;
            link.as = 'document';
            document.head.appendChild(link);
        } catch (e) {}
    }

    // 3. Touch prefetching (triggers 100-200ms before click on mobile)
    document.addEventListener('touchstart', function(e) {
        const anchor = e.target.closest('a');
        if (anchor && anchor.href) {
            prefetchUrl(anchor.href);
        }
    }, { passive: true });

    // 4. Mouseover prefetching (desktop hover)
    let hoverTimeout = null;
    document.addEventListener('mouseover', function(e) {
        const anchor = e.target.closest('a');
        if (anchor && anchor.href) {
            clearTimeout(hoverTimeout);
            hoverTimeout = setTimeout(function() {
                prefetchUrl(anchor.href);
            }, 65);
        }
    }, { passive: true });

    // 5. Instant Top Navigation Progress Bar (gives 0ms tactile feedback on click)
    let progressBar = null;
    function showProgressBar() {
        if (!progressBar) {
            progressBar = document.createElement('div');
            progressBar.id = 'instant-nav-progress';
            progressBar.style.cssText = 'position:fixed;top:0;left:0;height:2.5px;width:0%;background:linear-gradient(90deg, #f97316, #ea580c);z-index:999999;transition:width 0.4s ease-out;box-shadow:0 0 8px rgba(249,115,22,0.6);pointer-events:none;';
            document.body.appendChild(progressBar);
        }
        progressBar.style.width = '0%';
        progressBar.style.display = 'block';
        setTimeout(() => { if (progressBar) progressBar.style.width = '70%'; }, 20);
        setTimeout(() => { if (progressBar) progressBar.style.width = '90%'; }, 250);
    }

    document.addEventListener('click', function(e) {
        const anchor = e.target.closest('a');
        if (!anchor || !anchor.href) return;
        if (anchor.target === '_blank' || anchor.hasAttribute('download')) return;
        if (anchor.href.startsWith('#') || anchor.href.startsWith('javascript:')) return;

        try {
            const target = new URL(anchor.href, window.location.href);
            if (target.origin === window.location.origin && target.href !== window.location.href) {
                showProgressBar();
            }
        } catch (e) {}
    }, true);

    // Reset progress on pageshow / history back
    window.addEventListener('pageshow', function() {
        if (progressBar) {
            progressBar.style.width = '100%';
            setTimeout(() => {
                if (progressBar) progressBar.style.display = 'none';
            }, 150);
        }
    });

})();
