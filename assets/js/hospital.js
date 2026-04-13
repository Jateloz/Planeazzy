'use strict';
/* ================================================================
   Planeazzy Hospital Dashboard — hospital.js
   Full sidebar, modals, forms, AJAX, charts, real-time updates
================================================================ */

/* ── SIDEBAR ── */
const HSidebar = (() => {
  let sb, main;
  function init() {
    sb = document.getElementById('hSidebar');
    main = document.getElementById('hMain');
    if (!sb) return;
    const stored = localStorage.getItem('h_sb') || 'open';
    if (stored === 'collapsed' && window.innerWidth > 768) _collapse(false);
    sb.querySelector('.hs-toggle-btn')?.addEventListener('click', toggle);
  }
  function toggle() { sb?.classList.contains('collapsed') ? _expand() : _collapse(); }
  function _collapse(save = true) {
    sb?.classList.add('collapsed');
    document.getElementById('hLayout')?.classList.add('sb-col');
    const ic = document.getElementById('sbToggleIc');
    if (ic) { ic.classList.remove('fa-chevron-left'); ic.classList.add('fa-chevron-right'); }
    if (save) localStorage.setItem('h_sb', 'collapsed');
  }
  function _expand(save = true) {
    sb?.classList.remove('collapsed');
    document.getElementById('hLayout')?.classList.remove('sb-col');
    const ic = document.getElementById('sbToggleIc');
    if (ic) { ic.classList.remove('fa-chevron-right'); ic.classList.add('fa-chevron-left'); }
    if (save) localStorage.setItem('h_sb', 'open');
  }
  function openMob() {
    sb?.classList.add('mob-open');
    const ov = document.getElementById('hMobOv');
    if (ov) ov.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }
  function closeMob() {
    sb?.classList.remove('mob-open');
    const ov = document.getElementById('hMobOv');
    if (ov) ov.style.display = 'none';
    document.body.style.overflow = '';
  }
  return { init, toggle, openMob, closeMob };
})();
window.toggleHSidebar = () => HSidebar.openMob();
window.closeHSidebar  = () => HSidebar.closeMob();

/* ── MODALS ── */
function hOpenModal(id) {
  const m = document.getElementById(id);
  if (!m) return;
  m.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function hCloseModal(id) {
  const m = document.getElementById(id);
  if (!m) return;
  m.classList.remove('open');
  document.body.style.overflow = '';
}
window.hOpenModal  = hOpenModal;
window.hCloseModal = hCloseModal;

/* ── ALERTS ── */
const HUI = {
  alert(type, msg, id = 'hAlertBox') {
    const box = document.getElementById(id);
    if (!box) return;
    const icons = { err: 'fa-circle-exclamation', ok: 'fa-circle-check', info: 'fa-circle-info', warn: 'fa-triangle-exclamation' };
    box.className = 'h-alert ' + type;
    box.innerHTML = `<i class="fa-solid ${icons[type] || 'fa-circle-info'}"></i><span>${msg}</span>`;
    box.classList.remove('hidden');
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    if (type === 'ok') setTimeout(() => box.classList.add('hidden'), 4000);
  },
  hide(id = 'hAlertBox') { document.getElementById(id)?.classList.add('hidden'); },
  loading(btnId, on) {
    if (!btnId) return;
    const btn = document.getElementById(btnId); if (!btn) return;
    btn.disabled = on;
    if (on) { btn.dataset.orig = btn.innerHTML; btn.innerHTML += ' <i class="fa-solid fa-circle-notch fa-spin" style="font-size:12px"></i>'; }
    else if (btn.dataset.orig) btn.innerHTML = btn.dataset.orig;
  }
};

/* ── FETCH ── */
async function hPost(url, data, btnId, alertId) {
  HUI.hide(alertId);
  HUI.loading(btnId, true);
  try {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), 15000);
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(data),
      credentials: 'same-origin',
      signal: ctrl.signal,
    });
    clearTimeout(t);
    const text = await res.text();
    try { return JSON.parse(text); }
    catch { console.error('Non-JSON response:', text.slice(0, 300)); return { success: false, message: 'Server error.' }; }
  } catch (err) {
    if (err.name === 'AbortError') HUI.alert('err', 'Request timed out.', alertId);
    else if (!navigator.onLine) HUI.alert('err', 'You are offline.', alertId);
    else HUI.alert('err', 'Server unreachable.', alertId);
    return null;
  } finally {
    HUI.loading(btnId, false);
  }
}

/* ── PASSWORD STRENGTH ── */
const HPwd = (() => {
  const lvls = [
    { l: 'Too weak', c: '#dc2626', w: '20%' },
    { l: 'Weak',     c: '#ea580c', w: '40%' },
    { l: 'Fair',     c: '#d97706', w: '60%' },
    { l: 'Good',     c: '#16a34a', w: '80%' },
    { l: 'Strong',   c: '#047857', w: '100%' },
  ];
  function score(p) {
    if (!p) return -1; let s = 0;
    if (p.length >= 8) s++; if (/[A-Z]/.test(p)) s++; if (/[a-z]/.test(p)) s++;
    if (/[0-9]/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++;
    return s - 1;
  }
  function update(pwd, fId, tId) {
    const f = document.getElementById(fId); if (!f) return;
    const t = document.getElementById(tId);
    const s = score(pwd);
    if (s < 0) { f.style.width = '0'; if (t) t.textContent = ''; return; }
    const lv = lvls[s]; f.style.width = lv.w; f.style.background = lv.c;
    if (t) { t.textContent = lv.l; t.style.color = lv.c; }
  }
  return { init: (iId, fId, tId) => { const el = document.getElementById(iId); if (el) el.addEventListener('input', () => update(el.value, fId, tId)); } };
})();

/* ── TOGGLE PASSWORD ── */
function hTogglePwd(inp, btn) {
  const el = document.getElementById(inp); if (!el) return;
  const b = document.getElementById(btn);
  const show = el.type === 'password'; el.type = show ? 'text' : 'password';
  if (b) { const ic = b.querySelector('i'); if (ic) { ic.classList.toggle('fa-eye', !show); ic.classList.toggle('fa-eye-slash', show); } }
}
window.hTogglePwd = hTogglePwd;

/* ── OTP ── */
const HOTP = (() => {
  function init(sel) {
    const grid = document.querySelector(sel || '.h-otp-row'); if (!grid) return;
    const inputs = [...grid.querySelectorAll('.h-otp-d')];
    inputs.forEach((inp, i) => {
      inp.addEventListener('input', () => {
        inp.value = inp.value.replace(/\D/g, '').slice(0, 1);
        inp.classList.toggle('filled', !!inp.value);
        if (inp.value && i < inputs.length - 1) inputs[i + 1].focus();
        const btn = document.getElementById('hVerifyBtn');
        if (btn) btn.disabled = !inputs.every(x => x.value);
      });
      inp.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !inp.value && i > 0) { inputs[i - 1].focus(); inputs[i - 1].value = ''; inputs[i - 1].classList.remove('filled'); }
        if (e.key === 'Enter') { const b = document.getElementById('hVerifyBtn'); if (b && !b.disabled) b.click(); }
      });
      inp.addEventListener('paste', e => {
        e.preventDefault();
        const d = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, inputs.length);
        d.split('').forEach((c, j) => { if (inputs[j]) { inputs[j].value = c; inputs[j].classList.add('filled'); } });
        const nx = inputs.findIndex(x => !x.value);
        (nx !== -1 ? inputs[nx] : inputs[inputs.length - 1]).focus();
        const btn = document.getElementById('hVerifyBtn');
        if (btn) btn.disabled = !inputs.every(x => x.value);
      });
    });
  }
  function value(sel) { return [...document.querySelectorAll((sel || '.h-otp-row') + ' .h-otp-d')].map(x => x.value).join(''); }
  return { init, value };
})();

/* ── MINI CHARTS ── */
function drawSparkline(canvasId, data, color = '#1978e5') {
  const canvas = document.getElementById(canvasId);
  if (!canvas || !canvas.getContext) return;
  const ctx = canvas.getContext('2d');
  const W = canvas.width, H = canvas.height;
  const max = Math.max(...data), min = Math.min(...data);
  const range = max - min || 1;
  ctx.clearRect(0, 0, W, H);
  ctx.beginPath();
  data.forEach((v, i) => {
    const x = (i / (data.length - 1)) * W;
    const y = H - ((v - min) / range) * (H - 8) - 4;
    i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
  });
  ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.lineJoin = 'round'; ctx.stroke();
  // Fill
  ctx.lineTo(W, H); ctx.lineTo(0, H); ctx.closePath();
  ctx.fillStyle = color + '22'; ctx.fill();
}

/* ── STAT COUNTER ANIMATION ── */
function animHCounters() {
  document.querySelectorAll('[data-hcount]').forEach(el => {
    const target = parseInt(el.dataset.hcount), sfx = el.dataset.suffix || '';
    let c = 0; const step = target / 50;
    const t = setInterval(() => {
      c = Math.min(c + step, target);
      el.textContent = Math.floor(c).toLocaleString() + sfx;
      if (c >= target) clearInterval(t);
    }, 25);
  });
}

/* ── APPOINTMENT ACTIONS ── */
async function hUpdateAppt(id, status) {
  const csrf = document.getElementById('hCsrf')?.value || '';
  const r = await hPost('/api/provider/update-appointment.php', { appointment_id: id, status, csrf_token: csrf }, null, null);
  if (r?.success) { location.reload(); }
  else alert(r?.message || 'Action failed.');
}
window.hUpdateAppt = hUpdateAppt;

/* ── TABLE SEARCH FILTER ── */
function hTableSearch(inputId, tableId) {
  const inp = document.getElementById(inputId);
  const tbl = document.getElementById(tableId);
  if (!inp || !tbl) return;
  inp.addEventListener('input', () => {
    const q = inp.value.toLowerCase().trim();
    tbl.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = q === '' || row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

/* ── BOOKING HOSPITAL ── */
async function hBookAppt() {
  const csrf = document.getElementById('hCsrf')?.value || '';
  const date = document.getElementById('hBookDate')?.value;
  const time = document.getElementById('hBookTime')?.value || '09:00';
  const title = document.getElementById('hBookTitle')?.value?.trim();
  if (!date) { HUI.alert('warn', 'Please select a date.', 'hBookAlert'); return; }
  if (!title) { HUI.alert('warn', 'Please enter reason for visit.', 'hBookAlert'); return; }
  const r = await hPost('/api/patient/book-appointment.php', {
    service_type: document.getElementById('hBookType')?.value || 'hospital',
    provider_id:  document.getElementById('hBookPid')?.value || null,
    appointment_at: date + ' ' + time + ':00',
    title, notes: document.getElementById('hBookNotes')?.value?.trim() || '',
    location_type: document.getElementById('hBookLocType')?.value || 'in_person',
    csrf_token: csrf,
  }, 'hBookBtn', 'hBookAlert');
  if (!r) return;
  if (r.success) { hCloseModal('hBookModal'); location.reload(); }
  else HUI.alert('err', r.message || 'Booking failed.', 'hBookAlert');
}
window.hBookAppt = hBookAppt;

/* ── HOSPITAL PROFILE SAVE ── */
async function hSaveProfile() {
  const csrf = document.getElementById('hCsrf')?.value || '';
  const data = {
    csrf_token: csrf,
    name:    document.getElementById('hProfName')?.value?.trim(),
    phone:   document.getElementById('hProfPhone')?.value?.trim(),
    address: document.getElementById('hProfAddr')?.value?.trim(),
    website: document.getElementById('hProfWeb')?.value?.trim(),
    description: document.getElementById('hProfDesc')?.value?.trim(),
  };
  const r = await hPost('/api/provider/update-profile.php', data, 'hProfBtn', 'hProfAlert');
  if (r?.success) HUI.alert('ok', 'Profile updated successfully.', 'hProfAlert');
  else HUI.alert('err', r?.message || 'Update failed.', 'hProfAlert');
}
window.hSaveProfile = hSaveProfile;

/* ── AVAILABILITY SAVE ── */
async function hSaveAvail() {
  const csrf = document.getElementById('hCsrf')?.value || '';
  const slots = [];
  document.querySelectorAll('.h-day-row').forEach(row => {
    const day = row.dataset.day; if (!day) return;
    const st = row.querySelector('.hts')?.value;
    const en = row.querySelector('.hte')?.value;
    const mode = row.querySelector('.h-mode-btn.active')?.dataset.mode || 'in_person';
    const closed = row.querySelector('.h-day-closed')?.checked;
    if (!closed && st && en) slots.push({ day, start: st, end: en, mode });
  });
  const r = await hPost('/api/provider/set-availability.php', { slots, csrf_token: csrf }, 'hAvailBtn', 'hAvailAlert');
  if (r?.success) HUI.alert('ok', 'Availability saved.', 'hAvailAlert');
  else HUI.alert('err', r?.message || 'Save failed.', 'hAvailAlert');
}
window.hSaveAvail = hSaveAvail;

/* ── NOTIFICATIONS MARK READ ── */
async function hMarkRead(id) {
  document.getElementById('hn-' + id)?.classList.remove('unread');
  document.getElementById('hn-' + id)?.querySelector('.h-notif-dot')?.remove();
  fetch('/api/patient/mark-notification-read.php?id=' + id, {
    method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).catch(() => {});
}
window.hMarkRead = hMarkRead;

/* ── INIT ── */
document.addEventListener('DOMContentLoaded', () => {
  // Language system — app.js provides the Lang global
  if (typeof Lang !== 'undefined') {
    Lang.init();
    document.getElementById('langToggle')?.addEventListener('click', () => Lang.toggle());
  }

  HSidebar.init();
  HOTP.init('.h-otp-row');

  // Modal close on overlay click
  document.querySelectorAll('.h-modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) hCloseModal(m.id); });
  });

  // Mobile sidebar
  document.getElementById('hMobToggle')?.addEventListener('click', () => HSidebar.openMob());
  document.getElementById('hMobOv')?.addEventListener('click', () => HSidebar.closeMob());

  // Topbar search
  const ts = document.getElementById('hTopSearch');
  if (ts) ts.addEventListener('keydown', e => {
    if (e.key === 'Enter') location.href = '/hospital/dashboard.php?tab=patients&q=' + encodeURIComponent(ts.value);
  });

  // Animate counters
  const fc = document.querySelector('[data-hcount]');
  if (fc) {
    const obs = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting) { animHCounters(); obs.disconnect(); }
    }, { threshold: 0.3 });
    obs.observe(fc.closest('section') || fc);
  } else {
    animHCounters();
  }

  // Table search
  hTableSearch('patSearch', 'patTable');
  hTableSearch('apptSearch', 'apptTable');
  hTableSearch('docSearch', 'docTable');

  // Availability mode buttons
  document.querySelectorAll('.h-day-row').forEach(row => {
    row.querySelectorAll('.h-mode-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        row.querySelectorAll('.h-mode-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      });
    });
    // Closed toggle
    row.querySelector('.h-day-closed')?.addEventListener('change', function () {
      const fields = row.querySelectorAll('.hts, .hte, .h-mode-btn');
      fields.forEach(f => { f.disabled = this.checked; f.style.opacity = this.checked ? '.4' : '1'; });
    });
  });

  // Sparklines
  const sparkData = {
    apptSpark:    [12, 18, 14, 22, 19, 27, 31, 25, 28, 35, 29, 42],
    patSpark:     [5, 8, 6, 11, 9, 14, 12, 16, 13, 18, 15, 20],
    revSpark:     [40, 55, 48, 62, 58, 70, 65, 78, 72, 85, 79, 92],
    bedSpark:     [60, 58, 62, 55, 68, 72, 65, 70, 75, 68, 72, 74],
  };
  const sparkColors = { apptSpark: '#1978e5', patSpark: '#0d9488', revSpark: '#16a34a', bedSpark: '#d97706' };
  Object.entries(sparkData).forEach(([id, data]) => drawSparkline(id, data, sparkColors[id] || '#1978e5'));
});
