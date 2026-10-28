import { useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { api } from '../api.js';
import { ROLE_LABELS, useUser } from '../auth.js';
import { Badge, ConfirmDelete, Empty, Field, Loading, Modal, PageHeader, Panel, useApi } from '../components.jsx';

function when(value) {
  if (!value) return '-';
  return new Date(value).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

export default function Accounts() {
  const me = useUser();
  const { data, error, loading, reload } = useApi(() => api.get('/users'), []);

  const [editing, setEditing] = useState(null); // {} = new account
  const [deleting, setDeleting] = useState(null);

  return (
    <>
      <PageHeader title="Accounts" subtitle="Who can sign in to the CRM">
        <button className="btn btn-primary" onClick={() => setEditing({})}><Plus size={18} /> Add account</button>
      </PageHeader>

      <Panel flush>
        <p className="notice">
          <strong>Super admin:</strong> everything, including accounts and the login log.{' '}
          <strong>Seller:</strong> sees Products (view only), Stock Prediction and Follow-ups, and can record orders and follow-ups.
        </p>
        <Loading error={error} loading={loading && !data} />
        {data && data.length === 0 && <Empty>No accounts yet.</Empty>}
        {data && data.length > 0 && (
          <table className="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>

                <th>Status</th>
                <th>Last sign-in</th>
                <th aria-label="Actions" />
              </tr>
            </thead>
            <tbody>
              {data.map((u) => (
                <tr key={u.id}>
                  <td>{u.name}{u.id === me.id && <span className="muted small"> (you)</span>}</td>
                  <td>{u.email}</td>
                  <td className="keep"><Badge tone={u.role === 'super_admin' ? 'ok' : 'neutral'}>{ROLE_LABELS[u.role]}</Badge></td>

                  <td><Badge tone={u.active ? 'ok' : 'neutral'}>{u.active ? 'Active' : 'Turned off'}</Badge></td>
                  <td>{when(u.last_login_at)}</td>
                  <td className="right">
                    <button className="btn btn-small" onClick={() => setEditing(u)}><Pencil size={14} /> Edit</button>{' '}
                    {u.id !== me.id && (
                      <button className="btn btn-ghost btn-small" onClick={() => setDeleting(u)} aria-label={`Delete ${u.name}`}><Trash2 size={15} /></button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Panel>

      {editing && (
        <AccountForm
          account={editing.id ? editing : null}
          isMe={editing.id === me.id}

          onClose={() => setEditing(null)}
          onSaved={() => { setEditing(null); reload(); }}
        />
      )}
      {deleting && (
        <ConfirmDelete
          title="Delete account"
          message={`Delete the account of ${deleting.name} (${deleting.email})? They will be signed out and can no longer sign in. Their login history is kept.`}
          onClose={() => setDeleting(null)}
          onConfirm={async () => { await api.del(`/users/${deleting.id}`); setDeleting(null); reload(); }}
        />
      )}
    </>
  );
}

function AccountForm({ account, isMe, onClose, onSaved }) {
  const [form, setForm] = useState({
    name: account?.name ?? '',
    email: account?.email ?? '',
    password: '',
    role: account?.role ?? 'representative',

    active: account?.active ?? true,
  });
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });
  const err = (k) => (Array.isArray(errors[k]) ? errors[k][0] : errors[k]);

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    const payload = { ...form, password: form.password || null };
    try {
      if (account) await api.put(`/users/${account.id}`, payload);
      else await api.post('/users', payload);
      onSaved();
    } catch (error) {
      setErrors(Object.keys(error.errors || {}).length ? error.errors : { name: error.message });
      setSaving(false);
    }
  }

  return (
    <Modal title={account ? `Edit ${account.name}` : 'Add account'} onClose={onClose}>
      <form onSubmit={submit} className="form-grid">
        <Field label={form.role === 'representative' ? 'Seller’s name' : 'Full name'} error={err('name')}>
          <input value={form.name} onChange={set('name')} required autoFocus />
        </Field>
        <Field label="Email (used to sign in)" error={err('email')}>
          <input type="email" value={form.email} onChange={set('email')} required autoComplete="off" />
        </Field>
        <Field
          label={account ? 'New password' : 'Password'}
          error={err('password')}
          hint={account ? 'Leave blank to keep the current password. Changing it signs them out.' : 'At least 8 characters. Give it to the person privately.'}
        >
          <input type="password" value={form.password} onChange={set('password')} required={!account} minLength={8} autoComplete="new-password" />
        </Field>
        <Field label="Role" error={err('role')}>
          <select value={form.role} onChange={set('role')} disabled={isMe}>
            <option value="representative">Seller</option>
            <option value="super_admin">Super admin</option>
          </select>
        </Field>

        <label className="field checkbox">
          <span><input type="checkbox" checked={form.active} onChange={set('active')} disabled={isMe} /> Can sign in</span>
          <span className="field-hint">Untick to turn the account off without deleting it.</span>
        </label>
        <div className="form-actions span-2">
          <button type="button" className="btn" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : account ? 'Save changes' : 'Create account'}</button>
        </div>
      </form>
    </Modal>
  );
}
