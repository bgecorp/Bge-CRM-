import { createContext, useContext } from 'react';

// The signed-in session: an API token plus the user it belongs to, kept in this browser.
const TOKEN_KEY = 'sari-crm.token';
const USER_KEY = 'sari-crm.user';

export const SUPER_ADMIN = 'super_admin';

export const ROLE_LABELS = {
  super_admin: 'Super admin',
  representative: 'Seller',
};

// "Remember me" keeps the session in localStorage, so it survives closing the browser.
// Otherwise it lives in sessionStorage and ends when the browser is closed.
function read(key) {
  try {
    return sessionStorage.getItem(key) ?? localStorage.getItem(key);
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

/** Stores a new sign-in (token + user), or with no token, updates the user of the current one. */
export function saveSession(token, user, remember = true) {
  try {
    let store = sessionStorage.getItem(TOKEN_KEY) ? sessionStorage : localStorage;
    if (token) {
      clearSession();
      store = remember ? localStorage : sessionStorage;
      store.setItem(TOKEN_KEY, token);
    }
    store.setItem(USER_KEY, JSON.stringify(user));
  } catch {
    // Private mode: the session lasts until the page is closed.
  }
}

export function clearSession() {
  try {
    for (const store of [localStorage, sessionStorage]) {
      store.removeItem(TOKEN_KEY);
      store.removeItem(USER_KEY);
    }
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
