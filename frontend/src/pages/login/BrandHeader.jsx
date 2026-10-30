/** The 3D cube, the company name and "Sales Intelligence Hub" at the top of the card. */
export default function BrandHeader() {
  return (
    <div className="lg-brand">
      <img src="/signin-logo.png" alt="" width="110" height="116" />
      <p className="lg-brand-name"><span>BGE</span>Corporation</p>
      <p className="lg-brand-hub">Sales Intelligence Hub</p>
    </div>
  );
}
