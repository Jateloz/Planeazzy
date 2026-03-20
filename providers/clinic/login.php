<?php
require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id'])) { header('Location: /providers/dashboard.php'); exit; }
$noSidebar = true; $portalType = 'clinic'; $pageTitle = 'Clinic Portal — Sign In';
include dirname(__DIR__, 2). '/includes/header.php';
$csrf = Security::csrfToken();
?>
<main style="flex:1;display:flex;align-items:stretch;min-height:calc(100vh - 64px)">
  <!-- Form left -->
  <div style="width:460px;flex-shrink:0;background:var(--white);display:flex;flex-direction:column;justify-content:center;padding:52px 44px;overflow-y:auto">
    <div style="display:inline-flex;align-items:center;gap:6px;background:var(--green-l);color:var(--green);padding:4px 12px;border-radius:99px;font-size:11px;font-weight:700;border:1px solid var(--green-b);margin-bottom:16px;text-transform:uppercase;letter-spacing:.4px">
      <span class="material-symbols-outlined" style="font-size:13px">local_pharmacy</span> Clinic Portal
    </div>
    <h1 style="font-family:var(--ff-head);font-size:26px;font-weight:900;color:var(--navy);letter-spacing:-0.04em;margin-bottom:6px">Clinic Sign In</h1>
    <p style="font-size:13px;color:var(--muted);margin-bottom:22px">Manage appointments, staff and patient care.</p>
    <div id="alertBox" class="alert hidden"><span class="material-symbols-outlined">error</span><span id="alertMsg"></span></div>
    <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="form-group">
      <label class="form-label">Clinic Email</label>
      <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">mail</span></span>
      <input type="email" id="email" class="form-input has-ico" placeholder="info@clinic.co.ke" autofocus></div>
    </div>
    <div class="form-group" style="margin-bottom:22px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px"><label class="form-label" style="margin-bottom:0">Password</label><a href="#" style="font-size:12px;font-weight:600;color:var(--green)">Forgot?</a></div>
      <div class="input-wrap"><span class="input-ico"><span class="material-symbols-outlined">lock</span></span>
      <input type="password" id="password" class="form-input has-ico" placeholder="••••••••">
      <button type="button" class="eye-btn" id="eye1" onclick="togglePwd('password','eye1')"><span class="material-symbols-outlined">visibility</span></button></div>
    </div>
    <button id="submitBtn" class="btn btn-full btn-lg" style="background:var(--green);color:#fff" onclick="doProviderLogin()">
      <span class="material-symbols-outlined">login</span> Sign In to Clinic Portal
    </button>
    <div class="divider" style="margin:20px 0"><span>New clinic?</span></div>
    <a href="/providers/clinic/register.php" class="btn btn-ghost btn-full">Register Your Clinic</a>
    <p style="text-align:center;margin-top:14px;font-size:13px;color:var(--muted)"><a href="/patients/login.php" style="color:var(--muted)">&larr; Patient login</a></p>
  </div>
  <!-- Visual right -->
  <div style="flex:1;background:linear-gradient(160deg,#064e3b 0%,#059669 55%,#10b981 100%);display:flex;flex-direction:column;justify-content:flex-end;padding:52px;position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;background:url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=800&q=70') center/cover;opacity:.13"></div>
    <div style="position:relative;z-index:1">
      <div style="width:52px;height:52px;border-radius:13px;background:rgba(16,185,129,.3);border:1px solid rgba(110,231,183,.3);display:flex;align-items:center;justify-content:center;margin-bottom:16px"><span class="material-symbols-outlined" style="font-size:26px;color:#6ee7b7">local_pharmacy</span></div>
      <h2 style="font-family:var(--ff-head);font-size:clamp(20px,2.5vw,28px);font-weight:900;color:#fff;letter-spacing:-0.04em;margin-bottom:9px;line-height:1.2">Your Clinic, Fully Connected</h2>
      <p style="font-size:13px;color:rgba(255,255,255,.6);line-height:1.75;margin-bottom:18px">Manage bookings, patient flow, and health records from a single dashboard.</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <?php foreach(['Appointment management','Patient flow tracking','Digital health records','Billing & invoicing','Staff scheduling','Inventory management'] as $f): ?>
        <div style="background:rgba(255,255,255,.1);border-radius:9px;padding:11px 13px;border:1px solid rgba(255,255,255,.1)"><div style="font-size:12px;font-weight:700;color:#fff"><?=htmlspecialchars($f)?></div></div>
        <?php endforeach; ?>
      </div>
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
