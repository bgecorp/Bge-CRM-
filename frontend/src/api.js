import { getToken, signalSignedOut } from './auth.js';

// Same host the page came from, so a phone on the Wi-Fi reaches the PC's API too.
export const API_BASE = import.meta.env.VITE_API_URL || `http://${window.location.hostname || '127.0.0.1'}:8000/api`;
const BASE = API_BASE;

async function request(method, path, body) {
  // FormData (file uploads) sets its own multipart Content-Type.
  const isForm = body instanceof FormData;
  const res = await fetch(BASE + path, {
    method,
    headers: {
      Accept: 'application/json',
      ...(isForm ? {} : { 'Content-Type': 'application/json' }),
      ...(getToken() ? { Authorization: `Bearer ${getToken()}` } : {}),
    },
    body: isForm ? body : body ? JSON.stringify(body) : undefined,
  });
  // Signed out elsewhere, expired, or the account was turned off.
  if (res.status === 401 && path !== '/login') signalSignedOut();
  if (res.status === 204) return null;
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const err = new Error(
      res.status === 404 ? 'This record no longer exists.'
        : res.status === 403 ? 'Your account does not have access to this.'
          : data.message || `Request failed (${res.status})`,
    );
    err.status = res.status;
    err.errors = data.errors || {};
    throw err;
  }
  return data;
}

/** Downloads a file from the API (signed in) and saves it under `filename`. */
export async function download(path, filename) {
  const res = await fetch(BASE + path, {
    headers: { Accept: 'application/octet-stream', ...(getToken() ? { Authorization: `Bearer ${getToken()}` } : {}) },
  });
  if (res.status === 401) signalSignedOut();
  if (!res.ok) throw new Error(res.status === 403 ? 'Your account does not have access to this.' : `Download failed (${res.status})`);
  const url = URL.createObjectURL(await res.blob());
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function qs(params) {
  const p = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v !== '' && v !== null && v !== undefined) p.append(k, v);
  });
  const s = p.toString();
  return s ? `?${s}` : '';
}

export const api = {
  get: (path) => request('GET', path),
  post: (path, body) => request('POST', path, body),
  put: (path, body) => request('PUT', path, body),
  del: (path) => request('DELETE', path),
};
