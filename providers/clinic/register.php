<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id'])) { header('Location: /providers/dashboard.php'); exit; }
$noSidebar = true;
$pageTitle = 'Clinic / Hospital Registration';
include dirname(__DIR__, 2) . '/includes/header.php';
$csrf = Security::csrfToken();
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--bg-light)">
  <div style="width:100%;max-width:560px">
    <div class="auth-card slide-up">
      <!-- Progress -->
      <div class="step-wrap">
        <div class="step-row">
          <span class="portal-badge pb-green"><i class="fa-solid fa-house-medical"></i> Clinic / Hospital</span>
          <span class="step-pct">Registration</span>
        </div>
        <div class="prog-track"><div class="prog-fill" style="width:33%"></div></div>
      </div>
      <h2 style="font-size:22px;font-weight:900;color:var(--slate-900);margin-bottom:6px;letter-spacing:-.03em">Register as a Clinic / Hospital</h2>
      <p style="font-size:14px;color:var(--slate-500);margin-bottom:22px">Join Planeazzy's verified provider network. Your account will be reviewed within 24 hours.</p>
      <div id="alertBox" class="alert hidden"><i class="fa-solid fa-circle-exclamation"></i><span id="alertMsg"></span></div>
      <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" id="provType" value="clinic">
      <div class="form-group"><label class="form-label"><span data-en="Full Name / Practice Name" data-sw="Jina Kamili / Jina la Mazoezi">Full Name / Practice Name</span> <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-id-badge input-ico"></i><input type="text" id="pname" class="form-input has-ico" placeholder="Dr. Jane Doe / Kenyatta Clinic" required autofocus></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div class="form-group" style="margin-bottom:0"><label class="form-label"><span data-en="Email" data-sw="Barua Pepe">Email</span> <span class="req">*</span></label>
          <div class="input-wrap"><i class="fa-solid fa-envelope input-ico"></i><input type="email" id="pemail" class="form-input has-ico" placeholder="provider@example.com" required></div>
        </div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label"><span data-en="Phone" data-sw="Simu">Phone</span> <span class="req">*</span></label>
          <div class="input-wrap"><i class="fa-solid fa-phone input-ico"></i><input type="tel" id="pphone" class="form-input has-ico" placeholder="+254 7XX XXX XXX" required></div>
        </div>
      </div>
      <div class="form-group"><label class="form-label"><span data-en="Specialty / Type" data-sw="Utaalamu / Aina">Specialty / Type</span> <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-house-medical input-ico" style="color:var(--green)"></i>
          <select id="pspecialty" class="form-select has-ico">
<?php if ('clinic' === 'doctor'): ?>
            <option value="">Select specialty</option>
            <option>General Physician</option><option>Cardiologist</option><option>Dermatologist</option>
            <option>Pediatrician</option><option>Gynecologist</option><option>Orthopedic Surgeon</option>
            <option>Psychiatrist</option><option>Ophthalmologist</option><option>ENT Specialist</option>
            <option>Neurologist</option><option>Oncologist</option><option>Radiologist</option>
<?php elseif ('clinic' === 'clinic'): ?>
            <option value="">Select type</option>
            <option>General Clinic</option><option>Private Hospital</option><option>Dental Clinic</option>
            <option>Eye Clinic</option><option>Physiotherapy Centre</option><option>Maternity Centre</option>
            <option>Diagnostic Centre</option><option>Specialist Hospital</option>
<?php else: ?>
            <option value="">Select type</option>
            <option>Basic Life Support (BLS)</option><option>Advanced Life Support (ALS)</option>
            <option>Patient Transport</option><option>Emergency Response Team</option>
<?php endif; ?>
          </select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">License / Registration Number <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-certificate input-ico"></i><input type="text" id="plicense" class="form-input has-ico" placeholder="e.g. MBChB/2019/1234" required></div>
      </div>
      <div class="form-group"><label class="form-label">Physical Address / Location <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-location-dot input-ico"></i><input type="text" id="paddress" class="form-input has-ico" placeholder="Street, Area, City" required></div>
      </div>
      <div class="form-group"><label class="form-label"><span data-en="Password" data-sw="Nenosiri">Password</span> <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-lock input-ico"></i>
          <input type="password" id="ppwd" class="form-input has-ico" placeholder="Min 8 characters" required>
          <button type="button" class="eye-btn" id="ep1" onclick="togglePwd('ppwd','ep1')"><i class="fa-solid fa-eye"></i></button>
        </div>
        <div class="str-bar"><div class="str-fill" id="sf"></div></div>
        <div class="str-txt" id="st"></div>
      </div>
      <div class="form-group">
        <label class="chk-label">
          <input type="checkbox" id="terms" required>
          <span class="chk-box"><i class="fa-solid fa-check" style="font-size:10px"></i></span>
          <span>I agree to the <a href="#">Terms of Service</a>, <a href="#">Privacy Policy</a> and Planeazzy's provider code of conduct.</span>
        </label>
      </div>
      <button id="regBtn" class="btn btn-primary btn-full btn-lg" style="background:var(--green);margin-top:8px" onclick="doProviderRegister()">
        <i class="fa-solid fa-house-medical"></i> Create Provider Account
      </button>
      <div class="divider"><span>Already registered?</span></div>
      <a href="/providers/clinic/login.php" class="btn btn-ghost btn-full"><i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In</a>
    </div>
  </div>
</main>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
<script>
PwdStrength.init('ppwd','sf','st');
async function doProviderRegister(){
  if(!document.getElementById('terms').checked){UI.alert('warn','Please accept the Terms of Service.','alertBox');return;}
  const r=await post('/api/provider/register.php',{
    csrf_token:document.getElementById('csrf').value,
    name:document.getElementById('pname').value.trim(),
    email:document.getElementById('pemail').value.trim(),
    phone:document.getElementById('pphone').value.trim(),
    specialty:document.getElementById('pspecialty').value,
    license_number:document.getElementById('plicense').value.trim(),
    address:document.getElementById('paddress').value.trim(),
    password:document.getElementById('ppwd').value,
    type:'clinic',
  },'regBtn','alertBox');
  if(!r)return;
  if(r.success){
    sessionStorage.setItem('pz_prov_id',r.provider_id);
    sessionStorage.setItem('pz_prov_email',r.email||document.getElementById('pemail').value.trim());
    UI.alert('ok','Account created! Check your email for verification code.','alertBox');
    setTimeout(()=>location.href='/providers/verify.php',1300);
  } else {
    const msg=Array.isArray(r.errors)?r.errors.join(' '):(r.message||'Registration failed.');
    UI.alert('err',msg,'alertBox');
  }
}
</script>
