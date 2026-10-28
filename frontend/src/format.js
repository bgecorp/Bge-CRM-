export const CATEGORY_LABELS = {
  small_sari_sari: 'Small Sari-Sari',
  regular_sari_sari: 'Regular Sari-Sari',
  mini_grocery: 'Mini-Grocery',
  regular_grocery: 'Regular Grocer',
};

export const STATUS_LABELS = {
  prospect: 'Prospect',
  active: 'Active',
  inactive: 'Inactive',
  lost: 'Lost',
};

/** Customer response recorded when a follow-up is done. */
export const OUTCOME_LABELS = {
  interested: 'Interested',
  ready_to_order: 'Ready to order',
  not_yet: 'Not yet',
  no_response: 'No response',
};

export const NEXT_ACTION_LABELS = {
  call: 'Call',
  message: 'Message',
  confirm_stock: 'Confirm stock',
  process_order: 'Process order',
};

export const FOLLOW_UP_TYPE_LABELS = {
  first_order: 'First-order check',
  manual: 'Set by a person',
  // Older automatic follow-ups (no longer created)
  reorder_estimate: 'Automatic (old)',
  needs_data: 'Automatic (old)',
  unconfirmed_check: 'Automatic (old)',
  default_7_day: 'Automatic (old)',
  manual_review: 'Automatic (old)',
};

export const PATTERN_LABELS = {
  frequent: 'Frequent',
  moderate: 'Moderate',
  occasional: 'Occasional',
  new: 'New (1 order)',
};

export const BASIS_LABELS = {
  baseline: 'First order only',
  first_estimate: 'First estimate',
  refined: 'Refined estimate',
  no_usage: 'Cannot estimate',
};

/** Predicted orders within this many days are "Ordering Soon". */
export const DUE_SOON_DAYS = 3;

/**
 * Status of a customer's predicted next purchase, shown in every table.
 * Works on a prediction or any row with days_until_next_purchase.
 */
export function stockStatus(p) {
  if (!p) return { tone: 'neutral', label: 'No orders yet' };
  if (p.basis === 'baseline') return { tone: 'neutral', label: 'Waiting for 2nd order' };
  const days = p.days_until_next_purchase;
  if (days === null || days === undefined) return { tone: 'neutral', label: 'Cannot estimate' };
  if (days < 0) return { tone: 'danger', label: 'Order expected already' };
  if (days === 0) return { tone: 'danger', label: 'Ordering today' };
  if (days <= DUE_SOON_DAYS) return { tone: 'warning', label: 'Ordering soon' };
  return { tone: 'ok', label: 'Has stock' };
}

export const STATUS_GUIDE = [
  { tone: 'danger', label: 'Order expected already', text: 'Their stock was expected to run out and they have not ordered again yet.' },
  { tone: 'danger', label: 'Ordering today', text: 'Their next order is expected today.' },
  { tone: 'warning', label: 'Ordering soon', text: `Their next order is expected within ${DUE_SOON_DAYS} days.` },
  { tone: 'ok', label: 'Has stock', text: 'Their latest order should still last a while.' },
  { tone: 'neutral', label: 'Waiting for 2nd order', text: 'Only one order so far. The prediction starts when they order again.' },
];

/** Column explanations for the prediction tables. */
export const PREDICTION_GUIDE = [
  ['Total orders', 'How many times the customer has ordered this product.'],
  ['Average days between orders', 'How often they order. Example: orders on Oct 1, 6 and 10 = every 4.5 days.'],
  ['Estimated daily consumption', 'Packs used per day, from the days between orders. Example: (12 + 6) packs ÷ (5 + 4) days = 2 packs a day.'],
  ['Latest order', 'The newest order: this is their current stock.'],
  ['Estimated stock duration', 'Latest order ÷ daily consumption. Example: 8 packs ÷ 2 a day = about 4 days.'],
  ['Predicted next purchase', 'Latest order date + stock duration.'],
  ['Buying pattern', 'Frequent, moderate or occasional, from how often they order (set in Settings).'],
];
// Exact values are kept in state; rounding happens only here, at display time.
export function num(value, digits = 2) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return '-';
  return Number(value).toLocaleString('en-PH', {
    minimumFractionDigits: 0,
    maximumFractionDigits: digits,
  });
}

export function money(value) {
  if (value === null || value === undefined) return '-';
  return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function date(value) {
  if (!value) return '-';
  const d = new Date(value.length === 10 ? value + 'T00:00:00' : value);
  return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

export function today() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function dueLabel(days) {
  if (days === null || days === undefined) return '';
  if (days < 0) return `${Math.abs(days)}d overdue`;
  if (days === 0) return 'Today';
  return `in ${days}d`;
}
