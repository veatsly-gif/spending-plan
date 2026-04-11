import { useCallback, useEffect, useMemo, useState } from 'react';
import { buildTokenHeaders, getStoredToken } from './useAuth';

const THEME_KEY = 'spending-plan-theme';
const SUPPORTED_THEMES = ['light', 'dark'];

function readCookie(name) {
  const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const match = document.cookie.match(new RegExp(`(?:^|; )${escapedName}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : '';
}

function normalizeTheme(rawTheme) {
  const theme = String(rawTheme || '').toLowerCase();
  return SUPPORTED_THEMES.includes(theme) ? theme : 'light';
}

function resolveInitialTheme() {
  try {
    const storedTheme = window.localStorage.getItem(THEME_KEY);
    if (storedTheme) {
      return normalizeTheme(storedTheme);
    }
  } catch (_error) {
    // Ignore localStorage failures.
  }

  const cookieTheme = readCookie('_theme');
  if (cookieTheme) {
    return normalizeTheme(cookieTheme);
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme) {
  const normalizedTheme = normalizeTheme(theme);
  document.documentElement.setAttribute('data-theme', normalizedTheme);
  document.documentElement.setAttribute('data-bs-theme', normalizedTheme);

  try {
    window.localStorage.setItem(THEME_KEY, normalizedTheme);
  } catch (_error) {
    // Ignore localStorage failures.
  }
}

export function usePreferences() {
  const [theme, setTheme] = useState(() => resolveInitialTheme());

  useEffect(() => {
    applyTheme(theme);
  }, [theme]);

  const changeTheme = useCallback(async (nextTheme) => {
    const normalizedTheme = normalizeTheme(nextTheme);
    setTheme(normalizedTheme);

    const token = getStoredToken();

    try {
      await fetch(`/theme/${normalizedTheme}`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
      });
    } catch (_error) {
      // Theme is still applied client-side even if network call fails.
    }

    if (!token) {
      return;
    }

    fetch('/api/preferences', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...buildTokenHeaders(token),
      },
      body: JSON.stringify({ theme: normalizedTheme }),
    }).catch(() => {
      // Ignore preference sync failures for expired/invalid tokens.
    });
  }, []);

  return useMemo(() => ({
    theme,
    changeTheme,
  }), [theme, changeTheme]);
}
