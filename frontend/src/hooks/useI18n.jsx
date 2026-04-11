import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { messages } from '../i18n/messages';
import { buildTokenHeaders, getStoredToken } from './useAuth';

const LOCALE_KEY = 'spending-plan-locale';
const SUPPORTED_LOCALES = ['en', 'ru'];

const I18nContext = createContext({
  locale: 'en',
  setLocale: () => {},
  t: (key) => key,
  supportedLocales: SUPPORTED_LOCALES,
});

function normalizeLocale(value) {
  const locale = String(value || '').toLowerCase();
  return SUPPORTED_LOCALES.includes(locale) ? locale : 'en';
}

function readCookie(name) {
  const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const match = document.cookie.match(new RegExp(`(?:^|; )${escapedName}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : '';
}

function resolveInitialLocale() {
  const url = new URL(window.location.href);
  const queryLocale = url.searchParams.get('_locale');
  if (queryLocale) {
    return normalizeLocale(queryLocale);
  }

  try {
    const storedLocale = window.localStorage.getItem(LOCALE_KEY);
    if (storedLocale) {
      return normalizeLocale(storedLocale);
    }
  } catch (_error) {
    // Ignore storage errors.
  }

  const cookieLocale = readCookie('_locale');
  if (cookieLocale) {
    return normalizeLocale(cookieLocale);
  }

  return normalizeLocale(String(window.navigator.language || 'en').slice(0, 2));
}

function interpolate(template, params) {
  if (!params || typeof template !== 'string') {
    return template;
  }

  return Object.entries(params).reduce(
    (result, [key, value]) => result.replaceAll(`{${key}}`, String(value)),
    template
  );
}

export function I18nProvider({ children }) {
  const [locale, setLocaleState] = useState(() => resolveInitialLocale());
  const hasMountedRef = useRef(false);

  useEffect(() => {
    const token = getStoredToken();
    if (!token) {
      return;
    }

    fetch('/api/preferences', {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        ...buildTokenHeaders(token),
      },
    })
      .then((response) => (response.ok ? response.json() : null))
      .then((payload) => {
        if (payload?.success && payload.preferences?.language) {
          setLocaleState(normalizeLocale(payload.preferences.language));
        }
      })
      .catch(() => {
        // Ignore fetch failures for expired/invalid tokens.
      });
  }, []);

  useEffect(() => {
    document.documentElement.setAttribute('lang', locale);
    document.cookie = `_locale=${encodeURIComponent(locale)}; path=/; max-age=${365 * 24 * 60 * 60}; SameSite=Lax`;

    try {
      window.localStorage.setItem(LOCALE_KEY, locale);
    } catch (_error) {
      // Ignore storage errors.
    }

    if (!hasMountedRef.current) {
      hasMountedRef.current = true;
      return;
    }

    const token = getStoredToken();
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
      body: JSON.stringify({ language: locale }),
    }).catch(() => {
      // Ignore preference sync failures for expired/invalid tokens.
    });
  }, [locale]);

  const setLocale = useCallback((nextLocale) => {
    setLocaleState(normalizeLocale(nextLocale));
  }, []);

  const t = useCallback((key, params = null) => {
    const localeCatalog = messages[locale] || messages.en;
    const value = localeCatalog[key] || messages.en[key] || key;
    return interpolate(value, params);
  }, [locale]);

  const value = useMemo(() => ({
    locale,
    setLocale,
    t,
    supportedLocales: SUPPORTED_LOCALES,
  }), [locale, setLocale, t]);

  return (
    <I18nContext.Provider value={value}>
      {children}
    </I18nContext.Provider>
  );
}

export function useI18n() {
  return useContext(I18nContext);
}
