/** A labelled text input with an icon on the left, an optional control on the right, and its error. */
export default function InputField({ id, label, icon: Icon, error, trailing, ...inputProps }) {
  const errorId = `${id}-error`;

  return (
    <div className="lg-field">
      <label htmlFor={id} className="sr-only">{label}</label>
      <div className={`lg-input${error ? ' lg-input-invalid' : ''}`}>
        <Icon className="lg-input-icon" aria-hidden="true" />
        <input id={id} placeholder={label} aria-invalid={Boolean(error)} aria-describedby={error ? errorId : undefined} {...inputProps} />
        {trailing}
      </div>
      {error && <p id={errorId} className="lg-field-error">{error}</p>}
    </div>
  );
}
