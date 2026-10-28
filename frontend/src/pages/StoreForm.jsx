import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { api } from '../api.js';
import { Field, Modal, useApi } from '../components.jsx';
import { CATEGORY_LABELS, STATUS_LABELS, num, today } from '../format.js';

const EMPTY_ROW = { product_id: '', packs_ordered: '', order_date: today() };

const EMPTY = {
  name: '',
  contact_person: '',
  phone: '',
  address: '',
  facebook_url: '',
  messenger_url: '',
  category: 'small_sari_sari',
  status: 'active',
  sales_rep_id: '',
  notes: '',
};

export default function StoreForm({ store, reps, onClose, onSaved }) {
  const [form, setForm] = useState(() => (store ? { ...EMPTY, ...store, sales_rep_id: store.sales_rep_id ?? '' } : EMPTY));
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);

  const [rows, setRows] = useState([{ ...EMPTY_ROW }]);
  const { data: products } = useApi(() => api.get('/products'), []);

  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    const payload = { ...form, sales_rep_id: form.sales_rep_id || null };
    if (!store) {
      payload.products = rows
        .filter((r) => r.product_id)
        .map((r) => Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v === '' ? null : v])));
    }
    try {
      const saved = store ? await api.put(`/stores/${store.id}`, payload) : await api.post('/stores', payload);
      onSaved(saved);
    } catch (err) {
      setErrors(err.errors || { name: err.message });
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal title={store ? 'Edit store' : 'Register store'} onClose={onClose} wide={!store}>
      <form onSubmit={submit} className="form-grid">
        <Field label="Store name" error={errors.name}>
          <input value={form.name} onChange={set('name')} required />
        </Field>
        <Field label="Contact person" error={errors.contact_person}>
          <input value={form.contact_person || ''} onChange={set('contact_person')} />
        </Field>
        <Field label="Phone" error={errors.phone}>
          <input value={form.phone || ''} onChange={set('phone')} placeholder="09XX XXX XXXX" />
        </Field>
        <Field label="Category" error={errors.category}>
          <select value={form.category} onChange={set('category')}>
            {Object.entries(CATEGORY_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </Field>
        <Field label="Address" error={errors.address}>
          <input value={form.address || ''} onChange={set('address')} />
        </Field>
        <Field label="Facebook page or profile" error={errors.facebook_url}>
          <input value={form.facebook_url || ''} onChange={set('facebook_url')} placeholder="https://www.facebook.com/yourstore" />
        </Field>
        <Field
          label="Business Suite chat link"
          error={errors.messenger_url}
          hint="Open this store's conversation in business.facebook.com → Inbox, then copy the address bar. Clicking the store name opens this chat."
        >
          <input value={form.messenger_url || ''} onChange={set('messenger_url')} placeholder="https://business.facebook.com/latest/inbox/all?asset_id=…&selected_item_id=…" />
        </Field>
        <Field label="Customer status" error={errors.status}>
          <select value={form.status} onChange={set('status')}>
            {Object.entries(STATUS_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </Field>
        <Field label="Assigned sales representative" error={errors.sales_rep_id}>
          <select value={form.sales_rep_id} onChange={set('sales_rep_id')}>
            <option value="">Unassigned</option>
            {reps.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
          </select>
        </Field>
        <Field label="Notes" error={errors.notes}>
          <input value={form.notes || ''} onChange={set('notes')} />
        </Field>
        {!store && (
          <StartingProducts rows={rows} setRows={setRows} products={products || []} errors={errors} />
        )}
        <div className="form-actions span-2">
          <button type="button" className="btn" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save store'}</button>
        </div>
      </form>
    </Modal>
  );
}

/**
 * The customer's first order per product, in plain words. It is the starting
 * point of their order history; predictions begin at the second order.
 */
function StartingProducts({ rows, setRows, products, errors }) {
  const update = (i, k) => (e) => setRows(rows.map((r, j) => (j === i ? { ...r, [k]: e.target.value } : r)));
  const rowError = (i) => ['product_id', 'packs_ordered', 'order_date']
    .map((k) => errors[`products.${i}.${k}`]?.[0])
    .find(Boolean);

  return (
    <div className="span-2 starting">
      <div className="starting-head">
        <strong>First order</strong>
        <span className="muted small">Optional. What did they order from you? This starts their order history.</span>
      </div>
      <div className="scroll-x">
        <table className="table table-soft starting-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Packs ordered<span>How many packs they bought</span></th>
              <th>Order date</th>
              <th>What this means</th>
              <th aria-label="Remove" />
            </tr>
          </thead>
          <tbody>
            {rows.map((r, i) => (
              <tr key={i}>
                <td>
                  <select value={r.product_id} onChange={update(i, 'product_id')} aria-label="Product">
                    <option value="">Choose product</option>
                    {products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </select>
                  {rowError(i) && <div className="field-error">{rowError(i)}</div>}
                </td>
                <td><input type="number" min="1" step="1" inputMode="numeric" placeholder="e.g. 12" value={r.packs_ordered} onChange={update(i, 'packs_ordered')} aria-label="Packs ordered" required={r.product_id !== ''} /></td>
                <td><input type="date" value={r.order_date} max={today()} onChange={update(i, 'order_date')} aria-label="Order date" /></td>
                <td className="meaning">
                  {!r.product_id ? <span className="muted">Choose a product first</span>
                    : r.packs_ordered === '' ? <span className="muted">Enter the packs ordered</span>
                      : <>First order of <strong>{num(Number(r.packs_ordered))} packs</strong> saved as the starting point. The prediction starts at their second order.</>}
                </td>
                <td>
                  {rows.length > 1 && (
                    <button type="button" className="icon-btn icon-danger" onClick={() => setRows(rows.filter((_, j) => j !== i))} aria-label="Remove this product">
                      <Trash2 size={16} />
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <button type="button" className="btn btn-small add-row" onClick={() => setRows([...rows, { ...EMPTY_ROW }])}>
        <Plus size={15} /> Add another product
      </button>
    </div>
  );
}