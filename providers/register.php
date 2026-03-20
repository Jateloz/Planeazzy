<?php
/**
 * Planeazzy — providers/register.php
 * Provider registration (doctors, clinics, hospitals, ambulance)
 */
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id'])) { header('Location: /providers/dashboard.php'); exit; }

$noSidebar = true;
$pageTitle = 'Provider Registration';
include dirname(__DIR__). '/includes/header.php';
$csrf = Security::csrfToken();
?>

<main style="min-height:calc(100vh - 64px);background:var(--bg);display:flex;align-items:center;justify-content:center;padding:40px 20px">
  <div class="split-layout">

    <div class="split-left">
      <div class="split-left-bg" style="background-image:url('https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=600&q=70')"></div>
      <div class="split-left-hex"></div>
      <div style="position:relative;z-index:1;margin-bottom:auto;padding-bottom:20px">
        <span class="pill pill-blue" style="margin-bottom:14px;display:inline-flex">Provider Portal</span>
      </div>
      <div class="split-left-content">
        <h2>Join Kenya's Leading Healthcare Network</h2>
        <p>Register your practice and connect with thousands of patients seeking quality healthcare across Kenya.</p>
        <div class="split-left-features">
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> Patient appointment management</div>
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> Real-time availability scheduling</div>
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> Telehealth video consultations</div>
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> Digital billing &amp; records</div>
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> H3 spatial patient matching</div>
        </div>
      </div>
    </div>

    <div class="split-right">
      <div style="max-width:400px;width:100%;margin:0 auto">
        <h2 style="font-family:var(--ff-head);font-size:24px;font-weight:900;color:var(--navy);margin-bottom:6px;letter-spacing:-0.04em">Register as a Provider</h2>
        <p style="font-size:13px;color:var(--muted);margin-bottom:22px">Create your provider account. Your account will be verified before activation.</p>

        <div id="alertBox" class="alert hidden"></div>

        <div id="regForm">
          <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">

          <div class="form-group">
            <label class="form-label">Provider Type <span class="req">*</span></label>
            <div class="inp-wrap">
              <span class="inp-ico"><span class="material-symbols-outlined">medical_services</span></span>
              <select id="ptype" class="form-select has-ico">
                <option value="">-- Select type --</option>
                <option value="doctor">Individual Doctor / Specialist</option>
                <option value="clinic">Clinic / Outpatient Centre</option>
                <option value="hospital">Hospital</option>
                <option value="ambulance">Ambulance / Emergency Service</option>
                <option value="pharmacy">Pharmacy</option>
                <option value="lab">Diagnostic Laboratory</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Full Name / Organisation <span class="req">*</span></label>
            <div class="inp-wrap">
              <span class="inp-ico"><span class="material-symbols-outlined">badge</span></span>
              <input type="text" id="pname" class="form-input has-ico" placeholder="e.g. Dr. James Omondi or Westlands Clinic">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Email <span class="req">*</span></label>
              <div class="inp-wrap">
                <span class="inp-ico"><span class="material-symbols-outlined">mail</span></span>
                <input type="email" id="pemail" class="form-input has-ico" placeholder="you@hospital.co.ke">
              </div>
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Phone <span class="req">*</span></label>
              <div class="inp-wrap">
                <span class="inp-ico"><span class="material-symbols-outlined">phone</span></span>
                <input type="tel" id="pphone" class="form-input has-ico" placeholder="+254 7XX XXX XXX">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Specialty / Service Focus</label>
            <div class="inp-wrap">
              <span class="inp-ico"><span class="material-symbols-outlined">stethoscope</span></span>
              <input type="text" id="pspec" class="form-input has-ico" placeholder="e.g. Cardiologist, Pediatrics, General Practice">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">License / Registration Number <span class="req">*</span></label>
            <div class="inp-wrap">
              <span class="inp-ico"><span class="material-symbols-outlined">verified</span></span>
              <input type="text" id="plicense" class="form-input has-ico" placeholder="Kenya Medical Board license number">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Practice Address <span class="req">*</span></label>
            <div class="inp-wrap">
              <span class="inp-ico"><span class="material-symbols-outlined">location_on</span></span>
              <input type="text" id="paddress" class="form-input has-ico" placeholder="Street, Area, City">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Password <span class="req">*</span></label>
            <div class="inp-wrap">
              <span class="inp-ico"><span class="material-symbols-outlined">lock</span></span>
              <input type="password" id="ppwd" class="form-input has-ico" placeholder="Min 8 chars" autocomplete="new-password">
              <button type="button" class="eye-btn" id="eye1" onclick="togglePwd('ppwd','eye1')"><span class="material-symbols-outlined">visibility</span></button>
            </div>
            <div class="str-bar"><div class="str-fill" id="strFill"></div></div>
            <span class="str-txt" id="strTxt"></span>
          </div>

          <div class="form-group" style="margin-bottom:20px">
            <label class="chk-label">
              <input type="checkbox" id="pterms">
              <span class="chk-box"><svg width="10" height="8" fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 10 8"><polyline points="1 4 3.5 7 9 1"/></svg></span>
              <span>I agree to the <a href="#">Provider Terms</a> and confirm all information is accurate</span>
            </label>
          </div>

          <button id="submitBtn" class="btn btn-primary btn-full btn-lg" type="button" onclick="submitProviderReg()">
            <span class="material-symbols-outlined">how_to_reg</span> Register Provider Account
          </button>
        </div>

        <div class="divider"><span>Already have an account?</span></div>
        <a href="/providers/login.php" class="btn btn-ghost btn-full">Sign In to Provider Portal</a>

        <div class="sec-note">
          <div class="sec-inner">
            <span class="material-symbols-outlined">verified_user</span>
            Your account will be reviewed and verified. You'll receive an email with a verification code within 24 hours.
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
PwdStrength.init('ppwd','strFill','strTxt');
async function submitProviderReg() {
  const terms = document.getElementById('pterms');
  if (!terms.checked) { UI.alert('warn','Please accept the Provider Terms to continue.','alertBox'); return; }
  const r = await post('/api/provider/register.php', {
    csrf_token:      document.getElementById('csrf').value,
    type:            document.getElementById('ptype').value,
    name:            document.getElementById('pname').value.trim(),
    email:           document.getElementById('pemail').value.trim(),
    phone:           document.getElementById('pphone').value.trim(),
    specialty:       document.getElementById('pspec').value.trim(),
    license_number:  document.getElementById('plicense').value.trim(),
    address:         document.getElementById('paddress').value.trim(),
    password:        document.getElementById('ppwd').value,
  }, 'submitBtn', 'alertBox');
  if (!r) return;
  if (r.success) {
    sessionStorage.setItem('pz_prov_id', r.provider_id);
    sessionStorage.setItem('pz_prov_email', r.email);
    UI.alert('ok', r.message || 'Account created! Check your email for the verification code.', 'alertBox');
    setTimeout(() => location.href = '/providers/verify.php', 1400);
  } else {
    const msg = Array.isArray(r.errors) ? r.errors.join('<br>') : (r.message || 'Registration failed.');
    UI.alert('err', msg, 'alertBox');
  }
}
</script>
