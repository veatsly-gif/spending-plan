import React from 'react';
import { createRoot } from 'react-dom/client';
import { HybridApp } from './App';
import { I18nProvider } from './hooks/useI18n';
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles.css';

function resolvePageFromPath(pathname) {
  if (pathname.startsWith('/app/dashboard')) {
    return 'dashboard';
  }

  if (pathname.startsWith('/app/login')) {
    return 'login';
  }

  return 'login';
}

const embeddedRoot = document.getElementById('react-app-root');
const standaloneRoot = document.getElementById('root');
const rootElement = embeddedRoot || standaloneRoot;

if (!rootElement) {
  throw new Error('React root container is not found.');
}

const page = embeddedRoot?.getAttribute('data-page') || resolvePageFromPath(window.location.pathname);

let frontendConfig = {
  apiLoginPath: '/api/login',
  apiLoginStubPath: '/api/login/stub',
  dashboardPath: '/app/dashboard',
  loginPath: '/app/login',
  apiDashboardPath: '/api/dashboard',
  apiCreateSpendPath: '/api/dashboard/spends',
  apiCreateIncomePath: '/api/dashboard/incomes',
  spendsPath: '/dashboard/spends',
  incomesPath: '/dashboard/incomes',
  homePath: '/',
};

if (embeddedRoot) {
  const rawConfig = embeddedRoot.getAttribute('data-frontend-config');
  if (rawConfig) {
    try {
      frontendConfig = {
        ...frontendConfig,
        ...JSON.parse(rawConfig),
      };
    } catch (e) {
      // Keep defaults when malformed payload is passed from server-side.
    }
  }
}

createRoot(rootElement).render(
  <React.StrictMode>
    <I18nProvider>
      <HybridApp page={page} config={frontendConfig} />
    </I18nProvider>
  </React.StrictMode>
);
