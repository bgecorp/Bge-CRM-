import { useState } from 'react';
import { Eye, EyeOff, LogIn } from 'lucide-react';
import { api } from '../api.js';
import { saveSession } from '../auth.js';

/** Staff sign-in form, shown in the landing page's pop-up. */
export default function LoginCard({ onSignedIn }) {
  const [form, setForm] = useState({ email: '', password: '' });
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const { token, user } = await api.post('/login', form);
      saveSession(token, user);
      onSignedIn(user);
    } catch (err) {
      setError(err.status === 429 ? 'Too many tries. Wait a minute and try again.' : err.errors?.email?.[0] || err.message);
      setBusy(false);
    }
  }

  return (
    <section className="login-card" id="staff-login" aria-labelledby="login-title">
      <h2 id="login-title">Staff sign in</h2>
      <p className="muted small">For BGE Corporation sellers and admins.</p>

      <form onSubmit={submit} className="login-form">
        <label className="field">
          <span className="field-label">Email</span>
          <input type="email" autoComplete="username" value={form.email} onChange={set('email')} required />
        </label>
        <label className="field">
          <span className="field-label">Password</span>
          <span className="password-wrap">
            <input
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              value={form.password}
              onChange={set('password')}
              required
            />
            <button type="button" className="icon-btn" onClick={() => setShowPassword(!showPassword)} aria-label={showPassword ? 'Hide password' : 'Show password'}>
              {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
            </button>
          </span>
        </label>
        {error && <div className="alert alert-error">{error}</div>}
        <button className="btn btn-primary login-submit" disabled={busy}>
          <LogIn size={18} /> {busy ? 'Signing in…' : 'Sign in'}
        </button>
      </form>
      <p className="muted small login-foot">Forgot your password? Ask your super admin to set a new one.</p>
    </section>
  );
}
