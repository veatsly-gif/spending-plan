function buildClassName(parts) {
  return parts.filter(Boolean).join(' ');
}

export function Alert({
  message = '',
  variant = 'danger',
  className = '',
  role = 'status',
}) {
  if (!message) {
    return null;
  }

  const alertClassName = buildClassName([
    'alert',
    `alert-${variant}`,
    'py-3',
    'mb-0',
    className,
  ]);

  return (
    <div className={alertClassName} role={role}>
      {message}
    </div>
  );
}
