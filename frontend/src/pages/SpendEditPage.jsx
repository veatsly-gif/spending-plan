import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AppHeader } from '../components/layout/AppHeader';
import { Button } from '../components/ui/Button';
import { buildTokenHeaders, useAuth } from '../hooks/useAuth';
import { useI18n } from '../hooks/useI18n';

export function SpendEditPage({ config }) {
  const { id } = useParams();
  const navigate = useNavigate();
  const { t } = useI18n();
  const effective = useMemo(() => ({
    loginPath: config.loginPath || '/app/login',
    apiSpendListPath: config.apiSpendListPath || '/api/dashboard/spends',
  }), [config]);

  const { token, logout } = useAuth(effective);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [form, setForm] = useState({
    amount: '',
    currencyId: '',
    spendingPlanId: '',
    spendDate: '',
    comment: '',
  });
  const [options, setOptions] = useState({ currencies: [], spendingPlans: [] });
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    if (!token || !id) {
      return;
    }
    setLoading(true);
    setError('');
    const response = await fetch(`${effective.apiSpendListPath}/${id}`, {
      headers: { ...buildTokenHeaders(token) },
    });
    const data = await response.json().catch(() => ({}));
    if (response.status === 401) {
      logout(effective.loginPath);
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
      spendingPlanId: String(d.spendingPlanId || ''),
      spendDate: String(d.spendDate || ''),
      comment: String(d.comment || ''),
    });
    setOptions({
      currencies: data.payload.form.currencies || [],
      spendingPlans: data.payload.form.spendingPlans || [],
    });
    setLoading(false);
  }, [effective.apiSpendListPath, effective.loginPath, id, logout, t, token]);

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
    const response = await fetch(`${effective.apiSpendListPath}/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        ...buildTokenHeaders(token),
      },
      body: JSON.stringify({
        amount: form.amount,
        currencyId: Number(form.currencyId),
        spendingPlanId: Number(form.spendingPlanId),
        spendDate: form.spendDate,
        comment: form.comment,
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
    navigate(`/dashboard/spends?month=${encodeURIComponent(form.spendDate.slice(0, 7))}`);
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
        <span className="kicker">{t('spend.kicker')}</span>
        <div className="actions" style={{ marginBottom: 8 }}>
          <Link className="btn btn-ghost" to="/dashboard/spends">{t('list.backSpends')}</Link>
        </div>
        <h1>{t('list.editSpendTitle', { id })}</h1>
        {error ? <p className="form-status form-status-error">{error}</p> : null}
        <form onSubmit={onSubmit} className="react-form-grid" style={{ marginTop: 12 }}>
          <div>
            <label htmlFor="edit-amount">{t('spend.amount')}</label>
            <input id="edit-amount" type="number" step="0.01" min="0" value={form.amount} onChange={(e) => setForm((p) => ({ ...p, amount: e.target.value }))} />
          </div>
          <div>
            <label htmlFor="edit-currency">{t('spend.currency')}</label>
            <select id="edit-currency" value={form.currencyId} onChange={(e) => setForm((p) => ({ ...p, currencyId: e.target.value }))}>
              <option value="">{t('spend.chooseCurrency')}</option>
              {options.currencies.map((c) => (
                <option key={c.id} value={c.id}>{c.code}</option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="edit-plan">{t('spend.plan')}</label>
            <select id="edit-plan" value={form.spendingPlanId} onChange={(e) => setForm((p) => ({ ...p, spendingPlanId: e.target.value }))}>
              <option value="">{t('spend.choosePlan')}</option>
              {options.spendingPlans.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="edit-date">{t('spend.date')}</label>
            <input id="edit-date" type="date" value={form.spendDate} onChange={(e) => setForm((p) => ({ ...p, spendDate: e.target.value }))} />
          </div>
          <div>
            <label htmlFor="edit-comment">{t('spend.comment')}</label>
            <textarea id="edit-comment" rows="2" value={form.comment} onChange={(e) => setForm((p) => ({ ...p, comment: e.target.value }))} />
          </div>
          <div className="actions">
            <Button type="submit" disabled={saving}>{saving ? t('list.saving') : t('list.save')}</Button>
          </div>
        </form>
      </main>
    </div>
  );
}
