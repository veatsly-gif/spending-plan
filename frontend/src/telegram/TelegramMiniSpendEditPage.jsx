import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Button } from '../components/ui/Button';
import { useTelegramMiniI18n } from './TelegramMiniI18n';
import { TelegramMiniChrome } from './TelegramMiniChrome';
import { syncTelegramMiniTokenFromUrl, telegramApiUrl } from './miniApi';

function applyDomTheme(theme) {
  const normalized = theme === 'dark' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', normalized);
  document.documentElement.setAttribute('data-bs-theme', normalized);
}

export function TelegramMiniSpendEditPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { t, setLocale } = useTelegramMiniI18n();
  const token = useMemo(() => syncTelegramMiniTokenFromUrl(), []);

  const [theme, setTheme] = useState('light');
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

  const syncChrome = useCallback(async () => {
    const response = await fetch(telegramApiUrl('/api/telegram/mini/bootstrap'));
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      return;
    }
    const prefs = data.preferences || {};
    const nextLocale = prefs.language === 'ru' ? 'ru' : 'en';
    setLocale(nextLocale);
    document.documentElement.setAttribute('lang', nextLocale);
    const nextTheme = prefs.theme === 'dark' ? 'dark' : 'light';
    setTheme(nextTheme);
    applyDomTheme(nextTheme);
  }, [setLocale]);

  const refreshPlansForDate = useCallback(async (dateStr) => {
    const response = await fetch(
      telegramApiUrl(`/api/telegram/mini/spend-form?spendDate=${encodeURIComponent(dateStr)}`),
    );
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      return;
    }
    setOptions((prev) => ({
      ...prev,
      spendingPlans: data.payload.spendingPlans || [],
    }));
    if (data.payload.defaults?.spendingPlanId) {
      setForm((p) => ({
        ...p,
        spendingPlanId: String(data.payload.defaults.spendingPlanId),
      }));
    }
  }, []);

  const load = useCallback(async () => {
    if (!token || !id) {
      return;
    }
    setLoading(true);
    setError('');
    const response = await fetch(telegramApiUrl(`/api/telegram/mini/spends/${id}`));
    const data = await response.json().catch(() => ({}));
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
  }, [id, t, token]);

  useEffect(() => {
    if (!token) {
      setLoading(false);
      setError(t('telegram.invalidToken'));
      return;
    }
    syncChrome().catch(() => {});
    load().catch(() => {
      setError(t('list.loadError'));
      setLoading(false);
    });
  }, [load, syncChrome, t, token]);

  const onSubmit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    const response = await fetch(telegramApiUrl(`/api/telegram/mini/spends/${id}`), {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
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
    if (!response.ok || !data.success) {
      setError(data.error || t('list.saveError'));
      setSaving(false);
      return;
    }
    setSaving(false);
    const month = form.spendDate.length >= 7 ? form.spendDate.slice(0, 7) : '';
    const qs = new URLSearchParams();
    qs.set('tab', 'spends');
    if (month) {
      qs.set('month', month);
    }
    navigate(`/spend?${qs.toString()}`);
  };

  if (!token) {
    return (
      <div className="react-portal telegram-mini-app">
        <main className="panel reveal tg-mini-panel">
          <p>{t('telegram.invalidToken')}</p>
        </main>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="react-portal telegram-mini-app">
        <main className="panel reveal tg-mini-panel">
          <p>{t('dashboard.loadingTitle')}</p>
        </main>
      </div>
    );
  }

  return (
    <div className="react-portal telegram-mini-app">
      <main className="panel reveal tg-mini-panel">
        <TelegramMiniChrome theme={theme} onThemeChange={setTheme} onLocalePersist={() => syncChrome().catch(() => {})} />
        <span className="kicker">{t('spend.kicker')}</span>
        <div className="actions" style={{ marginBottom: 8 }}>
          <Link className="btn btn-ghost" to="/spend">{t('list.backSpends')}</Link>
        </div>
        <h1>{t('list.editSpendTitle', { id })}</h1>
        {error ? <p className="form-status form-status-error">{error}</p> : null}
        <form onSubmit={onSubmit} className="react-form-grid" style={{ marginTop: 12 }}>
          <div>
            <label htmlFor="tg-edit-amount">{t('spend.amount')}</label>
            <input
              id="tg-edit-amount"
              type="number"
              step="0.01"
              min="0"
              value={form.amount}
              onChange={(e) => setForm((p) => ({ ...p, amount: e.target.value }))}
            />
          </div>
          <div>
            <label htmlFor="tg-edit-currency">{t('spend.currency')}</label>
            <select
              id="tg-edit-currency"
              value={form.currencyId}
              onChange={(e) => setForm((p) => ({ ...p, currencyId: e.target.value }))}
            >
              <option value="">{t('spend.chooseCurrency')}</option>
              {options.currencies.map((c) => (
                <option key={c.id} value={c.id}>{c.code}</option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="tg-edit-plan">{t('spend.plan')}</label>
            <select
              id="tg-edit-plan"
              value={form.spendingPlanId}
              onChange={(e) => setForm((p) => ({ ...p, spendingPlanId: e.target.value }))}
            >
              <option value="">{t('spend.choosePlan')}</option>
              {options.spendingPlans.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="tg-edit-date">{t('spend.date')}</label>
            <input
              id="tg-edit-date"
              type="date"
              value={form.spendDate}
              onChange={(e) => {
                const v = e.target.value;
                setForm((p) => ({ ...p, spendDate: v }));
                if (v) {
                  refreshPlansForDate(v).catch(() => {});
                }
              }}
            />
          </div>
          <div>
            <label htmlFor="tg-edit-comment">{t('spend.comment')}</label>
            <textarea
              id="tg-edit-comment"
              rows="2"
              value={form.comment}
              onChange={(e) => setForm((p) => ({ ...p, comment: e.target.value }))}
            />
          </div>
          <div className="actions">
            <Button type="submit" disabled={saving}>{saving ? t('list.saving') : t('list.save')}</Button>
          </div>
        </form>
      </main>
    </div>
  );
}
