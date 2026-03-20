<?php
require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id'])) { header('Location: /providers/dashboard.php'); exit; }
$noSidebar = true; $portalType = 'doctor'; $pageTitle = 'Doctor Portal — Sign In';
include dirname(__DIR__, 2). '/includes/header.php';
$csrf = Security::csrfToken();
?>
<main style="flex:1;display:flex;align-items:stretch;min-height:calc(100vh - 64px)">
  <div style="flex:1;background:linear-gradient(160deg,#0f172a 0%,#0e7490 55%,#0d9488 100%);display:flex;flex-direction:column;justify-content:flex-end;padding:52px;position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;background:url('https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=800&q=70') center/cover;opacity:.13"></div>
    <div style="position:absolute;inset:0;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='70' height='60'%3E%3Cpolygon points='35,3 67,20 67,40 35,57 3,40 3,20' fill='none' stroke='rgba(255,255,255,0.06)' stroke-width='1'/%3E%3C/svg%3E\");background-size:70px 60px"></div>
    <div style="position:absolute;top:48px;left:48px;z-index:1"><span style="display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);padding:5px 13px;border-radius:99px;font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.5px"><span class="material-symbols-outlined" style="font-size:13px">stethoscope</span> Doctor Portal</span></div>
    <div style="position:relative;z-index:1">
      <div style="width:52px;height:52px;border-radius:13px;background:rgba(14,116,144,.35);border:1px solid rgba(94,234,212,.3);display:flex;align-items:center;justify-content:center;margin-bottom:16px"><span class="material-symbols-outlined" style="font-size:26px;color:#5eead4">stethoscope</span></div>
      <h2 style="font-family:var(--ff-head);font-size:clamp(20px,3vw,30px);font-weight:900;color:#fff;letter-spacing:-0.04em;margin-bottom:9px;line-height:1.2">Manage Your Practice with Planeazzy</h2>
      <p style="font-size:13px;color:rgba(255,255,255,.6);line-height:1.75;margin-bottom:18px">Streamline appointments, consult patients via video, and deliver better care from one portal.</p>
      <?php foreach(['Real-time appointment scheduling','Patient health record access','Telehealth video consultations','H3 location-based patient matching'] as $f): ?>
      <div style="display:flex;align-items:center;gap:7px;font-size:13px;color:rgba(255,255,255,.72);margin-bottom:7px"><span class="material-symbols-outlined" style="font-size:15px;color:#5eead4;flex-shrink:0">check_circle</span><?=htmlspecialchars($f)?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div style="width:460px;flex-shrink:0;background:var(--white);display:flex;flex-direction:column;justify-content:center;padding:52px 44px;overflow-y:auto">
    <div style="display:inline-flex;align-items:center;gap:6px;background:var(--teal-l);color:var(--teal);padding:4px 12px;border-radius:99px;font-size:11px;font-weight:700;border:1px solid var(--teal-b);margin-bottom:16px;text-transform:uppercase;letter-spacing:.4px"><span class="material-symbols-outlined" style="font-size:13px">stethoscope</span> Doctor Portal</div>
    <h1 style="font-family:var(--ff-head);font-size:26px;font-weight:900;color:var(--navy);letter-spacing:-0.04em;margin-bottom:6px">Welcome back, Doctor</h1>
    <p style="font-size:13px;color:var(--muted);margin-bottom:22px">Sign in to your provider dashboard.</p>
    <div id="alertBox" class="alert hidden"><span class="material-symbols-outlined">error</span><span id="alertMsg"></span></div>
    <input type="hidden" id="csrf" value="<?=htmlspecialchars($csrf)?>">
    <div class="form-group">
      <label class="form-label">Email Address</label>
      <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">mail</span></span>
      <input type="email" id="email" class="form-input has-ico" placeholder="doctor@hospital.co.ke" autofocus></div>
    </div>
    <div class="form-group" style="margin-bottom:22px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px"><label class="form-label" style="margin-bottom:0">Password</label><a href="#" style="font-size:12px;font-weight:600;color:var(--teal)">Forgot password?</a></div>
      <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">lock</span></span>
      <input type="password" id="password" class="form-input has-ico" placeholder="••••••••">
      <button type="button" class="eye-btn" id="eye1" onclick="togglePwd('password','eye1')"><span class="material-symbols-outlined">visibility</span></button></div>
    </div>
    <button id="submitBtn" class="btn btn-full btn-lg" style="background:var(--teal);color:#fff" onclick="doProviderLogin()"><span class="material-symbols-outlined">login</span> Sign In to Doctor Portal</button>
    <div class="divider" style="margin:20px 0"><span>New to Planeazzy?</span></div>
    <a href="/providers/doctor/register.php" class="btn btn-ghost btn-full">Register as a Doctor</a>
    <div class="sec-notice"><div class="sec-inner"><span class="material-symbols-outlined">verified_user</span>All provider sessions are encrypted and HIPAA-compliant.</div></div>
    <p style="text-align:center;margin-top:14px;font-size:13px;color:var(--muted)"><a href="/patients/login.php" style="color:var(--muted)">&larr; Patient login</a></p>
  </div>
</main>
<?php include dirname(__DIR__, 2). '/includes/footer.php'; ?>
<script>
async function doProviderLogin(){
  const r=await post('/api/provider/login.php',{csrf_token:document.getElementById('csrf').value,email:document.getElementById('email').value.trim(),password:document.getElementById('password').value},'submitBtn','alertBox');
  if(!r)return;
  if(r.success){setTimeout(()=>location.href='/providers/dashboard.php',800);}
  else if(r.needs_verification){sessionStorage.setItem('pz_prov_id',r.provider_id);setTimeout(()=>location.href='/providers/verify.php',1400);}
  document.getElementById('alertBox').className='alert alert-'+(r.success?'ok':(r.needs_verification?'info':'err'));
  document.getElementById('alertMsg').textContent=r.success?'Redirecting…':r.message||'Login failed.';
  document.getElementById('alertBox').classList.remove('hidden');
}
</script>
