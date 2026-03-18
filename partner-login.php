<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/services/Security.php';
Security::startSession();
$pageTitle = 'Provider Portal';
$noSidebar = true;
include __DIR__ . '/includes/header.php';
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;background:var(--bg)">
  <div class="split-layout" style="animation:slideUp .4s cubic-bezier(.34,1.4,.64,1)">
    <div class="split-left" style="background:linear-gradient(135deg,#0f172a 0%,#1e40af 60%,#0e7490 100%)">
      <div class="split-left-bg"></div>
      <div style="position:absolute;top:40px;left:52px;z-index:1">
        <div style="display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);padding:5px 14px;border-radius:99px;font-size:11px;font-weight:700;font-family:var(--ff-display);text-transform:uppercase;letter-spacing:.5px;color:#fff">
          <span class="material-symbols-outlined" style="font-size:13px">business</span>
          Healthcare Provider Portal
        </div>
      </div>
      <div class="split-left-content">
        <h2>Manage Patients, Appointments &amp; Billing — All in One Platform</h2>
        <p>Join thousands of doctors, clinics, and hospitals already using Planeazzy to deliver better care more efficiently.</p>
        <div style="display:flex;flex-direction:column;gap:12px;margin-top:24px">
          <?php
          $feats = ['Real-time appointment management','Patient health record access','Digital billing and invoicing','Telemedicine video consultations'];
          foreach ($feats as $f): ?>
          <div style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,.8)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#34d399">check_circle</span>
            <?= htmlspecialchars($f) ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="split-right">
      <div style="max-width:420px;width:100%;margin:0 auto">
        <div style="margin-bottom:32px">
          <h2 style="font-family:var(--ff-display);font-size:28px;font-weight:900;color:var(--navy);letter-spacing:-0.04em;margin-bottom:8px">Provider Sign In</h2>
          <p style="font-size:14px;color:var(--muted)">Sign in to your provider dashboard.</p>
        </div>
        <div id="alertBox" class="alert hidden" role="alert"></div>
        <form id="partnerForm" novalidate>
          <div class="form-group">
            <label class="form-label">Organisation Email</label>
            <div class="input-wrap">
              <span class="input-ico"><span class="material-symbols-outlined">domain</span></span>
              <input type="email" id="email" class="form-input" placeholder="admin@hospital.co.ke" required autofocus>
            </div>
          </div>
          <div class="form-group" style="margin-bottom:24px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <label class="form-label" style="margin-bottom:0">Password</label>
              <a href="#" style="font-size:12px;font-weight:600;color:var(--primary)">Reset Password</a>
            </div>
            <div class="input-wrap">
              <span class="input-ico"><span class="material-symbols-outlined">lock</span></span>
              <input type="password" id="password" class="form-input" placeholder="••••••••" required>
              <button type="button" class="eye-btn" id="eye1" onclick="togglePwd('password','eye1')"><span class="material-symbols-outlined">visibility</span></button>
            </div>
          </div>
          <button type="submit" id="submitBtn" class="btn btn-primary btn-full">
            Sign In to Provider Portal
            <svg class="btn-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </form>
        <div class="divider" style="margin:24px 0"><span>Not a partner yet?</span></div>
        <a href="#" class="btn btn-outline btn-full">Apply for Provider Access</a>
        <div class="sec-notice" style="margin-top:20px">
          <div class="sec-inner">
            <span class="material-symbols-outlined">verified_user</span>
            <span>Provider access is strictly verified and audited. All activity is logged.</span>
          </div>
        </div>
        <p style="text-align:center;margin-top:20px;font-size:13px;color:var(--muted)">
          <a href="/patients/login.php" style="color:var(--muted)">&larr; Patient Login</a>
        </p>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.getElementById('partnerForm').addEventListener('submit', function(e) {
  e.preventDefault();
  UI.alert('info','Provider authentication coming soon. Contact your administrator.','alertBox');
});
</script>
