import { Button } from '../ui/Button';
import { useI18n } from '../../hooks/useI18n';
import { usePreferences } from '../../hooks/usePreferences';

export function AppHeader({ onLogout = null }) {
  const { locale, setLocale, t, supportedLocales } = useI18n();
  const { theme, changeTheme } = usePreferences();

  return (
    <header className="site-nav sp-react-header">
      <div className="nav-shell">
        <a className="brand" href="/">{t('header.brand')}</a>

        <div className="nav-links sp-native-switchers">
          <label className="sp-native-label" htmlFor="sp-header-language">{t('header.language')}</label>
          <select
            id="sp-header-language"
            className="form-select form-select-sm sp-header-select"
            value={locale}
            onChange={(event) => setLocale(event.target.value)}
            aria-label={t('header.language')}
          >
            {supportedLocales.map((optionLocale) => (
              <option key={optionLocale} value={optionLocale}>
                {t(`header.language.${optionLocale}`)}
              </option>
            ))}
          </select>

          <div className="form-check form-switch sp-theme-switch">
            <input
              id="sp-header-theme"
              className="form-check-input"
              type="checkbox"
              checked={theme === 'dark'}
              onChange={(event) => changeTheme(event.target.checked ? 'dark' : 'light')}
              aria-label={t('header.themeDark')}
            />
            <label className="form-check-label sp-native-label" htmlFor="sp-header-theme">
              {t('header.themeDark')}
            </label>
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
