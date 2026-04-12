import { PillToggle } from '../components/ui/PillToggle';
import { useTelegramMiniI18n } from './TelegramMiniI18n';
import { telegramApiUrl } from './miniApi';

function applyDomTheme(theme) {
  const normalized = theme === 'dark' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', normalized);
  document.documentElement.setAttribute('data-bs-theme', normalized);
  try {
    window.localStorage.setItem('spending-plan-theme', normalized);
  } catch (_e) {
    // Ignore.
  }
}

export function TelegramMiniChrome({ theme, onThemeChange, onLocalePersist }) {
  const { locale, setLocale, t } = useTelegramMiniI18n();

  const persist = async (body) => {
    await fetch(telegramApiUrl('/api/telegram/mini/preferences'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(body),
    });
  };

  const handleTheme = async (next) => {
    applyDomTheme(next);
    onThemeChange(next);
    await persist({ theme: next });
  };

  const handleLocale = async (next) => {
    setLocale(next);
    document.documentElement.setAttribute('lang', next);
    await persist({ language: next });
    if (onLocalePersist) {
      onLocalePersist(next);
    }
  };

  return (
    <div className="tg-mini-topbar">
      <span className="tg-mini-brand">telegram</span>
      <div className="tg-mini-topbar-switchers">
        <PillToggle
          name="tgTheme"
          value={theme}
          onChange={handleTheme}
          size="sm"
          className="sp-theme-toggle"
          options={[
            {
              value: 'light',
              label: (
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <circle cx="12" cy="12" r="5" />
                  <line x1="12" y1="1" x2="12" y2="3" />
                  <line x1="12" y1="21" x2="12" y2="23" />
                  <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                  <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                  <line x1="1" y1="12" x2="3" y2="12" />
                  <line x1="21" y1="12" x2="23" y2="12" />
                  <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                  <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                </svg>
              ),
            },
            {
              value: 'dark',
              label: (
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                </svg>
              ),
            },
          ]}
        />
        <PillToggle
          name="tgLang"
          value={locale}
          onChange={handleLocale}
          size="sm"
          options={[
            { value: 'en', label: t('header.language.en') },
            { value: 'ru', label: t('header.language.ru') },
          ]}
        />
      </div>
    </div>
  );
}
