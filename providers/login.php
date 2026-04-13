<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id'])) { header('Location: /providers/dashboard.php'); exit; }
$noSidebar = true; $pageTitle = 'Provider Sign In';
include dirname(__DIR__) . '/includes/header.php';
$csrf = Security::csrfToken();
?>
<style>
.auth-wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--slate-50)}
.auth-card{background:#fff;border-radius:20px;padding:40px;box-shadow:0 12px 40px rgba(0,0,0,.08);width:100%;max-width:440px}
.ptype-btn{display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9999px;border:1.5px solid var(--slate-200);background:#fff;color:var(--slate-600);font-size:.8125rem;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;transition:all .15s}
.ptype-btn:hover,.ptype-btn.active{border-color:var(--primary);background:var(--primary-10);color:var(--primary)}
</style>
<main class="auth-wrap">
  <div class="auth-card slide-up">
    <!-- Portal differentiation notice -->
    <div style="background:rgba(0,90,180,.06);border:1px solid rgba(0,90,180,.15);border-radius:12px;padding:12px 16px;margin-bottom:20px;display:flex;gap:10px;align-items:flex-start">
      <span class="material-symbols-outlined msf" style="font-size:18px;color:var(--cp-primary);flex-shrink:0;margin-top:1px">info</span>
      <div>
        <div style="font-size:.8125rem;font-weight:700;color:var(--cp-on-surface);margin-bottom:4px" data-en="Individual Provider Portal" data-sw="Lango la Mtoa Huduma Binafsi">Individual Provider Portal</div>
        <div style="font-size:.75rem;color:var(--cp-on-surface-var)" data-en="For doctors, individual clinics &amp; ambulance operators. If you are registering a Hospital or Medical Centre, please use the" data-sw="Kwa madaktari, kliniki binafsi na waendeshaji wa ambulensi. Ukiandikisha Hospitali au Kituo cha Matibabu, tafadhali tumia">
          For doctors, individual clinics &amp; ambulance operators. If you are registering a Hospital or Medical Centre, please use the
        </div>
        <a href="/hospital/onboarding/login.php" style="font-size:.75rem;font-weight:700;color:var(--cp-primary)" data-en="Hospital Portal →" data-sw="Lango la Hospitali →">Hospital Portal →</a>
      </div>
    </div>

    <!-- Provider type selector -->
    <div style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-bottom:24px">
      <a href="/providers/doctor/login.php" class="ptype-btn">
        <i class="fa-solid fa-stethoscope"></i>
        <span data-en="Doctor" data-sw="Daktari">Doctor</span>
      </a>
      <a href="/providers/clinic/login.php" class="ptype-btn">
        <i class="fa-solid fa-house-medical"></i>
        <span data-en="Clinic" data-sw="Kliniki">Clinic</span>
      </a>
      <a href="/providers/ambulance/login.php" class="ptype-btn">
        <i class="fa-solid fa-truck-medical"></i>
        <span data-en="Ambulance" data-sw="Ambulensi">Ambulance</span>
      </a>
    </div>

    <div style="text-align:center;margin-bottom:24px">
      <h2 style="font-size:1.375rem;font-weight:900;color:var(--slate-900);margin-bottom:6px;letter-spacing:-.03em"
          data-en="Provider Sign In" data-sw="Ingia kama Mtoa Huduma">Provider Sign In</h2>
      <p style="font-size:.875rem;color:var(--slate-500)"
         data-en="Access your provider dashboard." data-sw="Fikia dashibodi yako ya mtoa huduma.">Access your provider dashboard.</p>
    </div>

    <div id="alertBox" class="alert hidden"></div>
    <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div class="form-group">
      <label class="form-label" data-en="Email" data-sw="Barua Pepe">Email</label>
      <div class="input-wrap"><i class="fa-solid fa-envelope input-ico"></i>
        <input type="email" id="email" class="form-input has-ico"
          data-en-placeholder="provider@example.com" data-sw-placeholder="mtoa@mfano.com"
          placeholder="provider@example.com" autofocus autocomplete="email">
      </div>
    </div>
    <div class="form-group" style="margin-bottom:24px">
      <label class="form-label" data-en="Password" data-sw="Nenosiri">Password</label>
      <div class="input-wrap"><i class="fa-solid fa-lock input-ico"></i>
        <input type="password" id="password" class="form-input has-ico" placeholder="••••••••" autocomplete="current-password">
        <button type="button" class="eye-btn" id="ep1" onclick="togglePwd('password','ep1')"><i class="fa-solid fa-eye"></i></button>
      </div>
    </div>

    <button id="submitBtn" class="btn btn-primary btn-full btn-lg" onclick="doLogin()">
      <i class="fa-solid fa-arrow-right-to-bracket"></i>
      <span data-en="Sign In" data-sw="Ingia">Sign In</span>
    </button>
    <div class="divider"><span data-en="No account?" data-sw="Huna akaunti?">No account?</span></div>
    <a href="/providers/register.php" class="btn btn-ghost btn-full">
      <i class="fa-solid fa-user-plus"></i>
      <span data-en="Register as a Provider" data-sw="Jisajili kama Mtoa Huduma">Register as a Provider</span>
    </a>
    <p style="text-align:center;margin-top:14px;font-size:.8125rem;color:var(--slate-400)">
      <span data-en="Are you a patient?" data-sw="Je, wewe ni mgonjwa?">Are you a patient?</span>
      <a href="/patients/login.php" style="color:var(--primary);font-weight:600"
         data-en="Patient login →" data-sw="Ingia kama Mgonjwa →">Patient login →</a>
    </p>
  </div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
<script>
async function doLogin() {
  const r = await post('/api/provider/login.php', {
    csrf_token: document.getElementById('csrf').value,
    email: document.getElementById('email').value.trim(),
    password: document.getElementById('password').value
  }, 'submitBtn', 'alertBox');
  if (!r) return;
  if (r.success) setTimeout(() => location.href = '/providers/dashboard.php', 700);
  else if (r.needs_verification) {
    sessionStorage.setItem('pz_prov_id', r.provider_id);
    setTimeout(() => location.href = '/providers/verify.php', 1200);
  } else UI.alert('err', r.message || 'Login failed.', 'alertBox');
}
document.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
</script>
