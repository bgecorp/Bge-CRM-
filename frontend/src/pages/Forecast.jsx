import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api, qs } from '../api.js';
import { Empty, Field, Loading, PageHeader, Panel, SortHeader, StatusGuide, UrgencyBadge, useApi } from '../components.jsx';
import { BASIS_LABELS, PATTERN_LABELS, PREDICTION_GUIDE, date, dueLabel, num } from '../format.js';

export default function Forecast() {
  const [filters, setFilters] = useState({ product_id: '', pattern: '' });
  const [sort, setSort] = useState({ sort: 'next', dir: 'asc' });
  const params = qs({ ...filters, ...sort });
  const { data, error, loading } = useApi(() => api.get('/predictions' + params), [params]);
  const { data: products } = useApi(() => api.get('/products'), []);
  const set = (k) => (e) => setFilters({ ...filters, [k]: e.target.value });

  const onSort = (field) =>
    setSort((s) => ({ sort: field, dir: s.sort === field && s.dir === 'asc' ? 'desc' : 'asc' }));

  return (
    <>
      <PageHeader title="Stock prediction" subtitle="How long each customer’s latest order will last, and when they may order again" />

      <div className="toolbar">
        <select value={filters.product_id} onChange={set('product_id')} aria-label="Product">
          <option value="">All products</option>
          {(products || []).map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
        </select>
        <select value={filters.pattern} onChange={set('pattern')} aria-label="Buying pattern">
          <option value="">All buying patterns</option>
          {Object.entries(PATTERN_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
      </div>

      <Panel flush>
        <p className="notice">
          From order history only: <strong>daily consumption</strong> = packs ordered ÷ days until the next order (all past orders together);
          <strong> stock duration</strong> = latest order ÷ daily consumption; <strong>next purchase</strong> = latest order date + stock duration.
          1st order = record only, 2nd order = first estimate, 3rd onward = refined. These are estimates.
        </p>
        <div className="guide-wrap"><StatusGuide columns={PREDICTION_GUIDE} /></div>
        <Loading error={error} loading={loading && !data} />
        {data && data.length === 0 && <Empty>No orders match. Record orders on a customer’s page to start predicting.</Empty>}
        {data && data.length > 0 && (
          <table className="table">
            <thead>
              <tr>
                <SortHeader label="Customer" field="store" {...sort} onSort={onSort} />
                <th>Product</th>
                <SortHeader label="Total orders" field="orders" {...sort} onSort={onSort} />
                <SortHeader label="Total packs" field="packs" {...sort} onSort={onSort} />
                <th className="num">Average days between orders</th>
                <SortHeader label="Estimated daily consumption" field="usage" {...sort} onSort={onSort} />
                <th className="num">Latest order</th>
                <th className="num">Estimated stock duration</th>
                <SortHeader label="Predicted next purchase" field="next" {...sort} onSort={onSort} />
                <th>Buying pattern</th>
              </tr>
            </thead>
            <tbody>
              {data.map((p) => (
                <tr key={`${p.store_id}-${p.product_id}`}>
                  <td><Link to={`/stores/${p.store_id}`}>{p.store_name}</Link></td>
                  <td className="keep">{p.product_name}</td>
                  <td className="num">{num(p.total_orders, 0)}</td>
                  <td className="num">{num(p.total_packs)}</td>
                  <td className="num">{p.avg_days_between_orders !== null ? `${num(p.avg_days_between_orders, 1)} days` : '-'}</td>
                  <td className="num" title={p.avg_daily_consumption !== null ? `Exact: ${p.avg_daily_consumption}` : ''}>
                    {p.avg_daily_consumption !== null ? `${num(p.avg_daily_consumption)} /day` : '-'}
                  </td>
                  <td className="num">{num(p.latest_order_qty)}<div className="muted small">{date(p.latest_order_date)}</div></td>
                  <td className="num" title={p.estimated_stock_days !== null ? `Exact: ${p.estimated_stock_days}` : ''}>
                    {p.estimated_stock_days !== null ? `${num(p.estimated_stock_days, 1)} days` : '-'}
                  </td>
                  <td className="keep">
                    {p.next_purchase_date ? <>{date(p.next_purchase_date)} <div className="muted small">{dueLabel(p.days_until_next_purchase)}</div></> : '-'}
                    <UrgencyBadge prediction={p} />
                  </td>
                  <td>
                    {PATTERN_LABELS[p.buying_pattern]}
                    <div className="muted small">{BASIS_LABELS[p.basis]}</div>
                    <div className="muted small pred-summary">{p.summary}</div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Panel>

      <Calculator />
    </>
  );
}

const EXAMPLE = [
  { date: '2026-10-01', packs: '12' },
  { date: '2026-10-06', packs: '6' },
  { date: '2026-10-10', packs: '8' },
];

/** Same formula as the server, for trying out an order history by hand. */
function Calculator() {
  const [rows, setRows] = useState(EXAMPLE);
  const update = (i, k) => (e) => setRows(rows.map((r, j) => (j === i ? { ...r, [k]: e.target.value } : r)));

  const orders = rows
    .filter((r) => r.date && r.packs !== '' && Number(r.packs) >= 0)
    .map((r) => ({ date: new Date(r.date + 'T00:00:00'), packs: Number(r.packs) }))
    .sort((a, b) => a.date - b.date);
  const dayMs = 86400000;
  let packs = 0;
  let days = 0;
  for (let i = 0; i < orders.length - 1; i++) {
    packs += orders[i].packs;
    days += Math.round((orders[i + 1].date - orders[i].date) / dayMs);
  }
  const latest = orders[orders.length - 1];
  const rate = orders.length > 1 && days > 0 ? packs / days : null;
  const duration = rate > 0 ? latest.packs / rate : null;
  const next = duration !== null ? new Date(latest.date.getTime() + Math.ceil(duration) * dayMs) : null;

  let result;
  if (orders.length === 0) result = 'Enter at least one order.';
  else if (orders.length === 1) result = 'First order only. The estimate starts at the second order.';
  else if (!rate) result = 'Orders must be on different days with packs above 0.';
  else result = `Next order expected around ${next.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })}`;

  return (
    <Panel title="Try it: prediction calculator">
      <div className="calc">
        <div className="calc-inputs">
          {rows.map((r, i) => (
            <div key={i} className="calc-order">
              <Field label={`Order ${i + 1} date`}><input type="date" value={r.date} onChange={update(i, 'date')} /></Field>
              <Field label="Packs"><input type="number" min="0" step="1" value={r.packs} onChange={update(i, 'packs')} /></Field>
            </div>
          ))}
          <div className="calc-buttons">
            <button type="button" className="btn btn-small" onClick={() => setRows([...rows, { date: '', packs: '' }])}>Add order</button>
            {rows.length > 1 && <button type="button" className="btn btn-ghost btn-small" onClick={() => setRows(rows.slice(0, -1))}>Remove last</button>}
          </div>
        </div>
        <div className="calc-results">
          <div>
            <div className="stat-label">Daily consumption</div>
            <div className="stat-value small">{rate !== null ? `${num(rate)} packs/day` : '-'}</div>
            {rate !== null && <div className="muted small">{num(packs)} packs ÷ {num(days, 0)} days</div>}
          </div>
          <div>
            <div className="stat-label">Latest order lasts</div>
            <div className="stat-value small">{duration !== null ? `${num(duration, 2)} days` : '-'}</div>
            {duration !== null && <div className="muted small">{num(latest.packs)} ÷ {num(rate)}</div>}
          </div>
          <div><div className="stat-label">Predicted next purchase</div><div className="stat-value small">{result}</div></div>
        </div>
      </div>
      <p className="hint">Assumes each order is used up by the time of the next one. Leftovers or extra stock make it less accurate.</p>
    </Panel>
  );
}
