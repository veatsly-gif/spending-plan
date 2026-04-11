import { forwardRef } from 'react';

function buildClassName(parts) {
  return parts.filter(Boolean).join(' ');
}

export const Input = forwardRef(function Input(
  {
    id,
    label,
    type = 'text',
    size = 'lg',
    error = '',
    feedbackPlaceholder = '',
    rightSlot = null,
    className = '',
    wrapperClassName = 'mb-3',
    ...props
  },
  ref
) {
  const hasError = Boolean(error);
  const controlClassName = buildClassName([
    'form-control',
    size === 'lg' ? 'form-control-lg' : '',
    hasError ? 'is-invalid' : '',
    className,
  ]);

  const inputElement = (
    <input
      id={id}
      ref={ref}
      type={type}
      aria-invalid={hasError ? 'true' : 'false'}
      className={controlClassName}
      {...props}
    />
  );

  return (
    <div className={wrapperClassName}>
      <label className="form-label fw-semibold text-secondary" htmlFor={id}>
        {label}
      </label>
      {rightSlot ? (
        <div className={`input-group ${size === 'lg' ? 'input-group-lg' : ''}`}>
          {inputElement}
          {rightSlot}
        </div>
      ) : (
        inputElement
      )}
      <div className={`invalid-feedback d-block sp-invalid-feedback ${hasError ? 'is-visible' : ''}`}>
        {error || feedbackPlaceholder}
      </div>
    </div>
  );
});
