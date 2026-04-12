import { useCallback, useEffect, useMemo } from 'react';
import { AppHeader } from '../components/layout/AppHeader';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { useAuth } from '../hooks/useAuth';
import { useDashboard } from '../hooks/useDashboard';
import { useI18n } from '../hooks/useI18n';

export function DashboardPage({ config }) {
  const { t } = useI18n();
  const effectiveConfig = useMemo(() => ({
    loginPath: config.loginPath || '/app/login',
    apiDashboardPath: config.apiDashboardPath || '/api/dashboard',
    apiCreateSpendPath: config.apiCreateSpendPath || '/api/dashboard/spends',
    apiCreateIncomePath: config.apiCreateIncomePath || '/api/dashboard/incomes',
    spendsPath: config.spendsPath || '/app/dashboard/spends',
    incomesPath: config.incomesPath || '/app/dashboard/incomes',
  }), [config]);

  const { token, logout } = useAuth(effectiveConfig);
  const handleUnauthorized = useCallback(() => {
    logout(effectiveConfig.loginPath);
  }, [logout, effectiveConfig.loginPath]);

  const {
    loading,
    error,
    payload,
    status,
    spendForm,
    incomeForm,
    submittingSpend,
    submittingIncome,
    setSpendForm,
    setIncomeForm,
    submitSpend,
    submitIncome,
    loadDashboard,
  } = useDashboard({
    config: effectiveConfig,
    token,
    onUnauthorized: handleUnauthorized,
    t,
  });

  useEffect(() => {
    if (!token) {
      window.location.assign(effectiveConfig.loginPath);
    }
  }, [token, effectiveConfig.loginPath]);

  if (!token || loading) {
    return (
      <div className="react-portal">
        <AppHeader onLogout={token ? () => logout(effectiveConfig.loginPath) : null} />
        <main className="panel reveal">
          <span className="kicker">{t('dashboard.section')}</span>
          <h1>{t('dashboard.loadingTitle')}</h1>
          <p>{t('dashboard.loadingText')}</p>
        </main>
      </div>
    );
  }

  if (error || !payload) {
    return (
      <div className="react-portal">
        <AppHeader onLogout={() => logout(effectiveConfig.loginPath)} />
        <main className="panel reveal">
          <span className="kicker">{t('dashboard.section')}</span>
          <h1>{t('dashboard.unavailableTitle')}</h1>
          <p>{error || t('dashboard.unavailableText')}</p>
          <div className="actions" style={{ marginTop: 8 }}>
            <Button type="button" onClick={() => loadDashboard()}>{t('dashboard.retry')}</Button>
            <Button type="button" variant="ghost" onClick={() => logout(effectiveConfig.loginPath)}>{t('header.logout')}</Button>
          </div>
        </main>
      </div>
    );
  }

  const spendWidget = payload.spendWidget || {};
  const incomeWidget = payload.incomeWidget || {};
  const spendFormOptions = payload.forms?.spend || {};
  const incomeFormOptions = payload.forms?.income || null;

  return (
    <div className="react-portal">
      <AppHeader onLogout={() => logout(effectiveConfig.loginPath)} />

      <Alert
        message={status.message}
        variant={status.type === 'error' ? 'danger' : 'success'}
        className={status.type === 'error' ? 'form-status form-status-error' : 'form-status form-status-success'}
      />

      <main className="panel reveal">
        <div className="dash-top-row">
          <span className="kicker">{t('dashboard.overview')}</span>
          <div className="dash-rates-row reveal delay">
            <span className="dash-rate-pill">EUR/GEL: <strong>{incomeWidget.eurGelRate || 'n/a'}</strong></span>
            <span className="dash-rate-pill">USDT/GEL: <strong>{incomeWidget.usdtGelRate || 'n/a'}</strong></span>
          </div>
        </div>

        <section className="grid dashboard-double-grid">
          <article className="metric reveal delay spend-widget dashboard-main-card">
            <h3>{t('dashboard.spendsFor', { monthLabel: spendWidget.monthLabel })}</h3>
            <div className="dash-progress-head">
              <div>
                <p className="subtle">{t('dashboard.monthSpent')}</p>
                <p className="dash-big">{spendWidget.monthSpentGel} GEL</p>
              </div>
              <div className="dash-progress-side">
                <p className="subtle">{t('dashboard.monthLimit')}</p>
                <p className="dash-big">{spendWidget.monthLimitGel} GEL</p>
              </div>
            </div>
            <div className="dash-progress-track">
              <span
                className={`dash-progress-fill is-${spendWidget.monthSpendProgressTone || 'ok'}`}
                style={{ width: `${Number(spendWidget.monthSpendProgressBarPercent || 0)}%` }}
              />
            </div>
            <p className="subtle">
              {t('dashboard.progressToLimit')} <strong>{spendWidget.monthSpendProgressPercent}%</strong>
            </p>

            <div className="dash-today-row">
              <span>{t('dashboard.spendsToday')}</span>
              <strong>{spendWidget.todaySpentGel} GEL</strong>
            </div>

            <div className="table-wrap dash-recent-table">
              <table>
                <thead>
                  <tr>
                    <th>{t('dashboard.table.amount')}</th>
                    <th>{t('dashboard.table.datetime')}</th>
                    <th>{t('dashboard.table.user')}</th>
                    <th>{t('dashboard.table.description')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(spendWidget.recentSpends || []).length > 0 ? (
                    spendWidget.recentSpends.map((spend) => (
                      <tr key={spend.id}>
                        <td>{spend.amount} {spend.currencyCode}</td>
                        <td>{spend.createdAtLabel}</td>
                        <td>{spend.username}</td>
                        <td>{spend.comment || 'n/a'}</td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan="4">{t('dashboard.noSpends')}</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            <div className="actions" style={{ marginTop: 8 }}>
              <Button href={effectiveConfig.spendsPath} variant="ghost">{t('dashboard.openSpends')}</Button>
            </div>
          </article>

          <article className="metric reveal delay dashboard-main-card">
            <h3>{t('dashboard.incomeFor', { monthLabel: incomeWidget.monthLabel })}</h3>
            <div className="dash-income-row">
              <span>{t('dashboard.totalIncomeGel')}</span>
              <strong>{incomeWidget.totalIncomeGel} GEL</strong>
            </div>
            <div className="dash-income-row">
              <span>{t('dashboard.availableToSpendGel')}</span>
              <strong>{incomeWidget.availableToSpendGel} GEL</strong>
            </div>
            <p className="subtle">{t('dashboard.regularPlannedTotal')} {incomeWidget.regularAndPlannedGel} GEL</p>
            <p className="subtle">{t('dashboard.updated')} {incomeWidget.ratesUpdatedAtLabel || 'n/a'}</p>
            <div className="actions" style={{ marginTop: 8 }}>
              <Button href={effectiveConfig.incomesPath} variant="ghost">{t('dashboard.openIncomes')}</Button>
            </div>
          </article>
        </section>
      </main>

      <section className="panel reveal delay" style={{ marginTop: 16 }}>
        <span className="kicker">{t('spend.kicker')}</span>
        <h2>{t('spend.title')}</h2>
        <form onSubmit={submitSpend} className="react-form-grid" noValidate>
          <div>
            <label htmlFor="spend-amount">{t('spend.amount')}</label>
            <input
              id="spend-amount"
              type="number"
              step="0.01"
              min="0"
              value={spendForm.amount}
              onChange={(event) => setSpendForm((previous) => ({ ...previous, amount: event.target.value }))}
            />
          </div>

          <div>
            <label htmlFor="spend-currency">{t('spend.currency')}</label>
            <select
              id="spend-currency"
              value={spendForm.currencyId}
              onChange={(event) => setSpendForm((previous) => ({ ...previous, currencyId: event.target.value }))}
            >
              <option value="">{t('spend.chooseCurrency')}</option>
              {(spendFormOptions.currencies || []).map((currency) => (
                <option key={currency.id} value={currency.id}>{currency.code}</option>
              ))}
            </select>
          </div>

          <div>
            <label htmlFor="spend-plan">{t('spend.plan')}</label>
            <select
              id="spend-plan"
              value={spendForm.spendingPlanId}
              onChange={(event) => setSpendForm((previous) => ({ ...previous, spendingPlanId: event.target.value }))}
            >
              <option value="">{t('spend.choosePlan')}</option>
              {(spendFormOptions.spendingPlans || []).map((plan) => (
                <option key={plan.id} value={plan.id}>
                  {plan.name} ({plan.dateFrom} - {plan.dateTo})
                </option>
              ))}
            </select>
          </div>

          <div>
            <label htmlFor="spend-date">{t('spend.date')}</label>
            <input
              id="spend-date"
              type="date"
              value={spendForm.spendDate}
              onChange={(event) => setSpendForm((previous) => ({ ...previous, spendDate: event.target.value }))}
            />
          </div>

          <div>
            <label htmlFor="spend-comment">{t('spend.comment')}</label>
            <textarea
              id="spend-comment"
              rows="2"
              value={spendForm.comment}
              onChange={(event) => setSpendForm((previous) => ({ ...previous, comment: event.target.value }))}
            />
          </div>

          <div className="actions">
            <Button type="submit" disabled={submittingSpend}>
              {submittingSpend ? t('spend.submitPending') : t('spend.submit')}
            </Button>
          </div>
        </form>
      </section>

      {incomeFormOptions ? (
        <section className="panel reveal delay" style={{ marginTop: 16 }}>
          <span className="kicker">{t('income.kicker')}</span>
          <h2>{t('income.title')}</h2>
          <form onSubmit={submitIncome} className="react-form-grid" noValidate>
            <div>
              <label htmlFor="income-amount">{t('income.amount')}</label>
              <input
                id="income-amount"
                type="number"
                step="0.01"
                min="0"
                value={incomeForm.amount}
                onChange={(event) => setIncomeForm((previous) => ({ ...previous, amount: event.target.value }))}
              />
            </div>

            <div>
              <label htmlFor="income-currency">{t('income.currency')}</label>
              <select
                id="income-currency"
                value={incomeForm.currencyId}
                onChange={(event) => setIncomeForm((previous) => ({ ...previous, currencyId: event.target.value }))}
              >
                <option value="">{t('income.chooseCurrency')}</option>
                {(incomeFormOptions.currencies || []).map((currency) => (
                  <option key={currency.id} value={currency.id}>{currency.code}</option>
                ))}
              </select>
            </div>

            <div>
              <label htmlFor="income-comment">{t('income.comment')}</label>
              <textarea
                id="income-comment"
                rows="2"
                value={incomeForm.comment}
                onChange={(event) => setIncomeForm((previous) => ({ ...previous, comment: event.target.value }))}
              />
            </div>

            <div className="react-checkbox-row">
              <input
                id="income-convert"
                type="checkbox"
                checked={incomeForm.convertToGel}
                onChange={(event) => setIncomeForm((previous) => ({ ...previous, convertToGel: event.target.checked }))}
              />
              <label htmlFor="income-convert">{t('income.convertToGel')}</label>
            </div>

            <div className="actions">
              <Button type="submit" disabled={submittingIncome}>
                {submittingIncome ? t('income.submitPending') : t('income.submit')}
              </Button>
            </div>
          </form>
        </section>
      ) : null}
    </div>
  );
}
