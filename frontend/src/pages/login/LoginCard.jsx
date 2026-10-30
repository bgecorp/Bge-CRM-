import { useState } from 'react';
import { ArrowRight, Mail } from 'lucide-react';
import BrandHeader from './BrandHeader.jsx';
import InputField from './InputField.jsx';
import PasswordField from './PasswordField.jsx';
import { useLogin } from './useLogin.js';

/** The glass sign-in card: brand, greeting, the form, and the company line. */
export default function LoginCard({ onSignedIn }) {
  const { values, setField, errors, formError, busy, submit } = useLogin(onSignedIn);
  const [showReset, setShowReset] = useState(false);

  return (
    <main className="lg-card">
      <BrandHeader />

      <h1 className="lg-title">Welcome Back</h1>
      <p className="lg-subtitle">Sign in to your account to continue</p>

      <form className="lg-form" onSubmit={submit} noValidate>
        <InputField
          id="login-email"
          label="Email Address"
          icon={Mail}
          type="email"
          autoComplete="username"
          value={values.email}
          onChange={(e) => setField('email', e.target.value)}
          error={errors.email}
        />
        <PasswordField
          id="login-password"
          label="Password"
          autoComplete="current-password"
          value={values.password}
          onChange={(e) => setField('password', e.target.value)}
          error={errors.password}
        />

        <div className="lg-options">
          <label className="lg-remember">
            <input type="checkbox" checked={values.remember} onChange={(e) => setField('remember', e.target.checked)} />
            <span>Remember me</span>
          </label>
          <button type="button" className="lg-link" onClick={() => setShowReset(!showReset)} aria-expanded={showReset}>
            Forgot password?
          </button>
        </div>
        {showReset && <p className="lg-note">Passwords are reset by your super admin. Ask them to set a new one for you.</p>}
        {formError && <p className="lg-alert" role="alert">{formError}</p>}

        <button type="submit" className="lg-submit" disabled={busy}>
          {busy ? 'Signing in…' : <>Log In <ArrowRight aria-hidden="true" /></>}
        </button>
      </form>

      <p className="lg-secure"> BGE Corporation.</p>
    </main>
  );
}
