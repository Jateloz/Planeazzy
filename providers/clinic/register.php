<?php
require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id'])) { header('Location: /providers/dashboard.php'); exit; }
$noSidebar = true; $portalType = 'clinic'; $pageTitle = ucfirst('clinic').' Portal — Register';
include dirname(__DIR__, 2). '/includes/header.php';
$csrf = Security::csrfToken();

$typeLabels = ['doctor'=>'Doctor / Specialist','clinic'=>'Clinic / Hospital','ambulance'=>'Ambulance Service'];
$typeLabel  = $typeLabels['clinic'];
$typeColors = ['doctor'=>'var(--teal)','clinic'=>'var(--green)','ambulance'=>'var(--red)'];
$typeColor  = $typeColors['clinic'];
$typeIcons  = ['doctor'=>'stethoscope','clinic'=>'local_pharmacy','ambulance'=>'ambulance'];
$typeIcon   = $typeIcons['clinic'];
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;background:var(--bg);min-height:calc(100vh - 64px)">
  <div style="width:100%;max-width:560px">
    <div style="text-align:center;margin-bottom:24px">
      <div style="display:inline-flex;align-items:center;gap:6px;background:<?= htmlspecialchars($typeColor) ?>18;color:<?= htmlspecialchars($typeColor) ?>;padding:5px 14px;border-radius:99px;font-size:11px;font-weight:700;border:1px solid <?= htmlspecialchars($typeColor) ?>30;text-transform:uppercase;letter-spacing:.4px">
        <span class="material-symbols-outlined" style="font-size:13px"><?= htmlspecialchars($typeIcon) ?></span> <?= htmlspecialchars($typeLabel) ?> Registration
      </div>
    </div>
    <div class="auth-card">
      <div class="card-icon" style="background:<?= htmlspecialchars($typeColor) ?>18;border-color:<?= htmlspecialchars($typeColor) ?>30;color:<?= htmlspecialchars($typeColor) ?>">
        <span class="material-symbols-outlined"><?= htmlspecialchars($typeIcon) ?></span>
      </div>
      <h1 class="card-title">Register as a <?= htmlspecialchars($typeLabel) ?></h1>
      <p class="card-sub">Join Planeazzy's verified provider network. Your account will be reviewed within 24 hours.</p>
      <div id="alertBox" class="alert hidden"><span class="material-symbols-outlined">error</span><span id="alertMsg"></span></div>
      <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" id="ptype" value="<?= htmlspecialchars('clinic') ?>">
      <div class="form-group">
        <label class="form-label"><?= htmlspecialchars($typeLabel) ?> Name <span class="req">*</span></label>
        <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">badge</span></span>
        <input type="text" id="pname" class="form-input has-ico" placeholder="Your full name or organisation" required></div>
      </div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Email <span class="req">*</span></label>
          <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">mail</span></span>
          <input type="email" id="pemail" class="form-input has-ico" placeholder="you@example.co.ke" required></div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Phone <span class="req">*</span></label>
          <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">phone</span></span>
          <input type="tel" id="pphone" class="form-input has-ico" placeholder="+254 7XX XXX XXX" required></div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Specialty / Service Type</label>
        <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined"><?= htmlspecialchars($typeIcon) ?></span></span>
        <input type="text" id="pspec" class="form-input has-ico" placeholder="e.g. Cardiologist, Outpatient, ICU Ambulance"></div>
      </div>
      <div class="form-group">
        <label class="form-label">License / Registration Number <span class="req">*</span></label>
        <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">verified</span></span>
        <input type="text" id="plicense" class="form-input has-ico" placeholder="Kenya Medical Board / NTSA registration" required></div>
      </div>
      <div class="form-group">
        <label class="form-label">Practice Address <span class="req">*</span></label>
        <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">location_on</span></span>
        <input type="text" id="paddress" class="form-input has-ico" placeholder="Street, Area, City" required></div>
      </div>
      <div class="form-group">
        <label class="form-label">Password <span class="req">*</span></label>
        <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">lock</span></span>
        <input type="password" id="ppwd" class="form-input has-ico" placeholder="Minimum 8 characters" autocomplete="new-password" required>
        <button type="button" class="eye-btn" id="eye1" onclick="togglePwd('ppwd','eye1')"><span class="material-symbols-outlined">visibility</span></button></div>
        <div class="str-bar"><div class="str-fill" id="strFill"></div></div>
        <span class="str-txt" id="strTxt"></span>
      </div>
      <div class="form-group" style="margin-bottom:20px">
        <label class="check-label">
          <input type="checkbox" id="pterms" required>
          <span class="check-box"><svg width="10" height="8" fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 10 8"><polyline points="1 4 3.5 7 9 1"/></svg></span>
          <span>I agree to the <a href="#">Provider Terms</a> and confirm my details are accurate</span>
        </label>
      </div>
      <button id="submitBtn" class="btn btn-full btn-lg" style="background:<?= htmlspecialchars($typeColor) ?>;color:#fff" onclick="submitReg()">
        <span class="material-symbols-outlined">how_to_reg</span> Create <?= htmlspecialchars($typeLabel) ?> Account
      </button>
      <div class="divider"><span>Already registered?</span></div>
      <a href="/providers/clinic/login.php" class="btn btn-ghost btn-full">Sign In</a>
      <div class="sec-notice"><div class="sec-inner"><span class="material-symbols-outlined">verified_user</span>Your account will be reviewed within 24 hours before activation.</div></div>
    </div>
  </div>
</main>
<?php include dirname(__DIR__, 2). '/includes/footer.php'; ?>
<script>
PwdStrength.init('ppwd','strFill','strTxt');
async function submitReg(){
  if(!document.getElementById('pterms').checked){
    UI.alert('warn','Please accept the Provider Terms.','alertBox'); return;
  }
  const r=await post('/api/provider/register.php',{
    csrf_token:document.getElementById('csrf').value,
    type:document.getElementById('ptype').value,
    name:document.getElementById('pname').value.trim(),
    email:document.getElementById('pemail').value.trim(),
    phone:document.getElementById('pphone').value.trim(),
    specialty:document.getElementById('pspec').value.trim(),
    license_number:document.getElementById('plicense').value.trim(),
    address:document.getElementById('paddress').value.trim(),
    password:document.getElementById('ppwd').value,
  },'submitBtn','alertBox');
  if(!r)return;
  if(r.success){
    sessionStorage.setItem('pz_prov_id',r.provider_id);
    sessionStorage.setItem('pz_prov_email',r.email);
    UI.alert('ok',r.message||'Account created! Check your email for verification code.','alertBox');
    setTimeout(()=>location.href='/providers/verify.php',1300);
  } else {
    const msg=Array.isArray(r.errors)?r.errors.join(' '):(r.message||'Registration failed.');
    UI.alert('err',msg,'alertBox');
  }
}
</script>
