import { useEffect, useState } from 'react';
import { Banknote, CheckCircle2, ChevronDown, LogIn, PhoneCall, ShieldCheck, ShoppingBag, Truck, X } from 'lucide-react';
import { api } from '../api.js';
import { money } from '../format.js';
import { Link } from 'react-router-dom';
import LoginCard from './Login.jsx';
import { LegalFooter } from './Legal.jsx';

const peso = (n) => money(n).replace('.00', '');

// Short sales lines per product (no medical claims; the posters carry the brand's own text).
const BLURBS = {
  'barley-powder': 'Green barley powder for your daily glass. Mix with water and drink.',
  'corn-coffee': 'A warm corn coffee with malunggay and mangosteen. A daily coffee alternative.',
  'barley-capsules': 'Pure barley in easy capsules. 100 capsules per bottle.',
};

/**
 * Public website: offers, posters and a cash-on-delivery order form.
 * Staff sign in from the "Sign in" link at the top right, which opens a pop-up card.
 */
export default function Landing({ onSignedIn }) {
  const [offers, setOffers] = useState([]);
  const [loadError, setLoadError] = useState('');
  const [showLogin, setShowLogin] = useState(false);
  const [choice, setChoice] = useState({ offer: '', quantity: 1 });
  // The order form stays folded into one button until someone wants to order.
  const [formOpen, setFormOpen] = useState(false);

  useEffect(() => {
    api.get('/offers')
      .then((list) => {
        setOffers(list);
        setChoice((c) => (c.offer ? c : { ...c, offer: list[0]?.key || '' }));
      })
      .catch(() => setLoadError('Could not load the products. Please refresh the page.'));
  }, []);

  // Escape closes the sign-in pop-up.
  useEffect(() => {
    if (!showLogin) return undefined;
    const onKey = (e) => e.key === 'Escape' && setShowLogin(false);
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [showLogin]);

  function openOrder(key) {
    if (key) setChoice({ offer: key, quantity: 1 });
    setFormOpen(true);
    // After the form has opened, bring it into view.
    setTimeout(() => document.getElementById('order')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
  }

  const orderLink = (e) => {
    e.preventDefault();
    openOrder();
  };

  const priced = offers.filter((o) => o.price);
  const perPack = priced.length ? Math.min(...priced.map((o) => o.price / o.packs)) : 68;
  const hero = offers[0];

  return (
    <div className="lp">
      <div className="lp-announce">
        {priced.map((o) => <span key={o.key}>{o.packs} packs {o.short || o.name} {peso(o.price)}</span>)}
        <span>Pay when it arrives</span>
      </div>

      <header className="lp-top">
        <div className="lp-brand"><img src="/bge-logo.png" alt="" /><span><span className="brand-accent">BGE</span> Corporation</span></div>
        <nav>
          <a href="#products">Products</a>
          <a href="#how">How to order</a>
          <a href="#faq">Questions</a>
          <a href="#order" className="lp-btn lp-btn-sm" onClick={orderLink}>Order now</a>
          <button type="button" className="signin-link" onClick={() => setShowLogin(true)}><LogIn size={16} /> Sign in</button>
        </nav>
      </header>

      <section className="lp-hero">
        <div className="lp-hero-copy">
          <p className="lp-eyebrow">Longlife Philippines · Official distributor</p>
          <h1>Only {peso(perPack)} a pack. Delivered to your door.</h1>
          <p className="lp-lead">
            Longlife Pure Barley Powder and Malunggay Mangosteen Corn Coffee at distributor price.
            Order now, and pay only when the package is in your hands.
          </p>
          <div className="lp-price-list">
            {priced.map((o) => (
              <div key={o.key}>
                <span className="lp-price">{peso(o.price)}</span>
                <span className="lp-price-note">{o.packs} packs {o.short || o.name}</span>
              </div>
            ))}
          </div>
          <a href="#order" className="lp-btn lp-btn-lg" onClick={orderLink}><ShoppingBag size={20} /> Order now, pay on delivery</a>
        </div>
        {hero && (
          <div className="lp-hero-art">
            <img src={hero.image} alt={hero.name} />
          </div>
        )}
      </section>

      <div className="lp-strip">
        <span><Truck size={18} /> Delivered to your door</span>
        <span><Banknote size={18} /> Cash on delivery</span>
        <span><ShieldCheck size={18} /> Genuine Longlife products</span>
      </div>

      <section id="products" className="lp-section">
        <h2>Pick your product</h2>
        <p className="lp-sub">Distributor price: {peso(perPack)} per pack on minimum orders.</p>
        {loadError && <div className="alert alert-error">{loadError}</div>}
        <div className="lp-cards">
          {offers.map((o) => (
            <article key={o.key} className="lp-card">
              <div className="lp-card-photo">
                <img src={o.image} alt={o.name} loading="lazy" />
                {o.deal && <span className="lp-ribbon">{o.deal}</span>}
              </div>
              <div className="lp-card-body">
                <h3>{o.name}</h3>
                <p>{BLURBS[o.key] || o.size}</p>
                <p className="lp-card-size">{o.size}</p>
                <div className="lp-card-price">
                  {o.price
                    ? <><strong>{peso(o.price)}</strong><span>/ {o.packs} packs · {peso(o.price / o.packs)} each</span></>
                    : <span>Price confirmed when we call you</span>}
                </div>
                <button type="button" className="lp-btn" onClick={() => openOrder(o.key)}>
                  Order {o.short ? o.short.toLowerCase() : 'this'}
                </button>
              </div>
            </article>
          ))}
        </div>
      </section>

      <section id="how" className="lp-section">
        <h2>Ordering takes a minute</h2>
        <p className="lp-sub">You pay nothing until the package is in your hands.</p>
        <ol className="lp-steps">
          <li><span>1</span><strong>Fill in the form</strong><p>Choose your product and tell us where to deliver.</p></li>
          <li><span>2</span><strong>We call to confirm</strong><p>Our team calls or texts you before we ship. No online payment.</p></li>
          <li><span>3</span><strong>Pay the courier</strong><p>Cash on delivery when your order arrives.</p></li>
        </ol>
      </section>

      <OrderForm offers={offers} choice={choice} setChoice={setChoice} open={formOpen} onOpen={() => openOrder()} onClose={() => setFormOpen(false)} />

      <section id="faq" className="lp-section lp-faq">
        <h2>Questions</h2>
        {[
          ['How do I pay?', 'Cash on delivery. Pay the courier when your parcel arrives. No card or online payment needed.'],
          ['Is there a minimum order?', `Yes: ${priced.map((o) => `${o.packs} packs of ${o.short || o.name} for ${peso(o.price)}`).join(', and ')}. You can order more than one set.`],
          ['How much are the barley capsules?', 'We confirm the capsule price when we call you to confirm your order.'],
          ['Can I order both products?', 'Yes. Place one order per product, or write it in the notes and we will combine them when we call.'],
          ['How do I use the products?', 'Follow the directions printed on each pack. Ask us when we call if you have questions.'],
        ].map(([q, a]) => (
          <details key={q}>
            <summary>{q}<ChevronDown size={18} /></summary>
            <p>{a}</p>
          </details>
        ))}
      </section>

      <LegalFooter />

      {showLogin && (
        <div className="login-overlay" onMouseDown={() => setShowLogin(false)}>
          <div className="login-pop" onMouseDown={(e) => e.stopPropagation()} role="dialog" aria-modal="true" aria-label="Staff sign in">
            <button type="button" className="icon-btn login-close" onClick={() => setShowLogin(false)} aria-label="Close"><X size={20} /></button>
            <div className="login-pop-brand">
              <img src="/bge-logo.png" alt="" />
              <div><span className="brand-accent">BGE</span> Corporation<div className="muted small">Customer Relationship Management</div></div>
            </div>
            <LoginCard onSignedIn={onSignedIn} />
          </div>
        </div>
      )}
    </div>
  );
}

const EMPTY = { customer_name: '', phone: '', address: '', city: '', notes: '', website: '' };

function OrderForm({ offers, choice, setChoice, open, onOpen, onClose }) {
  const [form, setForm] = useState(EMPTY);
  const [errors, setErrors] = useState({});
  const [sending, setSending] = useState(false);
  const [done, setDone] = useState(null);
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });
  const err = (k) => (Array.isArray(errors[k]) ? errors[k][0] : errors[k]);

  const offer = offers.find((o) => o.key === choice.offer);
  const total = offer?.price ? offer.price * choice.quantity : null;

  async function submit(e) {
    e.preventDefault();
    if (!offer) {
      setErrors({ items: 'Choose a product.' });
      return;
    }
    setSending(true);
    setErrors({});
    try {
      const res = await api.post('/web-orders', { ...form, items: [{ offer: offer.key, quantity: Number(choice.quantity) }] });
      setDone({ ...res, name: form.customer_name, phone: form.phone });
      setForm(EMPTY);
    } catch (error) {
      setErrors(error.status === 429 ? { items: 'Too many orders from this device. Please wait a minute and try again.' } : error.errors || { items: error.message });
    } finally {
      setSending(false);
    }
  }

  if (!open && !done) {
    return (
      <section id="order" className="lp-section lp-order">
        <div className="lp-order-cta">
          <div>
            <h2>Ready to order?</h2>
            <p className="lp-muted">It takes a minute. No payment today; pay cash when it arrives.</p>
          </div>
          <button type="button" className="lp-btn lp-btn-lg" onClick={onOpen}><ShoppingBag size={20} /> Place my order</button>
        </div>
      </section>
    );
  }

  return (
    <section id="order" className="lp-section lp-order">
      <div className="lp-order-head">
        <div>
          <h2>Place your order</h2>
          <p className="lp-sub">Send your details and we will confirm your order by call or text.</p>
        </div>
        {!done && <button type="button" className="lp-close" onClick={onClose} aria-label="Close the order form"><X size={18} /> Close</button>}
      </div>

      <div className="lp-order-wrap lp-open">
      {done ? (
        <div className="lp-form lp-done">
          <CheckCircle2 size={56} />
          <h3>Salamat, {done.name}! Your order is in.</h3>
          <p>Order number <strong>{done.reference}</strong>{done.total > 0 && <> · Total <strong>{money(done.total)}</strong></>}{done.price_to_confirm && ' (price confirmed when we call)'}</p>
          <p className="lp-muted"><PhoneCall size={16} /> We will call or text <strong>{done.phone}</strong> to confirm. Pay cash when it arrives.</p>
          <button type="button" className="lp-btn" onClick={() => { setDone(null); onOpen(); }}>Place another order</button>
        </div>
      ) : (
        <form className="lp-form" onSubmit={submit} noValidate>
          <label className="lp-field">
            <span>Product</span>
            <select value={choice.offer} onChange={(e) => setChoice({ ...choice, offer: e.target.value })}>
              {offers.map((o) => (
                <option key={o.key} value={o.key}>{o.name}{o.price ? `, ${o.packs} packs, ${peso(o.price)}` : ', price by phone'}</option>
              ))}
            </select>
          </label>
          <label className="lp-field">
            <span>Number of sets{offer?.packs > 1 ? ` (${offer.packs} packs each)` : ''}</span>
            <select value={choice.quantity} onChange={(e) => setChoice({ ...choice, quantity: Number(e.target.value) })}>
              {Array.from({ length: 20 }, (_, i) => i + 1).map((n) => (
                <option key={n} value={n}>{n}{offer?.packs > 1 ? ` set${n > 1 ? 's' : ''} (${n * offer.packs} packs)` : ''}</option>
              ))}
            </select>
          </label>
          {err('items') && <span className="field-error">{err('items')}</span>}
          <label className="lp-field">
            <span>Full name</span>
            <input value={form.customer_name} onChange={set('customer_name')} autoComplete="name" required />
            {err('customer_name') && <span className="field-error">{err('customer_name')}</span>}
          </label>
          <label className="lp-field">
            <span>Mobile number</span>
            <input type="tel" inputMode="tel" value={form.phone} onChange={set('phone')} autoComplete="tel" placeholder="09XX XXX XXXX" required />
            {err('phone') && <span className="field-error">{err('phone')}</span>}
          </label>
          <label className="lp-field">
            <span>House no., street, barangay</span>
            <input value={form.address} onChange={set('address')} autoComplete="street-address" required />
            {err('address') && <span className="field-error">{err('address')}</span>}
          </label>
          <label className="lp-field">
            <span>City / municipality, province</span>
            <input value={form.city} onChange={set('city')} autoComplete="address-level2" placeholder="e.g. Cagayan de Oro, Misamis Oriental" required />
            {err('city') && <span className="field-error">{err('city')}</span>}
          </label>
          <label className="lp-field">
            <span>Landmark or notes (optional)</span>
            <input value={form.notes} onChange={set('notes')} placeholder="e.g. near the chapel; add the other product too" />
          </label>
          {/* Hidden from people; bots that fill it are ignored. */}
          <input className="hp-field" tabIndex={-1} autoComplete="off" value={form.website} onChange={set('website')} aria-hidden="true" />
          <div className="lp-total">
            <span>Total to pay on delivery</span>
            <strong>{total !== null ? peso(total) : 'Confirmed by phone'}</strong>
          </div>
          <button className="lp-btn lp-btn-lg lp-submit" disabled={sending}>{sending ? 'Sending…' : 'Place my order'}</button>
          <p className="lp-muted small">
            By ordering you agree to our <Link to="/terms">Terms and Conditions</Link> and that we may call or text you about
            this order, as explained in our <Link to="/privacy">Privacy Policy</Link>.
          </p>
        </form>
      )}
        {offer && (
          <aside className="lp-order-aside">
            <img src={offer.image} alt={offer.name} />
            <div>
              <strong>{offer.name}</strong>
              <p className="lp-muted">
                {offer.price ? `${choice.quantity * offer.packs} packs · ${peso(offer.price * choice.quantity)}` : 'Price confirmed when we call you'}
              </p>
              <ul>
                <li><CheckCircle2 size={16} /> No payment today</li>
                <li><CheckCircle2 size={16} /> We call or text to confirm before shipping</li>
                <li><CheckCircle2 size={16} /> Pay cash when it arrives</li>
              </ul>
            </div>
          </aside>
        )}
      </div>
    </section>
  );
}
