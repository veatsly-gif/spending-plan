import { Button } from '../ui/Button';
import { PillToggle } from '../ui/PillToggle';
import { useI18n } from '../../hooks/useI18n';
import { usePreferences } from '../../hooks/usePreferences';

export function AppHeader({ onLogout = null }) {
  const { locale, setLocale, t } = useI18n();
  const { theme, changeTheme } = usePreferences();

  return (
    <header className="site-nav sp-react-header">
      <div className="nav-shell">
        <a className="brand" href="/">{t('header.brand')}</a>

        <div className="nav-links">
          <div className="lang-switch">
            <PillToggle
              name="langToggle"
              value={locale}
              onChange={setLocale}
              options={[
                { value: 'en', label: t('header.language.en') },
                { value: 'ru', label: t('header.language.ru') },
              ]}
            />
          </div>

          <div className="theme-switch">
            <PillToggle
              name="themeToggle"
              value={theme}
              onChange={(newTheme) => changeTheme(newTheme)}
              size="sm"
              className="sp-theme-toggle"
              options={[
                {
                  value: 'light',
                  label: (
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                      <circle cx="12" cy="12" r="5"></circle>
                      <line x1="12" y1="1" x2="12" y2="3"></line>
                      <line x1="12" y1="21" x2="12" y2="23"></line>
                      <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                      <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                      <line x1="1" y1="12" x2="3" y2="12"></line>
                      <line x1="21" y1="12" x2="23" y2="12"></line>
                      <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                      <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                  ),
                },
                {
                  value: 'dark',
                  label: (
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                  ),
                },
              ]}
            />
          </div>

          {onLogout ? (
            <Button type="button" variant="ghost" onClick={onLogout}>
              {t('header.logout')}
            </Button>
          ) : null}
        </div>
      </div>
    </header>
  );
}

/**
 * HeaderSwitchers component - only renders the language and theme toggles
 * For use in headers that already have their own navigation structure
 */
export function HeaderSwitchers() {
  const { locale, setLocale, t } = useI18n();
  const { theme, changeTheme } = usePreferences();

  return (
    <div className="nav-links">
      <div className="lang-switch">
        <PillToggle
          name="langToggle"
          value={locale}
          onChange={setLocale}
          options={[
            { value: 'en', label: t('header.language.en') },
            { value: 'ru', label: t('header.language.ru') },
          ]}
        />
      </div>

      <div className="theme-switch">
        <PillToggle
          name="themeToggle"
          value={theme}
          onChange={(newTheme) => changeTheme(newTheme)}
          size="sm"
          className="sp-theme-toggle"
          options={[
            {
              value: 'light',
              label: (
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <circle cx="12" cy="12" r="5"></circle>
                  <line x1="12" y1="1" x2="12" y2="3"></line>
                  <line x1="12" y1="21" x2="12" y2="23"></line>
                  <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                  <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                  <line x1="1" y1="12" x2="3" y2="12"></line>
                  <line x1="21" y1="12" x2="23" y2="12"></line>
                  <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                  <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
              ),
            },
            {
              value: 'dark',
              label: (
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
              ),
            },
          ]}
        />
      </div>
    </div>
  );
}
