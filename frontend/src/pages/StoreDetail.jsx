import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api.js';
import { isSuperAdmin, useUser } from '../auth.js';
import { Badge, Empty, Field, Loading, PageHeader, Panel, StatusGuide, UrgencyBadge, useApi } from '../components.jsx';
import {
  BASIS_LABELS, CATEGORY_LABELS, FOLLOW_UP_TYPE_LABELS, NEXT_ACTION_LABELS, PATTERN_LABELS, PREDICTION_GUIDE, STATUS_LABELS,
  date, dueLabel, money, num, today,
} from '../format.js';
import OutcomeForm from './OutcomeForm.jsx';
import StoreForm from './StoreForm.jsx';

export default function StoreDetail() {
  const { id } = useParams();
  const { data: store, error, loading, reload } = useApi(() => api.get(`/stores/${id}`), [id]);
  const { data: products } = useApi(() => api.get('/products'), []);
  const { data: reps } = useApi(() => api.get('/sales-reps'), []);
  const [editing, setEditing] = useState(false);
  const [completing, setCompleting] = useState(null);
  const admin = isSuperAdmin(useUser());

  if (!store) return <Loading error={error} loading={loading} />;

  const openFollowUps = store.follow_ups.filter((f) => f.status === 'pending');


  async function deleteOrder(order) {
    if (!window.confirm(`Delete the order of ${order.quantity} delivered ${date(order.delivered_at)}?`)) return;
    await api.del(`/orders/${order.id}`);
    reload();
  }

  return (
    <>
      <PageHeader title={store.name} subtitle={`${CATEGORY_LABELS[store.category]} · ${store.address || 'No address'}`}>
        {admin && <button className="btn" onClick={() => setEditing(true)}>Edit store</button>}
      </PageHeader>

      <div className="info-row">
        <Info label="Contact person" value={store.contact_person} />
        <Info label="Phone" value={store.phone} />
        <Info
          label="Facebook"
          value={store.facebook_url && <a href={store.facebook_url} target="_blank" rel="noopener noreferrer">Open page</a>}
        />
        <Info
          label="Business chat"
          value={store.messenger_url && <a href={store.messenger_url} target="_blank" rel="noopener noreferrer">Open chat</a>}
        />
        <Info label="Representative" value={store.sales_rep?.name || 'Unassigned'} />
        <Info label="Status" value={<Badge tone={store.status === 'active' ? 'ok' : 'neutral'}>{STATUS_LABELS[store.status]}</Badge>} />
        <Info label="Notes" value={store.notes} />
      </div>

      <Panel title="Stock prediction by product" flush>
        <p className="notice">
          Worked out from this customer’s <strong>order history</strong>: the first order is the starting point, the second gives the first estimate,
          and every order after that makes it better. These are <strong>estimates</strong>; leftovers or extra stock can make them off.
        </p>
        {store.predictions.length === 0 ? (
          <Empty>No orders yet. Record their first order below.</Empty>
        ) : (
          <>
            <div className="guide-wrap"><StatusGuide columns={PREDICTION_GUIDE} /></div>
            <PredictionTable predictions={store.predictions} />
          </>
        )}
      </Panel>

      <div className="grid-2">
        <OrderForm storeId={store.id} products={products || []} onSaved={reload} />
        <Panel title="Orders delivered" flush>
          {store.orders.length === 0 ? <Empty>No orders recorded.</Empty> : (
            <table className="table">
              <thead><tr><th>Delivered</th><th>Product</th><th className="num">Qty</th><th className="num">Value</th><th /></tr></thead>
              <tbody>
                {store.orders.map((o) => (
                  <tr key={o.id}>
                    <td>{date(o.delivered_at)}</td>
                    <td>{o.product?.name}</td>
                    <td className="num">{num(o.quantity)}</td>
                    <td className="num">{money(o.amount)}</td>
                    <td className="right"><button className="btn btn-ghost btn-small" onClick={() => deleteOrder(o)}>Delete</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Panel>
      </div>

      <Panel title="Open follow-ups" flush>
        {openFollowUps.length === 0 ? <Empty>No open follow-ups.</Empty> : (
          <table className="table">
            <thead><tr><th>Follow-Up Date</th><th>Type</th><th>Follow-up reason</th><th>Next action</th><th /></tr></thead>
            <tbody>
              {openFollowUps.map((f) => (
                <tr key={f.id}>
                  <td>{date(f.due_date)} <div className="muted small">{dueLabel(f.days_until_due)}</div></td>
                  <td>{FOLLOW_UP_TYPE_LABELS[f.type]}</td>
                  <td>{f.reason}</td>
                  <td>{NEXT_ACTION_LABELS[f.next_action] || f.next_action || '-'}</td>
                  <td className="right">
                    <button className="btn btn-small" onClick={() => setCompleting({ ...f, store })}>Record response</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Panel>

      {editing && (
        <StoreForm store={store} reps={reps || []} onClose={() => setEditing(false)} onSaved={() => { setEditing(false); reload(); }} />
      )}
      {completing && (
        <OutcomeForm followUp={completing} onClose={() => setCompleting(null)} onSaved={() => { setCompleting(null); reload(); }} />
      )}
    </>
  );
}

function PredictionTable({ predictions }) {
  return (
    <table className="table">
      <thead>
        <tr>
          <th>Product</th>
          <th className="num">Total orders</th>
          <th className="num">Total packs</th>
          <th className="num">Average days between orders</th>
          <th className="num">Estimated daily consumption</th>
          <th className="num">Latest order</th>
          <th className="num">Estimated stock duration</th>
          <th>Predicted next purchase</th>
          <th>Buying pattern</th>
        </tr>
      </thead>
      <tbody>
        {predictions.map((p) => (
          <tr key={p.product_id}>
            <td>{p.product_name}</td>
            <td className="num">{num(p.total_orders, 0)}</td>
            <td className="num">{num(p.total_packs)}</td>
            <td className="num">{p.avg_days_between_orders !== null ? `${num(p.avg_days_between_orders, 1)} days` : '-'}</td>
            <td className="num" title={p.avg_daily_consumption !== null ? `Exact: ${p.avg_daily_consumption}` : ''}>
              {p.avg_daily_consumption !== null ? `${num(p.avg_daily_consumption)} /day` : '-'}
              {p.weighted && <div className="muted small">recent orders weighted more</div>}
            </td>
            <td className="num">{num(p.latest_order_qty)}<div className="muted small">{date(p.latest_order_date)}</div></td>
            <td className="num" title={p.estimated_stock_days !== null ? `Exact: ${p.estimated_stock_days}` : ''}>
              {p.estimated_stock_days !== null ? `${num(p.estimated_stock_days, 1)} days` : '-'}
            </td>
            <td className="keep">
              {p.next_purchase_date ? <>{date(p.next_purchase_date)} <div className="muted small">{dueLabel(p.days_until_next_purchase)}</div></> : '-'}
              <UrgencyBadge prediction={p} />
            </td>
            <td>
              {PATTERN_LABELS[p.buying_pattern]}
              <div className="muted small">{BASIS_LABELS[p.basis]}</div>
              <div className="muted small pred-summary">{p.summary}</div>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
function OrderForm({ storeId, products, onSaved }) {
  const blank = { product_id: '', delivered_at: today(), quantity: '', unit_price: '', notes: '' };
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState({});
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    setErrors({});
    try {
      await api.post(`/stores/${storeId}/orders`, { ...form, unit_price: form.unit_price || null });
      setForm({ ...blank, product_id: form.product_id });
      onSaved();
    } catch (err) {
      setErrors(err.errors || { quantity: err.message });
    }
  }

  return (
    <Panel title="Record order">
      <form onSubmit={submit} className="form-grid">
        <Field label="Product" error={errors.product_id}>
          <select value={form.product_id} onChange={set('product_id')} required>
            <option value="">Select product</option>
            {products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
        </Field>
        <Field label="Order date" error={errors.delivered_at}>
          <input type="date" value={form.delivered_at} onChange={set('delivered_at')} max={today()} required />
        </Field>
        <Field label="Packs ordered" error={errors.quantity}>
          <input type="number" min="0.01" step="0.01" value={form.quantity} onChange={set('quantity')} required />
        </Field>
        <Field label="Unit price (₱)" error={errors.unit_price} hint="Blank uses the product's price.">
          <input type="number" min="0" step="0.01" value={form.unit_price} onChange={set('unit_price')} />
        </Field>
        <p className="hint span-2">Each order updates the prediction: the first order is the starting point, the second gives the first estimate.</p>
        <div className="form-actions span-2">
          <button type="submit" className="btn btn-primary">Save order</button>
        </div>
      </form>
    </Panel>
  );
}

function Info({ label, value }) {
  return (
    <div className="info">
      <div className="field-label">{label}</div>
      <div>{value || <span className="muted">-</span>}</div>
    </div>
  );
}
