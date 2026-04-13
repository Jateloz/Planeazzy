<?php
/**
 * Planeazzy — Hospital / Clinic Partner Login
 * /hospital/login.php
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id']) && !empty($_SESSION['is_provider'])) {
    header('Location: /hospital/dashboard.php'); exit;
}
$csrf    = Security::csrfToken();
$timeout = ($_GET['reason'] ?? '') === 'timeout';
?>
<!DOCTYPE html>
<html lang="en" id="htmlRoot">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Planeazzy Partner — Hospital & Clinic Provider Login">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%231978e5'/><text y='72' font-size='65' text-anchor='middle' x='50' fill='white'>+</text></svg>">
  <title>Partner Login — Planeazzy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/hospital.css">
</head>
<body style="display:flex;flex-direction:column;min-height:100vh">

<!-- HEADER — matches uploaded partner-login design -->
<header style="background:var(--white);border-bottom:1px solid var(--s200);position:sticky;top:0;z-index:50">
  <div style="max-width:1280px;margin:0 auto;padding:0 16px;height:64px;display:flex;align-items:center;justify-content:space-between">
    <a href="/" style="display:flex;align-items:center;gap:10px;text-decoration:none">
      <!-- SVG logo matching uploaded code -->
      <svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" style="width:32px;height:32px;color:var(--hp)">
        <path d="M4 42.4379C4 42.4379 14.0962 36.0744 24 41.1692C35.0664 46.8624 44 42.2078 44 42.2078L44 7.01134C44 7.01134 35.068 11.6577 24.0031 5.96913C14.0971 0.876274 4 7.27094 4 7.27094L4 42.4379Z" fill="currentColor"/>
      </svg>
      <span style="font-size:20px;font-weight:800;color:var(--s900);letter-spacing:-.03em">Planeazzy <span style="color:var(--hp)">Partner</span></span>
    </a>
    <div style="display:flex;align-items:center;gap:20px">
      <a href="/" style="font-size:14px;font-weight:500;color:var(--s500);text-decoration:none">Resources</a>
      <a href="/" style="font-size:14px;font-weight:500;color:var(--s500);text-decoration:none">Help Center</a>
      <button style="background:var(--hp-10);color:var(--hp);border:none;border-radius:10px;padding:8px 18px;font-size:14px;font-weight:700;font-family:var(--font);cursor:pointer">Support</button>
      <button id="langToggle" onclick="if(typeof Lang!=='undefined')Lang.toggle()" style="display:flex;align-items:center;gap:5px;background:var(--s100);border:1px solid var(--s200);border-radius:20px;padding:5px 12px;cursor:pointer;font-size:12px;font-weight:800;color:var(--hp);font-family:var(--font)">
        <i class="fa-solid fa-language" style="font-size:14px"></i> SW
      </button>
    </div>
  </div>
</header>

<main style="flex:1;display:flex">
  <!-- Left Panel — matches uploaded partner-login design -->
  <div class="h-split-left" style="display:none;position:relative;overflow:hidden;background:var(--s900);flex:0 0 50%">
    <div class="h-split-left-bg" style="background-image:url('https://images.unsplash.com/photo-1584820927498-cfe5211fd8bf?w=900&q=75');opacity:.55"></div>
    <div class="h-split-left-grad"></div>
    <div class="h-split-left-content" style="position:relative;z-index:2;display:flex;flex-direction:column;justify-content:flex-end;padding:48px;height:100%">
      <div style="max-width:420px">
        <span class="h-split-badge">Healthcare Portal</span>
        <h2 style="font-size:clamp(24px,3vw,36px);font-weight:900;color:#fff;line-height:1.2;margin-bottom:16px;letter-spacing:-.03em">
          Empowering Healthcare Providers Across Kenya
        </h2>
        <p style="font-size:16px;color:#cbd5e1;line-height:1.75;margin-bottom:28px">
          Streamline your clinical operations, manage patient bookings, and grow your practice with Planeazzy's integrated health ecosystem.
        </p>
        <div style="display:flex;align-items:center;gap:16px">
          <div style="display:flex">
            <?php foreach (['https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=80&q=70','https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=80&q=70','https://images.unsplash.com/photo-1594824476967-48c8b964273f?w=80&q=70'] as $i => $src): ?>
            <img src="<?= $src ?>" alt="Doctor" style="width:40px;height:40px;border-radius:50%;border:2px solid var(--s900);object-fit:cover;margin-left:<?= $i>0?'-10px':'0' ?>">
            <?php endforeach; ?>
          </div>
          <span style="color:#cbd5e1;font-size:14px;font-weight:500">Trusted by 500+ practices</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Panel: Login Form -->
  <div style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 24px;background:var(--white)">
    <div style="width:100%;max-width:440px">
      <div class="h-form-header">
        <h2 data-en="Partner Login" data-sw="Ingia kama Mshirika">Partner Login</h2>
        <p data-en="Access your professional medical dashboard." data-sw="Fikia dashibodi yako ya kitaalamu ya matibabu.">Access your professional medical dashboard.</p>
      </div>

      <?php if ($timeout): ?>
      <div class="h-alert warn" style="margin-bottom:16px"><i class="fa-solid fa-clock"></i><span>Session expired. Please sign in again.</span></div>
      <?php endif; ?>
      <div id="hAlertBox" class="h-alert hidden"><i class="fa-solid fa-circle-exclamation"></i><span id="hAlertMsg"></span></div>

      <input type="hidden" id="hCsrf" value="<?= htmlspecialchars($csrf) ?>">

      <!-- Email -->
      <div class="hf-group">
        <label class="hf-label">Professional Email Address</label>
        <div class="h-input-wrap">
          <i class="fa-solid fa-envelope h-input-ico"></i>
          <input type="email" id="hLoginEmail" class="h-input has-ico" placeholder="admin@hospital.co.ke" autocomplete="email" required autofocus>
        </div>
      </div>

      <!-- Password -->
      <div class="hf-group" style="margin-bottom:24px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <label class="hf-label" style="margin-bottom:0">Password</label>
          <a href="#" style="font-size:12px;font-weight:700;color:var(--hp)">Forgot password?</a>
        </div>
        <div class="h-input-wrap">
          <i class="fa-solid fa-lock h-input-ico"></i>
          <input type="password" id="hLoginPwd" class="h-input has-ico" placeholder="Enter your password" autocomplete="current-password" required>
          <button class="h-eye" id="hPwEye" type="button" onclick="hTogglePwd('hLoginPwd','hPwEye')"><i class="fa-solid fa-eye"></i></button>
        </div>
      </div>

      <!-- Sign In Button -->
      <button id="hLoginBtn" class="hbtn hbtn-primary hbtn-full hbtn-lg" style="margin-bottom:20px;font-size:16px" onclick="doHospitalLogin()">
        <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
      </button>

      <!-- Divider -->
      <div style="display:flex;align-items:center;gap:12px;margin:0 0 18px">
        <div style="flex:1;height:1px;background:var(--s200)"></div>
        <span style="font-size:11px;font-weight:700;color:var(--s400);text-transform:uppercase;letter-spacing:1px;white-space:nowrap">New to Planeazzy?</span>
        <div style="flex:1;height:1px;background:var(--s200)"></div>
      </div>

      <!-- Register link -->
      <a href="/hospital/register.php" class="hbtn hbtn-ghost hbtn-full hbtn-lg" style="margin-bottom:20px;font-size:15px">
        <i class="fa-solid fa-hospital"></i> Register Your Hospital
      </a>

      <!-- Footer links -->
      <div style="text-align:center;padding-top:16px;border-top:1px solid var(--s100)">
        <p style="font-size:13px;font-weight:500;color:var(--s500)">
          Looking for patient login? <a href="/patients/login.php" style="color:var(--hp);font-weight:700">Patient Portal →</a>
        </p>
        <p style="font-size:13px;font-weight:500;color:var(--s500);margin-top:6px">
          Individual doctor? <a href="/providers/doctor/login.php" style="color:var(--hp);font-weight:700">Doctor Portal →</a>
        </p>
      </div>
    </div>
  </div>
</main>

<!-- FOOTER -->
<footer style="background:var(--white);border-top:1px solid var(--s200);padding:20px 16px">
  <div style="max-width:1280px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px">
    <div style="display:flex;align-items:center;gap:8px">
      <i class="fa-solid fa-heart-pulse" style="color:var(--s400);font-size:18px"></i>
      <span style="font-size:13px;font-weight:500;color:var(--s500)">© 2025 Planeazzy Ltd. All rights reserved.</span>
    </div>
    <div style="display:flex;align-items:center;gap:24px">
      <a href="#" style="font-size:11px;font-weight:700;color:var(--s500);text-transform:uppercase;letter-spacing:1px">Privacy</a>
      <a href="#" style="font-size:11px;font-weight:700;color:var(--s500);text-transform:uppercase;letter-spacing:1px">Terms</a>
      <a href="#" style="font-size:11px;font-weight:700;color:var(--s500);text-transform:uppercase;letter-spacing:1px">Support</a>
    </div>
  </div>
</footer>

<!-- Show left panel on large screens -->
<style>@media(min-width:1024px){.h-split-left{display:flex!important;flex:0 0 50%}main>div:last-child{flex:0 0 50%}}</style>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/hospital.js"></script>
<script>
async function doHospitalLogin() {
  const email = document.getElementById('hLoginEmail')?.value?.trim();
  const pwd   = document.getElementById('hLoginPwd')?.value;
  if (!email || !pwd) { HUI.alert('warn', 'Please enter your email and password.', 'hAlertBox'); return; }
  const r = await hPost('/api/provider/login.php', {
    csrf_token: document.getElementById('hCsrf')?.value,
    email, password: pwd,
  }, 'hLoginBtn', 'hAlertBox');
  if (!r) return;
  if (r.success) {
    HUI.alert('ok', 'Login successful! Redirecting to dashboard…', 'hAlertBox');
    setTimeout(() => location.href = '/hospital/dashboard.php', 800);
  } else if (r.needs_verification) {
    sessionStorage.setItem('pz_prov_id', r.provider_id);
    sessionStorage.setItem('pz_prov_email', email);
    HUI.alert('info', 'Please verify your email first.', 'hAlertBox');
    setTimeout(() => location.href = '/hospital/verify.php', 1400);
  } else {
    HUI.alert('err', r.message || 'Invalid credentials. Please try again.', 'hAlertBox');
  }
}
document.addEventListener('keydown', e => { if (e.key === 'Enter') doHospitalLogin(); });
</script>
</body>
</html>
