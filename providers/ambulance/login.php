<?php
require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id'])) { header('Location: /providers/dashboard.php'); exit; }
$noSidebar = true; $portalType = 'ambulance'; $pageTitle = 'Ambulance Portal — Sign In';
include dirname(__DIR__, 2). '/includes/header.php';
$csrf = Security::csrfToken();
?>
<main style="flex:1;background:var(--navy);display:flex;align-items:center;justify-content:center;padding:40px 20px;min-height:calc(100vh - 64px)">
  <div style="width:100%;max-width:880px;display:grid;grid-template-columns:1fr 1fr;border-radius:24px;overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,.5)">
    <div style="background:linear-gradient(160deg,#1a0505 0%,#7f1d1d 50%,#dc2626 100%);padding:48px;position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between">
      <div style="position:absolute;inset:0;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='52'%3E%3Cpolygon points='30,2 58,17 58,35 30,50 2,35 2,17' fill='none' stroke='rgba(255,255,255,0.06)' stroke-width='1'/%3E%3C/svg%3E\");background-size:60px 52px"></div>
      <div style="position:relative;z-index:1"><span style="display:inline-flex;align-items:center;gap:6px;background:rgba(239,68,68,.25);border:1px solid rgba(239,68,68,.4);padding:5px 13px;border-radius:99px;font-size:11px;font-weight:700;color:#fca5a5;text-transform:uppercase;letter-spacing:.5px"><span class="material-symbols-outlined" style="font-size:13px">emergency</span>Emergency Services</span></div>
      <div style="position:relative;z-index:1">
        <div style="font-size:48px;margin-bottom:14px">🚑</div>
        <h2 style="font-family:var(--ff-head);font-size:clamp(20px,2.5vw,26px);font-weight:900;color:#fff;letter-spacing:-0.04em;margin-bottom:9px;line-height:1.2">24/7 Emergency Dispatch Portal</h2>
        <p style="font-size:13px;color:rgba(255,255,255,.6);line-height:1.7;margin-bottom:20px">Receive SOS alerts, dispatch units, and track fleets in real-time.</p>
        <div style="display:flex;gap:22px">
          <div><div style="font-family:var(--ff-head);font-size:24px;font-weight:900;color:#fca5a5">&lt;4min</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Avg. response</div></div>
          <div><div style="font-family:var(--ff-head);font-size:24px;font-weight:900;color:#fca5a5">24/7</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Always on</div></div>
          <div><div style="font-family:var(--ff-head);font-size:24px;font-weight:900;color:#fca5a5">GPS</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Live tracking</div></div>
        </div>
      </div>
    </div>
    <div style="background:#fff;padding:48px 44px;display:flex;flex-direction:column;justify-content:center">
      <div style="display:inline-flex;align-items:center;gap:6px;background:var(--red-l);color:var(--red);padding:4px 12px;border-radius:99px;font-size:11px;font-weight:700;border:1px solid var(--red-b);margin-bottom:16px;text-transform:uppercase;letter-spacing:.4px"><span class="material-symbols-outlined" style="font-size:13px">ambulance</span>Ambulance Portal</div>
      <h1 style="font-family:var(--ff-head);font-size:24px;font-weight:900;color:var(--navy);letter-spacing:-0.04em;margin-bottom:6px">Dispatcher Sign In</h1>
      <p style="font-size:13px;color:var(--muted);margin-bottom:22px">Access the emergency coordination dashboard.</p>
      <div id="alertBox" class="alert hidden"><span class="material-symbols-outlined">error</span><span id="alertMsg"></span></div>
      <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div class="form-group">
        <label class="form-label">Dispatch Email</label>
        <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">mail</span></span>
        <input type="email" id="email" class="form-input has-ico" placeholder="dispatch@service.co.ke" autofocus></div>
      </div>
      <div class="form-group" style="margin-bottom:22px">
        <label class="form-label">Password</label>
        <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">lock</span></span>
        <input type="password" id="password" class="form-input has-ico" placeholder="••••••••">
        <button type="button" class="eye-btn" id="eye1" onclick="togglePwd('password','eye1')"><span class="material-symbols-outlined">visibility</span></button></div>
      </div>
      <button id="submitBtn" class="btn btn-full btn-lg" style="background:var(--red);color:#fff" onclick="doProviderLogin()">
        <span class="material-symbols-outlined">emergency</span> Sign In — Emergency Portal
      </button>
      <div class="divider" style="margin:20px 0"><span>Register new service?</span></div>
      <a href="/providers/ambulance/register.php" class="btn btn-ghost btn-full">Register Ambulance Service</a>
      <p style="text-align:center;margin-top:14px;font-size:13px;color:var(--muted)"><a href="/patients/login.php" style="color:var(--muted)">&larr; Patient login</a></p>
    </div>
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
