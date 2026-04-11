import { useEffect, useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { AppHeader } from '../components/layout/AppHeader';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { useAuth } from '../hooks/useAuth';
import { useI18n } from '../hooks/useI18n';
import { createLoginSchema } from '../schemas/authSchema';

export function LoginPage({ config }) {
  const [requestError, setRequestError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  const { t } = useI18n();
  const effectiveConfig = useMemo(() => ({
    apiLoginPath: config.apiLoginPath || '/api/login',
    apiLoginStubPath: config.apiLoginStubPath || '/api/login/stub',
    dashboardPath: config.dashboardPath || '/app/dashboard',
    loginPath: config.loginPath || '/app/login',
  }), [config]);

  const { login, validateToken } = useAuth(effectiveConfig);
  const loginSchema = useMemo(() => createLoginSchema(t), [t]);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      username: '',
      password: '',
    },
  });

  useEffect(() => {
    validateToken()
      .then((result) => {
        if (result.valid) {
          window.location.assign(effectiveConfig.dashboardPath);
        }
      })
      .catch(() => {
        // Ignore validation failures here and keep user on login page.
      });
  }, [validateToken, effectiveConfig.dashboardPath]);

  const onSubmit = async (payload) => {
    setRequestError('');
    setSuccessMessage('');

    const result = await login(payload, t('auth.failed'));
    if (!result.success) {
      setRequestError(result.message || t('auth.failed'));
      return;
    }

    setSuccessMessage(t('login.successRedirect', { identifier: result.identifier }));
    window.setTimeout(() => {
      window.location.assign(effectiveConfig.dashboardPath);
    }, 350);
  };

  return (
    <div className="react-portal">
      <AppHeader />
      <div className="login-page">
        <main className="login-card card border-0 shadow-sm">
          <div className="card-body p-4 p-md-5">
            <span className="badge rounded-pill text-bg-success-subtle text-success-emphasis mb-3 sp-login-kicker">
              {t('login.kicker')}
            </span>
            <h1 className="display-5 fw-semibold mb-2">{t('login.title')}</h1>
            <p className="text-secondary fs-5 mb-4">{t('login.subtitle')}</p>

            <div className="sp-status-slot mb-4" aria-live="polite">
              <Alert message={requestError} variant="danger" />
              {!requestError ? <Alert message={successMessage} variant="success" /> : null}
              {!requestError && !successMessage ? <div className="sp-status-placeholder" aria-hidden="true" /> : null}
            </div>

            <form className="sp-login-form" onSubmit={handleSubmit(onSubmit)} noValidate>
              <Input
                id="username"
                label={t('login.username')}
                autoComplete="username"
                error={errors.username?.message || ''}
                feedbackPlaceholder={t('login.username')}
                {...register('username')}
              />

              <Input
                id="password"
                label={t('login.password')}
                type={showPassword ? 'text' : 'password'}
                autoComplete="current-password"
                error={errors.password?.message || ''}
                feedbackPlaceholder={t('login.password')}
                rightSlot={(
                  <Button
                    type="button"
                    variant="outlineSecondary"
                    className="sp-password-toggle"
                    onClick={() => setShowPassword((previous) => !previous)}
                  >
                    {showPassword ? t('login.hide') : t('login.show')}
                  </Button>
                )}
                {...register('password')}
              />

              <Button className="w-100 sp-submit-btn" variant="success" size="lg" type="submit" disabled={isSubmitting}>
                {isSubmitting ? t('login.submitPending') : t('login.submit')}
              </Button>
            </form>
          </div>
        </main>
      </div>
    </div>
  );
}
