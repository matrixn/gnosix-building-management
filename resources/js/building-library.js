const dialog = document.querySelector('[data-building-delete-dialog]');
if (dialog && typeof dialog.showModal === 'function') {
 let pending = null, trigger = null;
 document.querySelectorAll('[data-building-delete]').forEach(form => {
  const button = form.querySelector('button[type=submit]');
  button.disabled = false;
  form.addEventListener('submit', event => {
   if (form.dataset.confirmed === 'true') { button.disabled = true; return; }
   event.preventDefault();
   pending = form; trigger = button;
   dialog.querySelector('[data-delete-name]').textContent = form.dataset.buildingName;
   dialog.querySelector('[data-delete-reference]').textContent = form.dataset.buildingReference;
   dialog.showModal();
  });
 });
 dialog.querySelector('[data-delete-cancel]').addEventListener('click', () => dialog.close());
 dialog.querySelector('[data-delete-confirm]').addEventListener('click', () => {
  const form = pending;
  if (!form) return;
  dialog.close();
  form.elements.namedItem('confirmed').value = '1';
  form.dataset.confirmed = 'true';
  form.requestSubmit();
 });
 dialog.addEventListener('close', () => { pending = null; trigger?.focus(); });
 window.addEventListener('pageshow', () => {
  document.querySelectorAll('[data-building-delete]').forEach(form => {
   delete form.dataset.confirmed;
   form.elements.namedItem('confirmed').value = '0';
   form.querySelector('button[type=submit]').disabled = false;
  });
 });
}
