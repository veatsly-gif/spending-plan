import { createRoot } from 'react-dom/client';
import React from 'react';
import { I18nProvider } from './hooks/useI18n';
import { HeaderSwitchers } from './components/layout/AppHeader';
import './styles.css';

/**
 * Mounts React-based language and theme switchers in the header.
 * Used on pages that still use Twig templates but want React switchers.
 */
function mountHeaderSwitchers() {
  const containers = document.querySelectorAll('[data-react-header]');

  containers.forEach((container) => {
    if (container && !container.hasAttribute('data-react-mounted')) {
      container.setAttribute('data-react-mounted', 'true');

      try {
        const root = createRoot(container);
        root.render(
          <React.StrictMode>
            <I18nProvider>
              <HeaderSwitchers />
            </I18nProvider>
          </React.StrictMode>
        );
      } catch (error) {
        console.error('Failed to mount header switchers:', error);
      }
    }
  });
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountHeaderSwitchers, { once: true });
} else {
  mountHeaderSwitchers();
}

export { mountHeaderSwitchers };
