import { z } from 'zod';

export function createLoginSchema(t) {
  return z.object({
    username: z.string().trim().min(2, t('validation.login.username')),
    password: z.string().min(1, t('validation.login.password')),
  });
}

export function createSpendSchema(t) {
  const message = t('validation.spend.required');
  return z.object({
    amount: z.string().trim().min(1, message),
    currencyId: z.string().trim().min(1, message),
    spendingPlanId: z.string().trim().min(1, message),
    spendDate: z.string().trim().min(1, message),
    comment: z.string().optional(),
  });
}

export function createIncomeSchema(t) {
  const message = t('validation.income.required');
  return z.object({
    amount: z.string().trim().min(1, message),
    currencyId: z.string().trim().min(1, message),
    comment: z.string().optional(),
    convertToGel: z.boolean(),
  });
}
