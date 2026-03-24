'use strict';

/* ── SIDEBAR ──────────────────────────────────────── */
const Sidebar = (() => {
  let sb, mw;
  function init() {
    sb = document.getElementById('sidebar');
    mw = document.getElementById('mainWrap');
    if (!sb) return;
    const stored = localStorage.getItem('pz_sb') || 'open';
    if (stored === 'collapsed' && window.innerWidth > 768) _collapse(false);
    const toggleBtn = sb.querySelector('.s-toggle-btn');
    if (toggleBtn) toggleBtn.addEventListener('click', toggle);
  }
  function toggle() { sb?.classList.contains('collapsed') ? _expand() : _collapse(); }
  function _collapse(save = true) {
    sb?.classList.add('collapsed');
    if (mw) mw.style.marginLeft = 'var(--sidebar-col)';
    const ic = document.getElementById('sToggleIcon');
    if (ic) { ic.classList.remove('fa-chevron-left'); ic.classList.add('fa-chevron-right'); }
    if (save) localStorage.setItem('pz_sb', 'collapsed');
  }
  function _expand(save = true) {
    sb?.classList.remove('collapsed');
    if (mw) mw.style.marginLeft = 'var(--sidebar-w)';
    const ic = document.getElementById('sToggleIcon');
    if (ic) { ic.classList.remove('fa-chevron-right'); ic.classList.add('fa-chevron-left'); }
    if (save) localStorage.setItem('pz_sb', 'open');
  }
  function openMob() {
    sb?.classList.add('mob-open');
    const ov = document.getElementById('mobOv');
    if (ov) { ov.style.display = 'block'; }
    document.body.style.overflow = 'hidden';
  }
  function closeMob() {
    sb?.classList.remove('mob-open');
    const ov = document.getElementById('mobOv');
    if (ov) { ov.style.display = 'none'; }
    document.body.style.overflow = '';
  }
  return { init, toggle, openMob, closeMob };
})();
window.toggleSidebar  = () => Sidebar.openMob();
window.closeSidebar   = () => Sidebar.closeMob();

/* ── MODALS ──────────────────────────────────────── */
function openModal(id) {
  const m = document.getElementById(id);
  if (!m) return;
  // Support both .open class style and inline display style
  if (m.classList.contains('modal-overlay')) {
    m.classList.add('open');
  } else {
    m.style.display = 'flex';
  }
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (!m) return;
  m.classList.remove('open');
  m.style.display = 'none';
  document.body.style.overflow = '';
}
window.openModal  = openModal;
window.closeModal = closeModal;

/* ── LANG ────────────────────────────────────────── */
const Lang = (() => {
  let cur = localStorage.getItem('pz_lang') || 'en';
  function apply(lang) {
    cur = lang; localStorage.setItem('pz_lang', lang);
    document.querySelectorAll('[data-en]').forEach(el => {
      const v = el.getAttribute('data-' + lang);
      if (v != null) el.textContent = v;
    });
    const lb = document.getElementById('langLabel');
    if (lb) lb.textContent = lang === 'en' ? 'SW' : 'EN';
  }
  return { toggle: () => apply(cur === 'en' ? 'sw' : 'en'), init: () => apply(cur) };
})();

/* ── OTP ─────────────────────────────────────────── */
const OTP = (() => {
  function init(sel) {
    const grid = document.querySelector(sel || '#otpGrid');
    if (!grid) return;
    const inputs = [...grid.querySelectorAll('.otp-digit')];
    inputs.forEach((inp, i) => {
      inp.addEventListener('input', () => {
        inp.value = inp.value.replace(/\D/g, '').slice(0, 1);
        inp.classList.toggle('filled', !!inp.value);
        if (inp.value && i < inputs.length - 1) inputs[i + 1].focus();
        const btn = document.getElementById('verifyBtn');
        if (btn) btn.disabled = !inputs.every(x => x.value);
      });
      inp.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !inp.value && i > 0) {
          inputs[i - 1].focus(); inputs[i - 1].value = '';
          inputs[i - 1].classList.remove('filled');
        }
        if (e.key === 'ArrowLeft' && i > 0) inputs[i - 1].focus();
        if (e.key === 'ArrowRight' && i < inputs.length - 1) inputs[i + 1].focus();
        if (e.key === 'Enter') {
          const btn = document.getElementById('verifyBtn');
          if (btn && !btn.disabled) btn.click();
        }
      });
      inp.addEventListener('paste', e => {
        e.preventDefault();
        const d = (e.clipboardData || window.clipboardData).getData('text')
          .replace(/\D/g, '').slice(0, inputs.length);
        d.split('').forEach((c, j) => {
          if (inputs[j]) { inputs[j].value = c; inputs[j].classList.add('filled'); }
        });
        const nx = inputs.findIndex(x => !x.value);
        (nx !== -1 ? inputs[nx] : inputs[inputs.length - 1]).focus();
        const btn = document.getElementById('verifyBtn');
        if (btn) btn.disabled = !inputs.every(x => x.value);
      });
    });
  }
  function value(sel) {
    return [...document.querySelectorAll((sel || '#otpGrid') + ' .otp-digit')]
      .map(x => x.value).join('');
  }
  return { init, value };
})();

/* ── PASSWORD STRENGTH ───────────────────────────── */
const PwdStrength = (() => {
  const lvls = [
    { lbl: 'Too weak', col: '#dc2626', w: '20%' },
    { lbl: 'Weak',     col: '#ea580c', w: '40%' },
    { lbl: 'Fair',     col: '#d97706', w: '60%' },
    { lbl: 'Good',     col: '#059669', w: '80%' },
    { lbl: 'Strong',   col: '#047857', w: '100%' },
  ];
  function score(p) {
    if (!p) return -1;
    let s = 0;
    if (p.length >= 8) s++;
    if (/[A-Z]/.test(p)) s++;
    if (/[a-z]/.test(p)) s++;
    if (/[0-9]/.test(p)) s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    return s - 1;
  }
  function update(pwd, fId, tId) {
    const f = document.getElementById(fId); if (!f) return;
    const t = document.getElementById(tId);
    const s = score(pwd);
    if (s < 0) { f.style.width = '0'; if (t) t.textContent = ''; return; }
    const lv = lvls[s];
    f.style.width = lv.w; f.style.background = lv.col;
    if (t) { t.textContent = lv.lbl; t.style.color = lv.col; }
  }
  return {
    init: (iId, fId, tId) => {
      const el = document.getElementById(iId);
      if (el) el.addEventListener('input', () => update(el.value, fId, tId));
    }
  };
})();

/* ── UTILS ───────────────────────────────────────── */
function togglePwd(inputId, btnId) {
  const inp = document.getElementById(inputId);
  const btn = document.getElementById(btnId);
  if (!inp) return;
  const show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  if (btn) {
    const ic = btn.querySelector('i');
    if (ic) { ic.classList.toggle('fa-eye', !show); ic.classList.toggle('fa-eye-slash', show); }
  }
}

const UI = {
  alert(type, msg, id) {
    const box = document.getElementById(id || 'alertBox');
    if (!box) return;
    const icons = { err: 'fa-circle-exclamation', ok: 'fa-circle-check', info: 'fa-circle-info', warn: 'fa-triangle-exclamation' };
    box.className = 'alert alert-' + type;
    box.innerHTML = `<i class="fa-solid ${icons[type] || 'fa-circle-info'}"></i><span>${msg}</span>`;
    box.classList.remove('hidden');
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  },
  hide(id) { const b = document.getElementById(id || 'alertBox'); if (b) b.classList.add('hidden'); },
  loading(btnId, on) {
    if (!btnId) return;
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.disabled = on;
    if (on) { btn.dataset.origText = btn.innerHTML; btn.innerHTML += ' <i class="fa-solid fa-circle-notch fa-spin" style="font-size:13px"></i>'; }
    else if (btn.dataset.origText) btn.innerHTML = btn.dataset.origText;
  }
};

/* ── FETCH HELPER ────────────────────────────────── */
async function post(url, data, btnId, alertId) {
  UI.hide(alertId);
  UI.loading(btnId, true);
  try {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 15000);
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(data),
      credentials: 'same-origin',
      signal: ctrl.signal,
    });
    clearTimeout(timer);
    const text = await res.text();
    try { return JSON.parse(text); }
    catch { console.error('Non-JSON response:', text.slice(0, 300)); return { success: false, message: 'Server error. Check console.' }; }
  } catch (err) {
    if (err.name === 'AbortError') UI.alert('err', 'Request timed out.', alertId);
    else if (!navigator.onLine) UI.alert('err', 'You are offline. Check your connection.', alertId);
    else UI.alert('err', 'Server unreachable. Please try again.', alertId);
    return null;
  } finally {
    UI.loading(btnId, false);
  }
}

/* ── BOOKING ─────────────────────────────────────── */
async function submitBooking() {
  const csrf = document.getElementById('csrfToken')?.value || document.getElementById('csrf')?.value || '';
  const date = document.getElementById('bookDate')?.value || '';
  const time = document.getElementById('bookTime')?.value || '09:00';
  if (!date) { UI.alert('warn', 'Please select a date.', 'bookAlertBox'); return; }
  const title = document.getElementById('bookTitle')?.value?.trim();
  if (!title) { UI.alert('warn', 'Please enter a reason for the appointment.', 'bookAlertBox'); return; }
  const r = await post('/api/patient/book-appointment.php', {
    service_type:  document.getElementById('bookServiceType')?.value || 'doctor',
    provider_id:   document.getElementById('bookProvider')?.value || null,
    appointment_at: date + ' ' + time + ':00',
    title,
    notes:         document.getElementById('bookNotes')?.value?.trim() || '',
    location_type: document.getElementById('bookLocType')?.value || 'in_person',
    csrf_token:    csrf,
  }, 'bookBtn', 'bookAlertBox');
  if (!r) return;
  if (r.success) {
    closeModal('bookModal');
    UI.alert('ok', 'Appointment booked successfully!', 'alertBox');
    setTimeout(() => location.href = '/patients/dashboard.php?tab=appointments', 1200);
  } else {
    UI.alert('err', r.message || 'Booking failed. Please try again.', 'bookAlertBox');
  }
}
window.submitBooking = submitBooking;

/* ── GEO ─────────────────────────────────────────── */
function requestLocation(onSuccess) {
  if (!navigator.geolocation) { alert('Geolocation is not supported by your browser.'); return; }
  navigator.geolocation.getCurrentPosition(pos => {
    const { latitude: lat, longitude: lng } = pos.coords;
    fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`)
      .then(r => r.json())
      .then(d => {
        const city = d.address?.city || d.address?.town || d.address?.county || 'Your Location';
        const label = city + (d.address?.country ? ', ' + d.address.country : '');
        document.querySelectorAll('[data-location-label]').forEach(el => el.textContent = label);
        const sLoc = document.getElementById('sLoc');
        if (sLoc) sLoc.value = label;
        if (onSuccess) onSuccess(lat, lng, label);
      }).catch(() => {});
  }, err => {
    const msgs = { 1: 'Location access denied.', 2: 'Location unavailable.', 3: 'Location request timed out.' };
    alert(msgs[err.code] || 'Could not get location.');
  });
}
window.requestLocation = requestLocation;

/* ── REVEAL ANIMATIONS ───────────────────────────── */
function initReveal() {
  if (!window.IntersectionObserver) return;
  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } });
  }, { threshold: 0.1 });
  document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
}

/* ── COUNTERS ────────────────────────────────────── */
function animCounters() {
  document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count), sfx = el.dataset.suffix || '';
    let c = 0; const step = target / 55;
    const t = setInterval(() => {
      c = Math.min(c + step, target);
      el.textContent = (target >= 1000 ? Math.floor(c).toLocaleString() : Math.floor(c)) + sfx;
      if (c >= target) clearInterval(t);
    }, 28);
  });
}

/* ── TELEHEALTH CALL UI ──────────────────────────── */
function initCall() {
  const micBtn = document.getElementById('micBtn');
  const camBtn = document.getElementById('camBtn');
  micBtn?.addEventListener('click', () => {
    micBtn.classList.toggle('muted');
    const ic = micBtn.querySelector('i');
    if (ic) { ic.classList.toggle('fa-microphone', !micBtn.classList.contains('muted')); ic.classList.toggle('fa-microphone-slash', micBtn.classList.contains('muted')); }
  });
  camBtn?.addEventListener('click', () => {
    camBtn.classList.toggle('cam-off');
    const ic = camBtn.querySelector('i');
    if (ic) { ic.classList.toggle('fa-video', !camBtn.classList.contains('cam-off')); ic.classList.toggle('fa-video-slash', camBtn.classList.contains('cam-off')); }
  });
  const timerEl = document.getElementById('callTimer');
  if (timerEl) {
    let s = 0;
    setInterval(() => {
      s++;
      const m = String(Math.floor(s / 60)).padStart(2, '0');
      const sc = String(s % 60).padStart(2, '0');
      timerEl.textContent = `${m}:${sc}`;
    }, 1000);
  }
  const chatInp  = document.getElementById('chatInp');
  const chatSend = document.getElementById('chatSend');
  const chatMsgs = document.getElementById('chatMsgs');
  if (chatSend && chatInp && chatMsgs) {
    function sendMsg() {
      const msg = chatInp.value.trim(); if (!msg) return;
      const div = document.createElement('div'); div.className = 'chat-bubble me';
      div.innerHTML = `<div class="bubble">${msg}</div><div class="chat-time">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>`;
      chatMsgs.appendChild(div); chatMsgs.scrollTop = chatMsgs.scrollHeight; chatInp.value = '';
    }
    chatSend.addEventListener('click', sendMsg);
    chatInp.addEventListener('keydown', e => { if (e.key === 'Enter') sendMsg(); });
  }
}

/* ── PROVIDER AVAILABILITY ───────────────────────── */
async function saveAvailability() {
  const csrf = document.getElementById('csrfToken')?.value || '';
  const slots = [];
  document.querySelectorAll('.day-row').forEach(row => {
    const day = row.dataset.day; if (!day) return;
    row.querySelectorAll('.time-slot').forEach(slot => {
      const st   = slot.querySelector('.time-s')?.value;
      const en   = slot.querySelector('.time-e')?.value;
      const mode = slot.querySelector('.mode-btn.active')?.dataset.mode || 'in_person';
      if (st && en) slots.push({ day, start: st, end: en, mode });
    });
  });
  const r = await post('/api/provider/set-availability.php', { slots, csrf_token: csrf }, null, 'availAlert');
  if (r?.success) UI.alert('ok', 'Availability saved successfully.', 'availAlert');
  else UI.alert('err', r?.message || 'Failed to save.', 'availAlert');
}
window.saveAvailability = saveAvailability;

/* ── INIT ────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  Sidebar.init();
  Lang.init();
  OTP.init('#otpGrid');
  initReveal();
  initCall();

  // Close modals on overlay click
  document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
  });

  // Lang toggle
  document.getElementById('langToggle')?.addEventListener('click', Lang.toggle);

  // Mobile sidebar toggle button
  document.getElementById('mobToggle')?.addEventListener('click', () => Sidebar.openMob());
  document.getElementById('mobSidebarToggle')?.addEventListener('click', () => Sidebar.openMob());
  document.getElementById('mobOv')?.addEventListener('click', () => Sidebar.closeMob());

  // Animate counters when section scrolls into view
  const firstCounter = document.querySelector('[data-count]');
  if (firstCounter) {
    const section = firstCounter.closest('section') || firstCounter;
    const obs = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting) { animCounters(); obs.disconnect(); }
    }, { threshold: 0.3 });
    obs.observe(section);
  }

  // Search bar enter key on index page
  document.querySelectorAll('#sLoc, #sQuery').forEach(el => {
    el.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        const fn = window.doSearch;
        if (fn) fn();
      }
    });
  });

  // Dashboard topbar search enter
  const dashSearch = document.getElementById('dashSearch');
  if (dashSearch) {
    dashSearch.addEventListener('keydown', e => {
      if (e.key === 'Enter') location.href = '/patients/search.php?q=' + encodeURIComponent(dashSearch.value);
    });
  }
});
