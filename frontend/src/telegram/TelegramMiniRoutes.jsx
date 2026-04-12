import { Navigate, Route, Routes } from 'react-router-dom';
import { TelegramMiniSpendPage } from './TelegramMiniSpendPage';
import { TelegramMiniSpendEditPage } from './TelegramMiniSpendEditPage';

export function TelegramMiniRoutes() {
  return (
    <Routes>
      <Route path="spend" element={<TelegramMiniSpendPage />} />
      <Route path="spends/:id/edit" element={<TelegramMiniSpendEditPage />} />
      <Route path="" element={<Navigate to="spend" replace />} />
      <Route path="*" element={<Navigate to="spend" replace />} />
    </Routes>
  );
}
