<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id']) && !empty($_SESSION['is_provider'])) {
    header('Location: /hospital/dashboard.php'); exit;
}
$csrf = Security::csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Register your hospital or clinic on Planeazzy — Kenya's leading healthcare platform.">
  <link rel="icon" type="image/png" href="/assets/images/favicon.png">
  <link rel="apple-touch-icon" href="/assets/images/favicon.png"><rect width='100' height='100' rx='20' fill='%231978e5'/><text y='72' font-size='65' text-anchor='middle' x='50' fill='white'>+</text></svg>">
  <title>Register Hospital — Planeazzy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/hospital.css">
</head>
<body style="display:flex;flex-direction:column;min-height:100vh">

<header style="background:var(--white);border-bottom:1px solid var(--s200);position:sticky;top:0;z-index:50">
  <div style="max-width:1280px;margin:0 auto;padding:0 16px;height:64px;display:flex;align-items:center;justify-content:space-between">
    <a href="/" style="display:flex;align-items:center;gap:10px;text-decoration:none">
      <svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" style="width:32px;height:32px;color:var(--hp)">
        <path d="M4 42.4379C4 42.4379 14.0962 36.0744 24 41.1692C35.0664 46.8624 44 42.2078 44 42.2078L44 7.01134C44 7.01134 35.068 11.6577 24.0031 5.96913C14.0971 0.876274 4 7.27094 4 7.27094L4 42.4379Z" fill="currentColor"/>
      </svg>
      <span style="font-size:20px;font-weight:800;color:var(--s900);letter-spacing:-.03em">Planeazzy <span style="color:var(--hp)">Partner</span></span>
    </a>
    <a href="/hospital/login.php" class="hbtn hbtn-ghost hbtn-sm">Already registered? Sign In</a>
  </div>
</header>

<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;background:var(--bg)">
  <div style="width:100%;max-width:640px">

    <!-- Progress -->
    <div style="margin-bottom:24px;text-align:center">
      <div style="display:inline-block;padding:4px 14px;border-radius:9999px;background:var(--hp-10);color:var(--hp);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">
        <i class="fa-solid fa-hospital"></i> Hospital / Clinic Registration
      </div>
      <div style="height:6px;background:var(--s200);border-radius:9999px;overflow:hidden;max-width:320px;margin:0 auto">
        <div style="height:100%;width:33%;background:var(--hp);border-radius:9999px"></div>
      </div>
    </div>

    <div class="hc" style="padding:0">
      <div style="padding:28px 32px;border-bottom:1px solid var(--s100)">
        <h2 style="font-size:24px;font-weight:900;color:var(--s900);letter-spacing:-.03em;margin-bottom:4px">Register Your Hospital</h2>
        <p style="font-size:14px;color:var(--s500)">Join Planeazzy's verified provider network. Your account will be reviewed within 24–48 hours.</p>
      </div>

      <div style="padding:28px 32px">
        <div id="hAlertBox" class="h-alert hidden"><i class="fa-solid fa-circle-exclamation"></i><span></span></div>
        <input type="hidden" id="hCsrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="h-form-row">
          <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Facility Type <span class="hf-req">*</span></label>
            <div class="h-input-wrap"><i class="fa-solid fa-hospital h-input-ico"></i>
              <select id="hRegType" class="h-select has-ico">
                <option value="hospital">Hospital</option>
                <option value="clinic">Clinic / Medical Centre</option>
              </select>
            </div>
          </div>
          <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Facility Name <span class="hf-req">*</span></label>
            <div class="h-input-wrap"><i class="fa-solid fa-id-badge h-input-ico"></i>
              <input type="text" id="hRegName" class="h-input has-ico" placeholder="e.g. Kenyatta Hospital" required>
            </div>
          </div>
        </div>

        <div class="h-form-row mt2">
          <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Official Email <span class="hf-req">*</span></label>
            <div class="h-input-wrap"><i class="fa-solid fa-envelope h-input-ico"></i>
              <input type="email" id="hRegEmail" class="h-input has-ico" placeholder="admin@hospital.co.ke" required>
            </div>
          </div>
          <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Phone Number <span class="hf-req">*</span></label>
            <div class="h-input-wrap"><i class="fa-solid fa-phone h-input-ico"></i>
              <input type="tel" id="hRegPhone" class="h-input has-ico" placeholder="+254 20 XXX XXXX" required>
            </div>
          </div>
        </div>

        <div class="hf-group mt2"><label class="hf-label">Physical Address <span class="hf-req">*</span></label>
          <div class="h-input-wrap"><i class="fa-solid fa-map-marker-alt h-input-ico"></i>
            <input type="text" id="hRegAddr" class="h-input has-ico" placeholder="Street, Area, City e.g. Hospital Rd, Nairobi" required>
          </div>
        </div>

        <div class="h-form-row">
          <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Registration / License # <span class="hf-req">*</span></label>
            <div class="h-input-wrap"><i class="fa-solid fa-certificate h-input-ico"></i>
              <input type="text" id="hRegLicense" class="h-input has-ico" placeholder="MOH/HOSP/2024/XXXX" required>
            </div>
          </div>
          <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Bed Capacity</label>
            <div class="h-input-wrap"><i class="fa-solid fa-bed-pulse h-input-ico"></i>
              <input type="number" id="hRegBeds" class="h-input has-ico" placeholder="0" min="1">
            </div>
          </div>
        </div>

        <div class="hf-group mt2"><label class="hf-label">Website (optional)</label>
          <div class="h-input-wrap"><i class="fa-solid fa-globe h-input-ico"></i>
            <input type="url" id="hRegWeb" class="h-input has-ico" placeholder="https://yourhospital.co.ke">
          </div>
        </div>

        <div class="hf-group"><label class="hf-label">Brief Description</label>
          <textarea id="hRegDesc" class="h-textarea" rows="2" placeholder="Tell patients about your hospital, specialties and services…"></textarea>
        </div>

        <div class="hf-group"><label class="hf-label">Password <span class="hf-req">*</span></label>
          <div class="h-input-wrap"><i class="fa-solid fa-lock h-input-ico"></i>
            <input type="password" id="hRegPwd" class="h-input has-ico" placeholder="Min 8 characters" required>
            <button class="h-eye" id="hRegPwEye" type="button" onclick="hTogglePwd('hRegPwd','hRegPwEye')"><i class="fa-solid fa-eye"></i></button>
          </div>
          <div class="h-str-bar"><div class="h-str-fill" id="hRStrFill"></div></div>
          <div class="h-str-txt" id="hRStrTxt"></div>
        </div>

        <div class="hf-group"><label class="hf-label">Confirm Password <span class="hf-req">*</span></label>
          <div class="h-input-wrap"><i class="fa-solid fa-key h-input-ico"></i>
            <input type="password" id="hRegPwdC" class="h-input has-ico" placeholder="Repeat password" required>
          </div>
        </div>

        <div style="margin-bottom:20px">
          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:13px;color:var(--s600);line-height:1.6">
            <input type="checkbox" id="hRegTerms" style="width:18px;height:18px;border-radius:4px;accent-color:var(--hp);cursor:pointer;flex-shrink:0;margin-top:1px">
            <span>I agree to Planeazzy's <a href="#" style="color:var(--hp);font-weight:700">Terms of Service</a>, <a href="#" style="color:var(--hp);font-weight:700">Privacy Policy</a> and Healthcare Provider Code of Conduct.</span>
          </label>
        </div>

        <button id="hRegBtn" class="hbtn hbtn-primary hbtn-full hbtn-lg" onclick="doHospitalRegister()">
          <i class="fa-solid fa-hospital"></i> Create Hospital Account
        </button>

        <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--s500)">
          Already have an account? <a href="/hospital/login.php" style="color:var(--hp);font-weight:700" data-en="Sign in" data-sw="Ingia">Sign in</a>
        </p>
      </div>
    </div>
  </div>
</main>

<footer style="background:var(--white);border-top:1px solid var(--s200);padding:20px 16px;text-align:center">
  <p style="font-size:13px;color:var(--s500)">© 2025 Planeazzy Ltd. All rights reserved.</p>
</footer>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/hospital.js"></script>
<script>
HPwd.init('hRegPwd','hRStrFill','hRStrTxt');

async function doHospitalRegister() {
  if (!document.getElementById('hRegTerms').checked) {
    HUI.alert('warn', 'Please accept the Terms of Service.', 'hAlertBox'); return;
  }
  const pwd  = document.getElementById('hRegPwd')?.value;
  const pwdc = document.getElementById('hRegPwdC')?.value;
  if (pwd !== pwdc) { HUI.alert('err', 'Passwords do not match.', 'hAlertBox'); return; }
  if (pwd.length < 8) { HUI.alert('err', 'Password must be at least 8 characters.', 'hAlertBox'); return; }

  const r = await hPost('/api/hospital/register.php', {
    csrf_token:     document.getElementById('hCsrf')?.value,
    name:           document.getElementById('hRegName')?.value?.trim(),
    email:          document.getElementById('hRegEmail')?.value?.trim(),
    phone:          document.getElementById('hRegPhone')?.value?.trim(),
    address:        document.getElementById('hRegAddr')?.value?.trim(),
    license_number: document.getElementById('hRegLicense')?.value?.trim(),
    website:        document.getElementById('hRegWeb')?.value?.trim(),
    description:    document.getElementById('hRegDesc')?.value?.trim(),
    password:       pwd,
    type:           document.getElementById('hRegType')?.value || 'hospital',
  }, 'hRegBtn', 'hAlertBox');

  if (!r) return;
  if (r.success) {
    sessionStorage.setItem('pz_prov_id',    r.provider_id);
    sessionStorage.setItem('pz_prov_email', document.getElementById('hRegEmail')?.value?.trim());
    HUI.alert('ok', 'Account created! Redirecting to verify your email…', 'hAlertBox');
    setTimeout(() => location.href = '/hospital/verify.php', 1300);
  } else {
    const msg = Array.isArray(r.errors) ? r.errors.join(' ') : (r.message || 'Registration failed. Please try again.');
    HUI.alert('err', msg, 'hAlertBox');
  }
}
</script>
</body>
</html>
