import { useCallback, useMemo, useState } from 'react';

const TOKEN_KEY = 'spending-plan-api-token';

export function getStoredToken() {
  try {
    return window.localStorage.getItem(TOKEN_KEY) || '';
  } catch (_error) {
    return '';
  }
}

function setStoredToken(token) {
  try {
    window.localStorage.setItem(TOKEN_KEY, token);
  } catch (_error) {
    // Ignore storage failures.
  }
}

function clearStoredToken() {
  try {
    window.localStorage.removeItem(TOKEN_KEY);
  } catch (_error) {
    // Ignore storage failures.
  }
}

export function buildTokenHeaders(token) {
  if (!token) {
    return {};
  }

  return {
    Authorization: `Bearer ${token}`,
  };
}

export function useAuth(config) {
  const effectiveConfig = useMemo(() => ({
    apiLoginPath: config.apiLoginPath || '/api/login',
    apiLoginStubPath: config.apiLoginStubPath || '/api/login/stub',
    loginPath: config.loginPath || '/app/login',
  }), [config]);

  const [token, setToken] = useState(() => getStoredToken());

  const login = useCallback(async (credentials, fallbackMessage = 'Authentication failed.') => {
    const response = await fetch(effectiveConfig.apiLoginPath, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(credentials),
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok || !payload.success || typeof payload.token !== 'string' || payload.token === '') {
      return {
        success: false,
        message: payload.message || fallbackMessage,
      };
    }

    setStoredToken(payload.token);
    setToken(payload.token);

    return {
      success: true,
      identifier: payload.user?.identifier || credentials.username,
      token: payload.token,
    };
  }, [effectiveConfig.apiLoginPath]);

  const validateToken = useCallback(async () => {
    const currentToken = getStoredToken();
    if (!currentToken) {
      return { valid: false };
    }

    const response = await fetch(effectiveConfig.apiLoginStubPath, {
      method: 'GET',
      headers: {
        ...buildTokenHeaders(currentToken),
      },
    });

    const payload = await response.json().catch(() => ({}));
    if (response.ok && payload?.success) {
      setToken(currentToken);
      return { valid: true, token: currentToken };
    }

    clearStoredToken();
    setToken('');
    return { valid: false };
  }, [effectiveConfig.apiLoginStubPath]);

  const logout = useCallback((redirectPath = effectiveConfig.loginPath) => {
    clearStoredToken();
    setToken('');
    if (redirectPath) {
      window.location.assign(redirectPath);
    }
  }, [effectiveConfig.loginPath]);

  return {
    token,
    login,
    logout,
    validateToken,
  };
}
