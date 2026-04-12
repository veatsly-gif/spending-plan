import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { AppHeader } from '../components/layout/AppHeader';
import { Button } from '../components/ui/Button';
import { buildTokenHeaders, useAuth } from '../hooks/useAuth';
import { useI18n } from '../hooks/useI18n';

export function IncomeListPage({ config }) {
  const { t } = useI18n();
  const [searchParams] = useSearchParams();
  const effective = useMemo(() => ({
    loginPath: config.loginPath || '/app/login',
    apiIncomeListPath: config.apiIncomeListPath || '/api/dashboard/incomes',
  }), [config]);

  const { token, logout } = useAuth(effective);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [payload, setPayload] = useState(null);

  const load = useCallback(async () => {
    if (!token) {
      return;
    }
    setLoading(true);
    setError('');
    const query = searchParams.toString();
    const url = query ? `${effective.apiIncomeListPath}?${query}` : effective.apiIncomeListPath;
    const response = await fetch(url, {
      headers: { ...buildTokenHeaders(token) },
    });
    const data = await response.json().catch(() => ({}));
    if (response.status === 401) {
      logout(effective.loginPath);
      return;
    }
    if (!response.ok || !data.success) {
      setError(data.error || t('list.loadError'));
      setLoading(false);
      return;
    }
    setPayload(data.payload);
    setLoading(false);
  }, [token, effective.apiIncomeListPath, effective.loginPath, logout, searchParams, t]);

  useEffect(() => {
    if (!token) {
      window.location.assign(effective.loginPath);
      return;
    }
    load().catch(() => {
      setError(t('list.loadError'));
      setLoading(false);
    });
  }, [effective.loginPath, load, token]);

  const deleteIncome = async (rowId) => {
    if (!window.confirm(t('list.confirmDeleteIncome'))) {
      return;
    }
    const response = await fetch(`${effective.apiIncomeListPath}/${rowId}`, {
      method: 'DELETE',
      headers: { ...buildTokenHeaders(token) },
    });
    if (response.status === 401) {
      logout(effective.loginPath);
      return;
    }
    await load();
  };

  if (!token || loading) {
    return (
      <div className="react-portal">
        <AppHeader onLogout={() => logout(effective.loginPath)} />
        <main className="panel reveal"><h1>{t('dashboard.loadingTitle')}</h1></main>
      </div>
    );
  }

  if (error || !payload) {
    return (
      <div className="react-portal">
        <AppHeader onLogout={() => logout(effective.loginPath)} />
        <main className="panel reveal">
          <p>{error || t('list.loadError')}</p>
          <Button type="button" onClick={() => load()}>{t('dashboard.retry')}</Button>
        </main>
      </div>
    );
  }

  const rows = payload.incomes || [];
  const monthTabs = payload.monthTabs || [];

  return (
    <div className="react-portal">
      <AppHeader onLogout={() => logout(effective.loginPath)} />
      <main className="panel reveal">
        <span className="kicker">{t('list.incomesKicker')}</span>
        <div className="actions" style={{ marginTop: 8 }}>
          <Link className="btn btn-ghost" to="/dashboard">{t('list.backDashboard')}</Link>
        </div>
        <h1>{t('list.incomesHeading', { month: payload.monthLabel })}</h1>

        <nav className="sp-tabs" aria-label={t('list.monthTabs')}>
          {monthTabs.map((tab) => (
            <Link
              key={tab.monthKey}
              to={`/dashboard/incomes?month=${encodeURIComponent(tab.monthKey)}&page=1`}
              className={`sp-tab ${tab.active ? 'is-active' : ''}`}
            >
              {tab.label}
            </Link>
          ))}
        </nav>

        <div className="table-wrap" style={{ marginTop: 14 }}>
          {rows.length === 0 ? (
            <div className="empty"><strong>{t('list.incomesEmpty')}</strong></div>
          ) : (
            <table>
              <thead>
                <tr>
                  <th>{t('list.created')}</th>
                  <th>{t('dashboard.table.user')}</th>
                  <th>{t('dashboard.table.amount')}</th>
                  <th>{t('spend.currency')}</th>
                  <th>{t('list.actions')}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id}>
                    <td>{row.createdAtLabel}</td>
                    <td>{row.username}</td>
                    <td>{row.amount}</td>
                    <td>{row.currencyCode}</td>
                    <td>
                      <Link className="btn btn-ghost btn-sm" to={`/dashboard/incomes/${row.id}/edit`}>{t('list.edit')}</Link>
                      {' '}
                      <Button type="button" variant="ghost" className="btn-sm" onClick={() => deleteIncome(row.id)}>{t('list.delete')}</Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </main>
    </div>
  );
}
