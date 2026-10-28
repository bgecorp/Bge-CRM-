import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api, download, qs } from '../api.js';
import { ChevronDown, FileSpreadsheet, Trash2 } from 'lucide-react';
import { Badge, ConfirmDelete, Empty, Loading, PageHeader, Panel, SortHeader, StatusGuide, UrgencyBadge, useApi } from '../components.jsx';
import { CATEGORY_LABELS, STATUS_LABELS, date, dueLabel, num, stockStatus } from '../format.js';
import StoreForm from './StoreForm.jsx';

export default function Stores() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const urlSearch = searchParams.get('search') || '';
  const [filters, setFilters] = useState({ search: urlSearch, category: '', status: '', sales_rep_id: '' });
  const [lastUrlSearch, setLastUrlSearch] = useState(urlSearch);
  // Phones show one short row per customer; these are the ones opened to show every detail.
  const [openIds, setOpenIds] = useState(() => new Set());
  const [allOpen, setAllOpen] = useState(false);
  const isOpen = (id) => allOpen || openIds.has(id);
  const toggle = (id) => {
    const next = new Set(openIds);
    if (isOpen(id)) {
      next.delete(id);
      if (allOpen) {
        setAllOpen(false);
        (stores || []).forEach((s) => s.id !== id && next.add(s.id));
      }
    } else {
      next.add(id);
    }
    setOpenIds(next);
  };

  // A new search from the top bar replaces the search box.
  if (urlSearch !== lastUrlSearch) {
    setLastUrlSearch(urlSearch);
    setFilters({ ...filters, search: urlSearch });
  }
  const [sort, setSort] = useState({ sort: 'urgency', dir: 'asc' });
  const [showForm, setShowForm] = useState(false);
  const [deleting, setDeleting] = useState(null);

  const params = qs({ ...filters, ...sort });
  const { data: stores, error, loading, reload } = useApi(() => api.get('/stores' + params), [params]);
  const { data: reps } = useApi(() => api.get('/sales-reps'), []);

  const [exporting, setExporting] = useState(false);
  const [exportError, setExportError] = useState('');
  async function exportExcel() {
    setExporting(true);
    setExportError('');
    try {
      const today = new Date().toLocaleDateString('en-CA');
      await download('/stores/export' + params, `customers-${today}.xlsx`);
    } catch (e) {
      setExportError(e.message);
    } finally {
      setExporting(false);
    }
  }

  const setFilter = (k) => (e) => setFilters({ ...filters, [k]: e.target.value });
  const onSort = (field) =>
    setSort((s) => ({ sort: field, dir: s.sort === field && s.dir === 'asc' ? 'desc' : 'asc' }));

  return (
    <>
      <PageHeader title="Customers" subtitle="Sari-sari stores and groceries you supply">
        <button
          className="btn"
          onClick={exportExcel}
          disabled={exporting || !stores?.length}
          title="Download the customers shown below, with the current search and filters, as an Excel file"
        >
          <FileSpreadsheet size={16} /> {exporting ? 'Exporting…' : 'Export to Excel'}
        </button>
        <button className="btn btn-primary" onClick={() => setShowForm(true)}>Register store</button>
      </PageHeader>
      {exportError && <div className="alert alert-error">{exportError}</div>}

      <div className="toolbar">
        <input className="search" placeholder="Search name, contact, phone or address" value={filters.search} onChange={setFilter('search')} />
        <select value={filters.category} onChange={setFilter('category')}>
          <option value="">All categories</option>
          {Object.entries(CATEGORY_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
        <select value={filters.status} onChange={setFilter('status')}>
          <option value="">All statuses</option>
          {Object.entries(STATUS_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
        <select value={filters.sales_rep_id} onChange={setFilter('sales_rep_id')}>
          <option value="">All representatives</option>
          {(reps || []).map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
        </select>
      </div>

      <StatusGuide columns={[
        ["Packs sold (30 days)", "How many packs this store sold in the last 30 days."],
        ["Predicted next purchase", "When the customer may order again, from their order history (for the product they’ll need soonest)."],
        ["Order status", "Whether their next order is expected already, today, soon, or later."],
      ]} />

      <Panel flush>
        <Loading error={error} loading={loading && !stores} />
        {stores && stores.length === 0 && <Empty>No stores match these filters.</Empty>}
        {stores && stores.length > 0 && (
          <div className="mobile-only expand-all">
            <span className="muted small">{stores.length} customer{stores.length === 1 ? '' : 's'}</span>
            <button type="button" className="btn btn-small" onClick={() => { setAllOpen(!allOpen); setOpenIds(new Set()); }}>
              {allOpen ? 'Hide all details' : 'Show all details'}
            </button>
          </div>
        )}
        {stores && stores.length > 0 && (
          <table className="table customers-table">
            <thead>
              <tr>
                <SortHeader label="Store name" field="name" {...sort} onSort={onSort} />
                <th>Category</th>
                <th>Contact person</th>
                <th>Representative</th>
                <th>Customer status</th>
                <SortHeader label="Packs sold (30 days)" field="sales" {...sort} onSort={onSort} />
                <SortHeader label="Predicted next purchase" field="urgency" {...sort} onSort={onSort} />
                <th>Order status</th>
                <th aria-label="Actions" />
              </tr>
            </thead>
            <tbody>
              {stores.map((s) => (
                <tr key={s.id} className={`clickable ${isOpen(s.id) ? 'open' : ''}`} onClick={() => navigate(`/stores/${s.id}`)}>
                  <td>
                    <div className="row-summary">
                      <div className="row-title">
                    {s.messenger_url ? (
                      <a href={s.messenger_url} target="_blank" rel="noopener noreferrer" title="Open chat in Business Suite" onClick={(e) => e.stopPropagation()}>
                        {s.name} <span className="fb-mark">Chat</span>
                      </a>
                    ) : s.facebook_url ? (
                      <a href={s.facebook_url} target="_blank" rel="noopener noreferrer" title="Open Facebook page" onClick={(e) => e.stopPropagation()}>
                        {s.name} <span className="fb-mark" aria-label="Facebook">f</span>
                      </a>
                    ) : (
                      <Link to={`/stores/${s.id}`} onClick={(e) => e.stopPropagation()}>{s.name}</Link>
                    )}
                      </div>
                      <span className={`mobile-only tag tag-${stockStatus(s.prediction).tone}`}>{stockStatus(s.prediction).label}</span>
                      <button
                        type="button"
                        className="mobile-only btn btn-small details-toggle"
                        aria-expanded={isOpen(s.id)}
                        onClick={(e) => { e.stopPropagation(); toggle(s.id); }}
                      >
                        {isOpen(s.id) ? 'Hide' : 'Details'} <ChevronDown size={15} />
                      </button>
                    </div>
                    <div className="muted small row-extra">{s.address}</div>
                  </td>
                  <td>{CATEGORY_LABELS[s.category]}</td>
                  <td>{s.contact_person}<div className="muted small">{s.phone}</div></td>
                  <td>{s.sales_rep?.name || <span className="muted">Unassigned</span>}</td>
                  <td><Badge tone={s.status === 'active' ? 'ok' : 'neutral'}>{STATUS_LABELS[s.status]}</Badge></td>
                  <td className="num">{num(s.sales_packs, 0)}</td>
                  <td>
                    {s.prediction?.next_purchase_date ? (
                      <>
                        {date(s.prediction.next_purchase_date)}
                        <div className="muted small">{dueLabel(s.prediction.days_until_next_purchase)} · {s.prediction.product_name}</div>
                      </>
                    ) : '-'}
                  </td>
                  <td><UrgencyBadge prediction={s.prediction} /></td>
                  <td className="right">
                    <button
                      className="btn btn-small btn-delete"
                      title={`Delete ${s.name}`}
                      onClick={(e) => { e.stopPropagation(); setDeleting(s); }}
                    >
                      <Trash2 size={15} /> Delete
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Panel>

      {deleting && (
        <ConfirmDelete
          title="Delete customer"
          message={`Delete "${deleting.name}"? Its recorded sales, stock counts, orders and follow-ups are deleted too.`}
          onClose={() => setDeleting(null)}
          onConfirm={async () => {
            await api.del(`/stores/${deleting.id}`);
            setDeleting(null);
            reload();
          }}
        />
      )}

      {showForm && (
        <StoreForm
          reps={reps || []}
          onClose={() => setShowForm(false)}
          onSaved={(s) => navigate(`/stores/${s.id}`)}
        />
      )}
    </>
  );
}
