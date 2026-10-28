import { useCallback, useEffect, useRef, useState } from 'react';
import { assistant, getToken, setToken } from '../assistantApi.js';
import { Empty, PageHeader, Panel } from '../components.jsx';
import Markdown from '../Markdown.jsx';
import { date } from '../format.js';

const TABS = [
  { key: 'chat', label: 'Chat' },
  { key: 'memory', label: 'Memory' },
  { key: 'changes', label: 'Change log' },
];

export default function Assistant() {
  const [token, setTokenState] = useState(getToken());
  const [status, setStatus] = useState(null);
  const [needsCode, setNeedsCode] = useState(false);
  const [gateError, setGateError] = useState('');
  const [tab, setTab] = useState('chat');

  // Ask the API first: the access code screen only appears if a code is required.
  useEffect(() => {
    setStatus(null);
    assistant.status()
      .then((s) => { setStatus(s); setNeedsCode(false); })
      .catch((err) => {
        if (err.status === 401) {
          if (token) setGateError(err.message);
          setToken('');
          setNeedsCode(true);
        } else {
          setGateError(err.message);
        }
      });
  }, [token]);

  if (!status && !needsCode) {
    return (
      <>
        <PageHeader title="Assistant" />
        {gateError ? <div className="alert alert-error">{gateError}</div> : <div className="muted pad">Loading…</div>}
      </>
    );
  }

  if (!status) {
    return (
      <AccessGate
        error={gateError}
        onSubmit={(code) => {
          setGateError('');
          setToken(code);
          setTokenState(code);
        }}
      />
    );
  }

  return (
    <>
      <PageHeader title="Assistant" subtitle={`Ask about your stores and sales, or ask for changes to data or the system · ${status.model}`}>
        <div className="tabs">
          {TABS.map((t) => (
            <button key={t.key} className={tab === t.key ? 'tab active' : 'tab'} onClick={() => setTab(t.key)}>{t.label}</button>
          ))}
        </div>
        {status.locked && <button className="btn btn-ghost" onClick={() => { setToken(''); setTokenState(''); }}>Lock</button>}
      </PageHeader>

      {!status.configured && (
        <div className="alert alert-error">
          The Claude API key is not set. Add <code>ANTHROPIC_API_KEY=…</code> to <code>backend/.env</code>, then restart <code>php artisan serve</code>.
        </div>
      )}
      {!status.code_edits && (
        <p className="notice boxed">Code changes are switched off; the assistant can read the code and manage data. Set <code>ASSISTANT_ALLOW_CODE_EDITS=true</code> in <code>backend/.env</code> to allow approved code changes.</p>
      )}

      {tab === 'chat' && <Chat />}
      {tab === 'memory' && <Memory />}
      {tab === 'changes' && <Changes />}
    </>
  );
}

function AccessGate({ onSubmit, error }) {
  const [code, setCode] = useState('');
  return (
    <>
      <PageHeader title="Assistant" />
      <Panel title="Enter access code">
        <form className="gate" onSubmit={(e) => { e.preventDefault(); if (code.trim()) onSubmit(code.trim()); }}>
          <p className="muted">The assistant can change your data and code, so it is locked. The access code is <code>ASSISTANT_TOKEN</code> in <code>backend/.env</code>.</p>
          <input type="password" value={code} onChange={(e) => setCode(e.target.value)} placeholder="Access code" autoFocus />
          {error && <span className="field-error">{error}</span>}
          <div><button className="btn btn-primary">Unlock</button></div>
        </form>
      </Panel>
    </>
  );
}

function Chat() {
  const [list, setList] = useState([]);
  const [current, setCurrent] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const loadList = useCallback(() => assistant.conversations().then(setList).catch((e) => setError(e.message)), []);

  useEffect(() => {
    loadList();
  }, [loadList]);

  async function open(id) {
    setError('');
    setNotice('');
    setCurrent(await assistant.get(id));
  }

  async function startNew() {
    setError('');
    setNotice('');
    const c = await assistant.create();
    setCurrent(c);
    loadList();
  }

  async function remove(id) {
    if (!window.confirm('Delete this conversation? Saved notes and the change log are kept.')) return;
    await assistant.remove(id);
    if (current?.id === id) setCurrent(null);
    loadList();
  }

  // Runs a request that may take a while (several model calls) and shows the result.
  async function run(action) {
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const data = await action();
      setCurrent(data);
      setNotice(data.notice || '');
    } catch (err) {
      // Keep the optimistic copy of the user's message if the server saved nothing.
      if (err.data?.items) setCurrent((c) => (err.data.items.length >= (c?.items?.length || 0) ? err.data : { ...c, pending: err.data.pending }));
      setError(err.message);
    } finally {
      setBusy(false);
      loadList();
    }
  }

  async function send(text) {
    let conv = current;
    if (!conv) {
      conv = await assistant.create();
      setCurrent(conv);
    }
    setCurrent((c) => ({ ...(c || conv), items: [...(c?.items || []), { type: 'user', text }] }));
    await run(() => assistant.send(conv.id, text));
  }

  return (
    <div className="assistant">
      <aside className="conv-list">
        <button className="btn btn-primary block" onClick={startNew} disabled={busy}>New conversation</button>
        {list.map((c) => (
          <div key={c.id} className={`conv ${current?.id === c.id ? 'active' : ''}`}>
            <button className="conv-title" onClick={() => open(c.id)} disabled={busy}>{c.title}</button>
            <button className="btn btn-ghost btn-small" onClick={() => remove(c.id)} aria-label="Delete conversation" disabled={busy}>✕</button>
          </div>
        ))}
      </aside>

      <section className="chat panel">
        <Transcript items={current?.items || []} busy={busy} />
        {error && <div className="alert alert-error chat-alert">{error}</div>}
        {notice && <div className="notice chat-alert">{notice}</div>}
        {current?.pending?.length > 0 && !busy && (
          <Approvals actions={current.pending} onSubmit={(decisions) => run(() => assistant.resolve(current.id, decisions))} />
        )}
        <Composer disabled={busy || current?.pending?.length > 0} onSend={send} />
      </section>
    </div>
  );
}

const SUGGESTIONS = [
  'Which stores should we contact today, and why?',
  'Summarise sales for the last 30 days by representative.',
  'Chat Bot',

];

function Transcript({ items, busy }) {
  const end = useRef(null);
  const [seconds, setSeconds] = useState(0);

  useEffect(() => {
    end.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }, [items.length, busy]);

  useEffect(() => {
    if (!busy) return undefined;
    const start = Date.now();
    const t = setInterval(() => setSeconds(Math.floor((Date.now() - start) / 1000)), 1000);
    return () => clearInterval(t);
  }, [busy]);

  return (
    <div className="transcript">
      {items.length === 0 && !busy && (
        <div className="empty">
          <p>Ask a question or describe a change. Changes to data or code wait for your approval.</p>
          <ul className="suggestions">{SUGGESTIONS.map((s) => <li key={s}>{s}</li>)}</ul>
        </div>
      )}
      {items.map((item, i) => {
        if (item.type === 'user') return <div key={i} className="msg msg-user">{item.text}</div>;
        if (item.type === 'assistant') return <div key={i} className="msg msg-assistant"><Markdown text={item.text} /></div>;
        return <ToolLine key={i} item={item} />;
      })}
      {busy && <div className="muted working">Working… {seconds > 0 ? `${seconds}s` : ''}</div>}
      <div ref={end} />
    </div>
  );
}

function toolLabel({ name, input }) {
  switch (name) {
    case 'api_get': return `Read /api${input.path || ''}`;
    case 'api_write': return input.summary || `${input.method} /api${input.path}`;
    case 'describe_database': return input.table ? `Looked at table ${input.table}` : 'Looked at the database structure';
    case 'query_database': return 'Queried the database';
    case 'list_files': return `Listed ${input.path || 'project'}`;
    case 'read_file': return `Read ${input.path}`;
    case 'search_code': return `Searched code for "${input.pattern}"`;
    case 'save_note': return 'Saved to memory';
    case 'delete_note': return 'Removed a memory note';
    case 'edit_file': return `Edited ${input.path}`;
    case 'write_file': return `Wrote ${input.path}`;
    case 'run_migrations': return 'Ran database migrations';
    default: return name;
  }
}

function ToolLine({ item }) {
  const icon = item.status === 'done' ? '✓' : item.status === 'error' ? '!' : '…';
  return (
    <details className={`tool tool-${item.status}`}>
      <summary><span className="tool-icon">{icon}</span> {toolLabel(item)}</summary>
      <div className="tool-body">
        <div className="field-label">Input</div>
        <pre>{JSON.stringify(item.input, null, 2)}</pre>
        {item.result && <><div className="field-label">Result</div><pre>{item.result}</pre></>}
      </div>
    </details>
  );
}

function Approvals({ actions, onSubmit }) {
  const [decisions, setDecisions] = useState({});
  const set = (id, value) => setDecisions((d) => ({ ...d, [id]: { ...d[id], ...value } }));
  const allDecided = actions.every((a) => decisions[a.id]?.approve !== undefined);

  return (
    <div className="approvals">
      <div className="approvals-head">
        <strong>{actions.length === 1 ? '1 change needs your approval' : `${actions.length} changes need your approval`}</strong>
        <button className="btn btn-small" onClick={() => onSubmit(Object.fromEntries(actions.map((a) => [a.id, { approve: true }])))}>
          Approve all
        </button>
      </div>
      {actions.map((a) => {
        const d = decisions[a.id] || {};
        const p = a.preview;
        return (
          <div key={a.id} className={`approval kind-${p.kind}`}>
            <div className="approval-title">{p.title}</div>
            {p.kind === 'code' ? (
              <>
                <div className="muted small">{p.detail}</div>
                <div className="diff">
                  {p.old && <pre className="diff-old">{p.old}</pre>}
                  <pre className="diff-new">{p.new}</pre>
                </div>
              </>
            ) : (
              <pre>{p.detail}</pre>
            )}
            <div className="approval-actions">
              <button className={`btn btn-small ${d.approve === true ? 'btn-primary' : ''}`} onClick={() => set(a.id, { approve: true })} disabled={p.kind === 'error'}>
                Approve
              </button>
              <button className={`btn btn-small ${d.approve === false ? 'btn-danger' : ''}`} onClick={() => set(a.id, { approve: false })}>
                Reject
              </button>
              {d.approve === false && (
                <input className="reason" placeholder="Why? (optional, helps the assistant)" value={d.reason || ''} onChange={(e) => set(a.id, { reason: e.target.value })} />
              )}
            </div>
          </div>
        );
      })}
      <div className="form-actions">
        <button className="btn btn-primary" disabled={!allDecided} onClick={() => onSubmit(decisions)}>Apply decisions</button>
      </div>
    </div>
  );
}

function Composer({ disabled, onSend }) {
  const [text, setText] = useState('');

  function submit(e) {
    e?.preventDefault();
    const t = text.trim();
    if (!t || disabled) return;
    setText('');
    onSend(t);
  }

  return (
    <form className="composer" onSubmit={submit}>
      <textarea
        value={text}
        onChange={(e) => setText(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter' && !e.shiftKey) submit(e);
        }}
        placeholder={disabled ? 'Waiting…' : 'Message the assistant (Enter to send, Shift+Enter for a new line)'}
        rows={2}
        disabled={disabled}
      />
      <button className="btn btn-primary" disabled={disabled || !text.trim()}>Send</button>
    </form>
  );
}

function Memory() {
  const [notes, setNotes] = useState(null);
  const [text, setText] = useState('');
  const load = useCallback(() => assistant.notes().then(setNotes), []);

  useEffect(() => {
    load();
  }, [load]);

  async function add(e) {
    e.preventDefault();
    if (!text.trim()) return;
    await assistant.addNote(text.trim());
    setText('');
    load();
  }

  return (
    <Panel title="What the assistant remembers" flush>
      <p className="notice">These notes are included in every conversation. Remove anything wrong or outdated; add facts it should always know.</p>
      {notes && notes.length === 0 && <Empty>No notes yet.</Empty>}
      {notes && notes.length > 0 && (
        <table className="table">
          <tbody>
            {notes.map((n) => (
              <tr key={n.id}>
                <td className="muted small nowrap">#{n.id} · {date(n.created_at)}</td>
                <td>{n.note}</td>
                <td className="right"><button className="btn btn-ghost btn-small" onClick={() => assistant.removeNote(n.id).then(load)}>Remove</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <form className="composer bordered-top" onSubmit={add}>
        <input value={text} onChange={(e) => setText(e.target.value)} placeholder="e.g. Deliveries to Kauswagan happen on Tuesdays and Fridays." />
        <button className="btn btn-primary">Add note</button>
      </form>
    </Panel>
  );
}

function Changes() {
  const [changes, setChanges] = useState(null);

  useEffect(() => {
    assistant.changes().then(setChanges);
  }, []);

  return (
    <Panel title="Changes made through the assistant" flush>
      <p className="notice">Every approved or rejected change. Code edits keep a backup of the previous file under <code>backend/storage/app/assistant-backups</code>.</p>
      {changes && changes.length === 0 && <Empty>No changes yet.</Empty>}
      {changes && changes.length > 0 && (
        <table className="table">
          <thead><tr><th>When</th><th>Status</th><th>Change</th><th>Target</th><th>Result</th></tr></thead>
          <tbody>
            {changes.map((c) => (
              <tr key={c.id}>
                <td className="nowrap">{new Date(c.created_at).toLocaleString('en-PH')}</td>
                <td><span className={`badge badge-${c.status === 'applied' ? 'ok' : c.status === 'failed' ? 'danger' : 'neutral'}`}>{c.status}</span></td>
                <td>{c.summary}<div className="muted small">{c.tool}</div></td>
                <td className="small">{c.target}</td>
                <td className="small result-cell">{c.result}{c.backup_path && <div className="muted">Backup: {c.backup_path}</div>}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Panel>
  );
}
