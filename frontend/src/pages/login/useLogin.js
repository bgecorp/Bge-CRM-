import { useState } from 'react';
import { api } from '../../api.js';
import { saveSession } from '../../auth.js';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Checks the form before it is sent; returns a message per field, or {} when it is fine. */
export function validateLogin({ email, password }) {
  const errors = {};
  if (!email.trim()) errors.email = 'Enter your email address.';
  else if (!EMAIL_PATTERN.test(email.trim())) errors.email = 'Enter a valid email address, like name@company.com.';
  if (!password) errors.password = 'Enter your password.';
  return errors;
}

/** Login form state: the fields, field errors, the server's answer, and the submit handler. */
export function useLogin(onSignedIn) {
  const [values, setValues] = useState({ email: '', password: '', remember: false });
  const [errors, setErrors] = useState({});
  const [submitted, setSubmitted] = useState(false);
  const [formError, setFormError] = useState('');
  const [busy, setBusy] = useState(false);

  // After the first try, fields re-check as the person types, so errors clear as soon as they are fixed.
  function setField(name, value) {
    const next = { ...values, [name]: value };
    setValues(next);
    if (submitted) setErrors(validateLogin(next));
  }

  async function submit(e) {
    e.preventDefault();
    setSubmitted(true);
    setFormError('');
    const found = validateLogin(values);
    setErrors(found);
    if (Object.keys(found).length) return;

    setBusy(true);
    try {
      const { token, user } = await api.post('/login', { email: values.email.trim(), password: values.password });
      saveSession(token, user, values.remember);
      onSignedIn(user);
    } catch (err) {
      setFormError(err.status === 429 ? 'Too many tries. Wait a minute and try again.' : err.errors?.email?.[0] || err.message);
      setBusy(false);
    }
  }

  return { values, setField, errors, formError, busy, submit };
}
