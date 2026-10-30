import LoginCard from './LoginCard.jsx';
import './login.css';

/** The /login page: the city background with the 3D logo, and the sign-in card. */
export default function LoginPage({ onSignedIn }) {
  return (
    <div className="lg-page">
      <div className="lg-stage">
        <img className="lg-bg" src="/signin-bg.webp" alt="" width="1671" height="941" decoding="async" fetchPriority="high" />
        <div className="lg-halo" aria-hidden="true" />
        <LoginCard onSignedIn={onSignedIn} />
      </div>
    </div>
  );
}
