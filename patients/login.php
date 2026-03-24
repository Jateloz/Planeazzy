<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true;
$pageTitle  = 'Patient Login';
$timeout   = ($_GET['reason'] ?? '') === 'timeout';
include dirname(__DIR__). '/includes/header.php';
$csrf = Security::csrfToken();
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--bg-light)">
  <div class="split-layout slide-up">
    <div class="split-left" style="background:linear-gradient(160deg,#0f172a 0%,var(--primary-h) 55%,var(--teal) 100%)">
      <div class="split-left-bg" style="background-image:url('https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=700&q=80')"></div>
      <div class="split-left-hex"></div>
      <div class="split-left-content">
        <span class="portal-badge pb-blue" style="margin-bottom:16px"><i class="fa-solid fa-user"></i> Patient Portal</span>
        <h2>Your Direct Path to Better Healthcare</h2>
        <p>Access your appointments, find doctors, and connect with healthcare providers across Kenya.</p>
        <div class="split-left-features">
          <div class="split-feature"><i class="fa-solid fa-check-circle"></i> HIPAA Compliant &amp; Encrypted</div>
          <div class="split-feature"><i class="fa-solid fa-check-circle"></i> Book appointments in seconds</div>
          <div class="split-feature"><i class="fa-solid fa-check-circle"></i> 24/7 emergency services</div>
          <div class="split-feature"><i class="fa-solid fa-check-circle"></i> Telehealth video consultations</div>
        </div>
      </div>
    </div>
    <div class="split-right">
      <div style="max-width:380px;width:100%;margin:0 auto">
        <span class="portal-badge pb-blue"><i class="fa-solid fa-user"></i> Patient Portal</span>
        <h2 style="font-size:26px;font-weight:900;color:var(--slate-900);margin-bottom:6px;letter-spacing:-.03em">Welcome back</h2>
        <p style="font-size:14px;color:var(--slate-500);margin-bottom:24px">Sign in to your Planeazzy health dashboard.</p>
        <?php if($timeout): ?><div class="alert alert-info"><i class="fa-solid fa-circle-info"></i><span>Session expired. Please sign in again.</span></div><?php endif; ?>
        <div id="alertBox" class="alert hidden"><i class="fa-solid fa-circle-exclamation"></i><span id="alertMsg"></span></div>
        <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div class="form-group"><label class="form-label">Email Address</label>
          <div class="input-wrap"><i class="fa-solid fa-envelope input-ico"></i><input type="email" id="email" class="form-input has-ico" placeholder="you@example.com" autocomplete="email" required autofocus></div>
        </div>
        <div class="form-group" style="margin-bottom:24px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <label class="form-label" style="margin-bottom:0">Password</label>
            <a href="#" style="font-size:12px;font-weight:600;color:var(--primary)">Forgot password?</a>
          </div>
          <div class="input-wrap"><i class="fa-solid fa-lock input-ico"></i>
            <input type="password" id="password" class="form-input has-ico" placeholder="••••••••" autocomplete="current-password" required>
            <button type="button" class="eye-btn" id="ep1" onclick="togglePwd('password','ep1')"><i class="fa-solid fa-eye"></i></button>
          </div>
        </div>
        <button id="submitBtn" class="btn btn-primary btn-full btn-lg" onclick="doLogin()"><i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In</button>
        <div class="divider"><span>New to Planeazzy?</span></div>
        <a href="/patients/register.php" class="btn btn-ghost btn-full"><i class="fa-solid fa-user-plus"></i> Create Free Account</a>
        <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--slate-100)">
          <div style="display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:8px;background:var(--primary-10);border:1px solid var(--primary-20);font-size:12px;color:var(--slate-600);line-height:1.6">
            <i class="fa-solid fa-shield-halved" style="color:var(--primary);margin-top:1px"></i>
            Your session is protected with end-to-end encryption.
          </div>
        </div>
        <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--slate-400)">
          Are you a provider? 
          <a href="/providers/doctor/login.php" style="color:var(--primary);font-weight:600">Provider login →</a>
        </p>
      </div>
    </div>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
async function doLogin(){
  const r=await post('/api/auth/login.php',{csrf_token:document.getElementById('csrf').value,email:document.getElementById('email').value.trim(),password:document.getElementById('password').value},'submitBtn','alertBox');
  if(!r)return;
  if(r.success){
    UI.alert('ok','Login successful! Redirecting…','alertBox');
    setTimeout(()=>location.href='/patients/dashboard.php',800);
  } else if(r.needs_verification){
    sessionStorage.setItem('pz_pat_id',r.patient_id);
    UI.alert('info','Please verify your email. Redirecting…','alertBox');
    setTimeout(()=>location.href='/patients/verify-email.php',1400);
  } else {
    UI.alert('err',r.message||'Login failed. Check your credentials.','alertBox');
  }
}
document.addEventListener('keydown',e=>{if(e.key==='Enter')doLogin();});
</script>
