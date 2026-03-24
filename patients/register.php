<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true; $pageTitle = 'Create Patient Account';
include dirname(__DIR__). '/includes/header.php';
$csrf = Security::csrfToken();
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--bg-light)">
  <div style="width:100%;max-width:540px">
    <div class="auth-card slide-up">
      <!-- Step progress -->
      <div class="step-wrap">
        <div class="step-row">
          <span class="step-badge"><i class="fa-solid fa-user"></i> Patient Registration</span>
          <span class="step-pct">Step 1 of 3</span>
        </div>
        <div class="prog-track"><div class="prog-fill" style="width:33%"></div></div>
      </div>
      <h2 style="font-size:22px;font-weight:900;color:var(--slate-900);margin-bottom:6px;letter-spacing:-.03em">Create your account</h2>
      <p style="font-size:14px;color:var(--slate-500);margin-bottom:24px">Join thousands of Kenyans accessing better healthcare.</p>
      <div id="alertBox" class="alert hidden"><i class="fa-solid fa-circle-exclamation"></i><span id="alertMsg"></span></div>
      <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div class="form-group" style="margin-bottom:0"><label class="form-label">First Name <span class="req">*</span></label>
          <div class="input-wrap"><i class="fa-solid fa-user input-ico"></i><input type="text" id="fname" class="form-input has-ico" placeholder="John" required></div>
        </div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Last Name <span class="req">*</span></label>
          <div class="input-wrap"><i class="fa-solid fa-user input-ico"></i><input type="text" id="lname" class="form-input has-ico" placeholder="Doe" required></div>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Email Address <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-envelope input-ico"></i><input type="email" id="email" class="form-input has-ico" placeholder="you@example.com" autocomplete="email" required></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Phone <span class="req">*</span></label>
          <div class="input-wrap"><i class="fa-solid fa-phone input-ico"></i><input type="tel" id="phone" class="form-input has-ico" placeholder="+254 7XX XXX XXX" required></div>
        </div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Date of Birth <span class="req">*</span></label>
          <div class="input-wrap"><i class="fa-solid fa-calendar input-ico"></i><input type="date" id="dob" class="form-input has-ico" required></div>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Gender</label>
        <div class="input-wrap"><i class="fa-solid fa-venus-mars input-ico"></i>
          <select id="gender" class="form-select has-ico"><option value="">Select gender</option><option>Male</option><option>Female</option><option>Other</option></select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Password <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-lock input-ico"></i>
          <input type="password" id="password" class="form-input has-ico" placeholder="Min 8 characters" autocomplete="new-password" required>
          <button type="button" class="eye-btn" id="ep1" onclick="togglePwd('password','ep1')"><i class="fa-solid fa-eye"></i></button>
        </div>
        <div class="str-bar"><div class="str-fill" id="strFill"></div></div>
        <div class="str-txt" id="strTxt"></div>
      </div>
      <div class="form-group"><label class="form-label">Confirm Password <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-lock input-ico"></i>
          <input type="password" id="confirm" class="form-input has-ico" placeholder="Repeat password" required>
          <button type="button" class="eye-btn" id="ep2" onclick="togglePwd('confirm','ep2')"><i class="fa-solid fa-eye"></i></button>
        </div>
      </div>
      <div class="form-group">
        <label class="chk-label">
          <input type="checkbox" id="terms" required>
          <span class="chk-box"><i class="fa-solid fa-check" style="font-size:10px"></i></span>
          <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
        </label>
      </div>
      <button id="submitBtn" class="btn btn-primary btn-full btn-lg" style="margin-top:8px" onclick="doRegister()">
        <i class="fa-solid fa-user-plus"></i> Create Account
      </button>
      <div class="divider"><span>Already have an account?</span></div>
      <a href="/patients/login.php" class="btn btn-ghost btn-full"><i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In</a>
    </div>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
PwdStrength.init('password','strFill','strTxt');
async function doRegister(){
  if(!document.getElementById('terms').checked){UI.alert('warn','Please accept the Terms of Service.','alertBox');return;}
  const pwd=document.getElementById('password').value;
  const conf=document.getElementById('confirm').value;
  if(pwd!==conf){UI.alert('err','Passwords do not match.','alertBox');return;}
  const r=await post('/api/auth/register.php',{
    csrf_token:document.getElementById('csrf').value,
    first_name:document.getElementById('fname').value.trim(),
    last_name:document.getElementById('lname').value.trim(),
    email:document.getElementById('email').value.trim(),
    phone:document.getElementById('phone').value.trim(),
    date_of_birth:document.getElementById('dob').value,
    gender:document.getElementById('gender').value,
    password:pwd,
  },'submitBtn','alertBox');
  if(!r)return;
  if(r.success){
    sessionStorage.setItem('pz_pat_id',r.patient_id);
    sessionStorage.setItem('pz_pat_email',r.email);
    UI.alert('ok','Account created! Check your email for the verification code.','alertBox');
    setTimeout(()=>location.href='/patients/verify-email.php',1300);
  } else {
    const msg=Array.isArray(r.errors)?r.errors.join(' '):(r.message||'Registration failed.');
    UI.alert('err',msg,'alertBox');
  }
}
</script>
