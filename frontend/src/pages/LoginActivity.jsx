import { useState } from 'react';
import { api, qs } from '../api.js';
import { ROLE_LABELS } from '../auth.js';
import { Empty, Loading, PageHeader, Panel, useApi } from '../components.jsx';

const EVENTS = {
  login: { label: 'Logged in', tone: 'ok' },
  logout: { label: 'Logged out', tone: 'neutral' },
  failed: { label: 'Failed sign-in', tone: 'danger' },
};

/** Short device description from the browser's user-agent text. */
function device(ua) {
  if (!ua) return '-';
  const os = /Android/i.test(ua) ? 'Android' : /iPhone|iPad/i.test(ua) ? 'iPhone/iPad' : /Windows/i.test(ua) ? 'Windows' : /Mac OS/i.test(ua) ? 'Mac' : /Linux/i.test(ua) ? 'Linux' : 'Other';
  const browser = /Edg\//.test(ua) ? 'Edge' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox' : /Safari\//.test(ua) ? 'Safari' : '';
  return [browser, os].filter(Boolean).join(' on ');
}

export default function LoginActivity() {
  const [filters, setFilters] = useState({ user_id: '', event: '' });
  const params = qs(filters);
  const { data, error, loading } = useApi(() => api.get('/login-logs' + params), [params]);
  const { data: users } = useApi(() => api.get('/users'), []);
  const set = (k) => (e) => setFilters({ ...filters, [k]: e.target.value });

  return (
    <>
      <PageHeader title="Login activity" subtitle="Who signed in and out, and when (latest 500)" />

      <div className="toolbar">
        <select value={filters.user_id} onChange={set('user_id')} aria-label="Account">
          <option value="">All accounts</option>
          {(users || []).map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
        </select>
        <select value={filters.event} onChange={set('event')} aria-label="Activity">
          <option value="">All activity</option>
          {Object.entries(EVENTS).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
        </select>
      </div>

      <Panel flush>
        <Loading error={error} loading={loading && !data} />
        {data && data.length === 0 && <Empty>No activity yet.</Empty>}
        {data && data.length > 0 && (
          <table className="table">
            <thead>
              <tr>
                <th>Date and time</th>
                <th>Account</th>
                <th>Activity</th>
                <th>Device</th>
                <th>IP address</th>
              </tr>
            </thead>
            <tbody>
              {data.map((l) => (
                <tr key={l.id}>
                  <td>{new Date(l.created_at).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit' })}</td>
                  <td>
                    {l.user?.name || <span className="muted">Unknown account</span>}
                    <div className="muted small">{l.email}{l.user ? ` · ${ROLE_LABELS[l.user.role]}` : ''}</div>
                  </td>
                  <td><span className={`tag tag-${EVENTS[l.event]?.tone || 'neutral'}`}>{EVENTS[l.event]?.label || l.event}</span></td>
                  <td>{device(l.user_agent)}</td>
                  <td className="muted">{l.ip_address || '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Panel>
    </>
  );
}
