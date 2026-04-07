/**
 * Telegram Mini App - User Preferences Loader
 *
 * Fetches user preferences from backend on app launch
 * and applies theme and locale settings automatically.
 *
 * Architecture:
 *   - IIFE with singleton guard
 *   - Runs after DOM ready in Telegram WebView only
 *   - Fetches preferences via API and applies them
 *   - Works alongside telegram-locale.js
 */
(function () {
    'use strict';

    if (window.__telegramPreferencesLoaded) {
        return;
    }
    window.__telegramPreferencesLoaded = true;

    const THEME_STORAGE_KEY = 'spending-plan-theme';

    /**
     * Get authentication token from URL
     */
    function getToken() {
        const params = new URLSearchParams(window.location.search);
        return params.get('token') || '';
    }

    /**
     * Fetch user preferences from backend
     */
    async function fetchUserPreferences() {
        const token = getToken();
        if (!token) {
            return null;
        }

        try {
            const response = await fetch('/user/preferences', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                console.warn('Failed to fetch user preferences:', response.status);
                return null;
            }

            const data = await response.json();
            if (data.success && data.preferences) {
                return data.preferences;
            }

            return null;
        } catch (error) {
            console.warn('Error fetching user preferences:', error);
            return null;
        }
    }

    /**
     * Apply theme from preferences
     */
    function applyTheme(theme) {
        if (!theme || (theme !== 'light' && theme !== 'dark')) {
            return;
        }

        // Persist to localStorage and cookie
        try {
            localStorage.setItem(THEME_STORAGE_KEY, theme);
        } catch (e) {
            console.warn('localStorage not available:', e);
        }
        document.cookie = '_theme=' + theme + '; path=/; max-age=' + (365 * 24 * 60 * 60) + '; SameSite=Lax';

        // Apply to HTML element
        const html = document.documentElement;
        html.setAttribute('data-theme-transition', 'true');
        html.setAttribute('data-theme', theme);

        // Update toggle UI if present
        updateThemeToggleUI(theme);

        setTimeout(() => {
            html.removeAttribute('data-theme-transition');
        }, 350);
    }

    /**
     * Update theme toggle UI elements
     */
    function updateThemeToggleUI(theme) {
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
     * Apply locale from preferences
     */
    function applyLocale(locale) {
        if (!locale || (locale !== 'en' && locale !== 'ru')) {
            return;
        }

        const currentLocale = document.documentElement.lang || 'en';
        const currentLocaleBase = currentLocale.split('-')[0];

        // Only redirect if locale is different
        if (locale !== currentLocaleBase) {
            const url = new URL(window.location.href);
            url.searchParams.set('_locale', locale);
            window.location.replace(url.toString());
        }
    }

    /**
     * Initialize preferences loading
     */
    async function init() {
        // Only run in Telegram WebView
        if (!(window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initData)) {
            return;
        }

        // Fetch and apply user preferences
        const preferences = await fetchUserPreferences();
        if (preferences) {
            // Apply theme immediately
            applyTheme(preferences.theme);

            // Apply locale (may cause redirect)
            applyLocale(preferences.language);
        }
    }

    // DOM ready handling
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
