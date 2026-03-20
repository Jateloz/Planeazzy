<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
$noSidebar = true;
$pageTitle = 'Verify Provider Account';
include dirname(__DIR__). '/includes/header.php';
$csrf = Security::csrfToken();
?>

<main style="min-height:calc(100vh - 64px);background:var(--bg);display:flex;align-items:center;justify-content:center;padding:40px 20px">
  <div style="width:100%;max-width:460px">
    <div class="prog-wrap">
      <div class="prog-row">
        <span class="prog-badge"><span class="material-symbols-outlined">mark_email_read</span> Verify Account</span>
        <span class="prog-pct">Step 2 of 2</span>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:80%"></div></div>
    </div>
    <div class="auth-card fade-up">
      <div class="card-icon"><span class="material-symbols-outlined">domain_verification</span></div>
      <div class="card-title2">Verify your provider account</div>
      <div class="card-sub2">We sent a 6-digit verification code to <strong id="emailShow">your email</strong>. It expires in <?= OTP_EXPIRY_MINUTES ?> minutes.</div>

      
      <?php
      if (APP_ENV === 'development') {
          $logFile = ROOT_DIR . '/logs/mail_dev.log';
          $devOtp  = '';
          if (file_exists($logFile)) {
              $lines = array_reverse(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
              foreach ($lines as $line) {
                  if (preg_match('/OTP for[^:]+:\s*(\d{4,8})/', $line, $m)) {
                      $devOtp = $m[1]; break;
                  }
              }
          }
          if ($devOtp): ?>
      <div style="background:#fefce8;border:1.5px dashed #d97706;border-radius:10px;padding:14px 16px;margin-bottom:16px;text-align:center">
        <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">⚡ Dev Mode — Latest OTP Code</div>
        <div style="font-family:monospace;font-size:32px;font-weight:900;letter-spacing:10px;color:#0e7490"><?= htmlspecialchars($devOtp) ?></div>
        <div style="font-size:11px;color:#92400e;margin-top:5px">Copy this code into the boxes above &bull; <a href="/dev-otp.php" style="color:#0e7490">View all codes</a></div>
      </div>
      <?php endif; ?>
      <?php } ?>

      <div id="alertBox" class="alert hidden"></div>

      <div class="otp-row" id="otpGrid">
        <?php for($i=0;$i<OTP_LENGTH;$i++): ?>
        <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="off" <?=$i===0?'autofocus':''?>>
        <?php endfor; ?>
      </div>

      <button id="verifyBtn" class="btn btn-primary btn-full btn-lg" disabled onclick="doVerify()">
        <span class="material-symbols-outlined">verified</span> Verify Account
      </button>

      <div class="resend-row">
        <button class="resend-btn" id="resendBtn">
          <span class="material-symbols-outlined">refresh</span>
          Didn't get the code? <span class="link">Resend</span>
        </button>
      </div>

      <div class="sec-note">
        <div class="sec-inner">
          <span class="material-symbols-outlined">verified_user</span>
          After email verification, our team will review your credentials within 24 hours before activating your account.
        </div>
      </div>
    </div>
    <p class="tc mt2" style="font-size:13px;color:var(--muted)">
      <a href="/providers/register.php" style="color:var(--muted)">&larr; Back to Registration</a>
    </p>
  </div>
</main>

<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
(function(){
  const pid   = sessionStorage.getItem('pz_prov_id');
  const email = sessionStorage.getItem('pz_prov_email');
  if (!pid) { location.href = '/providers/register.php'; return; }
  const el = document.getElementById('emailShow');
  if (el && email) el.textContent = email;

  document.getElementById('verifyBtn').addEventListener('click', doVerify);

  async function doVerify() {
    const otp = OTP.value('#otpGrid');
    if (otp.length < <?= OTP_LENGTH ?>) { UI.alert('err','Enter all <?= OTP_LENGTH ?> digits.','alertBox'); return; }
    const r = await post('/api/provider/verify-otp.php', {
      provider_id: parseInt(pid), otp, csrf_token: '<?= htmlspecialchars($csrf) ?>'
    }, 'verifyBtn', 'alertBox');
    if (!r) return;
    if (r.success) {
      UI.alert('ok', r.message || 'Account verified! Redirecting…', 'alertBox');
      sessionStorage.removeItem('pz_prov_id');
      sessionStorage.removeItem('pz_prov_email');
      setTimeout(() => location.href = '/providers/login.php?verified=1', 1300);
    } else {
      UI.alert('err', r.message || 'Invalid code.', 'alertBox');
    }
  }
  window.doVerify = doVerify;

  // Resend
  let cd = 0;
  document.getElementById('resendBtn').addEventListener('click', async function() {
    if (cd > 0) return;
    const r = await post('/api/provider/resend-otp.php', {
      provider_id: parseInt(pid), csrf_token: '<?= htmlspecialchars($csrf) ?>'
    }, null, 'alertBox');
    if (r?.success) {
      UI.alert('info', r.message || 'New code sent!', 'alertBox');
      cd = 60;
      const t = setInterval(() => {
        cd--;
        const lnk = document.querySelector('#resendBtn .link');
        if (lnk) lnk.textContent = cd > 0 ? `Resend in ${cd}s` : 'Resend';
        if (cd <= 0) clearInterval(t);
      }, 1000);
    } else { UI.alert('err', r?.message || 'Failed to resend.', 'alertBox'); }
  });
})();
</script>
