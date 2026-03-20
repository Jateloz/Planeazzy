<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true;
$pageTitle = 'Patient Login';
$timeout   = ($_GET['reason']??'')==='timeout';
include dirname(__DIR__). '/includes/header.php';
$csrf = Security::csrfToken();
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;background:var(--bg);min-height:calc(100vh - var(--header-h))">
  <div class="split-layout">
    <div class="split-left">
      <div class="split-left-bg" style="background-image:url('https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=700&q=80')"></div>
      <div class="split-left-hex"></div>
      <div class="split-left-content">
        <h2>Your Direct Path to Better Healthcare</h2>
        <p>Access your medical records, book appointments, and connect with healthcare providers across Kenya.</p>
        <div class="split-left-features">
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> HIPAA Compliant &amp; Encrypted</div>
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> Book appointments in seconds</div>
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> 24/7 emergency services</div>
          <div class="split-feature"><span class="material-symbols-outlined">check_circle</span> Telehealth video consultations</div>
        </div>
      </div>
    </div>
    <div class="split-right">
      <div style="max-width:380px;width:100%;margin:0 auto">
        <h2 style="font-family:var(--ff-head);font-size:24px;font-weight:900;color:var(--navy);margin-bottom:6px;letter-spacing:-0.04em">Welcome back</h2>
        <p style="font-size:13px;color:var(--muted);margin-bottom:22px">Sign in to your Planeazzy health dashboard.</p>
        <?php if($timeout): ?>
        <div class="alert alert-info keep"><span class="material-symbols-outlined">info</span><span>Session expired. Please sign in again.</span></div>
        <?php endif; ?>
        <div id="alertBox" class="alert hidden"><span class="material-symbols-outlined">error</span><span id="alertMsg"></span></div>
        <input type="hidden" id="csrf" value="<?=htmlspecialchars($csrf)?>">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-wrap">
            <span class="input-ico"><span class="material-symbols-outlined">mail</span></span>
            <input type="email" id="email" class="form-input has-ico" placeholder="you@example.com" autocomplete="email" required autofocus>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:20px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
            <label class="form-label" style="margin-bottom:0">Password</label>
            <a href="#" style="font-size:12px;font-weight:600;color:var(--blue)">Forgot password?</a>
          </div>
          <div class="input-wrap">
            <span class="input-ico"><span class="material-symbols-outlined">lock</span></span>
            <input type="password" id="password" class="form-input has-ico" placeholder="••••••••" autocomplete="current-password" required>
            <button type="button" class="eye-btn" id="eye1" onclick="togglePwd('password','eye1')"><span class="material-symbols-outlined">visibility</span></button>
          </div>
        </div>
        <button id="submitBtn" class="btn btn-primary btn-full btn-lg" onclick="doLogin()">
          <span class="material-symbols-outlined">login</span> Sign In
        </button>
        <div class="divider"><span>New to Planeazzy?</span></div>
        <a href="/patients/register.php" class="btn btn-ghost btn-full">Create Free Account</a>
        <div class="sec-notice">
          <div class="sec-inner">
            <span class="material-symbols-outlined">verified_user</span>
            Your session is protected with end-to-end encryption.
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
async function doLogin(){
  const r=await post('/api/auth/login.php',{
    csrf_token:document.getElementById('csrf').value,
    email:document.getElementById('email').value.trim(),
    password:document.getElementById('password').value,
  },'submitBtn','alertBox');
  if(!r)return;
  if(r.success){
    document.getElementById('alertBox').className='alert alert-ok';
    document.getElementById('alertMsg').textContent='Login successful! Redirecting…';
    document.getElementById('alertBox').classList.remove('hidden');
    setTimeout(()=>location.href='/patients/dashboard.php',900);
  } else if(r.needs_verification){
    sessionStorage.setItem('pz_pid',r.patient_id);
    document.getElementById('alertBox').className='alert alert-info';
    document.getElementById('alertMsg').textContent=r.message;
    document.getElementById('alertBox').classList.remove('hidden');
    setTimeout(()=>location.href='/patients/verify-email.php',1500);
  } else {
    document.getElementById('alertBox').className='alert alert-err';
    document.getElementById('alertMsg').textContent=r.message||'Login failed.';
    document.getElementById('alertBox').classList.remove('hidden');
  }
}
</script>
