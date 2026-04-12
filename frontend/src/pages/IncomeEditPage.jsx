import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AppHeader } from '../components/layout/AppHeader';
import { Button } from '../components/ui/Button';
import { buildTokenHeaders, useAuth } from '../hooks/useAuth';
import { useI18n } from '../hooks/useI18n';

export function IncomeEditPage({ config }) {
  const { id } = useParams();
  const navigate = useNavigate();
  const { t } = useI18n();
  const effective = useMemo(() => ({
    loginPath: config.loginPath || '/app/login',
    apiIncomeListPath: config.apiIncomeListPath || '/api/dashboard/incomes',
  }), [config]);

  const { token, logout } = useAuth(effective);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [form, setForm] = useState({
    amount: '',
    currencyId: '',
    comment: '',
    convertToGel: true,
  });
  const [options, setOptions] = useState({ currencies: [] });
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    if (!token || !id) {
      return;
    }
    setLoading(true);
    setError('');
    const response = await fetch(`${effective.apiIncomeListPath}/${id}`, {
      headers: { ...buildTokenHeaders(token) },
    });
    const data = await response.json().catch(() => ({}));
    if (response.status === 401) {
      logout(effective.loginPath);
      return;
    }
    if (response.status === 403) {
      setError(t('list.forbiddenIncome'));
      setLoading(false);
      return;
    }
    if (!response.ok || !data.success || !data.payload?.form) {
      setError(data.error || t('list.loadError'));
      setLoading(false);
      return;
    }
    const d = data.payload.form.defaults;
    setForm({
      amount: String(d.amount || ''),
      currencyId: String(d.currencyId || ''),
      comment: String(d.comment || ''),
      convertToGel: Boolean(d.convertToGel),
    });
    setOptions({ currencies: data.payload.form.currencies || [] });
    setLoading(false);
  }, [effective.apiIncomeListPath, effective.loginPath, id, logout, t, token]);

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

  const onSubmit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    const response = await fetch(`${effective.apiIncomeListPath}/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        ...buildTokenHeaders(token),
      },
      body: JSON.stringify({
        amount: form.amount,
        currencyId: Number(form.currencyId),
        comment: form.comment,
        convertToGel: form.convertToGel,
      }),
    });
    const data = await response.json().catch(() => ({}));
    if (response.status === 401) {
      logout(effective.loginPath);
      setSaving(false);
      return;
    }
    if (!response.ok || !data.success) {
      setError(data.error || t('list.saveError'));
      setSaving(false);
      return;
    }
    setSaving(false);
    navigate('/dashboard/incomes');
  };

  if (!token || loading) {
    return (
      <div className="react-portal">
        <AppHeader onLogout={() => logout(effective.loginPath)} />
        <main className="panel reveal"><h1>{t('dashboard.loadingTitle')}</h1></main>
      </div>
    );
  }

  return (
    <div className="react-portal">
      <AppHeader onLogout={() => logout(effective.loginPath)} />
      <main className="panel reveal">
        <span className="kicker">{t('income.kicker')}</span>
        <div className="actions" style={{ marginBottom: 8 }}>
          <Link className="btn btn-ghost" to="/dashboard/incomes">{t('list.backIncomes')}</Link>
        </div>
        <h1>{t('list.editIncomeTitle', { id })}</h1>
        {error ? <p className="form-status form-status-error">{error}</p> : null}
        <form onSubmit={onSubmit} className="react-form-grid" style={{ marginTop: 12 }}>
          <div>
            <label htmlFor="inc-amount">{t('income.amount')}</label>
            <input id="inc-amount" type="number" step="0.01" min="0" value={form.amount} onChange={(e) => setForm((p) => ({ ...p, amount: e.target.value }))} />
          </div>
          <div>
            <label htmlFor="inc-currency">{t('income.currency')}</label>
            <select id="inc-currency" value={form.currencyId} onChange={(e) => setForm((p) => ({ ...p, currencyId: e.target.value }))}>
              <option value="">{t('income.chooseCurrency')}</option>
              {options.currencies.map((c) => (
                <option key={c.id} value={c.id}>{c.code}</option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="inc-comment">{t('income.comment')}</label>
            <textarea id="inc-comment" rows="2" value={form.comment} onChange={(e) => setForm((p) => ({ ...p, comment: e.target.value }))} />
          </div>
          <div className="react-checkbox-row">
            <input id="inc-convert" type="checkbox" checked={form.convertToGel} onChange={(e) => setForm((p) => ({ ...p, convertToGel: e.target.checked }))} />
            <label htmlFor="inc-convert">{t('income.convertToGel')}</label>
          </div>
          <div className="actions">
            <Button type="submit" disabled={saving}>{saving ? t('list.saving') : t('list.save')}</Button>
          </div>
        </form>
      </main>
    </div>
  );
}
