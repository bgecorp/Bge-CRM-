import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api, qs } from '../api.js';
import { Empty, Loading, PageHeader, Panel, useApi } from '../components.jsx';
import { CATEGORY_LABELS, PATTERN_LABELS, date, dueLabel, money, num, stockStatus, today } from '../format.js';

const METRICS = [
  { key: 'packs', label: 'Total packs sold', purpose: 'Highest sales volume' },
  { key: 'avg_daily', label: 'Average daily sales', purpose: 'Sells products fastest' },
  { key: 'stock_days', label: 'Latest order lasts (days)', purpose: 'Will run out soonest' },
  { key: 'growth', label: 'Sales increasing or decreasing', purpose: 'Biggest change compared with the period before' },
  { key: 'days_since_order', label: 'Days since last order', purpose: 'May need re-engagement' },
  { key: 'orders', label: 'Reorder frequency', purpose: 'Orders most often' },
  { key: 'priority', label: 'Predicted next purchase', purpose: 'Expected to order soonest' },
  { key: 'amount', label: 'Sales value', purpose: 'Highest peso value' },
];

function daysAgo(n) {
  const d = new Date();
  d.setDate(d.getDate() - n);
  const pad = (x) => String(x).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export default function Performance() {
  const [f, setF] = useState({ from: daysAgo(29), to: today(), category: '', sales_rep_id: '', trend: '', sort: 'packs', dir: '' });
  const params = qs(f);
  const { data, error, loading } = useApi(() => api.get('/performance' + params), [params]);
  const { data: reps } = useApi(() => api.get('/sales-reps'), []);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value, ...(k === 'sort' ? { dir: '' } : {}) });
  const metric = METRICS.find((m) => m.key === f.sort);

  return (
    <>
      <PageHeader title="Sales performance" subtitle="Rank customers several ways; compare stores within their category" />

      <div className="toolbar">
        <label className="inline">From <input type="date" value={f.from} max={f.to} onChange={set('from')} /></label>
        <label className="inline">To <input type="date" value={f.to} max={today()} onChange={set('to')} /></label>
        <select value={f.category} onChange={set('category')}>
          <option value="">All categories</option>
          {Object.entries(CATEGORY_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
        <select value={f.sales_rep_id} onChange={set('sales_rep_id')}>
          <option value="">All representatives</option>
          {(reps || []).map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
        </select>
        <select value={f.trend} onChange={set('trend')} aria-label="Sales trend">
          <option value="">Sales going up or down</option>
          <option value="up">Sales increasing</option>
          <option value="down">Sales decreasing</option>
        </select>
        <label className="inline">
          Rank by
          <select value={f.sort} onChange={set('sort')}>
            {METRICS.map((m) => <option key={m.key} value={m.key}>{m.label}</option>)}
          </select>
        </label>
        <button className="btn" onClick={() => setF({ ...f, dir: (data?.dir === 'asc' ? 'desc' : 'asc') })}>
          {data?.dir === 'asc' ? 'Ascending ▲' : 'Descending ▼'}
        </button>
      </div>

      <Panel flush>
        <p className="notice">
          {metric?.label}: {metric?.purpose.toLowerCase()}. Range {date(data?.from)} – {date(data?.to)}; growth compares with {date(data?.previous_from)} – {date(data?.previous_to)}.
          “Category rank” compares each store only with stores of the same category.
        </p>
        <Loading error={error} loading={loading && !data} />
        {data && data.rows.length === 0 && <Empty>No active stores match these filters.</Empty>}
        {data && data.rows.length > 0 && (
          <div className="scroll-x">
            <table className="table">
              <thead>
                <tr>
                  <th>Store</th>
                  <th>Rank in its category</th>
                  <th className="num">Packs sold</th>
                  <th className="num">Average Daily Sales</th>
                  <th className="num">Latest order lasts</th>
                  <th className="num">Change vs. before</th>
                  <th className="num">Days since last order</th>
                  <th className="num">Orders in this period</th>
                  <th>Predicted next purchase</th>
                  <th className="num">Sales value</th>
                </tr>
              </thead>
              <tbody>
                {data.rows.map((r) => (
                  <tr key={r.id}>
                    <td>
                      <Link to={`/stores/${r.id}`}>{r.name}</Link>
                      <div className="muted small">{CATEGORY_LABELS[r.category]}{r.sales_rep ? ` · ${r.sales_rep}` : ''}</div>
                    </td>
                    <td>{r.category_rank ? `#${r.category_rank} of ${r.category_size}` : '-'}</td>
                    <td className="num keep">{num(r.packs)}</td>
                    <td className="num" title={r.avg_daily_sales !== null ? `Exact: ${r.avg_daily_sales}` : ''}>
                      {r.avg_daily_sales !== null ? num(r.avg_daily_sales) : '-'}
                      <div className="muted small">{r.selling_days} selling days</div>
                    </td>
                    <td className="num">
                      {r.estimated_stock_days !== null ? `${num(r.estimated_stock_days, 1)} days` : '-'}
                      <div><span className={`tag tag-${stockStatus(r).tone}`}>{stockStatus(r).label}</span></div>
                    </td>
                    <td className={`num ${r.growth_pct > 0 ? 'text-ok' : r.growth_pct < 0 ? 'text-danger' : ''}`}>
                      {r.growth_pct !== null ? `${r.growth_pct > 0 ? '+' : ''}${num(r.growth_pct, 1)}%` : r.growth_note === 'new' ? 'New' : '-'}
                    </td>
                    <td className="num">{r.days_since_last_order !== null ? `${r.days_since_last_order}d` : <span className="muted">No orders</span>}</td>
                    <td className="num">
                      {r.orders_in_range}
                      {r.avg_days_between_orders !== null && <div className="muted small">every ~{num(r.avg_days_between_orders, 1)}d</div>}
                    </td>
                    <td>
                      {r.next_purchase_date ? <>{date(r.next_purchase_date)}<div className="muted small">{dueLabel(r.days_until_next_purchase)}</div></> : '-'}
                      {r.buying_pattern && r.buying_pattern !== 'new' && <div className="muted small">{PATTERN_LABELS[r.buying_pattern]} buyer</div>}
                    </td>
                    <td className="num">{money(r.amount)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </>
  );
}
