/**
 * Pill Toggle Component
 *
 * Apple-style sliding toggle with realistic 3D physics:
 * - Recessed track with inner shadow
 * - Sliding pill indicator with GPU-accelerated transform
 * - Active label shifts up (raised button effect)
 *
 * Usage:
 *   Include templates/components/pill_toggle.html.twig in your Twig template.
 *   This script auto-initializes all [data-pill-toggle] elements.
 *
 * Architecture:
 *   - IIFE with singleton guard (follows project convention)
 *   - Event delegation via ResizeObserver for responsive positioning
 *   - No external dependencies
 */
(function () {
    'use strict';

    // Singleton guard to prevent double initialization
    if (window.__pillToggleInitialized) {
        return;
    }
    window.__pillToggleInitialized = true;

    /**
     * Initialize a single pill toggle wrapper
     * @param {HTMLElement} wrapper - The [data-pill-toggle] element
     */
    function initToggle(wrapper) {
        const slider = wrapper.querySelector('.pill-toggle-slider');
        if (!slider) return;

        const labels = wrapper.querySelectorAll('[data-pill-toggle-label]');
        const inputs = wrapper.querySelectorAll('.pill-toggle');

        /**
         * Position the slider to match the active label
         */
        function positionSlider() {
            const activeLabel = wrapper.querySelector('.pill-toggle-label.is-active');
            if (!activeLabel) return;

            const wrapperRect = wrapper.getBoundingClientRect();
            const labelRect = activeLabel.getBoundingClientRect();
            const offsetLeft = labelRect.left - wrapperRect.left;

            // 2px accounts for wrapper padding
            slider.style.transform = `translateX(${offsetLeft - 2}px)`;
        }

        /**
         * Handle label click - update UI and navigate
         */
        function navigateTo(url) {
            if (!url) return;

            try {
                const targetUrl = new URL(url, window.location.href);
                const isSameOrigin = targetUrl.origin === window.location.origin;

                // For same-origin URLs (both Telegram and regular browser)
                if (isSameOrigin) {
                    // Wait for the slider animation to play (350ms total, wait 250ms for smooth feel)
                    setTimeout(() => {
                        // Use replace() instead of href to avoid adding to browser history
                        // This makes the back button work correctly in Telegram WebView
                        window.location.replace(targetUrl.href);
                    }, 250);
                    return;
                }

                // For external URLs in Telegram WebView
                if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.openLink) {
                    window.Telegram.WebApp.openLink(targetUrl.href);
                    return;
                }

                window.location.href = targetUrl.href;
            } catch (_e) {
                window.location.href = url;
            }
        }

        function activateLabel(label) {
            const inputId = label.getAttribute('for');
            const input = document.getElementById(inputId);

            if (!input) return;

            // Get URL from input's data attribute
            const url = input.getAttribute('data-pill-toggle-url');
            if (!url) return;

            // Update active state immediately (before navigation)
            labels.forEach(l => l.classList.remove('is-active'));
            label.classList.add('is-active');

            // Check the radio input
            input.checked = true;

            // Reposition slider
            positionSlider();

            navigateTo(url);
        }

        function handleLabelClick(e) {
            activateLabel(e.currentTarget);
        }

        // Attach click handlers to all labels
        labels.forEach(label => {
            label.addEventListener('click', handleLabelClick);
        });

        // Initial position
        positionSlider();

        // Reposition on resize (debounced via requestAnimationFrame)
        let resizeTimer;
        const resizeObserver = new ResizeObserver(() => {
            cancelAnimationFrame(resizeTimer);
            resizeTimer = requestAnimationFrame(positionSlider);
        });
        resizeObserver.observe(wrapper);
    }

    /**
     * Initialize all pill toggles on the page
     */
    function init() {
        const toggles = document.querySelectorAll('[data-pill-toggle]');
        toggles.forEach(initToggle);
    }

    // DOM ready handling (follows project convention)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
