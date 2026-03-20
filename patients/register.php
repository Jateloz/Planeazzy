<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true;
$pageTitle = 'Create Account';
include dirname(__DIR__). '/includes/header.php';
$csrf = Security::csrfToken();
?>
<main class="page-main">
  <div class="auth-wrap">
    <div class="step-wrap">
      <div class="step-row">
        <span class="step-badge"><span class="material-symbols-outlined">person_add</span> Step 2 of 5</span>
        <span class="pct">20% Complete</span>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:20%"></div></div>
    </div>
    <div class="auth-card">
      <div class="card-icon"><span class="material-symbols-outlined">person_add</span></div>
      <h1 class="card-title">Create your account</h1>
      <p class="card-sub">Join thousands of Kenyans managing their health with Planeazzy.</p>
      <div id="alertBox" class="alert hidden"><span class="material-symbols-outlined">error</span><span id="alertMsg"></span></div>
      <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-ico"><span class="material-symbols-outlined">badge</span></span>
            <input type="text" id="firstName" class="form-input has-ico" placeholder="e.g. Amina" autocomplete="given-name" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Last Name <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-ico"><span class="material-symbols-outlined">badge</span></span>
            <input type="text" id="lastName" class="form-input has-ico" placeholder="e.g. Kamau" autocomplete="family-name" required>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address <span class="req">*</span></label>
        <div class="input-wrap">
          <span class="input-ico"><span class="material-symbols-outlined">mail</span></span>
          <input type="email" id="email" class="form-input has-ico" placeholder="you@example.com" autocomplete="email" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Phone Number <span class="req">*</span></label>
        <div class="input-wrap">
          <span class="input-ico"><span class="material-symbols-outlined">phone</span></span>
          <input type="tel" id="phone" class="form-input has-ico" placeholder="+254 7XX XXX XXX" autocomplete="tel" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date of Birth <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-ico"><span class="material-symbols-outlined">calendar_today</span></span>
            <input type="date" id="dob" class="form-input has-ico" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Gender <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-ico"><span class="material-symbols-outlined">wc</span></span>
            <select id="gender" class="form-select has-ico" required>
              <option value="">Select gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="non_binary">Non-binary</option>
              <option value="prefer_not">Prefer not to say</option>
            </select>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Password <span class="req">*</span></label>
        <div class="input-wrap">
          <span class="input-ico"><span class="material-symbols-outlined">lock</span></span>
          <input type="password" id="password" class="form-input has-ico" placeholder="Min 8 chars, uppercase, number, symbol" autocomplete="new-password" required>
          <button type="button" class="eye-btn" id="eye1" onclick="togglePwd('password','eye1')"><span class="material-symbols-outlined">visibility</span></button>
        </div>
        <div class="str-bar"><div class="str-fill" id="strFill"></div></div>
        <span class="str-txt" id="strTxt"></span>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password <span class="req">*</span></label>
        <div class="input-wrap">
          <span class="input-ico"><span class="material-symbols-outlined">lock_clock</span></span>
          <input type="password" id="pwdConfirm" class="form-input has-ico" placeholder="Re-enter your password" autocomplete="new-password" required>
          <button type="button" class="eye-btn" id="eye2" onclick="togglePwd('pwdConfirm','eye2')"><span class="material-symbols-outlined">visibility</span></button>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:20px">
        <label class="check-label">
          <input type="checkbox" id="terms" required>
          <span class="check-box"><svg width="10" height="8" fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 10 8"><polyline points="1 4 3.5 7 9 1"/></svg></span>
          <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
        </label>
      </div>
      <button id="submitBtn" class="btn btn-primary btn-full btn-lg" type="button" onclick="doRegister()">
        <span class="material-symbols-outlined">how_to_reg</span> Create Account
      </button>
      <div class="divider"><span>Already have an account?</span></div>
      <a href="/patients/login.php" class="btn btn-ghost btn-full">Sign In Instead</a>
      <div class="sec-notice">
        <div class="sec-inner">
          <span class="material-symbols-outlined">verified_user</span>
          Your data is encrypted with 256-bit TLS. We never share your medical information.
        </div>
      </div>
    </div>
    <p class="tc mt2" style="font-size:13px;color:var(--muted)"><a href="/" style="color:var(--muted)">&larr; Back to Home</a></p>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
PwdStrength.init('password','strFill','strTxt');
async function doRegister() {
  const pwd = document.getElementById('password').value;
  if (pwd !== document.getElementById('pwdConfirm').value) {
    UI.alert('err','Passwords do not match.','alertBox'); document.getElementById('alertMsg').textContent='Passwords do not match.'; return;
  }
  if (!document.getElementById('terms').checked) {
    document.getElementById('alertBox').className='alert alert-err';
    document.getElementById('alertMsg').textContent='Please accept the Terms to continue.';
    document.getElementById('alertBox').classList.remove('hidden'); return;
  }
  const r = await post('/api/auth/register.php', {
    csrf_token: document.getElementById('csrf').value,
    first_name: document.getElementById('firstName').value.trim(),
    last_name:  document.getElementById('lastName').value.trim(),
    email:      document.getElementById('email').value.trim(),
    phone:      document.getElementById('phone').value.trim(),
    dob:        document.getElementById('dob').value,
    gender:     document.getElementById('gender').value,
    password:   pwd, language: 'en',
  }, 'submitBtn', 'alertBox');
  if (!r) return;
  if (r.success) {
    sessionStorage.setItem('pz_pid', r.patient_id);
    sessionStorage.setItem('pz_email', r.email);
    document.getElementById('alertBox').className='alert alert-ok';
    document.getElementById('alertMsg').textContent=r.message||'Account created! Redirecting…';
    document.getElementById('alertBox').classList.remove('hidden');
    setTimeout(()=>location.href='/patients/verify-email.php',1200);
  } else {
    const msg=Array.isArray(r.errors)?r.errors.join(' '):(r.message||'Registration failed.');
    document.getElementById('alertBox').className='alert alert-err';
    document.getElementById('alertMsg').textContent=msg;
    document.getElementById('alertBox').classList.remove('hidden');
  }
}
</script>
