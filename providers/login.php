<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
if(!empty($_SESSION['provider_id'])){header('Location: /providers/dashboard.php');exit;}
$noSidebar=true; $pageTitle='Provider Login';
include dirname(__DIR__). '/includes/header.php';
$csrf=Security::csrfToken();
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--bg-light)">
  <div style="width:100%;max-width:480px">
    <div class="auth-card slide-up">
      <div style="text-align:center;margin-bottom:22px">
        <div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-bottom:20px">
          <a href="/providers/doctor/login.php"    class="btn btn-ghost btn-sm"><i class="fa-solid fa-stethoscope"></i> Doctor</a>
          <a href="/providers/clinic/login.php"    class="btn btn-ghost btn-sm"><i class="fa-solid fa-house-medical"></i> Clinic</a>
          <a href="/providers/ambulance/login.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-truck-medical"></i> Ambulance</a>
        </div>
        <h2 style="font-size:22px;font-weight:900;color:var(--slate-900);margin-bottom:6px">Provider Sign In</h2>
        <p style="font-size:14px;color:var(--slate-500)">Access your provider dashboard.</p>
      </div>
      <div id="alertBox" class="alert hidden"><i class="fa-solid fa-circle-exclamation"></i><span id="alertMsg"></span></div>
      <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div class="form-group"><label class="form-label">Email</label>
        <div class="input-wrap"><i class="fa-solid fa-envelope input-ico"></i><input type="email" id="email" class="form-input has-ico" placeholder="provider@example.com" autofocus></div>
      </div>
      <div class="form-group" style="margin-bottom:24px"><label class="form-label">Password</label>
        <div class="input-wrap"><i class="fa-solid fa-lock input-ico"></i>
          <input type="password" id="password" class="form-input has-ico" placeholder="••••••••">
          <button type="button" class="eye-btn" id="ep1" onclick="togglePwd('password','ep1')"><i class="fa-solid fa-eye"></i></button>
        </div>
      </div>
      <button id="submitBtn" class="btn btn-primary btn-full btn-lg" onclick="doLogin()"><i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In</button>
      <div class="divider"><span>No account?</span></div>
      <a href="/providers/register.php" class="btn btn-ghost btn-full">Register as a Provider</a>
    </div>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
async function doLogin(){
  const r=await post('/api/provider/login.php',{csrf_token:document.getElementById('csrf').value,email:document.getElementById('email').value.trim(),password:document.getElementById('password').value},'submitBtn','alertBox');
  if(!r)return;
  if(r.success)setTimeout(()=>location.href='/providers/dashboard.php',700);
  else if(r.needs_verification){sessionStorage.setItem('pz_prov_id',r.provider_id);setTimeout(()=>location.href='/providers/verify.php',1200);}
  else UI.alert('err',r.message||'Login failed.','alertBox');
}
</script>
