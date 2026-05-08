/* 
   PLANEAZZY — Global JavaScript Utilities
    */
'use strict';

/*  Toast Notifications  */
const Toast = (() => {
  let container = null;
  const get = () => {
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    return container;
  };
  return {
    show(msg, type = 'info', duration = 4000) {
      const icons = { success: '', error: '', info: 'ℹ', warn: '' };
      const el = document.createElement('div');
      el.className = `toast ${type}`;
      el.innerHTML = `<span>${icons[type] || 'ℹ'}</span><span>${msg}</span>`;
      get().appendChild(el);
      setTimeout(() => {
        el.style.animation = 'slideOutRight .3s ease forwards';
        el.addEventListener('animationend', () => el.remove());
      }, duration);
    },
    success: (m, d) => Toast.show(m, 'success', d),
    error:   (m, d) => Toast.show(m, 'error', d),
    info:    (m, d) => Toast.show(m, 'info', d),
  };
})();

/*  Mobile Nav  */
function initMobileNav() {
  const hamburger = document.querySelector('.hamburger');
  const mobileNav = document.querySelector('.mobile-nav');
  const overlay   = mobileNav?.querySelector('.mobile-nav-overlay');
  const closeBtn  = mobileNav?.querySelector('.mobile-nav-close');
  if (!hamburger || !mobileNav) return;
  const open  = () => mobileNav.classList.add('open');
  const close = () => mobileNav.classList.remove('open');
  hamburger.addEventListener('click', open);
  overlay?.addEventListener('click', close);
  closeBtn?.addEventListener('click', close);
}

/*  CSRF helper  */
function getCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/*  API helper  */
async function api(url, data = {}) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({ ...data, csrf_token: getCsrf() }),
  });
  return res.json();
}

/*  Booking Modal  */
class BookingModal {
  constructor() {
    this.overlay  = document.getElementById('bookingModal');
    this.provider = null;
    this.state    = { step: 1, reason: '', date: null, time: null, visitType: 'in_person', notes: '', serviceType: 'doctor' };
    this.currentMonth = new Date();
    this.slots    = {};
    if (this.overlay) this.init();
  }

  init() {
    // Close handlers
    this.overlay.addEventListener('click', e => { if (e.target === this.overlay) this.close(); });
    document.getElementById('modalCloseBtn')?.addEventListener('click', () => this.close());
    document.addEventListener('keydown', e => { if (e.key === 'Escape') this.close(); });

    // Step navigation
    document.getElementById('bookNextBtn')?.addEventListener('click', () => this.nextStep());
    document.getElementById('bookBackBtn')?.addEventListener('click', () => this.prevStep());
    document.getElementById('confirmBookBtn')?.addEventListener('click', () => this.confirm());

    // Reason dropdown
    document.getElementById('reasonSelect')?.addEventListener('change', e => {
      this.state.reason = e.target.value;
      const notesRow = document.getElementById('notesRequiredRow');
      if (notesRow) notesRow.style.display = e.target.value === 'other' ? 'block' : 'none';
    });

    // Visit type
    document.querySelectorAll('.type-toggle-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.type-toggle-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        this.state.visitType = btn.dataset.type;
      });
    });

    // Notes
    document.getElementById('bookingNotes')?.addEventListener('input', e => {
      this.state.notes = e.target.value;
    });
  }

  open(provider) {
    this.provider = provider;
    this.state    = { step: 1, reason: '', date: null, time: null, visitType: 'in_person', notes: '', serviceType: provider.serviceType || 'doctor' };
    this.currentMonth = new Date();
    this.renderStep(1);
    this.overlay.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Set provider info in modal
    const nameEl = document.getElementById('modalProviderName');
    const locEl  = document.getElementById('modalProviderLoc');
    if (nameEl) nameEl.textContent = provider.name;
    if (locEl)  locEl.textContent  = provider.location || '';
  }

  close() {
    this.overlay.classList.remove('active');
    document.body.style.overflow = '';
    // Reset
    setTimeout(() => { this.state.step = 1; this.renderStep(1); }, 300);
  }

  renderStep(step) {
    document.querySelectorAll('.book-step').forEach(s => s.classList.add('hidden'));
    const active = document.getElementById(`bookStep${step}`);
    if (active) active.classList.remove('hidden');
    this.updateStepBar(step);
    this.updateNavButtons(step);
    if (step === 2) this.renderCalendar();
    if (step === 3) this.renderTimeSlots();
    if (step === 5) this.renderSummary();
  }

  updateStepBar(step) {
    document.querySelectorAll('.step-item').forEach((el, i) => {
      el.classList.remove('active', 'done');
      if (i + 1 < step) el.classList.add('done');
      if (i + 1 === step) el.classList.add('active');
    });
  }

  updateNavButtons(step) {
    const back    = document.getElementById('bookBackBtn');
    const next    = document.getElementById('bookNextBtn');
    const confirm = document.getElementById('confirmBookBtn');
    if (back)    back.classList.toggle('hidden', step === 1);
    if (next)    next.classList.toggle('hidden', step === 5);
    if (confirm) confirm.classList.toggle('hidden', step !== 5);
  }

  nextStep() {
    const s = this.state;
    if (s.step === 1 && !s.reason) { Toast.error('Please select a reason for your visit.'); return; }
    if (s.step === 1 && s.reason === 'other' && !s.notes.trim()) { Toast.error('Please add notes for "Other" reason.'); return; }
    if (s.step === 2 && !s.date) { Toast.error('Please pick an available date.'); return; }
    if (s.step === 3 && !s.time) { Toast.error('Please select a time slot.'); return; }
    s.step++;
    this.renderStep(s.step);
  }

  prevStep() { this.state.step--; this.renderStep(this.state.step); }

  renderCalendar() {
    const cal    = document.getElementById('calendarGrid');
    const label  = document.getElementById('calMonthLabel');
    const hint   = document.getElementById('nextAvailableHint');
    if (!cal) return;

    const today = new Date(); today.setHours(0,0,0,0);
    const yr  = this.currentMonth.getFullYear();
    const mo  = this.currentMonth.getMonth();
    const first = new Date(yr, mo, 1);
    const days  = new Date(yr, mo + 1, 0).getDate();
    const startDay = first.getDay();
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    if (label) label.textContent = `${months[mo]} ${yr}`;

    // Simulate available days (in real app: fetch from API)
    const availDays = new Set();
    for (let d = 1; d <= days; d++) {
      const dt = new Date(yr, mo, d);
      const dow = dt.getDay();
      if (dow !== 0 && dt >= today) availDays.add(d); // not Sunday, future
    }

    // Find next available
    let nextAvail = null;
    for (let d = 1; d <= days; d++) {
      const dt = new Date(yr, mo, d);
      if (dt >= today && availDays.has(d)) { nextAvail = dt; break; }
    }
    if (hint && nextAvail) {
      const opts = { weekday:'short', month:'short', day:'numeric' };
      hint.textContent = `⏰ Next available: ${nextAvail.toLocaleDateString('en-KE', opts)} at 9:00 AM`;
    }

    // Prev/next month nav
    document.getElementById('calPrev')?.removeEventListener('click', this._calPrevFn);
    document.getElementById('calNext')?.removeEventListener('click', this._calNextFn);
    this._calPrevFn = () => { this.currentMonth.setMonth(mo - 1); this.renderCalendar(); };
    this._calNextFn = () => { this.currentMonth.setMonth(mo + 1); this.renderCalendar(); };
    document.getElementById('calPrev')?.addEventListener('click', this._calPrevFn);
    document.getElementById('calNext')?.addEventListener('click', this._calNextFn);

    // Build grid
    const dayNames = ['Su','Mo','Tu','We','Th','Fr','Sa'];
    let html = dayNames.map(d => `<div class="cal-day-head">${d}</div>`).join('');
    // Empty cells
    for (let i = 0; i < startDay; i++) html += `<button class="cal-day empty" disabled></button>`;
    for (let d = 1; d <= days; d++) {
      const dt   = new Date(yr, mo, d);
      const isToday  = dt.toDateString() === today.toDateString();
      const isAvail  = availDays.has(d);
      const isPast   = dt < today;
      const isSelected = this.state.date && new Date(this.state.date).toDateString() === dt.toDateString();
      const cls = ['cal-day', isToday ? 'today' : '', isAvail ? 'available' : '', isPast ? 'past' : '', isSelected ? 'selected' : ''].filter(Boolean).join(' ');
      html += `<button class="${cls}" data-date="${dt.toISOString().split('T')[0]}" ${!isAvail || isPast ? 'disabled' : ''}>${d}</button>`;
    }
    cal.innerHTML = html;
    cal.querySelectorAll('.cal-day:not([disabled])').forEach(btn => {
      btn.addEventListener('click', () => {
        cal.querySelectorAll('.cal-day').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        this.state.date = btn.dataset.date;
        // Auto-advance hint
        if (this.provider?.isHospital) {
          document.getElementById('hospitalMatchHint')?.classList.remove('hidden');
        }
      });
    });
  }

  renderTimeSlots() {
    const container = document.getElementById('timeSlotsContainer');
    if (!container) return;

    // Simulate slots — real app fetches from API
    const morning = ['9:00 AM','9:30 AM','10:00 AM','10:30 AM','11:00 AM'];
    const afternoon = ['2:00 PM','2:30 PM','3:00 PM','3:30 PM','4:00 PM'];
    // Randomly mark some as booked
    const booked = new Set([1, 3, 6]);

    let html = `<p class="slots-period-label"> Morning</p><div class="slots-grid">`;
    morning.forEach((t, i) => {
      const isBooked = booked.has(i);
      const isSelected = this.state.time === t;
      html += `<button class="slot-btn${isSelected ? ' selected' : ''}" data-time="${t}" ${isBooked ? 'disabled' : ''}>${t}${isBooked ? '<br><small>Booked</small>':''}</button>`;
    });
    html += `</div><p class="slots-period-label"> Afternoon</p><div class="slots-grid">`;
    afternoon.forEach((t, i) => {
      const isBooked = booked.has(i + 5);
      const isSelected = this.state.time === t;
      html += `<button class="slot-btn${isSelected ? ' selected' : ''}" data-time="${t}" ${isBooked ? 'disabled' : ''}>${t}${isBooked ? '<br><small>Booked</small>':''}</button>`;
    });
    html += `</div>`;
    container.innerHTML = html;

    container.querySelectorAll('.slot-btn:not([disabled])').forEach(btn => {
      btn.addEventListener('click', () => {
        container.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        this.state.time = btn.dataset.time;
      });
    });
  }

  renderSummary() {
    const p = this.provider;
    const s = this.state;
    const reasonMap = {
      general_consultation: 'General Consultation', followup: 'Follow-up', checkup: 'Check-up',
      specialist: 'Specialist Visit', emergency: 'Emergency', other: 'Other'
    };
    const visMap = { in_person: ' In-Person', telehealth: ' Telehealth', home_visit: ' Home Visit' };
    const fmtDate = s.date ? new Date(s.date + 'T00:00').toLocaleDateString('en-KE', { weekday:'long', month:'long', day:'numeric' }) : '—';

    document.getElementById('sumProvider').textContent = p?.name || '—';
    document.getElementById('sumLocation').textContent = p?.location || '—';
    document.getElementById('sumDate').textContent     = `${fmtDate}`;
    document.getElementById('sumTime').textContent     = s.time || '—';
    document.getElementById('sumVisit').textContent    = visMap[s.visitType] || s.visitType;
    document.getElementById('sumReason').textContent   = reasonMap[s.reason] || s.reason;
    const feeRow = document.getElementById('sumFeeRow');
    if (feeRow) {
      if (p?.fee && !p?.isHospital) {
        feeRow.classList.remove('hidden');
        document.getElementById('sumFee').textContent = `KSh ${p.fee}`;
      } else {
        feeRow.classList.add('hidden');
      }
    }
  }

  async confirm() {
    const btn = document.getElementById('confirmBookBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = `<span class="spinner"></span> Booking…`; }

    const s   = this.state;
    const p   = this.provider;
    const dt  = s.date && s.time ? `${s.date} ${this.to24h(s.time)}:00` : null;

    if (!dt) { Toast.error('Missing date/time.'); if (btn) { btn.disabled = false; btn.textContent = 'Confirm Appointment'; } return; }

    const payload = {
      service_type:    p?.serviceType || 'doctor',
      provider_id:     p?.providerId  || null,
      appointment_at:  dt,
      title:           s.reason,
      notes:           s.notes,
      location_type:   s.visitType,
    };

    try {
      const res = await api('/api/patient/book-appointment.php', payload);
      if (res.requires_login) { window.location.href = res.redirect; return; }
      if (res.success) {
        this.showSuccess(res.appointment_id);
      } else {
        Toast.error(res.message || 'Booking failed. Please try again.');
        if (btn) { btn.disabled = false; btn.textContent = 'Confirm Appointment'; }
      }
    } catch(e) {
      Toast.error('Network error. Please try again.');
      if (btn) { btn.disabled = false; btn.textContent = 'Confirm Appointment'; }
    }
  }

  showSuccess(apptId) {
    // Hide steps/footer, show success
    document.querySelector('.steps-bar')?.classList.add('hidden');
    document.querySelector('.modal-footer')?.classList.add('hidden');
    document.querySelectorAll('.book-step').forEach(s => s.classList.add('hidden'));
    const succ = document.getElementById('bookSuccess');
    if (succ) {
      succ.classList.remove('hidden');
      const refEl = succ.querySelector('.booking-ref');
      if (refEl) refEl.textContent = `PZY-${String(apptId).padStart(6,'0')}`;
    }
  }

  to24h(t) {
    const [time, meridiem] = t.split(' ');
    let [h, m] = time.split(':').map(Number);
    if (meridiem === 'PM' && h !== 12) h += 12;
    if (meridiem === 'AM' && h === 12) h = 0;
    return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
  }
}

/*  Feedback Modal  */
class FeedbackModal {
  constructor() {
    this.overlay = document.getElementById('feedbackModal');
    this.rating  = 0;
    if (this.overlay) this.init();
  }
  init() {
    this.overlay.addEventListener('click', e => { if (e.target === this.overlay) this.close(); });
    document.getElementById('feedbackCloseBtn')?.addEventListener('click', () => this.close());
    this.overlay.querySelectorAll('.rating-large .star').forEach((star, i) => {
      star.addEventListener('click', () => {
        this.rating = i + 1;
        this.overlay.querySelectorAll('.rating-large .star').forEach((s, j) => {
          s.classList.toggle('active', j <= i);
        });
      });
    });
    document.getElementById('submitFeedbackBtn')?.addEventListener('click', () => this.submit());
  }
  open(apptId, providerName) {
    this.apptId = apptId;
    this.overlay.querySelector('.feedback-provider-name').textContent = providerName || 'your provider';
    this.rating = 0;
    this.overlay.querySelectorAll('.rating-large .star').forEach(s => s.classList.remove('active'));
    this.overlay.querySelector('#feedbackComment').value = '';
    this.overlay.classList.add('active');
  }
  close() { this.overlay.classList.remove('active'); }
  async submit() {
    if (!this.rating) { Toast.error('Please select a rating.'); return; }
    const comment = document.getElementById('feedbackComment')?.value || '';
    const btn = document.getElementById('submitFeedbackBtn');
    if (btn) btn.disabled = true;
    const res = await api('/api/patient/submit-feedback.php', { appointment_id: this.apptId, rating: this.rating, comment });
    if (res.success) {
      Toast.success('Thank you for your feedback! ');
      this.close();
    } else {
      Toast.error(res.message || 'Could not submit feedback.');
      if (btn) btn.disabled = false;
    }
  }
}

/*  Delete Account  */
function initDeleteAccount() {
  const form = document.getElementById('deleteAccountForm');
  if (!form) return;
  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (!confirm(' This will permanently delete your account and all data. Are you absolutely sure?')) return;
    const pwd = form.querySelector('[name="password"]')?.value;
    const btn = form.querySelector('[type="submit"]');
    if (btn) btn.disabled = true;
    const res = await api('/api/auth/delete-account.php', { password: pwd });
    if (res.success) {
      Toast.success('Account deleted. Redirecting…');
      setTimeout(() => window.location.href = '/', 1800);
    } else {
      Toast.error(res.message || 'Could not delete account.');
      if (btn) btn.disabled = false;
    }
  });
}

/*  Geofencing / Location  */
const GeoFilter = {
  userLat: null, userLng: null,
  async getLocation() {
    return new Promise(res => {
      if (!navigator.geolocation) { res(null); return; }
      navigator.geolocation.getCurrentPosition(
        pos => { this.userLat = pos.coords.latitude; this.userLng = pos.coords.longitude; res(pos.coords); },
        () => res(null),
        { timeout: 8000 }
      );
    });
  },
  distance(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  },
  filter(providers, radiusKm = 50) {
    if (!this.userLat) return providers;
    return providers.filter(p => {
      if (!p.lat || !p.lng) return true;
      return this.distance(this.userLat, this.userLng, p.lat, p.lng) <= radiusKm;
    }).map(p => ({
      ...p,
      distKm: this.distance(this.userLat, this.userLng, p.lat, p.lng).toFixed(1)
    })).sort((a, b) => a.distKm - b.distKm);
  }
};

/*  Init on DOM ready  */
document.addEventListener('DOMContentLoaded', () => {
  initMobileNav();
  initDeleteAccount();
  window.bookingModal  = new BookingModal();
  window.feedbackModal = new FeedbackModal();
  window.GeoFilter     = GeoFilter;
});
