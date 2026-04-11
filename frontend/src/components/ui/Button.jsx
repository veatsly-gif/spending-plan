const VARIANT_CLASS = {
  primary: 'btn-primary',
  success: 'btn-success',
  secondary: 'btn-secondary',
  outlineSecondary: 'btn-outline-secondary',
  ghost: 'btn-ghost',
};

const SIZE_CLASS = {
  sm: 'btn-sm',
  md: '',
  lg: 'btn-lg',
};

function buildClassName(parts) {
  return parts.filter(Boolean).join(' ');
}

export function Button({
  variant = 'primary',
  size = 'md',
  className = '',
  href = '',
  children,
  ...props
}) {
  const buttonClassName = buildClassName([
    'btn',
    VARIANT_CLASS[variant] || VARIANT_CLASS.primary,
    SIZE_CLASS[size] || '',
    className,
  ]);

  if (href) {
    return (
      <a href={href} className={buttonClassName} {...props}>
        {children}
      </a>
    );
  }

  return (
    <button className={buttonClassName} {...props}>
      {children}
    </button>
  );
}
