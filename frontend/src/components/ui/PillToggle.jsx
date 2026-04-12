import React, { useMemo } from 'react';

/**
 * PillToggle Component
 *
 * Apple-style two-state toggle with sliding pill indicator.
 * Uses Bootstrap button-group styling with custom CSS for the sliding effect.
 *
 * @param {Object} props
 * @param {string} props.name - Unique name for the toggle group
 * @param {Array<{value: string, label: React.ReactNode}>} props.options - Array of exactly 2 options
 * @param {string} props.value - Currently selected value
 * @param {function} props.onChange - Callback when value changes (receives new value)
 * @param {string} props.className - Additional CSS classes
 * @param {string} props.size - Size variant: 'sm' or 'default'
 */
export function PillToggle({ name, options, value, onChange, className = '', size = 'default' }) {
  const uniqueId = useMemo(() => `pill-toggle-${name}-${Math.random().toString(36).slice(2)}`, [name]);

  if (!options || options.length !== 2) {
    console.warn('PillToggle requires exactly 2 options');
    return null;
  }

  const handleOptionClick = (optionValue) => {
    if (optionValue !== value && onChange) {
      onChange(optionValue);
    }
  };

  const sizeClass = size === 'sm' ? 'btn-group-sm' : '';

  return (
    <div
      className={`sp-pill-toggle btn-group ${sizeClass} ${className}`}
      role="group"
      aria-label={name}
    >
      {options.map((option, index) => {
        const isActive = option.value === value;
        const positionClass = index === 0 ? 'first' : index === options.length - 1 ? 'last' : '';

        return (
          <React.Fragment key={option.value}>
            <input
              type="radio"
              className={`btn-check visually-hidden sp-pill-toggle-input ${isActive ? 'active' : ''}`}
              name={uniqueId}
              id={`${uniqueId}-${option.value}`}
              autoComplete="off"
              checked={isActive}
              onChange={() => handleOptionClick(option.value)}
            />
            <label
              className={`btn sp-pill-toggle-label ${isActive ? 'active' : ''} ${positionClass}`}
              htmlFor={`${uniqueId}-${option.value}`}
            >
              {option.label}
            </label>
          </React.Fragment>
        );
      })}
    </div>
  );
}
