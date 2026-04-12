import { Navigate, Route, Routes } from 'react-router-dom';
import { LoginPage } from './pages/LoginPage';
import { DashboardPage } from './pages/DashboardPage';
import { SpendListPage } from './pages/SpendListPage';
import { SpendEditPage } from './pages/SpendEditPage';
import { IncomeListPage } from './pages/IncomeListPage';
import { IncomeEditPage } from './pages/IncomeEditPage';

export function AppRoutes({ config }) {
  return (
    <Routes>
      <Route path="login" element={<LoginPage config={config} />} />
      <Route path="dashboard" element={<DashboardPage config={config} />} />
      <Route path="dashboard/spends" element={<SpendListPage config={config} />} />
      <Route path="dashboard/spends/:id/edit" element={<SpendEditPage config={config} />} />
      <Route path="dashboard/incomes" element={<IncomeListPage config={config} />} />
      <Route path="dashboard/incomes/:id/edit" element={<IncomeEditPage config={config} />} />
      <Route path="" element={<Navigate to="login" replace />} />
      <Route path="*" element={<Navigate to="login" replace />} />
    </Routes>
  );
}
