/**
 * Planeazzy Healthcare — app.js v3.0
 * Lang · OTP · PwdStrength · AJAX (fixed) · Sidebar · Location · Modals
 */
'use strict';

/* ── Language ──────────────────────────────────────────── */
const Lang = (() => {
  let cur = localStorage.getItem('pz_lang') || 'en';
  function apply(lang) {
    cur = lang; localStorage.setItem('pz_lang', lang);
    document.querySelectorAll('[data-en]').forEach(el => {
      const v = el.getAttribute('data-' + lang); if (v != null) el.textContent = v;
    });
    document.querySelectorAll('[data-en-ph]').forEach(el => {
      const v = el.getAttribute('data-' + lang + '-ph'); if (v != null) el.placeholder = v;
    });
    const lbl = document.getElementById('langLabel');
    if (lbl) lbl.textContent = lang === 'en' ? 'SW' : 'EN';
  }
  return {
    toggle: () => apply(cur === 'en' ? 'sw' : 'en'),
    init:   () => apply(cur),
    get:    () => cur,
  };
})();

/* ── OTP Input ─────────────────────────────────────────── */
const OTP = (() => {
  function init(sel) {
    const grid = document.querySelector(sel || '#otpGrid'); if (!grid) return;
    const inputs = [...grid.querySelectorAll('.otp-digit')];
    inputs.forEach((inp, i) => {
      inp.addEventListener('input', () => {
        inp.value = inp.value.replace(/\D/g, '').slice(0, 1);
        inp.classList.toggle('filled', !!inp.value);
        if (inp.value && i < inputs.length - 1) inputs[i+1].focus();
        check(inputs);
      });
      inp.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !inp.value && i > 0) {
          inputs[i-1].focus(); inputs[i-1].value = ''; inputs[i-1].classList.remove('filled');
        }
        if (e.key === 'ArrowLeft'  && i > 0)               inputs[i-1].focus();
        if (e.key === 'ArrowRight' && i < inputs.length-1) inputs[i+1].focus();
      });
      inp.addEventListener('paste', e => {
        e.preventDefault();
        const digits = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0, inputs.length);
        digits.split('').forEach((d, j) => { if (inputs[j]) { inputs[j].value = d; inputs[j].classList.add('filled'); } });
        const next = inputs.findIndex(x => !x.value);
        (next !== -1 ? inputs[next] : inputs[inputs.length-1]).focus();
        check(inputs);
      });
    });
  }
  function check(inputs) {
    const done = inputs.every(x => x.value);
    const btn  = document.getElementById('verifyBtn');
    if (btn) btn.disabled = !done;
  }
  function value(sel) {
    return [...document.querySelectorAll((sel || '#otpGrid') + ' .otp-digit')].map(x => x.value).join('');
  }
  return { init, value };
})();

/* ── Password Strength ─────────────────────────────────── */
const PwdStrength = (() => {
  const levels = [
    { label:'Too weak', color:'#dc2626', w:'20%' },
    { label:'Weak',     color:'#ea580c', w:'40%' },
    { label:'Fair',     color:'#d97706', w:'60%' },
    { label:'Good',     color:'#059669', w:'80%' },
    { label:'Strong',   color:'#047857', w:'100%' },
  ];
  function score(p) {
    if (!p) return -1;
    let s = 0;
    if (p.length >= 8)        s++;
    if (/[A-Z]/.test(p))      s++;
    if (/[a-z]/.test(p))      s++;
    if (/[0-9]/.test(p))      s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    return s - 1;
  }
  function update(pwd, fillId, txtId) {
    const fill = document.getElementById(fillId); if (!fill) return;
    const txt  = document.getElementById(txtId);
    const s    = score(pwd);
    if (s < 0) { fill.style.width = '0'; if (txt) txt.textContent = ''; return; }
    const lvl  = levels[s];
    fill.style.width = lvl.w; fill.style.background = lvl.color;
    if (txt) { txt.textContent = lvl.label; txt.style.color = lvl.color; }
  }
  return {
    init: (inpId, fillId, txtId) => {
      const el = document.getElementById(inpId);
      if (el) el.addEventListener('input', () => update(el.value, fillId, txtId));
    }
  };
})();

/* ── Toggle password visibility ────────────────────────── */
function togglePwd(inputId, btnId) {
  const inp = document.getElementById(inputId), btn = document.getElementById(btnId);
  if (!inp) return;
  const show = inp.type === 'password'; inp.type = show ? 'text' : 'password';
  if (btn) { const ico = btn.querySelector('.material-symbols-outlined'); if (ico) ico.textContent = show ? 'visibility_off' : 'visibility'; }
}

/* ── UI Alerts & Loading ───────────────────────────────── */
const UI = {
  alert(type, msg, id) {
    const box = document.getElementById(id || 'alertBox'); if (!box) return;
    const icons = { err:'error', ok:'check_circle', info:'info', warn:'warning' };
    box.className = 'alert alert-' + type;
    box.innerHTML = `<span class="material-symbols-outlined">${icons[type]||'info'}</span><span>${msg}</span>`;
    box.classList.remove('hidden');
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  },
  hide(id) { const b = document.getElementById(id || 'alertBox'); if (b) b.classList.add('hidden'); },
  loading(btnId, on) {
    const btn = document.getElementById(btnId); if (!btn) return;
    btn.classList.toggle('loading', on); btn.disabled = on;
  }
};

/* ── POST (network-error safe) ─────────────────────────── */
async function post(url, data, btnId, alertId) {
  UI.hide(alertId); UI.loading(btnId, true);
  try {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 15000);
    const res   = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(data),
      credentials: 'same-origin',
      signal: ctrl.signal,
    });
    clearTimeout(timer);
    return await res.json().catch(() => ({ success: false, message: 'Unexpected server response.' }));
  } catch (err) {
    if (err.name === 'AbortError') {
      UI.alert('err', 'Request timed out. Check your connection and try again.', alertId);
    } else if (!navigator.onLine) {
      UI.alert('err', 'You are offline. Please check your internet connection.', alertId);
    } else {
      UI.alert('err', 'Could not reach the server. Please try again.', alertId);
    }
    return null;
  } finally {
    UI.loading(btnId, false);
  }
}

/* ── Sidebar (mobile) ──────────────────────────────────── */
function initSidebar() {
  const sidebar    = document.getElementById('sidebar');
  const overlay    = document.getElementById('mobOverlay');
  const toggle     = document.getElementById('mobToggle');
  if (!sidebar) return;
  function open()  { sidebar.classList.add('open'); if (overlay) overlay.classList.add('open'); }
  function close() { sidebar.classList.remove('open'); if (overlay) overlay.classList.remove('open'); }
  if (toggle)  toggle.addEventListener('click', () => sidebar.classList.contains('open') ? close() : open());
  if (overlay) overlay.addEventListener('click', close);
  window.toggleSidebar = () => sidebar.classList.contains('open') ? close() : open();
  window.closeSidebar  = close;
}

/* ── Modals ────────────────────────────────────────────── */
function openModal(id)  { const m = document.getElementById(id); if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; } }
function closeModal(id) { const m = document.getElementById(id); if (m) { m.classList.remove('open'); document.body.style.overflow = ''; } }
window.openModal  = openModal;
window.closeModal = closeModal;

function initModals() {
  document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) { m.classList.remove('open'); document.body.style.overflow = ''; } });
  });
}

/* ── Geolocation ───────────────────────────────────────── */
function requestLocation(onSuccess) {
  if (!navigator.geolocation) {
    alert('Geolocation not supported. Please enter location manually.'); return;
  }
  navigator.geolocation.getCurrentPosition(
    pos => {
      const lat = pos.coords.latitude, lng = pos.coords.longitude;
      // Reverse geocode via Nominatim (free, no API key)
      fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`)
        .then(r => r.json())
        .then(d => {
          const city    = d.address?.city || d.address?.town || d.address?.county || 'Your Location';
          const country = d.address?.country || '';
          const label   = city + (country ? ', ' + country : '');
          // Update all location labels
          ['locLabel','nearbyLocText','mapLocText','locTextDisplay'].forEach(id => {
            const el = document.getElementById(id); if (el) el.textContent = label;
          });
          // Approx H3-style hex ID
          const hexEl = document.getElementById('hexId');
          if (hexEl) hexEl.textContent = coordsToHexId(lat, lng);
          if (onSuccess) onSuccess(lat, lng, label);
        })
        .catch(() => {
          const label = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
          ['locLabel','nearbyLocText','mapLocText'].forEach(id => { const el = document.getElementById(id); if (el) el.textContent = label; });
        });
      closeModal('locationModal');
    },
    err => {
      const msgs = { 1:'Location access denied. Please enter manually.', 2:'Location unavailable.', 3:'Request timed out.' };
      alert(msgs[err.code] || 'Could not get location.');
    },
    { enableHighAccuracy: true, timeout: 12000 }
  );
}

function setManualLocation() {
  const val = (document.getElementById('manualLocInput')?.value || '').trim();
  if (!val) return;
  ['locLabel','nearbyLocText','mapLocText','locTextDisplay'].forEach(id => { const el = document.getElementById(id); if (el) el.textContent = val; });
  closeModal('locationModal');
}

function coordsToHexId(lat, lng) {
  const a = Math.abs(Math.floor(lat * 1000)).toString(16).padStart(4,'0');
  const b = Math.abs(Math.floor(lng * 1000)).toString(16).padStart(4,'0');
  return '892' + a + b + 'fff';
}

/* ── Service preference cards ──────────────────────────── */
function initSvcCards() {
  document.querySelectorAll('.svc-label input[type="radio"]').forEach(r => {
    r.addEventListener('change', () => {
      document.querySelectorAll('.svc-card').forEach(c => c.classList.remove('selected'));
      if (r.checked) r.closest('.svc-label')?.querySelector('.svc-card')?.classList.add('selected');
    });
  });
}

/* ── FAQ accordion ─────────────────────────────────────── */
function initFaq() {
  document.querySelectorAll('.faq-item').forEach(item => {
    item.querySelector('.faq-q')?.addEventListener('click', () => {
      const isOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(x => x.classList.remove('open'));
      if (!isOpen) item.classList.add('open');
    });
  });
}

/* ── Scroll reveal ─────────────────────────────────────── */
function initReveal() {
  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } });
  }, { threshold: 0.1 });
  document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
}

/* ── Topbar scroll shadow ──────────────────────────────── */
function initTopbar() {
  const el = document.querySelector('.topbar');
  if (!el) return;
  window.addEventListener('scroll', () => el.classList.toggle('scrolled', window.scrollY > 8), { passive: true });
}

/* ── Counter animation ─────────────────────────────────── */
function animateCounters() {
  document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count);
    const suffix = el.dataset.suffix || '';
    let current  = 0;
    const step   = target / (1600 / 30);
    const t      = setInterval(() => {
      current = Math.min(current + step, target);
      el.textContent = Math.floor(current).toLocaleString() + suffix;
      if (current >= target) clearInterval(t);
    }, 30);
  });
}

/* ── Telehealth call controls ──────────────────────────── */
function initCallControls() {
  const micBtn = document.getElementById('micBtn');
  const camBtn = document.getElementById('camBtn');
  if (micBtn) micBtn.addEventListener('click', () => {
    micBtn.classList.toggle('active');
    const ico = micBtn.querySelector('.material-symbols-outlined');
    if (ico) ico.textContent = micBtn.classList.contains('active') ? 'mic_off' : 'mic';
  });
  if (camBtn) camBtn.addEventListener('click', () => {
    camBtn.classList.toggle('active');
    const ico = camBtn.querySelector('.material-symbols-outlined');
    if (ico) ico.textContent = camBtn.classList.contains('active') ? 'videocam_off' : 'videocam';
  });

  // Session timer
  const timerEl = document.getElementById('sessionTimer');
  if (timerEl) {
    let secs = 0;
    setInterval(() => {
      secs++;
      const m = String(Math.floor(secs/60)).padStart(2,'0');
      const s = String(secs%60).padStart(2,'0');
      timerEl.textContent = `${m}:${s}`;
    }, 1000);
  }

  // Chat send
  const chatInput = document.getElementById('chatInput');
  const chatSend  = document.getElementById('chatSend');
  const chatMsgs  = document.getElementById('chatMsgs');
  if (chatSend && chatInput && chatMsgs) {
    function sendMsg() {
      const msg = chatInput.value.trim(); if (!msg) return;
      const div = document.createElement('div');
      div.className = 'chat-bubble me';
      div.innerHTML = `<div class="bubble">${msg}</div><div class="chat-time">${new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})}</div>`;
      chatMsgs.appendChild(div);
      chatMsgs.scrollTop = chatMsgs.scrollHeight;
      chatInput.value = '';
    }
    chatSend.addEventListener('click', sendMsg);
    chatInput.addEventListener('keydown', e => { if (e.key === 'Enter') sendMsg(); });
  }
}

/* ── Dashboard preferences ─────────────────────────────── */
let _selPref = '';
function setPref(val) {
  _selPref = val;
  document.querySelectorAll('.pref-card').forEach(c => c.classList.toggle('selected', c.dataset.val === val));
}
async function savePreferences() {
  if (!_selPref) { UI.alert('warn', 'Please select a service first.', 'prefAlert'); return; }
  const r = await post('/api/patient/save-preferences.php', {
    service: _selPref,
    csrf_token: document.getElementById('csrfToken')?.value || ''
  }, null, 'prefAlert');
  if (r?.success) UI.alert('ok', 'Preferences saved successfully!', 'prefAlert');
  else UI.alert('err', r?.message || 'Failed to save.', 'prefAlert');
}
window.setPref = setPref;
window.savePreferences = savePreferences;

/* ── Emergency ─────────────────────────────────────────── */
function triggerSOS() {
  if (!confirm('EMERGENCY SOS\n\nThis will immediately dispatch emergency services to your current location.\n\nContinue?')) return;
  requestLocation((lat, lng, label) => {
    const okEl = document.getElementById('emergOk');
    if (okEl) { okEl.textContent = `Emergency request sent. Nearest responder dispatched to: ${label} (${lat.toFixed(4)}, ${lng.toFixed(4)}).`; okEl.parentElement.classList.remove('hidden'); }
  });
}
window.triggerSOS = triggerSOS;

/* ── Book appointment ──────────────────────────────────── */
async function submitBooking() {
  const date    = document.getElementById('bookDate')?.value;
  const time    = document.getElementById('bookTime')?.value;
  const type    = document.getElementById('bookServiceType')?.value;
  const prov    = document.getElementById('bookProvider')?.value;
  const title   = document.getElementById('bookTitle')?.value?.trim();
  const locType = document.getElementById('bookLocType')?.value;
  const csrf    = document.getElementById('csrfToken')?.value || document.getElementById('csrf')?.value || '';

  if (!date || !time) { UI.alert('err', 'Please select a date and time.', 'bookAlertBox'); return; }

  const r = await post('/api/patient/book-appointment.php', {
    service_type:   type,
    provider_id:    prov || null,
    appointment_at: date + ' ' + time + ':00',
    title:          title || ucfirst(type) + ' Appointment',
    notes:          document.getElementById('bookNotes')?.value?.trim() || '',
    location_type:  locType,
    csrf_token:     csrf,
  }, 'bookBtn', 'bookAlertBox');

  if (!r) return;
  if (r.success) {
    closeModal('bookModal');
    window.location.href = '?tab=appointments';
  } else {
    UI.alert('err', r.message || 'Booking failed.', 'bookAlertBox');
  }
}
window.submitBooking = submitBooking;

function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
window.requestLocation = requestLocation;
window.setManualLocation = setManualLocation;

/* ── Init on DOM ready ─────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  Lang.init();
  OTP.init('#otpGrid');
  initSvcCards();
  initFaq();
  initReveal();
  initTopbar();
  initSidebar();
  initModals();
  initCallControls();

  // Language toggle
  document.getElementById('langToggle')?.addEventListener('click', Lang.toggle);

  // Animate counters when visible
  const statsObs = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) { animateCounters(); statsObs.disconnect(); }
  }, { threshold: 0.3 });
  const statsEl = document.querySelector('.stats-row, [data-count]');
  if (statsEl) statsObs.observe(statsEl);

  // Auto-dismiss non-persistent alerts
  document.querySelectorAll('.alert:not(.keep)').forEach(a => {
    setTimeout(() => a.classList.add('hidden'), 7000);
  });
});
