import { createContext, useContext } from 'react';

// The signed-in session: an API token plus the user it belongs to, kept in this browser.
const TOKEN_KEY = 'sari-crm.token';
const USER_KEY = 'sari-crm.user';

export const SUPER_ADMIN = 'super_admin';

export const ROLE_LABELS = {
  super_admin: 'Super admin',
  representative: 'Seller',
};

function read(key) {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

export function getToken() {
  return read(TOKEN_KEY) || '';
}

export function getStoredUser() {
  try {
    return JSON.parse(read(USER_KEY) || 'null');
  } catch {
    return null;
  }
}

export function saveSession(token, user) {
  try {
    if (token) localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
  } catch {
    // Private mode: the session lasts until the page is closed.
  }
}

export function clearSession() {
  try {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  } catch {
    // Nothing stored.
  }
}

/** Fired when the API says the sign-in is no longer valid; App shows the login page. */
export const SIGNED_OUT_EVENT = 'sari-crm:signed-out';

export function signalSignedOut() {
  clearSession();
  window.dispatchEvent(new Event(SIGNED_OUT_EVENT));
}

/** The signed-in user, for pages that show or hide things by role. */
export const UserContext = createContext(null);

export function useUser() {
  return useContext(UserContext);
}

export function isSuperAdmin(user) {
  return user?.role === SUPER_ADMIN;
}
