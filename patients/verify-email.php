<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true; $pageTitle = 'Verify Your Email';
include dirname(__DIR__). '/includes/header.php';
$csrf = Security::csrfToken();
// Dev mode: show OTP from log
$devOtp = '';
if (APP_ENV === 'development') {
    foreach ([ROOT_DIR.'/logs/mail_dev.log', sys_get_temp_dir().'/planeazzy_logs/mail_dev.log'] as $logFile) {
        if (file_exists($logFile)) {
            $lines = array_reverse(file($logFile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES));
            foreach ($lines as $line) { if (preg_match('/OTP for[^:]+:\s*(\d{4,8})/', $line, $m)) { $devOtp = $m[1]; break 2; } }
        }
    }
}
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--bg-light)">
  <div style="width:100%;max-width:480px">
    <div class="auth-card slide-up">
      <div class="step-wrap">
        <div class="step-row"><span class="step-badge"><i class="fa-solid fa-envelope-circle-check"></i> Verify Email</span><span class="step-pct">Step 2 of 3</span></div>
        <div class="prog-track"><div class="prog-fill" style="width:66%"></div></div>
      </div>
      <div style="text-align:center;margin-bottom:22px">
        <div style="width:56px;height:56px;border-radius:14px;background:var(--primary-10);color:var(--primary);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:24px;border:1px solid var(--primary-20)"><i class="fa-solid fa-envelope-circle-check"></i></div>
        <h2 style="font-size:22px;font-weight:900;color:var(--slate-900);margin-bottom:6px;letter-spacing:-.03em">Check your email</h2>
        <p style="font-size:14px;color:var(--slate-500);line-height:1.6">We sent a 6-digit verification code to <strong id="emailShow" style="color:var(--slate-900)">your email</strong>. It expires in <?= OTP_EXPIRY_MINUTES ?> minutes.</p>
      </div>
      <?php if ($devOtp): ?>
      <div style="background:#fefce8;border:1.5px dashed #d97706;border-radius:10px;padding:14px;text-align:center;margin-bottom:16px">
        <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">⚡ Dev Mode — OTP Code</div>
        <div style="font-family:monospace;font-size:34px;font-weight:900;letter-spacing:12px;color:var(--primary)"><?= htmlspecialchars($devOtp) ?></div>
        <div style="font-size:11px;color:#92400e;margin-top:5px">Copy into boxes below · <a href="/dev-otp.php" style="color:var(--primary)">View all codes</a></div>
      </div>
      <?php endif; ?>
      <div id="alertBox" class="alert hidden"><i class="fa-solid fa-circle-exclamation"></i><span id="alertMsg"></span></div>
      <div class="otp-row" id="otpGrid">
        <?php for($i=0;$i<6;$i++): ?><input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="off" <?=$i===0?'autofocus':''>><?php endfor; ?>
      </div>
      <button id="verifyBtn" class="btn btn-primary btn-full btn-lg" disabled onclick="doVerify()"><i class="fa-solid fa-check-circle"></i> Verify Email</button>
      <div class="resend-row" style="margin-top:16px">
        <span class="resend-btn">Didn't receive a code? <button class="link" onclick="doResend()" style="background:none;border:none;cursor:pointer;color:var(--primary);font-weight:700;text-decoration:underline;font-family:'Inter',sans-serif">Resend code</button></span>
      </div>
      <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--slate-100)">
        <div style="display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:8px;background:var(--primary-10);border:1px solid var(--primary-20);font-size:12px;color:var(--slate-600);line-height:1.6">
          <i class="fa-solid fa-shield-halved" style="color:var(--primary)"></i>
          Your code is valid for <?= OTP_EXPIRY_MINUTES ?> minutes. Never share it with anyone.
        </div>
      </div>
    </div>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
OTP.init('#otpGrid');
// Set email from session storage
const storedEmail=sessionStorage.getItem('pz_pat_email');
if(storedEmail){const el=document.getElementById('emailShow');if(el)el.textContent=storedEmail;}
async function doVerify(){
  const code=OTP.value('#otpGrid');
  const id=sessionStorage.getItem('pz_pat_id')||0;
  const r=await post('/api/auth/verify-otp.php',{csrf_token:document.getElementById('csrfToken').value,patient_id:parseInt(id),otp:code},'verifyBtn','alertBox');
  if(!r)return;
  if(r.success){UI.alert('ok','Email verified! Setting up your account…','alertBox');setTimeout(()=>location.href='/patients/preferences.php',1000);}
  else UI.alert('err',r.message||'Invalid code. Please try again.','alertBox');
}
async function doResend(){
  const id=sessionStorage.getItem('pz_pat_id')||0;
  const r=await post('/api/auth/resend-otp.php',{csrf_token:document.getElementById('csrfToken').value,patient_id:parseInt(id)},null,'alertBox');
  if(r?.success)UI.alert('ok','A new code has been sent to your email.','alertBox');
  else UI.alert('err',r?.message||'Could not resend. Try again later.','alertBox');
}
</script>
