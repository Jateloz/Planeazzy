'use strict';

/* ─────────────────────────────────────────────────────────────
   PLANEAZZY  app.js  v6
   Handles: Sidebar · Language (EN/SW, ALL pages) · Modals ·
            OTP · Password strength · Fetch · Animations · Geo
   ─────────────────────────────────────────────────────────────*/

/* ── SIDEBAR ─────────────────────────────────────── */
const Sidebar = (() => {
  let sb, mw;
  function init() {
    sb = document.getElementById('sidebar');
    mw = document.getElementById('mainWrap');
    if (!sb) return;
    if (localStorage.getItem('pz_sb') === 'collapsed' && window.innerWidth > 768) _collapse(false);
    sb.querySelector('.s-toggle-btn')?.addEventListener('click', toggle);
  }
  function toggle() { sb?.classList.contains('collapsed') ? _expand() : _collapse(); }
  function _collapse(save = true) {
    sb?.classList.add('collapsed');
    if (mw) mw.style.marginLeft = 'var(--sidebar-col)';
    const ic = document.getElementById('sToggleIcon');
    if (ic) { ic.classList.replace('fa-chevron-left','fa-chevron-right'); }
    if (save) localStorage.setItem('pz_sb', 'collapsed');
  }
  function _expand(save = true) {
    sb?.classList.remove('collapsed');
    if (mw) mw.style.marginLeft = 'var(--sidebar-w)';
    const ic = document.getElementById('sToggleIcon');
    if (ic) { ic.classList.replace('fa-chevron-right','fa-chevron-left'); }
    if (save) localStorage.setItem('pz_sb', 'open');
  }
  function openMob()  {
    sb?.classList.add('mob-open');
    const ov = document.getElementById('mobOv');
    if (ov) ov.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }
  function closeMob() {
    sb?.classList.remove('mob-open');
    const ov = document.getElementById('mobOv');
    if (ov) ov.style.display = 'none';
    document.body.style.overflow = '';
  }
  return { init, toggle, openMob, closeMob };
})();
window.toggleSidebar = () => Sidebar.openMob();
window.closeSidebar  = () => Sidebar.closeMob();

/* ── MODALS ──────────────────────────────────────── */
function openModal(id) {
  const m = document.getElementById(id); if (!m) return;
  m.classList.contains('modal-overlay') ? m.classList.add('open') : (m.style.display = 'flex');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  const m = document.getElementById(id); if (!m) return;
  m.classList.remove('open'); m.style.display = 'none';
  document.body.style.overflow = '';
}
window.openModal  = openModal;
window.closeModal = closeModal;

/* ═══════════════════════════════════════════════════
   LANGUAGE SYSTEM — EN / SW
   Works on EVERY page that uses app.js.
   Strategy:
     [data-en] + [data-sw]           → swap innerHTML
     [data-en-placeholder]           → swap placeholder
     [data-en-label]                 → swap aria-label
     [data-en-title]                 → swap title attr
     [data-en-value]                 → swap option value text
   Lang is persisted in localStorage('pz_lang').
   On every page load Lang.init() re-applies the stored lang.
   The toggle button (#langToggle) calls Lang.toggle().
═══════════════════════════════════════════════════ */
const Lang = (() => {
  let cur = localStorage.getItem('pz_lang') || 'en';

  function apply(lang) {
    cur = lang;
    localStorage.setItem('pz_lang', lang);
    document.documentElement.lang = lang === 'sw' ? 'sw' : 'en';

    /* 1 ── text / HTML content */
    document.querySelectorAll('[data-en]').forEach(el => {
      const val = el.getAttribute('data-' + lang);
      if (val === null) return;
      /* Use innerHTML when the stored value contains markup OR
         when a data-html="true" override is set.
         Otherwise use textContent to avoid XSS from user content. */
      if (el.dataset.html === 'true' || /<[a-z]/i.test(val)) {
        el.innerHTML = val;
      } else {
        el.textContent = val;
      }
    });

    /* 2 ── placeholder */
    document.querySelectorAll('[data-en-placeholder]').forEach(el => {
      const val = el.getAttribute('data-' + lang + '-placeholder');
      if (val !== null) el.placeholder = val;
    });

    /* 3 ── aria-label */
    document.querySelectorAll('[data-en-label]').forEach(el => {
      const val = el.getAttribute('data-' + lang + '-label');
      if (val !== null) el.setAttribute('aria-label', val);
    });

    /* 4 ── title attribute */
    document.querySelectorAll('[data-en-title]').forEach(el => {
      const val = el.getAttribute('data-' + lang + '-title');
      if (val !== null) el.title = val;
    });

    /* 5 ── <select> options with data-en-value / data-sw-value */
    document.querySelectorAll('option[data-en-value]').forEach(opt => {
      const val = opt.getAttribute('data-' + lang + '-value');
      if (val !== null) opt.text = val;
    });

    /* 6 ── hero <select> #sType (special case for homepage) */
    const sType = document.getElementById('sType');
    if (sType) {
      const opts = lang === 'sw'
        ? ['Ana kwa Ana', 'Dawa Mtandaoni']
        : ['In-person', 'Telehealth'];
      [...sType.options].forEach((o, i) => { if (opts[i]) o.text = opts[i]; });
    }

    /* 7 ── lang button label */
    const lb = document.getElementById('langLabel');
    if (lb) lb.textContent = lang === 'en' ? 'SW' : 'EN';

    /* 8 ── lang button aria-label */
    const btn = document.getElementById('langToggle');
    if (btn) btn.setAttribute('aria-label',
      lang === 'en' ? 'Switch to Swahili' : 'Badili hadi Kiingereza');

    /* 9 ── dispatch custom event so page-specific JS can react */
    document.dispatchEvent(new CustomEvent('langchange', { detail: { lang } }));
  }

  return {
    get:    ()  => cur,
    set:    (l) => apply(l),
    toggle: ()  => apply(cur === 'en' ? 'sw' : 'en'),
    init:   ()  => apply(cur),
  };
})();

/* ── OTP grid ────────────────────────────────────── */
const OTP = (() => {
  function init(sel) {
    const grid = document.querySelector(sel || '#otpGrid'); if (!grid) return;
    const inputs = [...grid.querySelectorAll('.otp-digit')];
    inputs.forEach((inp, i) => {
      inp.addEventListener('input', () => {
        inp.value = inp.value.replace(/\D/g,'').slice(0,1);
        inp.classList.toggle('filled', !!inp.value);
        if (inp.value && i < inputs.length - 1) inputs[i+1].focus();
        const btn = document.getElementById('verifyBtn');
        if (btn) btn.disabled = !inputs.every(x => x.value);
      });
      inp.addEventListener('keydown', e => {
        if (e.key==='Backspace' && !inp.value && i>0) { inputs[i-1].focus(); inputs[i-1].value=''; inputs[i-1].classList.remove('filled'); }
        if (e.key==='ArrowLeft'  && i>0)               inputs[i-1].focus();
        if (e.key==='ArrowRight' && i<inputs.length-1) inputs[i+1].focus();
        if (e.key==='Enter') { const btn=document.getElementById('verifyBtn'); if(btn&&!btn.disabled)btn.click(); }
      });
      inp.addEventListener('paste', e => {
        e.preventDefault();
        const d = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,inputs.length);
        d.split('').forEach((c,j)=>{ if(inputs[j]){inputs[j].value=c;inputs[j].classList.add('filled');} });
        const nx = inputs.findIndex(x=>!x.value);
        (nx!==-1?inputs[nx]:inputs[inputs.length-1]).focus();
        const btn=document.getElementById('verifyBtn'); if(btn)btn.disabled=!inputs.every(x=>x.value);
      });
    });
  }
  function value(sel) { return [...document.querySelectorAll((sel||'#otpGrid')+' .otp-digit')].map(x=>x.value).join(''); }
  return { init, value };
})();

/* ── PASSWORD STRENGTH ───────────────────────────── */
const PwdStrength = (() => {
  const lvls = [
    {lbl:'Too weak',col:'#dc2626',w:'20%'},{lbl:'Weak',col:'#ea580c',w:'40%'},
    {lbl:'Fair',col:'#d97706',w:'60%'},{lbl:'Good',col:'#059669',w:'80%'},{lbl:'Strong',col:'#047857',w:'100%'},
  ];
  function score(p) {
    if (!p) return -1; let s=0;
    if (p.length>=8)s++;if(/[A-Z]/.test(p))s++;if(/[a-z]/.test(p))s++;
    if(/[0-9]/.test(p))s++;if(/[^A-Za-z0-9]/.test(p))s++;return s-1;
  }
  function update(pwd,barId,lblId) {
    const bar=document.getElementById(barId); if(!bar)return;
    const lbl=document.getElementById(lblId);
    const sc=score(pwd);
    if(sc<0){bar.style.width='0';if(lbl)lbl.textContent='';return;}
    const lv=lvls[sc]; bar.style.width=lv.w; bar.style.background=lv.col;
    if(lbl){lbl.textContent=lv.lbl;lbl.style.color=lv.col;}
  }
  function init(inputId,barId,lblId) {
    const el=document.getElementById(inputId);
    if(el)el.addEventListener('input',()=>update(el.value,barId,lblId));
  }
  return {init,update};
})();
window.PwdStrength = PwdStrength;

/* ── ALERTS ──────────────────────────────────────── */
const UI = (() => {
  const icons={ok:'fa-circle-check',err:'fa-circle-exclamation',warn:'fa-triangle-exclamation',info:'fa-circle-info'};
  function alert(type,msg,boxId='alertBox') {
    const box=document.getElementById(boxId); if(!box)return;
    box.className='alert alert-'+type;
    box.innerHTML=`<i class="fa-solid ${icons[type]||'fa-circle-info'}"></i><span>${msg}</span>`;
    box.classList.remove('hidden');
    box.scrollIntoView({behavior:'smooth',block:'nearest'});
    if(type==='ok')setTimeout(()=>box.classList.add('hidden'),4500);
  }
  function hide(boxId='alertBox'){document.getElementById(boxId)?.classList.add('hidden');}
  function loading(btnId,on){
    const btn=document.getElementById(btnId);if(!btn)return;
    btn.disabled=on;
    if(on){btn.dataset.orig=btn.innerHTML;btn.innerHTML+=` <i class="fa-solid fa-circle-notch fa-spin" style="font-size:12px"></i>`;}
    else if(btn.dataset.orig)btn.innerHTML=btn.dataset.orig;
  }
  return {alert,hide,loading};
})();
window.UI = UI;

/* ── FETCH HELPER ────────────────────────────────── */
async function post(url, data, btnId, alertId) {
  UI.hide(alertId);
  if (btnId) UI.loading(btnId, true);
  try {
    const ctrl = new AbortController();
    const tid  = setTimeout(() => ctrl.abort(), 15000);
    const res  = await fetch(url, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body:JSON.stringify(data),
      credentials:'same-origin',
      signal:ctrl.signal,
    });
    clearTimeout(tid);
    const text = await res.text();
    try { return JSON.parse(text); }
    catch { console.error('Non-JSON:', text.slice(0,300)); return {success:false,message:'Server error.'}; }
  } catch(err) {
    const msg = err.name==='AbortError' ? 'Request timed out.' : !navigator.onLine ? 'You are offline.' : 'Server unreachable.';
    if (alertId) UI.alert('err', msg, alertId);
    return null;
  } finally {
    if (btnId) UI.loading(btnId, false);
  }
}
window.post = post;

/* ── BOOKING (dashboard pages) ───────────────────── */
async function submitBooking() {
  const csrf  = document.getElementById('csrfToken')?.value || '';
  const date  = document.getElementById('bookDate')?.value;
  const time  = document.getElementById('bookTime')?.value || '09:00';
  const title = document.getElementById('bookTitle')?.value?.trim();
  if (!date)  { UI.alert('warn','Please select a date.','bookAlertBox'); return; }
  if (!title) { UI.alert('warn','Please enter a reason for the appointment.','bookAlertBox'); return; }
  const insDocId  = document.getElementById('bookInsDoc')?.value  || null;
  const shareIns  = insDocId && document.getElementById('bookShareInsurance')?.checked;
  const r = await post('/api/patient/book-appointment.php', {
    service_type:   document.getElementById('bookServiceType')?.value || 'doctor',
    provider_id:    document.getElementById('bookProvider')?.value    || null,
    appointment_at: date + ' ' + time + ':00',
    title, notes:   document.getElementById('bookNotes')?.value?.trim() || '',
    location_type:  document.getElementById('bookLocType')?.value || 'in_person',
    insurance_doc_id: insDocId ? parseInt(insDocId) : null,
    share_insurance:  shareIns,
    csrf_token: csrf,
  }, 'bookBtn', 'bookAlertBox');
  if (!r) return;
  if (r.requires_login) { location.href = r.redirect; return; }
  if (r.success) {
    closeModal('bookModal');
    UI.alert('ok','Appointment booked! Confirmation email sent.','alertBox');
    setTimeout(() => location.href = '?tab=appointments', 1500);
  } else {
    UI.alert('err', r.message || 'Booking failed.', 'bookAlertBox');
  }
}
window.submitBooking = submitBooking;

/* ── NOTIFICATION HELPERS ────────────────────────── */
function markRead(id) {
  document.getElementById('ni-'+id)?.classList.remove('unread');
  document.getElementById('ni-'+id)?.querySelector('.notif-unread-dot')?.remove();
  fetch('/api/patient/mark-notification-read.php?id='+id,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'}}).catch(()=>{});
}
function markAllRead() {
  document.querySelectorAll('.notif-list-item').forEach(i=>{i.classList.remove('unread');i.querySelector('.notif-unread-dot')?.remove();});
  fetch('/api/patient/mark-notifications-read.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'}}).catch(()=>{});
}
window.markRead    = markRead;
window.markAllRead = markAllRead;

/* ── GEOLOCATION ─────────────────────────────────── */
function requestLocation(onSuccess) {
  if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
  navigator.geolocation.getCurrentPosition(pos => {
    const {latitude:lat, longitude:lng} = pos.coords;
    fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`)
      .then(r=>r.json()).then(d=>{
        const city = d.address?.city||d.address?.town||d.address?.county||'Your Location';
        const label = city+(d.address?.country?', '+d.address.country:'');
        document.querySelectorAll('[data-location-label]').forEach(el=>el.textContent=label);
        const sLoc=document.getElementById('sLoc'); if(sLoc)sLoc.value=label;
        if(onSuccess) onSuccess(lat,lng,label);
      }).catch(()=>{});
  }, err=>{
    const msgs={1:'Location access denied.',2:'Location unavailable.',3:'Request timed out.'};
    alert(msgs[err.code]||'Could not get location.');
  });
}
window.requestLocation = requestLocation;

/* ── SCROLL REVEAL ───────────────────────────────── */
function initReveal() {
  if (!window.IntersectionObserver) return;
  const obs = new IntersectionObserver(entries=>{
    entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');obs.unobserve(e.target);}});
  },{threshold:0.1});
  document.querySelectorAll('.reveal').forEach(el=>obs.observe(el));
}

/* ── COUNTER ANIMATIONS ──────────────────────────── */
function animCounters() {
  document.querySelectorAll('[data-count]').forEach(el => {
    const target=parseInt(el.dataset.count), sfx=el.dataset.suffix||'';
    let c=0; const step=target/55;
    const t=setInterval(()=>{
      c=Math.min(c+step,target);
      el.textContent=(target>=1000?Math.floor(c).toLocaleString():Math.floor(c))+sfx;
      if(c>=target)clearInterval(t);
    },28);
  });
}

/* ── TELEHEALTH CALL UI ──────────────────────────── */
function initCall() {
  const micBtn=document.getElementById('micBtn');
  const camBtn=document.getElementById('camBtn');
  micBtn?.addEventListener('click',()=>{
    micBtn.classList.toggle('muted');
    const ic=micBtn.querySelector('i');
    if(ic){ic.classList.toggle('fa-microphone',!micBtn.classList.contains('muted'));ic.classList.toggle('fa-microphone-slash',micBtn.classList.contains('muted'));}
  });
  camBtn?.addEventListener('click',()=>{
    camBtn.classList.toggle('cam-off');
    const ic=camBtn.querySelector('i');
    if(ic){ic.classList.toggle('fa-video',!camBtn.classList.contains('cam-off'));ic.classList.toggle('fa-video-slash',camBtn.classList.contains('cam-off'));}
  });
  const timerEl=document.getElementById('callTimer');
  if(timerEl){let s=0;setInterval(()=>{s++;timerEl.textContent=`${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}`;},1000);}
  const chatInp=document.getElementById('chatInp'),chatSend=document.getElementById('chatSend'),chatMsgs=document.getElementById('chatMsgs');
  if(chatSend&&chatInp&&chatMsgs){
    function sendMsg(){const msg=chatInp.value.trim();if(!msg)return;const div=document.createElement('div');div.className='chat-bubble me';div.innerHTML=`<div class="bubble">${msg}</div><div class="chat-time">${new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})}</div>`;chatMsgs.appendChild(div);chatMsgs.scrollTop=chatMsgs.scrollHeight;chatInp.value='';}
    chatSend.addEventListener('click',sendMsg);chatInp.addEventListener('keydown',e=>{if(e.key==='Enter')sendMsg();});
  }
}

/* ── PROVIDER AVAILABILITY ───────────────────────── */
async function saveAvailability() {
  const csrf=document.getElementById('csrfToken')?.value||'';
  const slots=[];
  document.querySelectorAll('.day-row').forEach(row=>{
    const day=row.dataset.day;if(!day)return;
    row.querySelectorAll('.time-slot').forEach(slot=>{
      const st=slot.querySelector('.time-s')?.value,en=slot.querySelector('.time-e')?.value,mode=slot.querySelector('.mode-btn.active')?.dataset.mode||'in_person';
      if(st&&en)slots.push({day,start:st,end:en,mode});
    });
  });
  const r=await post('/api/provider/set-availability.php',{slots,csrf_token:csrf},null,'availAlert');
  if(r?.success)UI.alert('ok','Availability saved.','availAlert');
  else UI.alert('err',r?.message||'Failed to save.','availAlert');
}
window.saveAvailability = saveAvailability;

/* ════════════════════════════════════════════════════
   DOMContentLoaded — wire everything up
════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {

  /* Core inits */
  Sidebar.init();
  OTP.init('#otpGrid');
  initReveal();
  initCall();

  /* ── LANGUAGE INIT (runs on EVERY page) ──────────
     Reads stored lang from localStorage and applies it
     to every [data-en]/[data-sw] element on the page. */
  Lang.init();

  /* Wire the toggle button (present in header on all pages) */
  document.getElementById('langToggle')?.addEventListener('click', () => {
    Lang.toggle();
  });

  /* ── MODAL overlay clicks ── */
  document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target===m) closeModal(m.id); });
  });

  /* ── Mobile sidebar ── */
  document.getElementById('mobToggle')?.addEventListener('click', ()=>Sidebar.openMob());
  document.getElementById('mobSidebarToggle')?.addEventListener('click', ()=>Sidebar.openMob());
  document.getElementById('mobOv')?.addEventListener('click', ()=>Sidebar.closeMob());

  /* ── Counter animation on scroll ── */
  const firstCounter = document.querySelector('[data-count]');
  if (firstCounter) {
    const section = firstCounter.closest('section') || firstCounter;
    const obs = new IntersectionObserver(entries=>{
      if(entries[0].isIntersecting){animCounters();obs.disconnect();}
    },{threshold:0.3});
    obs.observe(section);
  }

  /* ── Search bar Enter key (homepage) ── */
  ['sLoc','sQuery'].forEach(id=>{
    document.getElementById(id)?.addEventListener('keydown',e=>{
      if(e.key==='Enter'&&window.doSearch) window.doSearch();
    });
  });

  /* ── Dashboard topbar search ── */
  const dashSearch = document.getElementById('dashSearch');
  if (dashSearch) {
    dashSearch.addEventListener('keydown', e=>{
      if(e.key==='Enter') location.href='/patients/search.php?q='+encodeURIComponent(dashSearch.value);
    });
  }

  /* ── Mobile hamburger nav ── */
  document.getElementById('navHamburger')?.addEventListener('click', ()=>{
    document.getElementById('pubNav')?.classList.toggle('mob-nav-open');
  });

});
