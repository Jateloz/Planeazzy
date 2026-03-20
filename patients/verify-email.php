<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true;
$pageTitle = 'Verify Email';
include dirname(__DIR__). '/includes/header.php';
$csrf = Security::csrfToken();
?>
<main class="page-main">
  <div class="auth-wrap">
    <div class="step-wrap">
      <div class="step-row">
        <span class="step-badge"><span class="material-symbols-outlined">mark_email_read</span> Step 3 of 5</span>
        <span class="pct">40% Complete</span>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:40%"></div></div>
    </div>
    <div class="auth-card">
      <div class="card-icon"><span class="material-symbols-outlined">mark_email_read</span></div>
      <h1 class="card-title">Verify your email</h1>
      <p class="card-sub">We sent a 6-digit code to <strong id="emailShow">your email</strong>. It expires in <?= OTP_EXPIRY_MINUTES ?> minutes.</p>
      
      <?php
      // ── DEV MODE: show OTP inline so you don't need email ────
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
        <div style="font-family:monospace;font-size:32px;font-weight:900;letter-spacing:10px;color:#1978e5"><?= htmlspecialchars($devOtp) ?></div>
        <div style="font-size:11px;color:#92400e;margin-top:5px">Copy this code into the boxes above &bull; <a href="/dev-otp.php" style="color:#1978e5">View all codes</a></div>
      </div>
      <?php endif; ?>
      <?php } ?>

      <div id="alertBox" class="alert hidden"><span class="material-symbols-outlined">info</span><span id="alertMsg"></span></div>
      <div class="otp-row" id="otpGrid">
        <?php for($i=0;$i<OTP_LENGTH;$i++): ?>
        <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="off" <?=$i===0?'autofocus':''?>>
        <?php endfor; ?>
      </div>
      <button id="verifyBtn" class="btn btn-primary btn-full btn-lg" disabled>
        <span class="material-symbols-outlined">verified</span> Verify Email
      </button>
      <div class="resend-row">
        <button class="resend-btn" id="resendBtn">
          <span class="material-symbols-outlined">refresh</span>
          Didn't receive it? <span class="link">Resend Code</span>
        </button>
      </div>
      <div class="sec-notice">
        <div class="sec-inner">
          <span class="material-symbols-outlined">verified_user</span>
          Verification keeps your medical records private and secure.
        </div>
      </div>
    </div>
    <p class="tc mt2" style="font-size:13px;color:var(--muted)"><a href="/patients/register.php" style="color:var(--muted)">&larr; Back to Registration</a></p>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
(function(){
  const pid=sessionStorage.getItem('pz_pid'), email=sessionStorage.getItem('pz_email');
  if(!pid){location.href='/patients/register.php';return;}
  const es=document.getElementById('emailShow'); if(es&&email) es.textContent=email;
  document.getElementById('verifyBtn').addEventListener('click',async function(){
    const otp=OTP.value('#otpGrid');
    if(otp.length<<?=OTP_LENGTH?>){
      document.getElementById('alertBox').className='alert alert-err';
      document.getElementById('alertMsg').textContent='Enter all <?=OTP_LENGTH?> digits.';
      document.getElementById('alertBox').classList.remove('hidden'); return;
    }
    const r=await post('/api/auth/verify-otp.php',{patient_id:parseInt(pid),otp,csrf_token:'<?=htmlspecialchars($csrf)?>'},'verifyBtn','alertBox');
    if(!r)return;
    if(r.success){
      document.getElementById('alertBox').className='alert alert-ok';
      document.getElementById('alertMsg').textContent=r.message||'Email verified!';
      document.getElementById('alertBox').classList.remove('hidden');
      sessionStorage.setItem('pz_pid_pref',pid);
      sessionStorage.removeItem('pz_pid'); sessionStorage.removeItem('pz_email');
      setTimeout(()=>location.href='/patients/preferences.php',1100);
    } else {
      document.getElementById('alertBox').className='alert alert-err';
      document.getElementById('alertMsg').textContent=r.message||'Invalid code.';
      document.getElementById('alertBox').classList.remove('hidden');
    }
  });
  let cd=0;
  document.getElementById('resendBtn').addEventListener('click',async function(){
    if(cd>0)return;
    const r=await post('/api/auth/resend-otp.php',{patient_id:parseInt(pid),csrf_token:'<?=htmlspecialchars($csrf)?>'},null,'alertBox');
    if(r?.success){
      document.getElementById('alertBox').className='alert alert-info';
      document.getElementById('alertMsg').textContent=r.message||'New code sent!';
      document.getElementById('alertBox').classList.remove('hidden');
      cd=60; const t=setInterval(()=>{cd--;const l=document.querySelector('#resendBtn .link');if(l)l.textContent=cd>0?`Resend in ${cd}s`:'Resend Code';if(cd<=0)clearInterval(t);},1000);
    }
  });
})();
</script>
