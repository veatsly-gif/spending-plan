const TOKEN_STORAGE = 'sp_telegram_mini_token';

/**
 * Reads token from the current URL (if present) and mirrors it into sessionStorage
 * so client-side navigation keeps working inside Telegram WebView.
 */
export function syncTelegramMiniTokenFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const fromQuery = (params.get('token') || '').trim();
  if (fromQuery) {
    try {
      sessionStorage.setItem(TOKEN_STORAGE, fromQuery);
    } catch (_e) {
      // Ignore storage errors.
    }
    return fromQuery;
  }

  try {
    return (sessionStorage.getItem(TOKEN_STORAGE) || '').trim();
  } catch (_e) {
    return '';
  }
}

export function telegramApiUrl(path) {
  const url = new URL(path, window.location.origin);
  const token = syncTelegramMiniTokenFromUrl();
  if (token) {
    url.searchParams.set('token', token);
  }
  return url.toString();
}
