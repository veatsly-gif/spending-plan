import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { AppRoutes } from './AppRoutes';
import { I18nProvider } from './hooks/useI18n';
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles.css';

const embeddedRoot = document.getElementById('react-app-root');
const standaloneRoot = document.getElementById('root');
const rootElement = embeddedRoot || standaloneRoot;

if (!rootElement) {
  throw new Error('React root container is not found.');
}

let frontendConfig = {
  apiLoginPath: '/api/login',
  apiLoginStubPath: '/api/login/stub',
  dashboardPath: '/app/dashboard',
  loginPath: '/app/login',
  apiDashboardPath: '/api/dashboard',
  apiCreateSpendPath: '/api/dashboard/spends',
  apiCreateIncomePath: '/api/dashboard/incomes',
  apiSpendListPath: '/api/dashboard/spends',
  apiIncomeListPath: '/api/dashboard/incomes',
  spendsPath: '/app/dashboard/spends',
  incomesPath: '/app/dashboard/incomes',
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
      <BrowserRouter basename="/app">
        <AppRoutes config={frontendConfig} />
      </BrowserRouter>
    </I18nProvider>
  </React.StrictMode>
);
