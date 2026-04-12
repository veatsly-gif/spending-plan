import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles.css';
import { TelegramMiniI18nProvider } from './telegram/TelegramMiniI18n';
import { TelegramMiniRoutes } from './telegram/TelegramMiniRoutes';
import { syncTelegramMiniTokenFromUrl } from './telegram/miniApi';

syncTelegramMiniTokenFromUrl();

const rootElement = document.getElementById('telegram-mini-root');
if (!rootElement) {
  throw new Error('Telegram mini-app root container is not found.');
}

createRoot(rootElement).render(
  <React.StrictMode>
    <TelegramMiniI18nProvider initialLocale="en">
      <BrowserRouter basename="/telegram/mini">
        <TelegramMiniRoutes />
      </BrowserRouter>
    </TelegramMiniI18nProvider>
  </React.StrictMode>,
);
