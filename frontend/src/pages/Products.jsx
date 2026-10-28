import { useEffect, useState } from 'react';
import { ImagePlus, Package, Pencil, Plus, Trash2 } from 'lucide-react';
import { api } from '../api.js';
import { isSuperAdmin, useUser } from '../auth.js';
import { ConfirmDelete, Empty, Field, Loading, Modal, PageHeader, useApi } from '../components.jsx';
import { money, num } from '../format.js';

export default function Products() {
  const { data, error, loading, reload } = useApi(() => api.get('/products'), []);
  const [editing, setEditing] = useState(null); // null = closed, {} = new, product = edit
  const [deleting, setDeleting] = useState(null);
  const [search, setSearch] = useState('');
  // Representatives can look but not change products.
  const canEdit = isSuperAdmin(useUser());

  const shown = (data || []).filter((p) => p.name.toLowerCase().includes(search.trim().toLowerCase()));

  return (
    <>
      <PageHeader title="Products" subtitle={data ? `${data.length} product${data.length === 1 ? '' : 's'}` : 'Everything you sell to your customers'}>
        {canEdit && <button className="btn btn-primary" onClick={() => setEditing({})}><Plus size={18} /> Add product</button>}
      </PageHeader>

      <div className="toolbar">
        <input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search products…" aria-label="Search products" />
      </div>

      <Loading error={error} loading={loading && !data} />
      {data && data.length === 0 && <Empty>No products yet.</Empty>}
      {data && data.length > 0 && shown.length === 0 && <Empty>No product matches “{search}”.</Empty>}

      <div className="product-grid">
        {shown.map((p) => (
          <article key={p.id} className="product-card">
            <div className="product-photo">
              {p.image_url ? <img src={p.image_url} alt={p.name} loading="lazy" /> : <Package size={40} aria-hidden="true" />}
            </div>
            <div className="product-info">
              <h3>{p.name}</h3>
              <div className="product-price">{money(p.price)} <span className="muted small">per {p.unit}</span></div>
              {p.sku && <div className="muted small">Code: {p.sku}</div>}
              <dl className="product-stats">
                <div><dt>Sold (30 days)</dt><dd>{num(p.packs_30_days, 0)} {p.unit}s</dd></div>
                <div><dt>Customers</dt><dd>{num(p.customers, 0)}</dd></div>
              </dl>
            </div>
            {canEdit && <div className="product-actions">
              <button className="btn btn-small" onClick={() => setEditing(p)}><Pencil size={15} /> Edit</button>
              <button className="btn btn-ghost btn-small" onClick={() => setDeleting(p)} aria-label={`Delete ${p.name}`}><Trash2 size={15} /></button>
            </div>}
          </article>
        ))}
      </div>

      {editing && (
        <ProductForm product={editing.id ? editing : null} onClose={() => { setEditing(null); reload(); }} onSaved={() => { setEditing(null); reload(); }} />
      )}
      {deleting && (
        <ConfirmDelete
          title="Delete product"
          message={`Delete ${deleting.name}? Products with recorded sales, stock counts or orders can’t be deleted.`}
          onClose={() => setDeleting(null)}
          onConfirm={async () => { await api.del(`/products/${deleting.id}`); setDeleting(null); reload(); }}
        />
      )}
    </>
  );
}

function ProductForm({ product, onClose, onSaved }) {
  const [form, setForm] = useState({
    name: product?.name ?? '', sku: product?.sku ?? '', unit: product?.unit ?? 'pack', price: product?.price ?? '',
  });
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(product?.image_url ?? null);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  // Show the chosen picture before it is uploaded.
  useEffect(() => {
    if (!file) return undefined;
    const url = URL.createObjectURL(file);
    setPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    const body = new FormData();
    Object.entries({ ...form, price: form.price === '' ? 0 : form.price }).forEach(([k, v]) => body.append(k, v ?? ''));
    if (file) body.append('image', file);
    try {
      await api.post(product ? `/products/${product.id}` : '/products', body);
      onSaved();
    } catch (err) {
      setErrors(err.errors || { name: err.message });
    } finally {
      setSaving(false);
    }
  }

  async function removePicture() {
    if (file) {
      setFile(null);
      setPreview(product?.image_url ?? null);
      return;
    }
    await api.del(`/products/${product.id}/image`);
    setPreview(null);
  }

  return (
    <Modal title={product ? `Edit ${product.name}` : 'Add product'} onClose={onClose}>
      <form onSubmit={submit} className="form-grid">
        <div className="span-2 photo-picker">
          <div className="product-photo">
            {preview ? <img src={preview} alt="" /> : <Package size={40} aria-hidden="true" />}
          </div>
          <div>
            <label className="btn">
              <ImagePlus size={17} /> {preview ? 'Change picture' : 'Add picture'}
              <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" hidden onChange={(e) => setFile(e.target.files?.[0] || null)} />
            </label>
            {preview && (file || product?.image_url) && (
              <button type="button" className="btn btn-ghost" onClick={removePicture}>Remove picture</button>
            )}
            <p className="hint">JPG, PNG or WebP, up to 5 MB. You can take a photo with your phone.</p>
            {errors.image && <span className="field-error">{Array.isArray(errors.image) ? errors.image[0] : errors.image}</span>}
          </div>
        </div>
        <Field label="Product name" error={errors.name}>
          <input value={form.name} onChange={set('name')} required autoFocus />
        </Field>
        <Field label="Price per unit (₱)" error={errors.price}>
          <input type="number" min="0" step="0.01" value={form.price} onChange={set('price')} placeholder="0.00" />
        </Field>
        <Field label="Unit" error={errors.unit} hint="How you count it, e.g. pack, box, sachet.">
          <input value={form.unit} onChange={set('unit')} required />
        </Field>
        <Field label="Product code (optional)" error={errors.sku}>
          <input value={form.sku} onChange={set('sku')} />
        </Field>
        <div className="form-actions span-2">
          <button type="button" className="btn" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : product ? 'Save changes' : 'Add product'}</button>
        </div>
      </form>
    </Modal>
  );
}
