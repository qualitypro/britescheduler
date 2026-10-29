/* Cycle 66: owner directory accordion opens directly into editable fields.
   No second Details/Edit action; keep only one expanded record. */
(() => {
  'use strict';
  const selector = '.brite-directory-record';
  document.addEventListener('toggle', event => {
    const record = event.target;
    if (!(record instanceof HTMLDetailsElement) || !record.matches(selector)) return;
    if (!record.open) {
      const form = record.querySelector(':scope > form.brite-directory-form');
      if (form && !form.dataset.saving) form.reset();
      return;
    }
    const container = record.parentElement;
    if (!container) return;
    for (const other of container.querySelectorAll(':scope > details.brite-directory-record[open]')) {
      if (other !== record) other.open = false;
    }
  }, true);
})();
