import { useState } from 'react';
import { api } from '../api.js';
import { Field, Modal, SalePreview, useApi } from '../components.jsx';
import { today } from '../format.js';

/**
 * Record a sale, a stock count or a delivered order for any store without
 * opening its page first. Saving recalculates the store's follow-up.
 */
export default function QuickRecordModal({ kind, onClose, onSaved }) {
  const { data: stores } = useApi(() => api.get('/stores?sort=name'), []);
  const { data: products } = useApi(() => api.get('/products'), []);
  const [mode, setMode] = useState(kind === 'sale' ? 'sale' : kind === 'stock' ? 'count' : 'order');
  const [form, setForm] = useState({ store_id: '', product_id: '', date: today(), quantity: '', days_covered: 1 });
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  const title = { sale: 'Record sale', count: 'Record order', order: 'Record order' }[mode];

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    const base = { product_id: form.product_id, quantity: form.quantity };
    const [path, body] = {
      sale: ['sales', { ...base, sale_date: form.date, days_covered: form.days_covered }],
      count: ['stock-counts', { ...base, counted_at: form.date }],
      order: ['orders', { ...base, delivered_at: form.date }],
    }[mode];
    try {
      await api.post(`/stores/${form.store_id}/${path}`, body);
      onSaved(form.store_id);
    } catch (err) {
      setErrors(err.errors || { quantity: err.message });
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal title={title} onClose={onClose}>
      {mode !== 'sale' && (
        <div className="tabs tabs-block">
          <button type="button" className={mode === 'order' ? 'tab active' : 'tab'} onClick={() => setMode('order')}>Order</button>
          <button type="button" className={mode === 'count' ? 'tab active' : 'tab'} onClick={() => setMode('count')}>Stock count</button>
        </div>
      )}
      <form onSubmit={submit} className="form-grid">
        <Field label="Customer" error={errors.store_id}>
          <select value={form.store_id} onChange={set('store_id')} required>
            <option value="">Select customer</option>
            {(stores || []).map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
        </Field>
        <Field label="Product" error={errors.product_id}>
          <select value={form.product_id} onChange={set('product_id')} required>
            <option value="">Select product</option>
            {(products || []).map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
        </Field>
        <Field label={{ sale: 'Sale date', count: 'Count date', order: 'Order date' }[mode]} error={errors.sale_date || errors.counted_at || errors.delivered_at}>
          <input type="date" value={form.date} max={today()} onChange={set('date')} required />
        </Field>
        <Field label={{ sale: 'Packs sold', count: 'Packs on hand', order: 'Packs ordered' }[mode]} error={errors.quantity}>
          <input type="number" min="0" step="0.01" value={form.quantity} onChange={set('quantity')} required />
        </Field>
        {mode === 'sale' && (
          <Field label="Selling days" error={errors.days_covered} hint="How many days these packs were sold over, ending on the sale date.">
            <input type="number" min="1" step="1" value={form.days_covered} onChange={set('days_covered')} required />
          </Field>
        )}
        {mode === 'sale' && (
          <SalePreview
            quantity={form.quantity}
            days={form.days_covered}
            unitPrice={(products || []).find((p) => String(p.id) === String(form.product_id))?.price ?? ''}
          />
        )}
        <p className="hint span-2">
          {mode === 'count' && 'For your records only; predictions come from orders.'}
          {mode === 'order' && 'Updates the customer’s prediction: daily consumption, how long this order lasts, and their next purchase date.'}
          {mode === 'sale' && 'Used for sales performance.'}
        </p>
        <div className="form-actions span-2">
          <button type="button" className="btn" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
        </div>
      </form>
    </Modal>
  );
}
