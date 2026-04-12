import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Button } from '../components/ui/Button';
import { useTelegramMiniI18n } from './TelegramMiniI18n';
import { TelegramMiniChrome } from './TelegramMiniChrome';
import { syncTelegramMiniTokenFromUrl, telegramApiUrl } from './miniApi';

function applyDomTheme(theme) {
  const normalized = theme === 'dark' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', normalized);
  document.documentElement.setAttribute('data-bs-theme', normalized);
  try {
    window.localStorage.setItem('spending-plan-theme', normalized);
  } catch (_e) {
    // Ignore.
  }
}

export function TelegramMiniSpendPage() {
  const { t, setLocale, locale } = useTelegramMiniI18n();
  const [searchParams, setSearchParams] = useSearchParams();

  const [theme, setTheme] = useState('light');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [overview, setOverview] = useState(null);
  const [spendList, setSpendList] = useState(null);
  const [progressOpen, setProgressOpen] = useState(false);

  const [amount, setAmount] = useState('');
  const [currencyId, setCurrencyId] = useState('');
  const [spendingPlanId, setSpendingPlanId] = useState('');
  const [spendDate, setSpendDate] = useState('');
  const [comment, setComment] = useState('');
  const [currencyOptions, setCurrencyOptions] = useState([]);
  const [planOptions, setPlanOptions] = useState([]);

  const [formStatus, setFormStatus] = useState('');
  const [formStatusKind, setFormStatusKind] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const token = useMemo(() => syncTelegramMiniTokenFromUrl(), []);

  const tab = searchParams.get('tab') === 'spends' ? 'spends' : 'add';
  const viewMode = searchParams.get('view') === 'stream' ? 'stream' : 'table';

  const listQuery = useMemo(() => {
    const next = new URLSearchParams(searchParams);
    next.set('tab', 'spends');
    if (!next.get('month')) {
      const now = new Date();
      next.set('month', `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`);
    }
    if (!next.get('view')) {
      next.set('view', 'table');
    }
    if (!next.get('page')) {
      next.set('page', '1');
    }
    if (!next.get('perPage')) {
      next.set('perPage', '10');
    }
    return next.toString();
  }, [searchParams]);

  const loadBootstrap = useCallback(async () => {
    const response = await fetch(telegramApiUrl('/api/telegram/mini/bootstrap'));
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      throw new Error(data.error || t('telegram.loadError'));
    }
    const prefs = data.preferences || {};
    const nextLocale = prefs.language === 'ru' ? 'ru' : 'en';
    setLocale(nextLocale);
    document.documentElement.setAttribute('lang', nextLocale);
    const nextTheme = prefs.theme === 'dark' ? 'dark' : 'light';
    setTheme(nextTheme);
    applyDomTheme(nextTheme);

    setOverview(data.overview);
    const spendForm = data.overview?.forms?.spend;
    if (spendForm) {
      setAmount(String(spendForm.defaults?.amount ?? ''));
      setCurrencyId(String(spendForm.defaults?.currencyId ?? ''));
      setSpendingPlanId(String(spendForm.defaults?.spendingPlanId ?? ''));
      setSpendDate(String(spendForm.defaults?.spendDate ?? ''));
      setComment(String(spendForm.defaults?.comment ?? ''));
      setCurrencyOptions(spendForm.currencies || []);
      setPlanOptions(spendForm.spendingPlans || []);
    }
  }, [setLocale, t]);

  const loadSpendList = useCallback(async () => {
    const response = await fetch(telegramApiUrl(`/api/telegram/mini/spends?${listQuery}`));
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      throw new Error(data.error || t('list.loadError'));
    }
    setSpendList(data.payload);
  }, [listQuery, t]);

  useEffect(() => {
    if (!token) {
      setLoading(false);
      setError(t('telegram.invalidToken'));
      return undefined;
    }

    let cancelled = false;
    (async () => {
      setLoading(true);
      setError('');
      try {
        await loadBootstrap();
      } catch (e) {
        if (!cancelled) {
          setError(e.message || t('telegram.loadError'));
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [token, loadBootstrap, t]);

  useEffect(() => {
    if (!token || tab !== 'spends') {
      return undefined;
    }

    let cancelled = false;
    loadSpendList()
      .catch(() => {
        if (!cancelled) {
          setError(t('list.loadError'));
        }
      });

    return () => {
      cancelled = true;
    };
  }, [token, tab, listQuery, loadSpendList, t]);

  const refreshPlansForDate = useCallback(async (dateStr) => {
    const response = await fetch(
      telegramApiUrl(`/api/telegram/mini/spend-form?spendDate=${encodeURIComponent(dateStr)}`),
    );
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      return;
    }
    const payload = data.payload;
    setPlanOptions(payload.spendingPlans || []);
    if (payload.defaults?.spendingPlanId) {
      setSpendingPlanId(String(payload.defaults.spendingPlanId));
    }
  }, []);

  const setTab = (next) => {
    const nextParams = new URLSearchParams(searchParams);
    if (next === 'spends') {
      nextParams.set('tab', 'spends');
    } else {
      nextParams.delete('tab');
    }
    setSearchParams(nextParams, { replace: true });
  };

  const toggleViewMode = () => {
    const nextParams = new URLSearchParams(searchParams);
    nextParams.set('tab', 'spends');
    nextParams.set('view', viewMode === 'stream' ? 'table' : 'stream');
    nextParams.set('page', '1');
    setSearchParams(nextParams, { replace: true });
  };

  const spendWidget = overview?.spendWidget || {};

  const submitSpend = async (event) => {
    event.preventDefault();
    if (submitting) {
      return;
    }
    setSubmitting(true);
    setFormStatus(t('telegram.spendJsSaving'));
    setFormStatusKind('pending');

    try {
      const response = await fetch(telegramApiUrl('/api/telegram/mini/spends'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          amount,
          currencyId: Number(currencyId),
          spendingPlanId: Number(spendingPlanId),
          spendDate,
          comment,
        }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.success) {
        setFormStatus(data.error || t('telegram.spendJsUnable'));
        setFormStatusKind('error');
        setSubmitting(false);
        return;
      }

      setOverview(data.payload);
      const spendForm = data.payload?.forms?.spend;
      if (spendForm) {
        setAmount(String(spendForm.defaults?.amount ?? ''));
        setCurrencyId(String(spendForm.defaults?.currencyId ?? ''));
        setSpendingPlanId(String(spendForm.defaults?.spendingPlanId ?? ''));
        setSpendDate(String(spendForm.defaults?.spendDate ?? ''));
        setComment(String(spendForm.defaults?.comment ?? ''));
        setCurrencyOptions(spendForm.currencies || []);
        setPlanOptions(spendForm.spendingPlans || []);
      }

      setFormStatus(data.message || t('telegram.spendJsAdded'));
      setFormStatusKind('success');
      setSubmitting(false);
      if (tab === 'spends') {
        loadSpendList().catch(() => {});
      }
    } catch (_e) {
      setFormStatus(t('telegram.spendJsNetwork'));
      setFormStatusKind('error');
      setSubmitting(false);
    }
  };

  const deleteSpend = async (id) => {
    if (!window.confirm(t('list.confirmDeleteSpend'))) {
      return;
    }
    const response = await fetch(telegramApiUrl(`/api/telegram/mini/spends/${id}`), {
      method: 'DELETE',
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
      return;
    }
    await loadSpendList();
    await loadBootstrap();
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

  if (!loading && error && !overview) {
    return (
      <div className="react-portal telegram-mini-app">
        <main className="panel reveal tg-mini-panel">
          <p>{error}</p>
        </main>
      </div>
    );
  }

  if (loading || !overview) {
    return (
      <div className="react-portal telegram-mini-app">
        <main className="panel reveal tg-mini-panel">
          <p>{error || t('dashboard.loadingTitle')}</p>
        </main>
      </div>
    );
  }

  const planName = spendWidget.currentTimePlanName || t('telegram.noCurrentPlan');
  const planTone = spendWidget.currentTimePlanProgressTone || 'ok';
  const monthTone = spendWidget.monthSpendProgressTone || 'ok';

  return (
    <div className="react-portal telegram-mini-app">
      <main className="panel reveal tg-mini-panel">
        <TelegramMiniChrome
          theme={theme}
          onThemeChange={setTheme}
          onLocalePersist={() => {
            loadBootstrap().catch(() => {});
            if (tab === 'spends') {
              loadSpendList().catch(() => {});
            }
          }}
        />

        {error ? <p className="tg-mini-inline-error">{error}</p> : null}

        <section className="metric spend-widget tg-mini-widget">
          <div className="tg-mini-progress-stack">
            <div className="tg-mini-progress-row tg-mini-progress-row--plan">
              <span className="tg-mini-progress-label">{planName}</span>
              <span className="tg-mini-progress-value">
                <span>{spendWidget.currentTimePlanSpentGel}</span>
                {' / '}
                <span>{spendWidget.currentTimePlanLimitGel}</span>
                {' ₾'}
              </span>
            </div>
            <div className="tg-mini-progress-track tg-mini-progress-track--plan">
              <span
                className={`dash-progress-fill is-${planTone}`}
                style={{
                  width: `${Number(spendWidget.currentTimePlanProgressBarPercent || 0)}%`,
                }}
              />
              <span className={`tg-mini-inline-label is-${planTone}`}>
                {Math.round(Number(spendWidget.currentTimePlanProgressPercent || 0))}
                %
              </span>
            </div>
            <div className="tg-mini-progress-row">
              <span className="tg-mini-progress-label">{t('telegram.forMonth')}</span>
              <span className={`tg-mini-month-summary is-${monthTone}`}>
                <span>{spendWidget.monthSpentGel}</span>
                {' / '}
                <span>{spendWidget.monthLimitGel}</span>
                {' ₾'}
              </span>
            </div>
          </div>
          <button
            type="button"
            className={`tg-mini-progress-toggle ${progressOpen ? 'is-open' : ''}`}
            aria-expanded={progressOpen}
            onClick={() => setProgressOpen(!progressOpen)}
            aria-label={progressOpen ? t('telegram.hideProgressDetails') : t('telegram.showProgressDetails')}
          >
            <span className="tg-mini-progress-toggle-icon">⌄</span>
          </button>
          {progressOpen ? (
            <div className="tg-mini-progress-details">
              <div className="tg-mini-progress-details-row">
                <span className="tg-mini-progress-label">
                  {t('telegram.monthProgress', { month: spendWidget.monthLabel || '' })}
                </span>
              </div>
              <div className="tg-mini-progress-track tg-mini-progress-track--month">
                <span
                  className={`dash-progress-fill is-${spendWidget.monthSpendProgressTone || 'ok'}`}
                  style={{
                    width: `${Number(spendWidget.monthSpendProgressBarPercent || 0)}%`,
                  }}
                />
                <span className={`tg-mini-inline-label is-${spendWidget.monthSpendProgressTone || 'ok'}`}>
                  {Math.round(Number(spendWidget.monthSpendProgressPercent || 0))}
                  %
                </span>
              </div>
            </div>
          ) : null}
        </section>

        <div className="tg-mini-tab-row">
          <div className="tg-mini-tab-nav" role="tablist">
            <button
              type="button"
              className={`tg-mini-tab-btn ${tab === 'add' ? 'is-active' : ''}`}
              role="tab"
              aria-selected={tab === 'add'}
              onClick={() => setTab('add')}
            >
              {t('telegram.tabAdd')}
            </button>
            <button
              type="button"
              className={`tg-mini-tab-btn ${tab === 'spends' ? 'is-active' : ''}`}
              role="tab"
              aria-selected={tab === 'spends'}
              onClick={() => setTab('spends')}
            >
              {t('telegram.tabSpends')}
            </button>
          </div>
          {tab === 'spends' ? (
            <button type="button" className="tg-mini-view-toggle" onClick={toggleViewMode}>
              {viewMode === 'stream' ? t('telegram.viewTable') : t('telegram.viewStream')}
            </button>
          ) : null}
        </div>

        {tab === 'add' ? (
          <section className="tg-mini-section">
            {formStatus ? (
              <p className={`form-status form-status-${formStatusKind}`}>{formStatus}</p>
            ) : null}
            <form className="react-form-grid" onSubmit={submitSpend}>
              <div>
                <label htmlFor="tg-spend-amount">{t('spend.amount')}</label>
                <input
                  id="tg-spend-amount"
                  type="number"
                  step="0.01"
                  min="0"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  required
                />
              </div>
              <div>
                <label htmlFor="tg-spend-comment">{t('spend.comment')}</label>
                <textarea
                  id="tg-spend-comment"
                  rows={2}
                  value={comment}
                  onChange={(e) => setComment(e.target.value)}
                />
              </div>
              <div className="tg-mini-field-row">
                <div>
                  <label htmlFor="tg-spend-plan">{t('spend.plan')}</label>
                  <select
                    id="tg-spend-plan"
                    value={spendingPlanId}
                    onChange={(e) => setSpendingPlanId(e.target.value)}
                    required
                  >
                    <option value="">{t('spend.choosePlan')}</option>
                    {planOptions.map((p) => (
                      <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label htmlFor="tg-spend-currency">{t('spend.currency')}</label>
                  <select
                    id="tg-spend-currency"
                    value={currencyId}
                    onChange={(e) => setCurrencyId(e.target.value)}
                    required
                  >
                    <option value="">{t('spend.chooseCurrency')}</option>
                    {currencyOptions.map((c) => (
                      <option key={c.id} value={c.id}>{c.code}</option>
                    ))}
                  </select>
                </div>
              </div>
              <div>
                <label htmlFor="tg-spend-date">{t('spend.date')}</label>
                <input
                  id="tg-spend-date"
                  type="date"
                  value={spendDate}
                  onChange={(e) => {
                    const v = e.target.value;
                    setSpendDate(v);
                    if (v) {
                      refreshPlansForDate(v).catch(() => {});
                    }
                  }}
                  required
                />
              </div>
              <div className="actions actions--full">
                <button type="submit" className="btn btn-primary" disabled={submitting}>
                  {submitting ? t('telegram.spendJsAdding') : t('spend.submit')}
                </button>
              </div>
            </form>
          </section>
        ) : null}

        {tab === 'spends' && spendList ? (
          <section className="tg-mini-section" id="mini-spends">
            <h2>{t('list.spendsHeading', { month: spendList.monthLabel })}</h2>
            <nav className="sp-tabs" aria-label={t('list.monthTabs')}>
              {(spendList.monthTabs || []).map((mt) => {
                const next = new URLSearchParams(searchParams);
                next.set('tab', 'spends');
                next.set('month', mt.monthKey);
                next.set('page', '1');
                next.set('view', viewMode);
                return (
                  <Link
                    key={mt.monthKey}
                    className={`sp-tab ${mt.active ? 'is-active' : ''}`}
                    to={`/spend?${next.toString()}`}
                  >
                    {mt.label}
                  </Link>
                );
              })}
            </nav>

            {spendList.spends?.length === 0 && (!spendList.streamGroups || spendList.streamGroups.length === 0) ? (
              <p><strong>{t('list.spendsEmpty')}</strong></p>
            ) : null}

            {viewMode === 'stream' ? (
              <div className="tg-mini-stream-wrap">
                <section className="spend-stream">
                  {(spendList.streamGroups || []).map((group) => (
                    <details key={group.id} className="spend-stream-group" open={group.expanded}>
                      <summary className="spend-stream-summary">
                        <div className="spend-stream-summary-inner">
                          <span className="spend-stream-title">
                            {group.name}
                            {group.current ? (
                              <span className="badge">{t('telegram.streamCurrent')}</span>
                            ) : null}
                          </span>
                          <span className="spend-stream-meta">
                            <span>
                              {t('telegram.streamSpent')}
                              :
                              <strong>{group.totalAmountLabel}</strong>
                            </span>
                            <span>
                              {t('telegram.streamPlanned')}
                              :
                              <strong>{group.plannedAmountLabel}</strong>
                            </span>
                          </span>
                        </div>
                        <span className="spend-stream-chevron" aria-hidden="true" />
                      </summary>
                      <div className="spend-stream-body">
                        {group.spends?.length === 0 ? (
                          <p className="spend-stream-empty">{t('telegram.streamEmpty')}</p>
                        ) : (
                          group.spends.map((spend) => (
                            <article key={spend.id} className="spend-stream-item">
                              <div className="spend-stream-item-inner">
                                <div className="spend-stream-item-line">
                                  <span className="spend-stream-item-field is-amount">
                                    {spend.amount}
                                    {' '}
                                    {spend.currencyCode}
                                  </span>
                                  <span className="spend-stream-item-field is-comment">{spend.comment || '—'}</span>
                                  <span className="spend-stream-item-field is-datetime">{spend.createdAtLabel}</span>
                                  <span className="spend-stream-item-field is-author">{spend.username}</span>
                                </div>
                                <div className="spend-stream-item-actions row-actions">
                                  <Link className="row-action-btn row-action-btn--edit" to={`/spends/${spend.id}/edit`}>✎</Link>
                                  <button
                                    type="button"
                                    className="row-action-btn row-action-btn--delete"
                                    onClick={() => deleteSpend(spend.id)}
                                  >
                                    ✕
                                  </button>
                                </div>
                              </div>
                            </article>
                          ))
                        )}
                      </div>
                    </details>
                  ))}
                </section>
              </div>
            ) : (
              <>
                <div className="table-wrap dash-recent-table">
                  <table>
                    <thead>
                      <tr>
                        <th>{t('list.spendDate')}</th>
                        <th>{t('dashboard.table.user')}</th>
                        <th>{t('dashboard.table.amount')}</th>
                        <th>{t('dashboard.table.description')}</th>
                        <th>{t('list.actions')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(spendList.spends || []).map((spend) => (
                        <tr key={spend.id}>
                          <td>{spend.spendDateLabel}</td>
                          <td>{spend.username}</td>
                          <td>{spend.amount} {spend.currencyCode}</td>
                          <td>{spend.comment || '—'}</td>
                          <td className="table-row-actions-cell">
                            <div className="row-actions">
                              <Link className="row-action-btn row-action-btn--edit" to={`/spends/${spend.id}/edit`}>✎</Link>
                              <button
                                type="button"
                                className="row-action-btn row-action-btn--delete"
                                onClick={() => deleteSpend(spend.id)}
                              >
                                ✕
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                {spendList.totalPages > 1 ? (
                  <nav className="tg-mini-pagination" aria-label={t('list.monthTabs')}>
                    <Button
                      type="button"
                      variant="ghost"
                      disabled={spendList.page <= 1}
                      onClick={() => {
                        const next = new URLSearchParams(searchParams);
                        next.set('tab', 'spends');
                        next.set('page', String(spendList.page - 1));
                        setSearchParams(next, { replace: true });
                      }}
                    >
                      «
                    </Button>
                    {Array.from({ length: spendList.totalPages }, (_, i) => i + 1).map((pageNo) => {
                      const next = new URLSearchParams(searchParams);
                      next.set('tab', 'spends');
                      next.set('page', String(pageNo));
                      return (
                        <Link
                          key={pageNo}
                          className={`btn btn-ghost ${pageNo === spendList.page ? 'is-active' : ''}`}
                          to={`/spend?${next.toString()}`}
                        >
                          {pageNo}
                        </Link>
                      );
                    })}
                    <Button
                      type="button"
                      variant="ghost"
                      disabled={spendList.page >= spendList.totalPages}
                      onClick={() => {
                        const next = new URLSearchParams(searchParams);
                        next.set('tab', 'spends');
                        next.set('page', String(spendList.page + 1));
                        setSearchParams(next, { replace: true });
                      }}
                    >
                      »
                    </Button>
                  </nav>
                ) : null}
              </>
            )}
          </section>
        ) : null}
      </main>
    </div>
  );
}
