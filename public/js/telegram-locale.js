/**
 * Telegram Mini-App Locale Helper
 *
 * Ensures the current locale is preserved across all navigation
 * by appending _locale query parameter to all internal links.
 *
 * This solves the issue where cookies might not persist reliably
 * in Telegram WebView, causing the locale to be lost on navigation.
 *
 * Architecture:
 *   - IIFE with singleton guard
 *   - Runs after DOM ready
 *   - Appends _locale to all <a href> and form action URLs on the page
 */
(function () {
    'use strict';

    if (window.__telegramLocaleInitialized) {
        return;
    }
    window.__telegramLocaleInitialized = true;

    /**
     * Get the current locale from the page
     */
    function getCurrentLocale() {
        // Try to get from <html lang="...">
        const htmlLang = document.documentElement.lang;
        if (htmlLang && (htmlLang.startsWith('en') || htmlLang.startsWith('ru'))) {
            return htmlLang.startsWith('ru') ? 'ru' : 'en';
        }

        // Fallback: check URL query parameter
        const params = new URLSearchParams(window.location.search);
        const localeParam = params.get('_locale');
        if (localeParam === 'ru' || localeParam === 'en') {
            return localeParam;
        }

        // Default
        return 'en';
    }

    /**
     * Append _locale query parameter to a URL
     */
    function appendLocaleToUrl(url, locale) {
        if (!url || url.startsWith('#') || url.startsWith('javascript:') || url.startsWith('mailto:')) {
            return url;
        }

        try {
            const baseUrl = new URL(url, window.location.origin);

            // Only append if it's a same-origin URL
            if (baseUrl.origin === window.location.origin) {
                // Don't add if it's already the locale setter URL
                if (baseUrl.pathname.startsWith('/locale/')) {
                    return url;
                }

                // Only add if _locale is not already present
                if (!baseUrl.searchParams.has('_locale')) {
                    baseUrl.searchParams.set('_locale', locale);
                }

                return baseUrl.pathname + baseUrl.search + baseUrl.hash;
            }
        } catch (_e) {
            // If URL parsing fails, return original
        }

        return url;
    }

    /**
     * Update all links on the page to include locale
     */
    function updateLinksWithLocale(locale) {
        // Update all <a> tags
        document.querySelectorAll('a[href]').forEach(link => {
            const href = link.getAttribute('href');
            if (href && !link.getAttribute('data-skip-locale')) {
                const newHref = appendLocaleToUrl(href, locale);
                if (newHref !== href) {
                    link.setAttribute('href', newHref);
                }
            }
        });

        // Update all form actions
        document.querySelectorAll('form[action]').forEach(form => {
            const action = form.getAttribute('action');
            if (action && !form.getAttribute('data-skip-locale')) {
                const newAction = appendLocaleToUrl(action, locale);
                if (newAction !== action) {
                    form.setAttribute('action', newAction);
                }
            }
        });
    }

    /**
     * Initialize
     */
    function init() {
        // Only run in Telegram WebView
        if (!(window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initData)) {
            return;
        }

        const locale = getCurrentLocale();
        updateLinksWithLocale(locale);

        // Also update when new content is added (e.g., via AJAX or dynamic rendering)
        const observer = new MutationObserver((mutations) => {
            let shouldUpdate = false;
            mutations.forEach(mutation => {
                if (mutation.addedNodes.length > 0) {
                    shouldUpdate = true;
                }
            });

            if (shouldUpdate) {
                updateLinksWithLocale(locale);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // DOM ready handling
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
