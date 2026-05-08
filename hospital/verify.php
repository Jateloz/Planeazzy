<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id']) && !empty($_SESSION['is_provider'])) {
    header('Location: /hospital/dashboard.php'); exit;
}
$csrf = Security::csrfToken();
// Dev mode OTP display
$devOtp = '';
if (APP_ENV === 'development') {
    foreach ([ROOT_DIR.'/logs/mail_dev.log', sys_get_temp_dir().'/planeazzy_logs/mail_dev.log'] as $lf) {
        if (file_exists($lf)) {
            $lines = array_reverse(file($lf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            foreach ($lines as $line) {
                if (preg_match('/OTP for[^:]+:\s*(\d{4,8})/', $line, $m)) { $devOtp = $m[1]; break 2; }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Account — Planeazzy Partner</title>
  <link rel="icon" type="image/png" href="/assets/images/favicon.png">
  <link rel="apple-touch-icon" href="/assets/images/favicon.png"><rect width='100' height='100' rx='20' fill='%231978e5'/><text y='72' font-size='65' text-anchor='middle' x='50' fill='white'>+</text></svg>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/hospital.css">
</head>
<body style="display:flex;flex-direction:column;min-height:100vh;background:var(--bg)">

<header style="background:var(--white);border-bottom:1px solid var(--s200);position:sticky;top:0;z-index:50">
  <div style="max-width:1280px;margin:0 auto;padding:0 16px;height:64px;display:flex;align-items:center;justify-content:space-between">
    <a href="/" style="display:flex;align-items:center;gap:10px;text-decoration:none">
      <svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" style="width:32px;height:32px;color:var(--hp)"><path d="M4 42.4379C4 42.4379 14.0962 36.0744 24 41.1692C35.0664 46.8624 44 42.2078 44 42.2078L44 7.01134C44 7.01134 35.068 11.6577 24.0031 5.96913C14.0971 0.876274 4 7.27094 4 7.27094L4 42.4379Z" fill="currentColor"/></svg>
      <span style="font-size:20px;font-weight:800;color:var(--s900);letter-spacing:-.03em">Planeazzy <span style="color:var(--hp)">Partner</span></span>
    </a>
  </div>
</header>

<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px">
  <div style="width:100%;max-width:460px">
    <div class="hc" style="padding:0">
      <div style="padding:28px 32px 20px;text-align:center;border-bottom:1px solid var(--s100)">
        <div style="width:56px;height:56px;border-radius:14px;background:var(--hp-10);color:var(--hp);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:24px;border:1px solid var(--hp-20)">
          <i class="fa-solid fa-envelope-circle-check"></i>
        </div>
        <h2 style="font-size:22px;font-weight:900;color:var(--s900);margin-bottom:6px;letter-spacing:-.03em" data-en="Verify Your Email" data-sw="Thibitisha Barua Pepe Yako">Verify Your Email</h2>
        <p style="font-size:14px;color:var(--s500);line-height:1.6">
          We sent a 6-digit code to <strong id="hEmailShow" style="color:var(--s900)">your email</strong>.<br>
          Code expires in <?= OTP_EXPIRY_MINUTES ?> minutes.
        </p>
      </div>

      <div style="padding:24px 32px 28px">
        <?php if ($devOtp): ?>
        <!--<div style="background:#fefce8;border:1.5px dashed #d97706;border-radius:10px;padding:14px;text-align:center;margin-bottom:16px">
          <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;margin-bottom:6px"> Dev Mode — OTP Code</div>
          <div style="font-family:monospace;font-size:32px;font-weight:900;letter-spacing:10px;color:var(--hp)"><?= htmlspecialchars($devOtp) ?></div>
          <div style="font-size:11px;color:#92400e;margin-top:5px">
            Copy into boxes below · <a href="/dev-otp.php" style="color:var(--hp)">View all codes →</a>
          </div>
        </div>-->
        <?php endif; ?>

        <div id="hAlertBox" class="h-alert hidden"><i class="fa-solid fa-circle-exclamation"></i><span></span></div>

        <!-- OTP digits -->
        <div class="h-otp-row" id="hOtpRow">
          <?php for ($i = 0; $i < 6; $i++): ?>
          <input class="h-otp-d" type="text" inputmode="numeric" maxlength="1" autocomplete="off" <?= $i === 0 ? 'autofocus' : '' ?>>
          <?php endfor; ?>
        </div>

        <button id="hVerifyBtn" class="hbtn hbtn-primary hbtn-full hbtn-lg" disabled onclick="doVerify()" style="margin-bottom:14px">
          <i class="fa-solid fa-circle-check"></i> Verify Account
        </button>

        <div style="text-align:center">
          <span style="font-size:13px;color:var(--s500)">
            <span data-en="Didn't receive the code?" data-sw="Hukupokea nambari?">Didn't receive the code?</span>
            <button onclick="doResend()" style="background:none;border:none;cursor:pointer;color:var(--hp);font-weight:700;text-decoration:underline;font-family:var(--font);font-size:13px" data-en="Resend code" data-sw="Tuma tena nambari">Resend code</button>
          </span>
        </div>

        <div style="margin-top:16px;padding:12px;border-radius:var(--r-lg);background:var(--hp-10);border:1px solid var(--hp-20);display:flex;align-items:flex-start;gap:9px;font-size:12px;color:var(--s600);line-height:1.6">
          <i class="fa-solid fa-shield-halved" style="color:var(--hp);flex-shrink:0;margin-top:1px"></i>
          Your verification code is valid for <?= OTP_EXPIRY_MINUTES ?> minutes and should never be shared.
        </div>
      </div>
    </div>
  </div>
</main>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/hospital.js"></script>
<script>
HOTP.init('#hOtpRow');
const pEmail = sessionStorage.getItem('pz_prov_email');
if (pEmail) { const el = document.getElementById('hEmailShow'); if (el) el.textContent = pEmail; }

async function doVerify() {
  const code = HOTP.value('#hOtpRow');
  const id   = parseInt(sessionStorage.getItem('pz_prov_id') || '0');
  const r    = await hPost('/api/hospital/verify-otp.php', {
    csrf_token:  document.querySelector('#hOtpRow')?.dataset.csrf || document.cookie.split(';').find(c=>c.includes('csrf'))?.split('=')[1] || '',
    provider_id: id, otp: code
  }, 'hVerifyBtn', 'hAlertBox');
  if (!r) return;
  if (r.success) {
    HUI.alert('ok', 'Account verified! Redirecting to dashboard…', 'hAlertBox');
    setTimeout(() => location.href = '/hospital/dashboard.php', 1000);
  } else {
    HUI.alert('err', r.message || 'Invalid or expired code. Please try again.', 'hAlertBox');
  }
}

async function doResend() {
  const id = parseInt(sessionStorage.getItem('pz_prov_id') || '0');
  const r  = await hPost('/api/hospital/resend-otp.php', { provider_id: id }, null, 'hAlertBox');
  if (r?.success) HUI.alert('ok', 'A new verification code has been sent.', 'hAlertBox');
  else HUI.alert('err', r?.message || 'Could not resend. Try again later.', 'hAlertBox');
}
</script>
</body>
</html>
