import { useEffect, useState } from 'react';
import { Navigate, NavLink, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import {
  Bell, Bot, CalendarDays, ChartColumnBig, History, House, LogOut, Menu, Package, Search, Settings as SettingsIcon, ShoppingBag, TrendingUp,
  UserCog, UserRound, Users, X,
} from 'lucide-react';
import { api } from './api.js';
import {
  ROLE_LABELS, SIGNED_OUT_EVENT, UserContext, clearSession, getStoredUser, getToken, isSuperAdmin, saveSession,
} from './auth.js';
import Dashboard from './pages/Dashboard.jsx';
import Stores from './pages/Stores.jsx';
import StoreDetail from './pages/StoreDetail.jsx';
import FollowUps from './pages/FollowUps.jsx';
import Forecast from './pages/Forecast.jsx';
import Settings from './pages/Settings.jsx';
import Assistant from './pages/Assistant.jsx';
import Performance from './pages/Performance.jsx';
import Products from './pages/Products.jsx';
import Landing from './pages/Landing.jsx';
import WebOrders from './pages/WebOrders.jsx';
import Accounts from './pages/Accounts.jsx';
import LoginActivity from './pages/LoginActivity.jsx';
import Legal from './pages/Legal.jsx';

// `admin: true` items are for the super admin only.
const NAV = [
  { to: '/', label: 'Dashboard', icon: House, end: true, admin: true },
  { to: '/web-orders', label: 'Web Orders', icon: ShoppingBag, admin: true, badge: 'webOrders' },
  { to: '/stores', label: 'Customers', icon: Users, admin: true },
  { to: '/products', label: 'Products', icon: Package },
  { to: '/performance', label: 'Sales Performance', icon: ChartColumnBig, admin: true },
  { to: '/forecast', label: 'Stock Prediction', icon: TrendingUp },
  { to: '/follow-ups', label: 'Follow-ups', icon: CalendarDays, badge: 'followUps' },
  { to: '/assistant', label: 'Assistant', icon: Bot, admin: true },
  { to: '/accounts', label: 'Accounts', icon: UserCog, admin: true },
  { to: '/login-activity', label: 'Login Activity', icon: History, admin: true },
  { to: '/settings', label: 'Settings', icon: SettingsIcon, admin: true },
];

// Main pages on the phone's bottom bar; the rest live in the slide-out menu.
const BOTTOM_NAV = {
  admin: [
    { to: '/', label: 'Home', icon: House, end: true },
    { to: '/stores', label: 'Customers', icon: Users },
    { to: '/follow-ups', label: 'Follow-ups', icon: CalendarDays, badge: 'followUps' },
    { to: '/forecast', label: 'Stock', icon: TrendingUp },
  ],
  rep: [
    { to: '/products', label: 'Products', icon: Package },
    { to: '/forecast', label: 'Stock', icon: TrendingUp },
    { to: '/follow-ups', label: 'Follow-ups', icon: CalendarDays, badge: 'followUps' },
  ],
};

export default function App() {
  const [user, setUser] = useState(() => (getToken() ? getStoredUser() : null));

  // Any 401 from the API (expired, signed out elsewhere, account turned off) returns to the login page.
  useEffect(() => {
    const onSignedOut = () => setUser(null);
    window.addEventListener(SIGNED_OUT_EVENT, onSignedOut);
    return () => window.removeEventListener(SIGNED_OUT_EVENT, onSignedOut);
  }, []);

  // Refresh the role and name in case the super admin changed them.
  useEffect(() => {
    if (!getToken()) return;
    api.get('/me').then((u) => { saveSession(null, u); setUser(u); }).catch(() => {});
  }, []);

  const { pathname } = useLocation();

  // Public legal pages open the same way whether or not staff are signed in.
  if (pathname === '/privacy' || pathname === '/terms') return <Legal page={pathname.slice(1)} />;

  // Signed out: the public website, with staff sign-in in its side panel.
  if (!user) return <Landing onSignedIn={setUser} />;

  return (
    <UserContext.Provider value={user}>
      <Shell user={user} onSignedOut={() => setUser(null)} />
    </UserContext.Provider>
  );
}

function Shell({ user, onSignedOut }) {
  const location = useLocation();
  const navigate = useNavigate();
  const [attention, setAttention] = useState(0);
  const [newWebOrders, setNewWebOrders] = useState(0);
  const [menuOpen, setMenuOpen] = useState(false);
  const closeMenu = () => setMenuOpen(false);
  const admin = isSuperAdmin(user);
  const nav = NAV.filter((n) => admin || !n.admin);
  const home = admin ? '/' : '/products';

  // Overdue + due-today follow-ups, refreshed on every page change.
  useEffect(() => {
    api.get('/follow-ups?bucket=today')
      .then((d) => setAttention((d.counts?.overdue || 0) + (d.counts?.today || 0)))
      .catch(() => {});
    if (admin) {
      api.get('/web-orders?status=new').then((d) => setNewWebOrders(d.counts?.new || 0)).catch(() => {});
    }
  }, [location.pathname, admin]);
  const badges = { followUps: attention, webOrders: newWebOrders };

  async function signOut() {
    try {
      await api.post('/logout');
    } catch {
      // Already signed out on the server; finish here anyway.
    }
    clearSession();
    navigate('/');
    onSignedOut();
  }

  const adminOnly = (element) => (admin ? element : <Navigate to={home} replace />);

  return (
    <div className={`shell ${menuOpen ? 'menu-open' : ''}`}>
      <div className="drawer-backdrop" onClick={closeMenu} aria-hidden="true" />
      <aside className="sidebar" aria-label="Main menu">
        <div className="brand">
          <span className="brand-mark"><img src="/bge-logo.png" alt="" /></span>
          <span>
            <span className="brand-name"><span className="brand-accent">BGE</span> Corporation</span>
            <span className="brand-sub">Customer Relationship Management</span>
          </span>
          <button className="icon-btn drawer-close" onClick={closeMenu} aria-label="Close menu"><X size={22} /></button>
        </div>
        <nav>
          {nav.map(({ to, label, icon: Icon, end, badge }) => (
            <NavLink key={to} to={to} end={end} onClick={closeMenu} className={({ isActive }) => (isActive ? 'active' : '')}>
              <Icon size={19} />
              <span>{label}</span>
              {badge && badges[badge] > 0 && <span className="nav-badge">{badges[badge]}</span>}
            </NavLink>
          ))}
        </nav>
        <div className="sidebar-user">
          <span className="avatar"><UserRound size={20} /></span>
          <span>
            <span className="user-name" title={user.email}>{user.name}</span>
            <span className="user-role">{ROLE_LABELS[user.role]}</span>
          </span>
          <button className="icon-btn logout-btn" onClick={signOut} aria-label="Log out" title="Log out"><LogOut size={20} /></button>
        </div>
      </aside>

      <div className="main">
        <TopBar attention={attention} admin={admin} onMenu={() => setMenuOpen(true)} />
        <main className="content">
          <Routes>
            <Route path="/" element={adminOnly(<Dashboard />)} />
            <Route path="/stores" element={adminOnly(<Stores />)} />
            <Route path="/stores/:id" element={<StoreDetail />} />
            <Route path="/products" element={<Products />} />
            <Route path="/follow-ups" element={<FollowUps />} />
            <Route path="/forecast" element={<Forecast />} />
            <Route path="/performance" element={adminOnly(<Performance />)} />
            <Route path="/settings" element={adminOnly(<Settings />)} />
            <Route path="/assistant" element={adminOnly(<Assistant />)} />
            <Route path="/accounts" element={adminOnly(<Accounts />)} />
            <Route path="/login-activity" element={adminOnly(<LoginActivity />)} />
            <Route path="/web-orders" element={adminOnly(<WebOrders />)} />
            <Route path="*" element={<Navigate to={home} replace />} />
          </Routes>
        </main>
      </div>

      <nav className="bottom-nav" aria-label="Quick navigation">
        {BOTTOM_NAV[admin ? 'admin' : 'rep'].map(({ to, label, icon: Icon, end, badge }) => (
          <NavLink key={to} to={to} end={end} className={({ isActive }) => (isActive ? 'active' : '')}>
            <span className="bn-icon">
              <Icon size={22} />
              {badge && badges[badge] > 0 && <span className="dot-badge">{badges[badge]}</span>}
            </span>
            <span>{label}</span>
          </NavLink>
        ))}
        <button type="button" onClick={() => setMenuOpen(true)}>
          <span className="bn-icon"><Menu size={22} /></span>
          <span>More</span>
        </button>
      </nav>
    </div>
  );
}

function TopBar({ attention, admin, onMenu }) {
  const navigate = useNavigate();
  const [q, setQ] = useState('');
  const [now] = useState(() => new Date());

  return (
    <header className="topbar">
      <button className="icon-btn menu-btn" onClick={onMenu} aria-label="Open menu"><Menu size={24} /></button>
      {admin ? (
        <form
          className="topbar-search"
          onSubmit={(e) => {
            e.preventDefault();
            navigate(`/stores${q.trim() ? `?search=${encodeURIComponent(q.trim())}` : ''}`);
          }}
        >
          <Search size={18} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search customer, product, or notes..." aria-label="Search" />
        </form>
      ) : <span />}
      <div className="topbar-right">
        <button className="icon-btn" onClick={() => navigate('/follow-ups')} aria-label={`${attention} follow-ups need attention`} title="Overdue and due-today follow-ups">
          <Bell size={21} />
          {attention > 0 && <span className="dot-badge">{attention}</span>}
        </button>
        <div className="topbar-date">
          <CalendarDays size={22} />
          <span>
            <strong>{now.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })}</strong>
            <span className="muted small">{now.toLocaleDateString('en-PH', { weekday: 'long' })}</span>
          </span>
        </div>
      </div>
    </header>
  );
}
