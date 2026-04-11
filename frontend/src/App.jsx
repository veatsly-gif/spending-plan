import { LoginPage } from './pages/LoginPage';
import { StubPage } from './pages/StubPage';

export function HybridApp({ page, config }) {
  if ('dashboard' === page) {
    return <StubPage config={config} />;
  }

  return <LoginPage config={config} />;
}
