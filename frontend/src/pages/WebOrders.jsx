import { useState } from 'react';
import { MessageSquare, Phone } from 'lucide-react';
import { api, qs } from '../api.js';
import { ConfirmDelete, Empty, Field, Loading, Modal, PageHeader, Panel, useApi } from '../components.jsx';
import { money } from '../format.js';

export const WEB_ORDER_STATUS = {
  new: { label: 'New', tone: 'danger' },
  confirmed: { label: 'Confirmed', tone: 'warning' },
  shipped: { label: 'Shipped', tone: 'neutral' },
  delivered: { label: 'Delivered', tone: 'ok' },
  cancelled: { label: 'Cancelled', tone: 'neutral' },
};

function when(value) {
  return new Date(value).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

/** Orders customers placed on the public landing page. */
export default function WebOrders() {
  const [status, setStatus] = useState('new');
  const params = qs({ status });
  const { data, error, loading, reload } = useApi(() => api.get('/web-orders' + params), [params]);
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const counts = data?.counts || {};
  const all = Object.values(counts).reduce((a, b) => a + Number(b), 0);

  return (
    <>
      <PageHeader title="Web orders" subtitle="Orders placed on your website (cash on delivery)" />

      <div className="toolbar">
        <div className="tabs">
          {Object.entries(WEB_ORDER_STATUS).map(([key, s]) => (
            <button key={key} className={status === key ? 'tab active' : 'tab'} onClick={() => setStatus(key)}>
              {s.label} <span className="tab-count">{counts[key] || 0}</span>
            </button>
          ))}
          <button className={status === '' ? 'tab active' : 'tab'} onClick={() => setStatus('')}>All <span className="tab-count">{all}</span></button>
        </div>
      </div>

      <Panel flush>
        <p className="notice">Call or text each new order to confirm it, then move it along: Confirmed → Shipped → Delivered.</p>
        <Loading error={error} loading={loading && !data} />
        {data && data.orders.length === 0 && <Empty>No orders here yet.</Empty>}
        {data && data.orders.length > 0 && (
          <table className="table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Products</th>
                <th className="num">Total</th>
                <th>Deliver to</th>
                <th>Status</th>
                <th aria-label="Actions" />
              </tr>
            </thead>
            <tbody>
              {data.orders.map((o) => (
                <tr key={o.id}>
                  <td>
                    <strong>{o.reference}</strong>
                    <div className="muted small">{when(o.created_at)}</div>
                  </td>
                  <td className="keep">
                    {o.customer_name}
                    <div className="small">
                      <a href={`tel:${o.phone.replace(/[^0-9+]/g, '')}`}><Phone size={13} /> {o.phone}</a>{' '}
                      <a href={`sms:${o.phone.replace(/[^0-9+]/g, '')}`} aria-label="Text"><MessageSquare size={13} /></a>
                    </div>
                  </td>
                  <td>
                    {o.items.map((i) => (
                      <div key={i.offer}>{i.quantity} × {i.name}{i.deal ? <span className="muted small"> ({i.deal})</span> : ''}</div>
                    ))}
                  </td>
                  <td className="num">
                    {money(o.total)}
                    {o.price_to_confirm && <div className="muted small">+ price to confirm</div>}
                  </td>
                  <td>
                    {o.address}
                    <div className="muted small">{o.city}</div>
                    {o.notes && <div className="muted small">“{o.notes}”</div>}
                  </td>
                  <td className="keep">
                    <span className={`tag tag-${WEB_ORDER_STATUS[o.status]?.tone}`}>{WEB_ORDER_STATUS[o.status]?.label}</span>
                    {o.admin_notes && <div className="muted small">{o.admin_notes}</div>}
                    {o.handler && <div className="muted small">by {o.handler.name}</div>}
                  </td>
                  <td className="right">
                    <button className="btn btn-small" onClick={() => setEditing(o)}>Update</button>{' '}
                    <button className="btn btn-ghost btn-small" onClick={() => setDeleting(o)}>Delete</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Panel>

      {editing && <StatusForm order={editing} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); reload(); }} />}
      {deleting && (
        <ConfirmDelete
          title="Delete web order"
          message={`Delete order ${deleting.reference} from ${deleting.customer_name}? Use this for spam or test orders; cancel real ones instead.`}
          onClose={() => setDeleting(null)}
          onConfirm={async () => { await api.del(`/web-orders/${deleting.id}`); setDeleting(null); reload(); }}
        />
      )}
    </>
  );
}

function StatusForm({ order, onClose, onSaved }) {
  const [form, setForm] = useState({ status: order.status, admin_notes: order.admin_notes || '' });
  const [error, setError] = useState('');

  async function submit(e) {
    e.preventDefault();
    try {
      await api.put(`/web-orders/${order.id}`, form);
      onSaved();
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <Modal title={`Order ${order.reference}: ${order.customer_name}`} onClose={onClose}>
      <form onSubmit={submit} className="form-grid">
        <Field label="Status">
          <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
            {Object.entries(WEB_ORDER_STATUS).map(([k, s]) => <option key={k} value={k}>{s.label}</option>)}
          </select>
        </Field>
        <Field label="Notes for the team" hint="e.g. Called, ships Friday via J&T">
          <input value={form.admin_notes} onChange={(e) => setForm({ ...form, admin_notes: e.target.value })} />
        </Field>
        {error && <div className="alert alert-error span-2">{error}</div>}
        <div className="form-actions span-2">
          <button type="button" className="btn" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn btn-primary">Save</button>
        </div>
      </form>
    </Modal>
  );
}
