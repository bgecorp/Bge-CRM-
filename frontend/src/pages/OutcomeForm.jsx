import { useState } from 'react';
import { api } from '../api.js';
import { Field, Modal } from '../components.jsx';
import { NEXT_ACTION_LABELS, OUTCOME_LABELS, date } from '../format.js';

// Records the customer's response to a contact and schedules the next one.
export default function OutcomeForm({ followUp, onClose, onSaved }) {
  const [form, setForm] = useState({
    outcome: 'interested', outcome_notes: '', next_action: '', next_order_date: '', next_contact_date: '',
  });
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post(`/follow-ups/${followUp.id}/complete`, {
        ...form,
        next_action: form.next_action || null,
        next_order_date: form.next_order_date || null,
        next_contact_date: form.next_contact_date || null,
      });
      onSaved();
    } catch (err) {
      setErrors(err.errors || { outcome: err.message });
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal title={`Record response: ${followUp.store?.name ?? ''}`} onClose={onClose}>
      <p className="muted">{followUp.due_date ? `Follow-up date ${date(followUp.due_date)}` : 'No date'} · {followUp.reason}</p>
      <form onSubmit={submit} className="form-grid">
        <Field label="Customer response" error={errors.outcome}>
          <select value={form.outcome} onChange={set('outcome')}>
            {Object.entries(OUTCOME_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </Field>
        <Field label="Next action" error={errors.next_action}>
          <select value={form.next_action} onChange={set('next_action')}>
            <option value="">None</option>
            {Object.entries(NEXT_ACTION_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </Field>
        <Field label="Next order date" error={errors.next_order_date} hint="When the customer plans to place their next order, if they said.">
          <input type="date" value={form.next_order_date} onChange={set('next_order_date')} />
        </Field>
        <Field
          label="Next contact date"
          error={errors.next_contact_date}
          hint="Leave blank if no further contact is needed."
        >
          <input type="date" value={form.next_contact_date} onChange={set('next_contact_date')} />
        </Field>
        <Field label="Notes" error={errors.outcome_notes}>
          <input value={form.outcome_notes} onChange={set('outcome_notes')} />
        </Field>
        {form.outcome === 'ready_to_order' && (
          <p className="hint span-2">When the order is delivered, record it on the customer page (Order delivered) so the stock and next follow-up are recalculated.</p>
        )}
        <div className="form-actions span-2">
          <button type="button" className="btn" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save response'}</button>
        </div>
      </form>
    </Modal>
  );
}
