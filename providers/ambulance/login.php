<?php
require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
Security::startSession();
if(!empty($_SESSION['provider_id'])){header('Location: /providers/dashboard.php');exit;}
$noSidebar=true; $pageTitle='Ambulance Portal — Sign In';
include dirname(__DIR__, 2). '/includes/header.php';
$csrf=Security::csrfToken();
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--bg-light)">
  <div class="split-layout slide-up">
    <div class="split-left" style="background:linear-gradient(160deg,#1a0505,#7f1d1d,#dc2626)">
      <div class="split-left-hex"></div>
      <div class="split-left-content">
        <span class="portal-badge pb-red" style="margin-bottom:16px"><i class="fa-solid fa-truck-medical"></i> Ambulance Portal</span>
        <h2>Manage Your Practice with Planeazzy</h2>
        <p>Streamline appointments, connect with patients, and deliver better care from one dashboard.</p>
        <div class="split-left-features">
          <div class="split-feature"><i class="fa-solid fa-check-circle"></i> Real-time appointment scheduling</div>
          <div class="split-feature"><i class="fa-solid fa-check-circle"></i> Patient health record access</div>
          <div class="split-feature"><i class="fa-solid fa-check-circle"></i> Telehealth video consultations</div>
          <div class="split-feature"><i class="fa-solid fa-check-circle"></i> Location-based patient matching</div>
        </div>
      </div>
    </div>
    <div class="split-right">
      <div style="max-width:380px;width:100%;margin:0 auto">
        <span class="portal-badge pb-red"><i class="fa-solid fa-truck-medical"></i> Ambulance Portal</span>
        <h2 style="font-size:24px;font-weight:900;color:var(--slate-900);margin-bottom:6px;letter-spacing:-.03em" data-en="Welcome back" data-sw="Karibu tena">Welcome back</h2>
        <p style="font-size:14px;color:var(--slate-500);margin-bottom:24px" data-en="Sign in to your provider dashboard." data-sw="Ingia kwenye dashibodi yako ya mtoa huduma.">Sign in to your provider dashboard.</p>
        <div id="alertBox" class="alert hidden"><i class="fa-solid fa-circle-exclamation"></i><span id="alertMsg"></span></div>
        <input type="hidden" id="csrf" value="<?=htmlspecialchars($csrf)?>">
        <div class="form-group"><label class="form-label"><span data-en="Email Address" data-sw="Barua Pepe">Email Address</span></label>
          <div class="input-wrap"><i class="fa-solid fa-envelope input-ico"></i><input type="email" id="email" class="form-input has-ico" data-en-placeholder="provider@example.com" data-sw-placeholder="mtoa@mfano.com" placeholder="provider@example.com" autofocus></div>
        </div>
        <div class="form-group" style="margin-bottom:24px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <label class="form-label" style="margin-bottom:0"><span data-en="Password" data-sw="Nenosiri">Password</span></label>
            <a href="#" style="font-size:12px;font-weight:600;color:#dc2626"><span data-en="Forgot password?" data-sw="Umesahau nenosiri?">Forgot password?</span></a>
          </div>
          <div class="input-wrap"><i class="fa-solid fa-lock input-ico"></i>
            <input type="password" id="password" class="form-input has-ico" placeholder="••••••••">
            <button type="button" class="eye-btn" id="ep1" onclick="togglePwd('password','ep1')"><i class="fa-solid fa-eye"></i></button>
          </div>
        </div>
        <button id="submitBtn" class="btn btn-primary btn-full btn-lg" style="background:#dc2626" onclick="doProviderLogin()"><i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In to Ambulance Portal</button>
        <div class="divider"><span><span data-en="New to Planeazzy?" data-sw="Mpya kwa Planeazzy?">New to Planeazzy?</span></div>
        <a href="/providers/ambulance/register.php" class="btn btn-ghost btn-full"><i class="fa-solid fa-user-plus"></i> Register as Ambulance Portal</a>
        <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--slate-400)"><a href="/patients/login.php" style="color:var(--slate-500)">← Patient login</a></p>
      </div>
    </div>
  </div>
</main>
<?php include dirname(__DIR__, 2). '/includes/footer.php'; ?>
<script>
async function doProviderLogin(){
  const r=await post('/api/provider/login.php',{csrf_token:document.getElementById('csrf').value,email:document.getElementById('email').value.trim(),password:document.getElementById('password').value},'submitBtn','alertBox');
  if(!r)return;
  if(r.success){UI.alert('ok','Login successful! Redirecting…','alertBox');setTimeout(()=>location.href='/providers/dashboard.php',800);}
  else if(r.needs_verification){sessionStorage.setItem('pz_prov_id',r.provider_id);UI.alert('info',r.message,'alertBox');setTimeout(()=>location.href='/providers/verify.php',1400);}
  else UI.alert('err',r.message||'Login failed.','alertBox');
}
document.addEventListener('keydown',e=>{if(e.key==='Enter')doProviderLogin();});
</script>
