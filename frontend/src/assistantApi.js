import { API_BASE } from './api.js';
import { getToken as getSignInToken } from './auth.js';

const BASE = API_BASE + '/assistant';
const KEY = 'sari-crm.assistantToken';

export function getToken() {
  try {
    return localStorage.getItem(KEY) || '';
  } catch {
    return '';
  }
}

export function setToken(token) {
  try {
    if (token) localStorage.setItem(KEY, token);
    else localStorage.removeItem(KEY);
  } catch {
    // Private mode: the code just has to be entered again next time.
  }
}

async function request(method, path, body) {
  const res = await fetch(BASE + path, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Assistant-Token': getToken(),
      Authorization: `Bearer ${getSignInToken()}`,
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (res.status === 204) return null;
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const err = new Error(data.message || `Request failed (${res.status})`);
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

export const assistant = {
  status: () => request('GET', '/status'),
  conversations: () => request('GET', '/conversations'),
  create: () => request('POST', '/conversations'),
  get: (id) => request('GET', `/conversations/${id}`),
  remove: (id) => request('DELETE', `/conversations/${id}`),
  send: (id, message) => request('POST', `/conversations/${id}/messages`, { message }),
  resolve: (id, decisions) => request('POST', `/conversations/${id}/resolve`, { decisions }),
  notes: () => request('GET', '/notes'),
  addNote: (note) => request('POST', '/notes', { note }),
  removeNote: (id) => request('DELETE', `/notes/${id}`),
  changes: () => request('GET', '/changes'),
};
