(() => {
  document.addEventListener('click', (event) => {
    const cancel = event.target.closest('[data-geoip-cancel]');
    if (!cancel) return;

    const widget = cancel.closest('[data-geoip-widget]');
    if (widget) widget.open = false;
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
