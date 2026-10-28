import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api, qs } from '../api.js';
import { Badge, Empty, Field, Loading, Modal, PageHeader, Panel, useApi } from '../components.jsx';
import { FOLLOW_UP_TYPE_LABELS, NEXT_ACTION_LABELS, OUTCOME_LABELS, date, dueLabel, today } from '../format.js';
import OutcomeForm from './OutcomeForm.jsx';

const TABS = [
  { key: 'overdue', label: 'Overdue' },
  { key: 'today', label: 'Due today' },
  { key: 'upcoming', label: 'Upcoming' },
  { key: 'completed', label: 'Completed' },
];

export default function FollowUps() {
  const [bucket, setBucket] = useState('today');
  const [repId, setRepId] = useState('');
  const [completing, setCompleting] = useState(null);
  const [creating, setCreating] = useState(false);

  const params = qs({ bucket, sales_rep_id: repId });
  const { data, error, loading, reload } = useApi(() => api.get('/follow-ups' + params), [params]);
  const { data: reps } = useApi(() => api.get('/sales-reps'), []);

  return (
    <>
      <PageHeader title="Follow-ups" subtitle="Your reminders. One is added automatically a few days after a customer’s first order (5 by default, set in Settings).">
        <button className="btn btn-primary" onClick={() => setCreating(true)}>New follow-up</button>
      </PageHeader>

      <div className="toolbar">
        <div className="tabs">
          {TABS.map((t) => (
            <button key={t.key} className={bucket === t.key ? 'tab active' : 'tab'} onClick={() => setBucket(t.key)}>
              {t.label}
              {data?.counts?.[t.key] !== undefined && <span className="tab-count">{data.counts[t.key]}</span>}
            </button>
          ))}
        </div>
        <select value={repId} onChange={(e) => setRepId(e.target.value)}>
          <option value="">All representatives</option>
          {(reps || []).map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
        </select>
      </div>

      <Panel flush>
        <Loading error={error} loading={loading && !data} />
        {data && data.items.length === 0 && <Empty>Nothing here.</Empty>}
        {data && data.items.length > 0 && (
          <table className="table">
            <thead>
              <tr>
                <th>{bucket === 'completed' ? 'Completed' : 'Follow-Up Date'}</th>
                <th>Store</th>
                <th>Phone</th>
                <th>Representative</th>
                <th>Type</th>
                <th>Follow-up reason</th>
                <th>{bucket === 'completed' ? 'Customer response' : 'Next action'}</th>
                {bucket !== 'completed' && <th />}
              </tr>
            </thead>
            <tbody>
              {data.items.map((f) => (
                <tr key={f.id}>
                  <td>
                    {bucket === 'completed' ? date(f.completed_at) : f.due_date ? date(f.due_date) : null}
                    {bucket !== 'completed' && f.due_date && <div className={`small ${f.days_until_due < 0 ? 'text-danger' : 'muted'}`}>{dueLabel(f.days_until_due)}</div>}
                    {bucket !== 'completed' && <FollowUpStatus days={f.days_until_due} />}
                  </td>
                  <td className="keep">
                    {f.store.messenger_url ? (
                      <a href={f.store.messenger_url} target="_blank" rel="noopener noreferrer" title="Open chat in Business Suite">
                        {f.store.name} <span className="fb-mark">Chat</span>
                      </a>
                    ) : f.store.facebook_url ? (
                      <a href={f.store.facebook_url} target="_blank" rel="noopener noreferrer" title="Open Facebook page">
                        {f.store.name} <span className="fb-mark" aria-label="Facebook">f</span>
                      </a>
                    ) : (
                      <Link to={`/stores/${f.store.id}`}>{f.store.name}</Link>
                    )}
                    <div className="muted small">
                      {f.store.contact_person}
                      {(f.store.messenger_url || f.store.facebook_url) && (
                        <> {f.store.contact_person && '· '}<Link to={`/stores/${f.store.id}`}>Store details</Link></>
                      )}
                    </div>
                  </td>
                  <td>{f.store.phone || '-'}</td>
                  <td>{f.sales_rep?.name || <span className="muted">Unassigned</span>}</td>
                  <td><Badge>{FOLLOW_UP_TYPE_LABELS[f.type]}</Badge></td>
                  <td>{f.reason}</td>
                  <td>
                    {bucket === 'completed'
                      ? (
                        <>
                          {OUTCOME_LABELS[f.outcome] || f.status}
                          <div className="muted small">
                            {NEXT_ACTION_LABELS[f.next_action] || f.next_action}
                            {f.next_order_date && <> · next order {date(f.next_order_date)}</>}
                          </div>
                        </>
                      )
                      : NEXT_ACTION_LABELS[f.next_action] || f.next_action || '-'}
                  </td>
                  {bucket !== 'completed' && (
                    <td className="right"><button className="btn btn-small" onClick={() => setCompleting(f)}>Record response</button></td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Panel>

      {completing && (
        <OutcomeForm followUp={completing} onClose={() => setCompleting(null)} onSaved={() => { setCompleting(null); reload(); }} />
      )}
      {creating && <NewFollowUp onClose={() => setCreating(false)} onSaved={() => { setCreating(false); reload(); }} />}
    </>
  );
}

export function NewFollowUp({ onClose, onSaved }) {
  const { data: stores } = useApi(() => api.get('/stores?sort=name'), []);
  const [form, setForm] = useState({ store_id: '', due_date: today(), reason: '', next_action: '' });
  const [errors, setErrors] = useState({});
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    try {
      await api.post('/follow-ups', form);
      onSaved();
    } catch (err) {
      setErrors(err.errors || { store_id: err.message });
    }
  }

  return (
    <Modal title="New follow-up" onClose={onClose}>
      <form onSubmit={submit} className="form-grid">
        <Field label="Store" error={errors.store_id}>
          <select value={form.store_id} onChange={set('store_id')} required>
            <option value="">Select store</option>
            {(stores || []).map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
        </Field>
        <Field label="Due date" error={errors.due_date}>
          <input type="date" value={form.due_date} onChange={set('due_date')} required />
        </Field>
        <Field label="Reason" error={errors.reason}>
          <input value={form.reason} onChange={set('reason')} placeholder="e.g. Introduce new product" />
        </Field>
        <Field label="Next action" error={errors.next_action}>
          <select value={form.next_action} onChange={set('next_action')}>
            <option value="">None</option>
            {Object.entries(NEXT_ACTION_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </Field>
        <div className="form-actions span-2">
          <button type="button" className="btn" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn btn-primary">Create</button>
        </div>
      </form>
    </Modal>
  );
}

function FollowUpStatus({ days }) {
  if (days === null || days === undefined) return null;
  const [tone, label] = days < 0 ? ['danger', 'Overdue'] : days === 0 ? ['danger', 'Due Today'] : days <= 3 ? ['warning', 'Due Soon'] : ['ok', 'Upcoming'];
  return <div><span className={`tag tag-${tone}`}>{label}</span></div>;
}
