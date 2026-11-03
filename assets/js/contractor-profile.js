(() => {
  'use strict';
  const form = document.getElementById('contractorProfileForm');
  if (!form) return;
  const status = document.getElementById('profileStatus');
  const button = document.getElementById('profileSave');
  const endpoint = (window.BRITE_BASE || '') + '/api/my_contractor_profile.php';
  const fields = ['headline','biography','specialties','years_experience','portfolio_url'];
  const notify = (message) => { status.textContent = message; };
  // Profile values arrive asynchronously, after the global pill initializer.
  const refreshPills = () => {
    if (typeof window.briteRefreshValuePills === 'function') {
      window.briteRefreshValuePills(form);
    }
  };
  async function request(method, body) {
    const response = await fetch(endpoint, {
      method, credentials: 'same-origin',
      headers: { 'Accept':'application/json', ...(body ? {'Content-Type':'application/json','X-CSRF-Token':window.BRITE_CSRF} : {}) },
      ...(body ? {body:JSON.stringify(body)} : {})
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Request failed');
    return data;
  }
  notify('Loading profile...');
  request('GET').then(data => {
    fields.forEach(key => { form.elements.namedItem(key).value = data.profile[key] ?? ''; });
    refreshPills();
    notify('Profile ready to edit.');
  }).catch(error => { notify(error.message); button.disabled = true; });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    button.disabled = true;
    notify('Saving...');
    const payload = {};
    fields.forEach(key => { payload[key] = form.elements.namedItem(key).value.trim(); });
    try {
      await request('PUT', payload);
      // Do not collapse a field while the user is still typing in it.
      if (form.contains(document.activeElement) && document.activeElement !== button) {
        document.activeElement.blur();
      }
      refreshPills();
      notify('Profile saved.');
    }
    catch (error) { notify(error.message); }
    finally { button.disabled = false; }
  });
})();
