import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { Empty, Field, PageHeader, Panel, useApi } from '../components.jsx';

export default function Settings() {
  return (
    <>
      <PageHeader title="Settings" subtitle="Prediction rules, sales team and products" />
      <ForecastSettings />
      <div className="grid-2">
        <Reps />
        <ProductsLink />
      </div>
    </>
  );
}

function ForecastSettings() {
  const { data, reload } = useApi(() => api.get('/settings'), []);
  const [form, setForm] = useState(null);
  const [errors, setErrors] = useState({});
  const [saved, setSaved] = useState(false);
  const values = form ?? data;
  if (!values) return null;

  const set = (k, cast = Number) => (e) => {
    setSaved(false);
    setForm({ ...values, [k]: e.target.type === 'checkbox' ? e.target.checked : cast(e.target.value) });
  };

  async function submit(e) {
    e.preventDefault();
    try {
      await api.put('/settings', values);
      setErrors({});
      setForm(null);
      setSaved(true);
      reload();
    } catch (err) {
      setErrors(err.errors || { frequent_max_days: err.message });
    }
  }

  return (
    <Panel title="Prediction rules">
      <form onSubmit={submit} className="form-grid settings-grid">
        <Field label="Frequent buyer: orders at least every … days" error={errors.frequent_max_days} hint="Average days between orders at or below this = Frequent.">
          <input type="number" min="1" max="90" value={values.frequent_max_days} onChange={set("frequent_max_days")} />
        </Field>
        <Field label="Moderate buyer: orders at least every … days" error={errors.moderate_max_days} hint="Above the frequent limit but at or below this = Moderate. Longer = Occasional.">
          <input type="number" min="1" max="365" value={values.moderate_max_days} onChange={set("moderate_max_days")} />
        </Field>
        <Field label="Follow-up after a first order (days)" error={errors.first_order_follow_up_days} hint="A reminder is added this many days after a customer’s first order of a product. It closes itself when they order again.">
          <input type="number" min="1" max="60" value={values.first_order_follow_up_days} onChange={set("first_order_follow_up_days")} />
        </Field>
        <label className="field checkbox">
          <span><input type="checkbox" checked={values.recent_weighting} onChange={set("recent_weighting")} /> Give more weight to recent orders</span>
          <span className="field-hint">Useful when order sizes or timing change a lot.</span>
        </label>
        <Field label="Weight half-life (days)" error={errors.weighting_half_life_days} hint="An order gap this old counts half as much as a current one.">
          <input type="number" min="1" max="365" value={values.weighting_half_life_days} onChange={set("weighting_half_life_days")} disabled={!values.recent_weighting} />
        </Field>
        <div className="form-actions span-2">
          {saved && <span className="muted">Saved. Predictions use the new rules now.</span>}
          <button className="btn btn-primary" disabled={!form}>Save rules</button>
        </div>
      </form>
    </Panel>
  );
}
function Reps() {
  const { data, reload } = useApi(() => api.get('/sales-reps'), []);
  const [form, setForm] = useState({ name: '', phone: '', email: '' });
  const [errors, setErrors] = useState({});
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    try {
      await api.post('/sales-reps', form);
      setForm({ name: '', phone: '', email: '' });
      setErrors({});
      reload();
    } catch (err) {
      setErrors(err.errors || { name: err.message });
    }
  }

  async function remove(rep) {
    if (!window.confirm(`Remove ${rep.name}? Their stores become unassigned.`)) return;
    await api.del(`/sales-reps/${rep.id}`);
    reload();
  }

  return (
    <Panel title="Sales representatives" flush>
      {data && data.length === 0 && <Empty>No representatives yet.</Empty>}
      {data && data.length > 0 && (
        <table className="table">
          <thead><tr><th>Name</th><th>Phone</th><th className="num">Stores</th><th /></tr></thead>
          <tbody>
            {data.map((r) => (
              <tr key={r.id}>
                <td>{r.name}<div className="muted small">{r.email}</div></td>
                <td>{r.phone || '-'}</td>
                <td className="num">{r.stores_count}</td>
                <td className="right"><button className="btn btn-ghost btn-small" onClick={() => remove(r)}>Remove</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <form onSubmit={submit} className="form-grid panel-body bordered-top">
        <Field label="Name" error={errors.name}><input value={form.name} onChange={set('name')} required /></Field>
        <Field label="Phone" error={errors.phone}><input value={form.phone} onChange={set('phone')} /></Field>
        <Field label="Email" error={errors.email}><input type="email" value={form.email} onChange={set('email')} /></Field>
        <div className="form-actions"><button className="btn btn-primary">Add representative</button></div>
      </form>
    </Panel>
  );
}

function ProductsLink() {
  return (
    <Panel title="Products">
      <p className="muted">Products, prices and pictures are on their own page.</p>
      <Link to="/products" className="btn">Open Products</Link>
    </Panel>
  );
}
