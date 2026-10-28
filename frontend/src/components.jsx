import { useEffect, useState, useCallback } from 'react';
import { STATUS_GUIDE, money, num, stockStatus } from './format.js';

export function useApi(loader, deps) {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);

  // eslint-disable-next-line react-hooks/exhaustive-deps
  const load = useCallback(loader, deps);

  const reload = useCallback(() => {
    setLoading(true);
    setError(null);
    return load()
      .then(setData)
      .catch(setError)
      .finally(() => setLoading(false));
  }, [load]);

  useEffect(() => {
    reload();
  }, [reload]);

  return { data, error, loading, reload };
}

export function PageHeader({ title, subtitle, children }) {
  return (
    <div className="page-header">
      <div>
        <h1>{title}</h1>
        {subtitle && <p className="muted">{subtitle}</p>}
      </div>
      <div className="actions">{children}</div>
    </div>
  );
}

export function Panel({ title, actions, children, flush }) {
  return (
    <section className="panel">
      {(title || actions) && (
        <header className="panel-head">
          <h2>{title}</h2>
          <div className="actions">{actions}</div>
        </header>
      )}
      <div className={flush ? '' : 'panel-body'}>{children}</div>
    </section>
  );
}

export function Stat({ label, value, note, tone }) {
  return (
    <div className={`stat ${tone || ''}`}>
      <div className="stat-label">{label}</div>
      <div className="stat-value">{value}</div>
      {note && <div className="stat-note">{note}</div>}
    </div>
  );
}

export function Badge({ tone = 'neutral', children }) {
  return <span className={`badge badge-${tone}`}>{children}</span>;
}

export function Loading({ error, loading }) {
  // No HTTP status means the request never reached the server.
  if (error) return <div className="alert alert-error">{error.status ? error.message : `${error.message}. Is the Laravel API running?`}</div>;
  if (loading) return <div className="muted pad">Loading…</div>;
  return null;
}

export function Empty({ children }) {
  return <div className="empty">{children}</div>;
}

export function Modal({ title, onClose, children, wide }) {
  useEffect(() => {
    const onKey = (e) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="modal-backdrop" onMouseDown={onClose}>
      <div className={`modal ${wide ? 'modal-wide' : ''}`} onMouseDown={(e) => e.stopPropagation()}>
        <header className="panel-head">
          <h2>{title}</h2>
          <button className="btn btn-ghost" onClick={onClose} aria-label="Close">✕</button>
        </header>
        <div className="panel-body">{children}</div>
      </div>
    </div>
  );
}

/** Asks before a permanent delete. onConfirm may be async; errors are shown in the dialog. */
export function ConfirmDelete({ title, message, onConfirm, onClose }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function confirm() {
    setBusy(true);
    setError('');
    try {
      await onConfirm();
    } catch (err) {
      setError(err.message);
      setBusy(false);
    }
  }

  return (
    <Modal title={title} onClose={onClose}>
      <p>{message}</p>
      <p className="muted small">This cannot be undone.</p>
      {error && <div className="alert alert-error">{error}</div>}
      <div className="form-actions">
        <button className="btn" onClick={onClose} disabled={busy}>Cancel</button>
        <button className="btn btn-danger" onClick={confirm} disabled={busy}>{busy ? 'Deleting…' : 'Delete'}</button>
      </div>
    </Modal>
  );
}

export function Field({ label, error, children, hint }) {
  return (
    <label className="field">
      <span className="field-label">{label}</span>
      {children}
      {hint && <span className="field-hint">{hint}</span>}
      {error && <span className="field-error">{Array.isArray(error) ? error[0] : error}</span>}
    </label>
  );
}

export function SortHeader({ label, field, sort, dir, onSort }) {
  const active = sort === field;
  return (
    <th className="sortable" onClick={() => onSort(field)}>
      {label} {active ? (dir === 'asc' ? '▲' : '▼') : ''}
    </th>
  );
}

export function UrgencyBadge({ prediction }) {
  const s = stockStatus(prediction);
  return (
    <span className="badges">
      <span className={`tag tag-${s.tone}`}>{s.label}</span>
      {prediction?.basis === 'first_estimate' && (
        <span className="tag tag-neutral" title="Based on only one gap between orders, so it may change">First estimate</span>
      )}
    </span>
  );
}

/** Collapsible plain-language explanation of the status labels and columns. */
export function StatusGuide({ columns }) {
  return (
    <details className="guide">
      <summary>How to read this table</summary>
      <div className="guide-body">
        {columns && (
          <dl className="guide-columns">
            {columns.map(([name, text]) => (
              <div key={name}><dt>{name}</dt><dd>{text}</dd></div>
            ))}
          </dl>
        )}
        <ul className="guide-status">
          {STATUS_GUIDE.map((g) => (
            <li key={g.label}><span className={`tag tag-${g.tone}`}>{g.label}</span> {g.text}</li>
          ))}
          <li><span className="tag tag-neutral">First estimate</span> Based on only two orders, so it may change. It gets better with every order.</li>
        </ul>
        <p className="muted small">
          Predictions come from order history only: each order is assumed used up by the time of the next one.
          They can be off if a customer had leftovers, bought extra, or orders irregularly. They update with every new order.
        </p>
      </div>
    </details>
  );
}
/** Live result of a sale entry: average daily sales and total value, worked out for the user. */
export function SalePreview({ quantity, days, unitPrice }) {
  const packs = Number(quantity);
  const span = Number(days);
  if (quantity === '' || !(span > 0)) return null;
  const price = unitPrice === '' || unitPrice === null || unitPrice === undefined ? null : Number(unitPrice);
  return (
    <p className="calc-preview span-2">
      Average Daily Sales: <strong>{num(packs / span)} packs/day</strong> ({num(packs)} ÷ {num(span, 0)} days)
      {price !== null && <> · Total Sales Value: <strong>{money(packs * price)}</strong> ({num(packs)} × {money(price)})</>}
    </p>
  );
}