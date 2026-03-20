<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id'])) { header('Location: /providers/dashboard.php'); exit; }
$noSidebar = true;
$pageTitle = 'Provider Sign In';
$timeout   = ($_GET['reason']??'')==='timeout';
include dirname(__DIR__). '/includes/header.php';
$csrf = Security::csrfToken();
?>

<main style="min-height:calc(100vh - 64px);background:var(--bg);display:flex;align-items:center;justify-content:center;padding:40px 20px">
  <div class="split-layout">

    <div class="split-left">
      <div class="split-left-bg" style="background-image:url('https://images.unsplash.com/photo-1631217868264-e5b90bb7e133?w=600&q=70')"></div>
      <div class="split-left-hex"></div>
      <div class="split-left-content">
        <h2>Your Direct Path to Better Patient Care</h2>
        <p>Access your provider dashboard, manage appointments, connect with patients, and dispatch emergency services.</p>
        <div class="split-left-features">
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> Real-time appointment management</div>
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> Patient health record access</div>
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> Telehealth video consultations</div>
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> H3 location-based patient matching</div>
        </div>
      </div>
    </div>

    <div class="split-right">
      <div style="max-width:380px;width:100%;margin:0 auto">
        <h2 style="font-family:var(--ff-head);font-size:24px;font-weight:900;color:var(--navy);margin-bottom:6px;letter-spacing:-0.04em">Provider Sign In</h2>
        <p style="font-size:13px;color:var(--muted);margin-bottom:22px">Sign in to your provider dashboard.</p>

        <?php if($timeout): ?>
        <div class="alert alert-info keep"><span class="material-symbols-outlined">info</span><span>Session expired. Please sign in again.</span></div>
        <?php endif; ?>

        <div id="alertBox" class="alert hidden"></div>

        <div>
          <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <div class="form-group">
            <label class="form-label">Organisation Email</label>
            <div class="inp-wrap">
              <span class="inp-ico"><span class="material-symbols-outlined">domain</span></span>
              <input type="email" id="pemail" class="form-input has-ico" placeholder="admin@hospital.co.ke" autofocus>
            </div>
          </div>
          <div class="form-group" style="margin-bottom:20px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
              <label class="form-label" style="margin-bottom:0">Password</label>
              <a href="#" style="font-size:12px;color:var(--blue)">Reset Password</a>
            </div>
            <div class="inp-wrap">
              <span class="inp-ico"><span class="material-symbols-outlined">lock</span></span>
              <input type="password" id="ppwd" class="form-input has-ico" placeholder="••••••••">
              <button type="button" class="eye-btn" id="eye1" onclick="togglePwd('ppwd','eye1')"><span class="material-symbols-outlined">visibility</span></button>
            </div>
          </div>
          <button id="submitBtn" class="btn btn-primary btn-full btn-lg" onclick="doProviderLogin()">
            <span class="material-symbols-outlined">login</span> Sign In to Portal
          </button>
        </div>

        <div class="divider"><span>Not a partner yet?</span></div>
        <a href="/providers/register.php" class="btn btn-outline btn-full">Register as Provider</a>

        <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--muted)">
          <a href="/patients/login.php" style="color:var(--muted)">&larr; Patient Login</a>
        </p>
      </div>
    </div>
  </div>
</main>

<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
async function doProviderLogin() {
  const r = await post('/api/provider/login.php', {
    csrf_token: document.getElementById('csrf').value,
    email: document.getElementById('pemail').value.trim(),
    password: document.getElementById('ppwd').value,
  }, 'submitBtn', 'alertBox');
  if (!r) return;
  if (r.success) {
    UI.alert('ok','Login successful! Redirecting…','alertBox');
    setTimeout(() => location.href = '/providers/dashboard.php', 900);
  } else if (r.needs_verification) {
    sessionStorage.setItem('pz_prov_id', r.provider_id);
    UI.alert('info', r.message, 'alertBox');
    setTimeout(() => location.href = '/providers/verify.php', 1400);
  } else {
    UI.alert('err', r.message || 'Login failed.', 'alertBox');
  }
}
</script>
