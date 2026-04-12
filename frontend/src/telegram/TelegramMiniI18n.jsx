import {
  createContext, useCallback, useContext, useMemo, useState,
} from 'react';
import { messages } from '../i18n/messages';

const TelegramMiniI18nContext = createContext({
  locale: 'en',
  setLocale: () => {},
  t: (key) => key,
});

function normalizeLocale(value) {
  const v = String(value || '').toLowerCase();
  return v === 'ru' ? 'ru' : 'en';
}

function interpolate(template, params) {
  if (!params || typeof template !== 'string') {
    return template;
  }
  return Object.entries(params).reduce(
    (result, [key, value]) => result.replaceAll(`{${key}}`, String(value)),
    template,
  );
}

export function TelegramMiniI18nProvider({ initialLocale, children }) {
  const [locale, setLocaleState] = useState(() => normalizeLocale(initialLocale));

  const setLocale = useCallback((next) => {
    setLocaleState(normalizeLocale(next));
  }, []);

  const t = useCallback((key, params = null) => {
    const catalog = messages[locale] || messages.en;
    const fallback = messages.en[key] || key;
    const value = catalog[key] || fallback;
    return interpolate(value, params);
  }, [locale]);

  const value = useMemo(() => ({
    locale,
    setLocale,
    t,
  }), [locale, setLocale, t]);

  return (
    <TelegramMiniI18nContext.Provider value={value}>
      {children}
    </TelegramMiniI18nContext.Provider>
  );
}

export function useTelegramMiniI18n() {
  return useContext(TelegramMiniI18nContext);
}
