import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  ArrowDownRight, ArrowUpRight, Boxes, CalendarPlus, ChartColumn, ChevronRight, Clock3, Handshake, Lightbulb,
  PackageCheck, PackagePlus, ShoppingCart, TriangleAlert, UserPlus, Users,
} from 'lucide-react';
import { api } from '../api.js';
import { Empty, Loading, useApi } from '../components.jsx';
import { CATEGORY_LABELS, FOLLOW_UP_TYPE_LABELS, PATTERN_LABELS, date, dueLabel, money, num, OUTCOME_LABELS, stockStatus } from '../format.js';
import StoreForm from './StoreForm.jsx';
import QuickRecordModal from './QuickActions.jsx';
import { NewFollowUp } from './FollowUps.jsx';

function greeting() {
  const h = new Date().getHours();
  if (h < 12) return 'Good morning';
  if (h < 18) return 'Good afternoon';
  return 'Good evening';
}

const urgency = stockStatus;

export default function Dashboard() {
  const navigate = useNavigate();
  const { data, error, loading, reload } = useApi(() => api.get('/dashboard'), []);
  const { data: reps } = useApi(() => api.get('/sales-reps'), []);
  const [modal, setModal] = useState(null);

  if (!data) return <Loading error={error} loading={loading} />;

  const { totals, week, sales, outcomes, next_purchases: np, first_order_only: firstOnly } = data;
  const orderingSoon = np.past_due + np.today + np.next_3_days;
  const done = () => { setModal(null); reload(); };

  return (
    <div className="dash">
      <div className="dash-main">
        <section className="welcome">
          <span className="welcome-icon"><Handshake size={30} /></span>
          <div>
            <h1>{greeting()}!</h1>
            <p>Here’s your business overview today.</p>
          </div>
        </section>

        <div className="kpis">
          <Kpi tone="blue" icon={Users} label="Total Customers" value={num(totals.stores, 0)}
            note={totals.new_this_week ? <><ArrowUpRight size={14} /> {totals.new_this_week} new this week</> : `${num(totals.active_customers, 0)} active`} noteTone="up" />
          <Kpi tone="green" icon={Boxes} label="Packs Sold (7 days)" value={num(week.packs, 0)}
            note={week.change_pct === null ? 'No sales the week before' : (
              <>{week.change_pct >= 0 ? <ArrowUpRight size={14} /> : <ArrowDownRight size={14} />} {num(Math.abs(week.change_pct), 0)}% from last week</>
            )}
            noteTone={week.change_pct === null ? '' : week.change_pct >= 0 ? 'up' : 'down'} />
          <Kpi tone="orange" icon={TriangleAlert} label="Ordering Soon" value={num(orderingSoon, 0)}
            note={np.past_due ? `${np.past_due} expected already` : 'Expected within 3 days'} noteTone="alert" />
          <Kpi tone="purple" icon={ChartColumn} label="Top Performer" value={week.top_performer?.name ?? '-'} small
            note={week.top_performer ? `${num(week.top_performer.packs)} packs (this week)` : 'No sales this week'} />
        </div>

        <div className="dash-row">
          <section className="card">
            <header className="card-head">
              <div>
                <h2>Sales Performance</h2>
                <p className="muted small">Top 5 customers by total packs sold, last {sales.period_days} days</p>
              </div>
              <Link to="/performance" className="small">View all</Link>
            </header>
            <TopCustomersChart rows={sales.top_stores.slice(0, 5)} />
          </section>

          <section className="card">
            <header className="card-head">
              <span className="head-icon"><Clock3 size={20} /></span>
              <div>
                <h2>Stock Prediction</h2>
                <p className="muted small">Predicted next purchase, from order history · * first estimate</p>
              </div>
              <Link to="/forecast" className="small push">View all</Link>
            </header>
            {data.stock_prediction.length === 0 ? (
              <Empty>Predictions start when a customer orders a second time.</Empty>
            ) : (
              <ul className="pred-list">
                {data.stock_prediction.map((p) => {
                  const u = urgency(p);
                  return (
                    <li key={p.store_id}>
                      <Link to={`/stores/${p.store_id}`}>{p.store_name}</Link>
                      <span className={`pill pill-${u.tone}`} title={`${p.product_name}: latest ${num(p.latest_order_qty)} packs last about ${num(p.estimated_stock_days, 1)} days`}>{date(p.next_purchase_date)}</span>
                      <span className={`tag tag-${u.tone}`}>{dueLabel(p.days_until_next_purchase)}</span>
                      {p.basis === 'first_estimate' && <span className="prov" title="First estimate: based on only two orders">*</span>}
                    </li>
                  );
                })}
              </ul>
            )}
          </section>
        </div>

        <section className="card">
          <header className="card-head">
            <span className="head-icon"><Users size={20} /></span>
            <h2>Recent Customers</h2>
            <Link to="/stores" className="small push">View all</Link>
          </header>
          {data.recent_customers.length === 0 ? <Empty>No customers yet.</Empty> : (
            <div className="scroll-x">
              <table className="table table-soft">
                <thead>
                  <tr>
                    <th>Customer name</th>
                    <th>Category</th>
                    <th className="num">Packs sold (30 days)</th>
                    <th className="num">Daily consumption</th>
                    <th>Predicted next purchase</th>
                    <th>Buying pattern</th>
                    <th>Order status</th>
                  </tr>
                </thead>
                <tbody>
                  {data.recent_customers.map((c) => {
                    const u = urgency(c);
                    return (
                      <tr key={c.id} className="clickable" onClick={() => navigate(`/stores/${c.id}`)}>
                        <td><span className="row-avatar">{c.name.slice(0, 1).toUpperCase()}</span>{c.name}</td>
                        <td>{CATEGORY_LABELS[c.category]}</td>
                        <td className="num">{num(c.packs)}</td>
                        <td className="num">{c.avg_daily_consumption ? `${num(c.avg_daily_consumption)} /day` : '-'}</td>
                        <td>{c.next_purchase_date ? date(c.next_purchase_date) : '-'}</td>
                        <td>{c.buying_pattern ? PATTERN_LABELS[c.buying_pattern] : '-'}</td>
                        <td className="keep"><span className={`tag tag-${u.tone}`}>{c.basis ? u.label : 'No orders yet'}</span></td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </section>

        {firstOnly.length > 0 && (
          <section className="card">
            <header className="card-head">
              <span className="head-icon warn"><TriangleAlert size={20} /></span>
              <h2>Waiting for a 2nd order</h2>
            </header>
            <ul className="review-list">
              {firstOnly.map((p) => (
                <li key={`${p.store_id}-${p.product_id}`}>
                  <Link to={`/stores/${p.store_id}`}>{p.store_name}</Link> · {p.product_name}
                  <div className="muted small">{p.summary}</div>
                </li>
              ))}
            </ul>
          </section>
        )}

        <div className="dash-row">
          <section className="card">
            <header className="card-head"><h2>Expected orders</h2><Link to="/forecast" className="small push">Stock prediction</Link></header>
            <div className="pipeline">
              {[['Expected already', np.past_due, 'danger'], ['Today', np.today, 'warning'], ['Next 3 days', np.next_3_days, ''], ['4–7 days', np.next_7_days, ''], ['Later', np.later, ''], ['1st order only', np.first_order_only, '']].map(([label, value, tone]) => (
                <div key={label} className="pipe">
                  <div className={`pipe-value ${value && tone ? `text-${tone}` : ''}`}>{num(value, 0)}</div>
                  <div className="muted small">{label}</div>
                </div>
              ))}
            </div>
            <div className="subhead">Customer responses, last {sales.period_days} days</div>
            <div className="pipeline">
              {Object.entries(OUTCOME_LABELS).map(([k, label]) => (
                <div key={k} className="pipe"><div className="pipe-value">{num(outcomes[k] || 0, 0)}</div><div className="muted small">{label}</div></div>
              ))}
            </div>
          </section>
          <section className="card">
            <header className="card-head"><h2>Sales by representative</h2></header>
            {sales.by_rep.length === 0 ? <Empty>No sales yet.</Empty> : (
              <table className="table table-soft">
                <thead><tr><th>Representative</th><th className="num">Stores</th><th className="num">Packs</th><th className="num">Value</th></tr></thead>
                <tbody>
                  {sales.by_rep.map((r) => (
                    <tr key={r.id ?? 'none'}><td>{r.name}</td><td className="num">{num(r.stores, 0)}</td><td className="num">{num(r.packs, 0)}</td><td className="num">{money(r.amount)}</td></tr>
                  ))}
                </tbody>
              </table>
            )}
          </section>
        </div>
      </div>

      <aside className="dash-side">
        <section className="card">
          <h2 className="side-title">Quick Actions</h2>
          <div className="quick">
            <button className="qa qa-green" onClick={() => setModal('customer')}><UserPlus size={20} /> Add New Customer</button>
            <button className="qa qa-blue" onClick={() => setModal('sale')}><ShoppingCart size={20} /> Record Sale</button>
            <button className="qa qa-orange" onClick={() => setModal('order')}><PackagePlus size={20} /> Record Order</button>
            <button className="qa qa-purple" onClick={() => setModal('followup')}><CalendarPlus size={20} /> Create Follow-up</button>
          </div>
        </section>

        <section className="card">
          <header className="card-head">
            <span className="head-icon"><PackageCheck size={20} /></span>
            <h2>Upcoming Follow-ups</h2>
            <Link to="/follow-ups" className="small push">View all</Link>
          </header>
          {data.upcoming_follow_ups.length === 0 ? <Empty>Nothing scheduled.</Empty> : (
            <ul className="upcoming">
              {data.upcoming_follow_ups.map((f) => {
                const d = new Date(f.due_date + 'T00:00:00');
                const overdue = f.due_date < data.as_of;
                return (
                  <li key={f.id}>
                    <Link to={`/stores/${f.store_id}`} className="upcoming-item">
                      <span className={`date-tile ${overdue ? 'overdue' : ''}`}>
                        <span>{d.toLocaleDateString('en-PH', { month: 'short' })}</span>
                        <strong>{d.getDate()}</strong>
                      </span>
                      <span className="upcoming-text">
                        <strong>{f.store?.name}</strong>
                        <span className="muted small">{overdue ? 'Overdue · ' : ''}{FOLLOW_UP_TYPE_LABELS[f.type]}</span>
                      </span>
                      <ChevronRight size={18} className="muted" />
                    </Link>
                  </li>
                );
              })}
            </ul>
          )}
        </section>

        <Reminder data={data} />
      </aside>

      {modal === 'customer' && <StoreForm reps={reps || []} onClose={() => setModal(null)} onSaved={(s) => navigate(`/stores/${s.id}`)} />}
      {(modal === 'sale' || modal === 'order') && <QuickRecordModal kind={modal} onClose={() => setModal(null)} onSaved={done} />}
      {modal === 'followup' && <NewFollowUp onClose={() => setModal(null)} onSaved={done} />}
    </div>
  );
}

function Kpi({ tone, icon: Icon, label, value, note, noteTone, small }) {
  return (
    <div className={`kpi kpi-${tone}`}>
      <span className="kpi-icon"><Icon size={22} /></span>
      <div className="kpi-label">{label}</div>
      <div className={`kpi-value ${small ? 'kpi-value-text' : ''}`} title={typeof value === 'string' ? value : undefined}>{value}</div>
      {note && <div className={`kpi-note ${noteTone ? `note-${noteTone}` : ''}`}>{note}</div>}
    </div>
  );
}

/** Single-series column chart: one colour, value labels, hover tooltip, recessive baseline. */
function TopCustomersChart({ rows }) {
  const [hover, setHover] = useState(null);
  if (rows.length === 0) return <Empty>No sales in this period.</Empty>;
  const max = Math.max(...rows.map((r) => Number(r.packs)), 1);

  return (
    <div className="colchart" role="img" aria-label={`Top customers by packs sold: ${rows.map((r) => `${r.name} ${num(r.packs)}`).join(', ')}`}>
      <div className="colchart-plot">
        {rows.map((r) => (
          <div
            key={r.id}
            className="col"
            onMouseEnter={() => setHover(r.id)}
            onMouseLeave={() => setHover(null)}
          >
            <span className="col-value">{num(r.packs)}</span>
            <span className="col-bar" style={{ height: `${(Number(r.packs) / max) * 100}%` }} />
            {hover === r.id && (
              <span className="col-tip">
                <strong>{r.name}</strong>
                <span>{num(r.packs)} packs · {money(r.amount)}</span>
              </span>
            )}
          </div>
        ))}
      </div>
      <div className="colchart-labels">
        {rows.map((r) => <Link key={r.id} to={`/stores/${r.id}`} title={r.name}>{r.name}</Link>)}
      </div>
    </div>
  );
}

function Reminder({ data }) {
  const { next_purchases: np } = data;
  let title = 'Reminder';
  let text = 'Record every order. The more orders a customer has, the better the CRM can predict when they will buy again.';
  if (np.past_due > 0) {
    title = `${np.past_due} order${np.past_due === 1 ? '' : 's'} expected already`;
    text = 'These customers were expected to run out and have not ordered again yet. Check the Stock Prediction page.';
  } else if (np.first_order_only > 0) {
    title = 'Some customers have only one order';
    text = `${np.first_order_only} customer product${np.first_order_only === 1 ? '' : 's'} will get a prediction once a second order is recorded.`;
  }
  return (
    <section className="reminder">
      <h2><Lightbulb size={22} /> {title}</h2>
      <p>{text}</p>
    </section>
  );
}
