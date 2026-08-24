'use strict';

// CSRF token for fetch requests
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

// Global fetch wrapper
async function apiFetch(url, options = {}) {
  const res = await fetch(url, {
    headers: {
      'X-CSRF-Token': csrfToken,
      'Accept': 'application/json',
      ...options.headers,
    },
    ...options,
  });
  if (!res.ok) throw new Error(await res.text());
  return res.json();
}

// POST form helper (returns JSON)
async function postForm(url, data = {}) {
  const fd = new FormData();
  fd.append('_csrf', csrfToken);
  for (const [k, v] of Object.entries(data)) fd.append(k, v);
  const res = await fetch(url, { method: 'POST', body: fd });
  if (!res.ok) throw new Error(await res.text());
  return res.json().catch(() => null);
}

// Sidebar toggle (mobile)
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.getElementById('sidebar')?.classList.toggle('open');
});

// Auto-dismiss alerts
setTimeout(() => {
  document.querySelectorAll('.alert.alert-success').forEach(el => {
    el.classList.remove('show');
    setTimeout(() => el.remove(), 300);
  });
}, 4000);

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'Wirklich löschen?')) e.preventDefault();
  });
});

// Lightbox
const lightbox = document.getElementById('lightbox');
if (lightbox) {
  const lbImg  = lightbox.querySelector('#lb-img');
  const lbVid  = lightbox.querySelector('#lb-vid');
  const lbClose = lightbox.querySelector('#lb-close');

  window.openLightbox = (src, isVideo = false) => {
    if (isVideo) {
      lbImg.style.display = 'none';
      lbVid.style.display = '';
      lbVid.src = src;
    } else {
      lbVid.style.display = 'none';
      lbVid.src = '';
      lbImg.style.display = '';
      lbImg.src = src;
    }
    lightbox.classList.add('active');
  };
  lbClose?.addEventListener('click', () => {
    lightbox.classList.remove('active');
    lbVid.src = '';
  });
  lightbox.addEventListener('click', e => {
    if (e.target === lightbox) {
      lightbox.classList.remove('active');
      lbVid.src = '';
    }
  });
}

// Toast notifications
window.showToast = (message, type = 'info') => {
  let container = document.getElementById('toastContainer');
  if (!container) return;
  const id = 'toast-' + Date.now();
  const bg = { success: 'bg-success', danger: 'bg-danger', warning: 'bg-warning text-dark', info: 'bg-info text-dark' }[type] || 'bg-secondary';
  container.insertAdjacentHTML('beforeend',
    `<div id="${id}" class="toast align-items-center text-white ${bg} border-0" role="alert">
       <div class="d-flex"><div class="toast-body">${message}</div>
       <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
     </div>`
  );
  const el = document.getElementById(id);
  new bootstrap.Toast(el, { delay: 4000 }).show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
};

// File upload zone
document.querySelectorAll('.upload-zone').forEach(zone => {
  zone.addEventListener('dragover', e => {
    e.preventDefault();
    zone.classList.add('drag-over');
  });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    const input = zone.querySelector('#fileInput');
    if (input && e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  // Only open file picker for direct clicks on zone background, not on buttons/labels
  zone.addEventListener('click', e => {
    if (e.target.closest('label, button, input, a')) return;
    zone.querySelector('#fileInput')?.click();
  });
});

// Comment form AJAX
document.querySelectorAll('.comment-form').forEach(form => {
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const body = form.querySelector('textarea').value.trim();
    if (!body) return;
    try {
      const data = await postForm(form.action, { body });
      if (data?.html) {
        document.querySelector('.comments-list')?.insertAdjacentHTML('beforeend', data.html);
        form.querySelector('textarea').value = '';
      } else {
        location.reload();
      }
    } catch {
      alert('Kommentar konnte nicht gespeichert werden');
    }
  });
});

// Todo toggle
document.querySelectorAll('.todo-toggle').forEach(btn => {
  btn.addEventListener('click', async () => {
    const url  = btn.dataset.url;
    const icon = btn.querySelector('i');
    try {
      const data = await postForm(url, {});
      if (data?.done) {
        icon?.classList.replace('bi-circle', 'bi-check-circle-fill');
        icon?.classList.add('text-success');
        btn.closest('.todo-item')?.classList.add('opacity-50');
      } else {
        icon?.classList.replace('bi-check-circle-fill', 'bi-circle');
        icon?.classList.remove('text-success');
        btn.closest('.todo-item')?.classList.remove('opacity-50');
      }
    } catch { location.reload(); }
  });
});

// Search live preview
const searchInput = document.getElementById('global-search');
if (searchInput) {
  let timer;
  searchInput.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      const q = searchInput.value.trim();
      if (q.length > 2) window.location = '/search?q=' + encodeURIComponent(q);
    }, 500);
  });
}

// Color picker preview
document.querySelectorAll('input[type=color]').forEach(input => {
  const preview = document.getElementById(input.dataset.preview);
  if (preview) {
    input.addEventListener('input', () => preview.style.background = input.value);
  }
});

// GPS capture
window.captureGPS = (latInput, lonInput, btn) => {
  if (!navigator.geolocation) { alert('Geolocation nicht verfügbar'); return; }
  btn.disabled = true;
  navigator.geolocation.getCurrentPosition(pos => {
    document.getElementById(latInput).value = pos.coords.latitude.toFixed(7);
    document.getElementById(lonInput).value = pos.coords.longitude.toFixed(7);
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Erfasst';
  }, () => {
    btn.disabled = false;
    alert('GPS-Position konnte nicht erfasst werden');
  });
};

// Confirm delete forms
document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', e => {
    if (!confirm(form.dataset.confirm || 'Wirklich löschen?')) e.preventDefault();
  });
});
