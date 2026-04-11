import { useCallback, useEffect, useMemo, useState } from 'react';
import { createIncomeSchema, createSpendSchema } from '../schemas/authSchema';
import { buildTokenHeaders } from './useAuth';

const EMPTY_SPEND_FORM = {
  amount: '',
  currencyId: '',
  spendingPlanId: '',
  spendDate: '',
  comment: '',
};

const EMPTY_INCOME_FORM = {
  amount: '',
  currencyId: '',
  comment: '',
  convertToGel: true,
};

function getValidationMessage(result, fallbackMessage) {
  return result.error?.issues?.[0]?.message || fallbackMessage;
}

export function useDashboard({ config, token, onUnauthorized, t }) {
  const effectiveConfig = useMemo(() => ({
    apiDashboardPath: config.apiDashboardPath || '/api/dashboard',
    apiCreateSpendPath: config.apiCreateSpendPath || '/api/dashboard/spends',
    apiCreateIncomePath: config.apiCreateIncomePath || '/api/dashboard/incomes',
  }), [config]);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [payload, setPayload] = useState(null);
  const [spendForm, setSpendForm] = useState(EMPTY_SPEND_FORM);
  const [incomeForm, setIncomeForm] = useState(EMPTY_INCOME_FORM);
  const [status, setStatus] = useState({ type: '', message: '' });
  const [submittingSpend, setSubmittingSpend] = useState(false);
  const [submittingIncome, setSubmittingIncome] = useState(false);
  const spendSchema = useMemo(() => createSpendSchema(t), [t]);
  const incomeSchema = useMemo(() => createIncomeSchema(t), [t]);

  const applyPayload = useCallback((nextPayload) => {
    setPayload(nextPayload);

    const spendDefaults = nextPayload?.forms?.spend?.defaults || {};
    setSpendForm({
      amount: String(spendDefaults.amount || ''),
      currencyId: String(spendDefaults.currencyId || ''),
      spendingPlanId: String(spendDefaults.spendingPlanId || ''),
      spendDate: String(spendDefaults.spendDate || ''),
      comment: String(spendDefaults.comment || ''),
    });

    const incomeDefaults = nextPayload?.forms?.income?.defaults || null;
    if (incomeDefaults) {
      setIncomeForm({
        amount: String(incomeDefaults.amount || ''),
        currencyId: String(incomeDefaults.currencyId || ''),
        comment: String(incomeDefaults.comment || ''),
        convertToGel: Boolean(incomeDefaults.convertToGel),
      });
    }
  }, []);

  const loadDashboard = useCallback(async () => {
    if (!token) {
      setLoading(false);
      return;
    }

    setLoading(true);
    setError('');

    const response = await fetch(effectiveConfig.apiDashboardPath, {
      method: 'GET',
      headers: {
        ...buildTokenHeaders(token),
      },
    });

    const data = await response.json().catch(() => ({}));
    if (response.status === 401) {
      onUnauthorized();
      return;
    }

    if (!response.ok || !data.success || !data.payload) {
      setError(data.error || t('status.dashboard.loadError'));
      setLoading(false);
      return;
    }

    applyPayload(data.payload);
    setLoading(false);
  }, [token, effectiveConfig.apiDashboardPath, onUnauthorized, applyPayload, t]);

  useEffect(() => {
    loadDashboard().catch(() => {
      setError(t('status.dashboard.loadError'));
      setLoading(false);
    });
  }, [loadDashboard, t]);

  const submitSpend = useCallback(async (event) => {
    event.preventDefault();
    setStatus({ type: '', message: '' });

    const validation = spendSchema.safeParse(spendForm);
    if (!validation.success) {
      setStatus({
        type: 'error',
        message: getValidationMessage(validation, t('validation.spend.required')),
      });
      return;
    }

    setSubmittingSpend(true);

    const response = await fetch(effectiveConfig.apiCreateSpendPath, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...buildTokenHeaders(token),
      },
      body: JSON.stringify({
        amount: spendForm.amount,
        currencyId: Number(spendForm.currencyId),
        spendingPlanId: Number(spendForm.spendingPlanId),
        spendDate: spendForm.spendDate,
        comment: spendForm.comment,
      }),
    });

    const data = await response.json().catch(() => ({}));
    if (response.status === 401) {
      onUnauthorized();
      setSubmittingSpend(false);
      return;
    }

    if (!response.ok || !data.success) {
      setStatus({
        type: 'error',
        message: data.error || t('status.spend.addError'),
      });
      setSubmittingSpend(false);
      return;
    }

    if (data.payload) {
      applyPayload(data.payload);
    } else {
      await loadDashboard();
    }

    setStatus({
      type: 'success',
      message: data.message || t('status.spend.addSuccess'),
    });
    setSubmittingSpend(false);
  }, [spendForm, effectiveConfig.apiCreateSpendPath, token, onUnauthorized, applyPayload, loadDashboard, spendSchema, t]);

  const submitIncome = useCallback(async (event) => {
    event.preventDefault();
    setStatus({ type: '', message: '' });

    const validation = incomeSchema.safeParse(incomeForm);
    if (!validation.success) {
      setStatus({
        type: 'error',
        message: getValidationMessage(validation, t('validation.income.required')),
      });
      return;
    }

    setSubmittingIncome(true);

    const response = await fetch(effectiveConfig.apiCreateIncomePath, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...buildTokenHeaders(token),
      },
      body: JSON.stringify({
        amount: incomeForm.amount,
        currencyId: Number(incomeForm.currencyId),
        comment: incomeForm.comment,
        convertToGel: incomeForm.convertToGel,
      }),
    });

    const data = await response.json().catch(() => ({}));
    if (response.status === 401) {
      onUnauthorized();
      setSubmittingIncome(false);
      return;
    }

    if (!response.ok || !data.success) {
      setStatus({
        type: 'error',
        message: data.error || t('status.income.addError'),
      });
      setSubmittingIncome(false);
      return;
    }

    if (data.payload) {
      applyPayload(data.payload);
    } else {
      await loadDashboard();
    }

    setStatus({
      type: 'success',
      message: data.message || t('status.income.addSuccess'),
    });
    setSubmittingIncome(false);
  }, [incomeForm, effectiveConfig.apiCreateIncomePath, token, onUnauthorized, applyPayload, loadDashboard, incomeSchema, t]);

  return {
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
  };
}
