/**
 * Theme Toggle Component
 *
 * Handles light/dark theme switching with:
 * - localStorage persistence
 * - System preference detection (prefers-color-scheme)
 * - Smooth transitions between themes
 * - Sync across tabs via storage events
 *
 * Usage:
 *   Include templates/components/theme_toggle.html.twig in your template.
 *   This script auto-initializes all [data-theme-toggle] elements.
 *
 * Architecture:
 *   - IIFE with singleton guard (follows project convention)
 *   - Applies [data-theme="light|dark"] to <html> element
 *   - Respects prefers-color-scheme if no explicit preference
 *   - Listens for storage events for cross-tab sync
 */
(function () {
    'use strict';

    // Singleton guard to prevent double initialization
    if (window.__themeToggleInitialized) {
        return;
    }
    window.__themeToggleInitialized = true;

    const THEME_STORAGE_KEY = 'spending-plan-theme';
    const html = document.documentElement;

    /**
     * Get the current theme from localStorage, cookie, or system preference
     * @returns {'light'|'dark'}
     */
    function getTheme() {
        const stored = localStorage.getItem(THEME_STORAGE_KEY);
        if (stored === 'light' || stored === 'dark') {
            return stored;
        }

        // Check cookie as fallback
        const cookies = document.cookie.split('; ');
        for (let i = 0; i < cookies.length; i++) {
            const [name, value] = cookies[i].split('=');
            if (name === '_theme' && (value === 'light' || value === 'dark')) {
                return value;
            }
        }

        // Fall back to system preference
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }

        return 'light';
    }

    /**
     * Apply theme to document with smooth transition
     * @param {'light'|'dark'} theme
     */
    function applyTheme(theme) {
        console.log(`[ThemeToggle] Applying theme: ${theme}`);
        console.log(`[ThemeToggle] HTML element before:`, html.getAttribute('data-theme'));

        // Enable transition animation
        html.setAttribute('data-theme-transition', 'true');

        // Apply theme
        html.setAttribute('data-theme', theme);

        console.log(`[ThemeToggle] HTML element after:`, html.getAttribute('data-theme'));
        console.log(`[ThemeToggle] data-theme-transition:`, html.getAttribute('data-theme-transition'));

        // Update all toggle UI elements
        updateToggleUI(theme);

        // Remove transition flag after animation completes
        setTimeout(() => {
            html.removeAttribute('data-theme-transition');
        }, 350);
    }

    /**
     * Update all toggle UI elements to match current theme
     * @param {'light'|'dark'} theme
     */
    function updateToggleUI(theme) {
        // Update all theme toggles (self-contained structure)
        const toggles = document.querySelectorAll('[data-theme-toggle]');
        toggles.forEach(wrapper => {
            const slider = wrapper.querySelector('.theme-toggle-slider-pill');
            const lightOption = wrapper.querySelector('[data-theme-toggle-light]');
            const darkOption = wrapper.querySelector('[data-theme-toggle-dark]');

            if (slider && lightOption && darkOption) {
                const isDark = theme === 'dark';
                slider.classList.toggle('is-dark', isDark);
                lightOption.classList.toggle('is-active', !isDark);
                darkOption.classList.toggle('is-active', isDark);
            }
        });
    }

    /**
     * Toggle between light and dark themes
     */
    function toggleTheme() {
        const current = getTheme();
        const next = current === 'light' ? 'dark' : 'light';
        setTheme(next);
        applyTheme(next);
    }

    /**
     * Set theme and persist it via both localStorage and cookie
     * @param {'light'|'dark'} theme
     */
    function setTheme(theme) {
        try {
            localStorage.setItem(THEME_STORAGE_KEY, theme);
        } catch (e) {
            console.warn('localStorage not available:', e);
        }
        // Set cookie so server-side Twig template can read the theme
        document.cookie = '_theme=' + theme + '; path=/; max-age=' + (365 * 24 * 60 * 60) + '; SameSite=Lax';
    }

    /**
     * Initialize all theme toggle elements
     */
    function initToggles() {
        const toggles = document.querySelectorAll('[data-theme-toggle]');

        toggles.forEach((wrapper, index) => {
            const slider = wrapper.querySelector('.theme-toggle-slider-pill');
            const lightOption = wrapper.querySelector('[data-theme-toggle-light]');
            const darkOption = wrapper.querySelector('[data-theme-toggle-dark]');

            // Update slider position based on current theme
            const updateSliderPosition = () => {
                const currentTheme = getTheme();
                const isDark = currentTheme === 'dark';
                slider.classList.toggle('is-dark', isDark);
                lightOption.classList.toggle('is-active', !isDark);
                darkOption.classList.toggle('is-active', isDark);
                console.log(`[ThemeToggle] Slider updated to: ${currentTheme}`);
            };

            updateSliderPosition();

            // Create handlers that work in all environments
            const handleLightClick = () => {
                console.log('[ThemeToggle] ☀️ Light theme clicked');
                setTheme('light');
                applyTheme('light');
                updateSliderPosition();
            };

            const handleDarkClick = () => {
                console.log('[ThemeToggle] 🌙 Dark theme clicked');
                setTheme('dark');
                applyTheme('dark');
                updateSliderPosition();
            };

            // Attach both click and touch handlers for maximum compatibility
            lightOption.addEventListener('click', (e) => {
                console.log('[ThemeToggle] Light option - click event fired');
                e.preventDefault();
                e.stopPropagation();
                handleLightClick();
            }, false);

            darkOption.addEventListener('click', (e) => {
                console.log('[ThemeToggle] Dark option - click event fired');
                e.preventDefault();
                e.stopPropagation();
                handleDarkClick();
            }, false);

            // Touch handlers for mobile/Telegram Mini App
            lightOption.addEventListener('touchend', (e) => {
                e.preventDefault();
                e.stopPropagation();
                handleLightClick();
            }, { passive: false });

            darkOption.addEventListener('touchend', (e) => {
                e.preventDefault();
                e.stopPropagation();
                handleDarkClick();
            }, { passive: false });
        });
    }

    /**
     * Initialize theme on page load
     */
    function init() {
        console.log('[ThemeToggle] === Initializing ===');
        const currentTheme = getTheme();
        console.log(`[ThemeToggle] Current theme from storage: ${currentTheme}`);
        applyTheme(currentTheme);
        initToggles();

        // Listen for system preference changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            // Only auto-switch if user hasn't explicitly set a preference
            if (!localStorage.getItem(THEME_STORAGE_KEY)) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });

        // Sync across tabs
        window.addEventListener('storage', (e) => {
            if (e.key === THEME_STORAGE_KEY && e.newValue) {
                applyTheme(e.newValue);
            }
        });
    }

    // DOM ready handling (follows project convention)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
