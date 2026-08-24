(() => {
  const configuration = window.ChidemoonCoreForms;

  const endpointFor = (form) => {
    if (form.dataset.chidemoonForm === 'price-alert') {
      return configuration?.priceAlertEndpoint || form.action;
    }

    return configuration?.leadEndpoint || form.action;
  };

  const setStatus = (form, message, isError = false) => {
    const status = form.querySelector('.chidemoon-form-status');
    if (!status) {
      return;
    }

    status.textContent = message;
    status.dataset.state = isError ? 'error' : 'success';
  };

  document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-chidemoon-form]')) {
      return;
    }

    event.preventDefault();
    const submitter = form.querySelector('button[type="submit"]');
    if (submitter instanceof HTMLButtonElement) {
      submitter.disabled = true;
    }

    try {
      const payload = Object.fromEntries(new FormData(form).entries());
      const response = await fetch(endpointFor(form), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(body.message || 'Unable to submit the form.');
      }

      form.reset();
      setStatus(form, 'Your request has been received.');
    } catch (error) {
      setStatus(form, error instanceof Error ? error.message : 'Unable to submit the form.', true);
    } finally {
      if (submitter instanceof HTMLButtonElement) {
        submitter.disabled = false;
      }
    }
  });
})();
