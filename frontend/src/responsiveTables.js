// On phones every `.table` is shown as stacked cards (see index.css). Each cell
// needs its column name as a visible label, so this copies the header text onto
// the cells as data-label, for every table now and any that render later.
//
// Tables with more than five columns also become one short row per item on
// phones: only the first cell (and any cell with class "keep") shows, and
// tapping that first cell opens or closes the rest (data-open on the row).

const PHONE = '(max-width: 700px)';
// These tables have their own phone layout.
const OWN_LAYOUT = '.md-table, .starting-table, .customers-table';

function headerText(th) {
  if (th.dataset.label !== undefined) return th.dataset.label;
  // The first text node is the column name; hint spans and sort arrows are skipped.
  const first = [...th.childNodes].find((n) => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
  return (first ? first.textContent : th.getAttribute('aria-label') ? '' : th.textContent).replace(/[▲▼]/g, '').trim();
}

function labelTable(table) {
  const headers = table.querySelectorAll(':scope > thead > tr > th');
  if (!headers.length) return;
  const names = [...headers].map(headerText);
  if (headers.length > 5 && !table.matches(OWN_LAYOUT) && table.dataset.collapse !== '1') table.dataset.collapse = '1';
  table.querySelectorAll(':scope > tbody > tr').forEach((tr) => {
    [...tr.children].forEach((cell, i) => {
      const name = names[i] ?? '';
      if (cell.dataset.label !== name) cell.dataset.label = name;
    });
  });
}

/** Tap on a row's first cell (not on a link or button inside it) opens or closes that row on phones. */
function onTap(e) {
  if (!window.matchMedia(PHONE).matches) return;
  const cell = e.target.closest('table[data-collapse] > tbody > tr > td:first-child');
  if (!cell || e.target.closest('a, button, input, select, textarea, label')) return;
  const row = cell.parentElement;
  if (row.dataset.open) delete row.dataset.open;
  else row.dataset.open = '1';
  // Opening a row should not also run the row's own click (e.g. opening the customer page).
  e.stopPropagation();
}

export function startResponsiveTables() {
  // Capture phase on the document runs before React's own click handlers.
  document.addEventListener('click', onTap, true);
  let queued = false;
  const run = () => {
    queued = false;
    document.querySelectorAll('table.table').forEach(labelTable);
  };
  // Only child-list changes are watched, so setting data-label does not retrigger it.
  new MutationObserver(() => {
    if (!queued) {
      queued = true;
      // A timer, not requestAnimationFrame: that one stalls while the tab is in the background.
      setTimeout(run, 0);
    }
  }).observe(document.body, { childList: true, subtree: true, characterData: true });
  run();
}
