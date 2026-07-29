(() => {
  document.addEventListener('click', (event) => {
    const edit = event.target.closest('[data-geoip-edit]');
    if (edit) {
      const widget = edit.closest('[data-geoip-widget]');
      const confirmation = widget?.querySelector('[data-geoip-confirmation]');
      const form = widget?.querySelector('[data-geoip-form]');
      if (confirmation && form) {
        confirmation.hidden = true;
        form.hidden = false;
        form.querySelector('input')?.focus();
      }
      return;
    }

    const cancel = event.target.closest('[data-geoip-cancel]');
    if (!cancel) return;

    const widget = cancel.closest('[data-geoip-widget]');
    const confirmation = widget?.querySelector('[data-geoip-confirmation]');
    const form = widget?.querySelector('[data-geoip-form]');
    if (confirmation && form) {
      form.hidden = true;
      confirmation.hidden = false;
      widget.querySelector('[data-geoip-edit]')?.focus();
    }
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-geoip-form]');
    if (!form) return;

    event.preventDefault();
    const submit = form.querySelector('[type="submit"]');
    const status = form.querySelector('[data-geoip-status]');
    if (submit) submit.disabled = true;
    if (status) status.textContent = 'Saving…';

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error('Location was not saved.');

      if (status) status.textContent = 'Location saved.';
      window.location.reload();
    } catch (error) {
      if (status) status.textContent = error.message || 'Location was not saved.';
      if (submit) submit.disabled = false;
    }
  });
})();
