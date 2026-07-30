(() => {
  async function loadFragment(host) {
    if (host.dataset.geoipFragmentLoading) return;
    host.dataset.geoipFragmentLoading = '1';
    try {
      const response = await fetch(host.dataset.geoipFragmentUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      const result = await response.json();
      if (!response.ok || !result.success || !result.html) {
        throw new Error('Location unavailable');
      }
      host.innerHTML = result.html;
      host.removeAttribute('data-geoip-fragment-loading');
    } catch (error) {
      const status = host.querySelector('.geoip-widget-fragment__status');
      if (status) status.textContent = 'Choose location';
    }
  }

  document.querySelectorAll('[data-geoip-fragment]').forEach(loadFragment);

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
