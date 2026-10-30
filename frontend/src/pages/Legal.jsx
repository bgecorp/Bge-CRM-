import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { BUSINESS } from '../business.js';

const missing = <em className="lp-missing">(to be added)</em>;

function Contact() {
  return (
    <ul>
      <li>Email: {BUSINESS.email || missing}</li>
      <li>Phone: {BUSINESS.phone || missing}</li>
      <li>Address: {BUSINESS.address || missing}</li>
    </ul>
  );
}

function Privacy() {
  return (
    <>
      <h1>Privacy Policy</h1>
      <p className="lp-muted">Effective {BUSINESS.effectiveDate}</p>

      <p>
        This policy explains what {BUSINESS.name} collects when you order through this website, why we collect it,
        and what you can ask us to do with it. We follow the Data Privacy Act of 2012 (Republic Act No. 10173).
      </p>

      <h2>What we collect</h2>
      <p>When you place an order, we collect what you type into the order form:</p>
      <ul>
        <li>your full name</li>
        <li>your mobile number</li>
        <li>your delivery address, city or municipality and province</li>
        <li>any landmark or notes you add</li>
        <li>the product and number of sets you order</li>
      </ul>
      <p>
        We also record the internet (IP) address your order came from. We use it only to stop spam and repeated fake orders.
      </p>
      <p>We do not ask for card, bank or e-wallet details. You pay in cash when your order arrives.</p>

      <h2>Why we use it</h2>
      <ul>
        <li>to call or text you to confirm your order</li>
        <li>to deliver your order and collect payment on delivery</li>
        <li>to answer your questions about your order</li>
        <li>to keep sales records our business is required to keep</li>
      </ul>
      <p>We do not sell your information, and we do not use it for advertising.</p>

      <h2>Who sees it</h2>
      <p>
        Only our own staff, who sign in with their own accounts, can see your order. We give your name, number and address
        to the courier who delivers your parcel, and only what they need to deliver it.
      </p>

      <h2>Cookies and tracking</h2>
      <p>
        This website does not use advertising or tracking cookies. The page loads its fonts from Google Fonts, so your
        browser connects to Google when the page opens. Staff who sign in have their session saved in their own browser
        until they sign out.
      </p>

      <h2>How long we keep it</h2>
      <p>
        We keep your order record while we need it for your order and our sales records. You can ask us to delete it at any
        time, and we will, unless the law requires us to keep it.
      </p>

      <h2>How we protect it</h2>
      <p>
        Order details are stored in our private system. Staff accounts need a password, sign-ins are logged, and the order
        form limits how often orders can be sent from one device.
      </p>

      <h2>Your rights</h2>
      <p>
        Under the Data Privacy Act you can ask to see the information we hold about you, have it corrected, or have it
        deleted. You can also object to how we use it. If you believe we have misused it, you can file a complaint with the
        National Privacy Commission.
      </p>

      <h2>Contact us</h2>
      <p>For any request about your information, contact {BUSINESS.name}:</p>
      <Contact />

      <h2>Changes to this policy</h2>
      <p>If we change this policy, we will update it on this page and change the date at the top.</p>
    </>
  );
}

function Terms() {
  return (
    <>
      <h1>Terms and Conditions</h1>
      <p className="lp-muted">Effective {BUSINESS.effectiveDate}</p>

      <p>
        These terms apply when you order from {BUSINESS.name} through this website. By placing an order you agree to them.
      </p>

      <h2>Orders</h2>
      <ul>
        <li>Sending the order form is a request to order. Your order is confirmed only when we call or text you to confirm it.</li>
        <li>We may decline an order, for example when we cannot reach you, cannot deliver to your area, or the product is out of stock.</li>
        <li>Each product is sold in sets with a minimum number of packs, shown on the website.</li>
      </ul>

      <h2>Prices</h2>
      <ul>
        <li>Prices are in Philippine pesos and are the prices shown when you order.</li>
        <li>Where the website says the price is confirmed by phone, we tell you the price when we call. You can cancel then at no cost.</li>
        <li>If there is a delivery fee for your area, we tell you when we call, before we ship.</li>
      </ul>

      <h2>Payment</h2>
      <p>You pay in cash to the courier when your order arrives. We do not take payment through this website.</p>

      <h2>Delivery</h2>
      <p>
        We deliver to the address you give us. Please make sure it is complete and that someone can receive and pay for the
        parcel. Delivery times depend on your location, and we tell you the expected date when we confirm your order.
      </p>

      <h2>Cancelling an order</h2>
      <p>You can cancel any time before we ship by calling or texting us. Please do not refuse a parcel you ordered without telling us first.</p>

      <h2>Returns</h2>
      <p>
        Check your parcel when it arrives. If an item is damaged, wrong or missing, contact us within 7 days of delivery
        and we will replace it or refund you. Your rights under the Consumer Act of the Philippines (Republic Act No. 7394)
        are not affected.
      </p>

      <h2>About the products</h2>
      <p>
        Our products are food supplements, not medicine. They do not cure or treat any disease. Follow the directions on
        each pack, and ask your doctor first if you are pregnant, nursing, taking medicine or have a medical condition.
      </p>

      <h2>Using this website</h2>
      <p>
        Please do not send fake orders, use someone else's details, or try to break into or overload the website. We may
        block orders that look like this.
      </p>

      <h2>Your information</h2>
      <p>How we handle your details is explained in our <Link to="/privacy">Privacy Policy</Link>.</p>

      <h2>Contact us</h2>
      <Contact />

      <h2>Changes to these terms</h2>
      <p>We may update these terms. The version on this page on the day you order is the one that applies to that order.</p>
    </>
  );
}

/** Public Privacy Policy and Terms pages, styled like the website. */
export default function Legal({ page }) {
  useEffect(() => {
    document.title = `${page === 'terms' ? 'Terms and Conditions' : 'Privacy Policy'} | ${BUSINESS.name}`;
    window.scrollTo(0, 0);
    return () => { document.title = 'BGE Corporation'; };
  }, [page]);

  return (
    <div className="lp">
      <header className="lp-top">
        <Link to="/login" className="lp-brand" style={{ textDecoration: 'none' }}>
          <img src="/bge-logo.png" alt="" /><span><span className="brand-accent">BGE</span> Corporation</span>
        </Link>
        <nav>
          <Link to="/login" className="lp-back"><ArrowLeft size={16} style={{ verticalAlign: '-3px' }} /> Back to sign in</Link>
        </nav>
      </header>
      <main className="lp-legal">
        {page === 'terms' ? <Terms /> : <Privacy />}
      </main>
      <LegalFooter />
    </div>
  );
}

function LegalFooter() {
  return (
    <footer className="lp-foot">
      <div><span className="brand-accent">BGE</span> Corporation · Distributor of Longlife products</div>
      <p>These products are food supplements, not medicine, and do not cure or treat any disease. Consult your doctor if you have a medical condition.</p>
      <p className="lp-foot-links"><Link to="/privacy">Privacy Policy</Link><Link to="/terms">Terms and Conditions</Link></p>
    </footer>
  );
}
