<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true; $pageTitle = 'Create Patient Account';
include dirname(__DIR__) . '/includes/header.php';
$csrf = Security::csrfToken();
?>
<style>
.auth-wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;background:var(--slate-50)}
.auth-card{background:#fff;border-radius:20px;padding:40px;box-shadow:0 12px 40px rgba(0,0,0,.08);width:100%;max-width:560px}
</style>
<main class="auth-wrap">
  <div class="auth-card slide-up">
    <!-- Progress -->
    <div class="step-wrap">
      <div class="step-row">
        <span class="step-badge"><i class="fa-solid fa-user"></i> <span data-en="Patient Registration" data-sw="Usajili wa Mgonjwa">Patient Registration</span></span>
        <span class="step-pct" data-en="Step 1 of 3" data-sw="Hatua 1 ya 3">Step 1 of 3</span>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:33%"></div></div>
    </div>

    <h2 style="font-size:1.375rem;font-weight:900;color:var(--slate-900);margin-bottom:6px;letter-spacing:-.03em"
        data-en="Create your account" data-sw="Unda akaunti yako">Create your account</h2>
    <p style="font-size:.875rem;color:var(--slate-500);margin-bottom:24px"
       data-en="Join thousands of Kenyans accessing better healthcare."
       data-sw="Jiunge na maelfu ya Wakenya wanaopata huduma bora ya afya.">
      Join thousands of Kenyans accessing better healthcare.
    </p>

    <div id="alertBox" class="alert hidden"></div>
    <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label" data-en="First Name" data-sw="Jina la Kwanza">First Name <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-user input-ico"></i>
          <input type="text" id="fname" class="form-input has-ico"
            data-en-placeholder="John" data-sw-placeholder="John" placeholder="John" required>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label" data-en="Last Name" data-sw="Jina la Ukoo">Last Name <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-user input-ico"></i>
          <input type="text" id="lname" class="form-input has-ico"
            data-en-placeholder="Kamau" data-sw-placeholder="Kamau" placeholder="Kamau" required>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" data-en="Email Address" data-sw="Barua Pepe">Email Address <span class="req">*</span></label>
      <div class="input-wrap"><i class="fa-solid fa-envelope input-ico"></i>
        <input type="email" id="email" class="form-input has-ico"
          data-en-placeholder="you@example.com" data-sw-placeholder="wewe@mfano.com"
          placeholder="you@example.com" autocomplete="email" required>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label" data-en="Phone" data-sw="Simu">Phone <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-phone input-ico"></i>
          <input type="tel" id="phone" class="form-input has-ico"
            data-en-placeholder="+254 7XX XXX XXX" data-sw-placeholder="+254 7XX XXX XXX"
            placeholder="+254 7XX XXX XXX" required>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label" data-en="Date of Birth" data-sw="Tarehe ya Kuzaliwa">Date of Birth <span class="req">*</span></label>
        <div class="input-wrap"><i class="fa-solid fa-calendar input-ico"></i>
          <input type="date" id="dob" class="form-input has-ico" required>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" data-en="Gender" data-sw="Jinsia">Gender</label>
      <div class="input-wrap"><i class="fa-solid fa-venus-mars input-ico"></i>
        <select id="gender" class="form-select has-ico">
          <option value="" data-en="Select gender" data-sw="Chagua jinsia">Select gender</option>
          <option value="Male" data-en="Male" data-sw="Mume">Male</option>
          <option value="Female" data-en="Female" data-sw="Mke">Female</option>
          <option value="Other" data-en="Other" data-sw="Nyingine">Other</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" data-en="Password" data-sw="Nenosiri">Password <span class="req">*</span></label>
      <div class="input-wrap"><i class="fa-solid fa-lock input-ico"></i>
        <input type="password" id="password" class="form-input has-ico"
          data-en-placeholder="Min 8 characters" data-sw-placeholder="Angalau herufi 8"
          placeholder="Min 8 characters" autocomplete="new-password" required>
        <button type="button" class="eye-btn" id="ep1" onclick="togglePwd('password','ep1')"><i class="fa-solid fa-eye"></i></button>
      </div>
      <div class="str-bar"><div class="str-fill" id="strFill"></div></div>
      <div class="str-txt" id="strTxt"></div>
    </div>

    <div class="form-group">
      <label class="form-label" data-en="Confirm Password" data-sw="Thibitisha Nenosiri">Confirm Password <span class="req">*</span></label>
      <div class="input-wrap"><i class="fa-solid fa-lock input-ico"></i>
        <input type="password" id="confirm" class="form-input has-ico"
          data-en-placeholder="Repeat password" data-sw-placeholder="Rudia nenosiri"
          placeholder="Repeat password" required>
        <button type="button" class="eye-btn" id="ep2" onclick="togglePwd('confirm','ep2')"><i class="fa-solid fa-eye"></i></button>
      </div>
    </div>

    <div class="form-group">
      <label class="chk-label">
        <input type="checkbox" id="terms" required>
        <span class="chk-box"><i class="fa-solid fa-check" style="font-size:10px"></i></span>
        <span>
          <span data-en="I agree to the" data-sw="Nakubaliana na">I agree to the</span>
          <a href="#" data-en="Terms of Service" data-sw="Masharti ya Huduma">Terms of Service</a>
          <span data-en="and" data-sw="na">and</span>
          <a href="#" data-en="Privacy Policy" data-sw="Sera ya Faragha">Privacy Policy</a>
        </span>
      </label>
    </div>

    <button id="submitBtn" class="btn btn-primary btn-full btn-lg" style="margin-top:8px" onclick="doRegister()">
      <i class="fa-solid fa-user-plus"></i>
      <span data-en="Create Account" data-sw="Unda Akaunti">Create Account</span>
    </button>

    <div class="divider">
      <span data-en="Already have an account?" data-sw="Una akaunti tayari?">Already have an account?</span>
    </div>
    <a href="/patients/login.php" class="btn btn-ghost btn-full">
      <i class="fa-solid fa-arrow-right-to-bracket"></i>
      <span data-en="Sign In" data-sw="Ingia">Sign In</span>
    </a>
  </div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
<script>
PwdStrength.init('password','strFill','strTxt');
async function doRegister(){
  if(!document.getElementById('terms').checked){
    UI.alert('warn', document.documentElement.lang==='sw'?'Tafadhali kubali Masharti ya Huduma.':'Please accept the Terms of Service.','alertBox'); return;
  }
  const pwd=document.getElementById('password').value;
  if(pwd!==document.getElementById('confirm').value){
    UI.alert('err', document.documentElement.lang==='sw'?'Manenosiri hayafanani.':'Passwords do not match.','alertBox'); return;
  }
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
    UI.alert('ok', document.documentElement.lang==='sw'?'Akaunti imeundwa! Angalia barua pepe yako.':'Account created! Check your email for the verification code.','alertBox');
    setTimeout(()=>location.href='/patients/verify-email.php',1300);
  } else {
    UI.alert('err',Array.isArray(r.errors)?r.errors.join(' '):(r.message||'Registration failed.'),'alertBox');
  }
}
</script>
