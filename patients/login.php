<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true;
$pageTitle  = 'Patient Sign In';
$timeout    = ($_GET['reason'] ?? '') === 'timeout';
include dirname(__DIR__) . '/includes/header.php';
$csrf = Security::csrfToken();
?>
<style>
.auth-wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--slate-50)}
.auth-split{width:100%;max-width:960px;display:grid;grid-template-columns:1fr 1fr;border-radius:24px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.12)}
.auth-left{background:linear-gradient(160deg,#0f172a 0%,#1462c4 55%,#0d9488 100%);padding:56px 48px;display:flex;flex-direction:column;justify-content:center;position:relative;overflow:hidden}
.auth-left-bg{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.12;mix-blend-mode:luminosity}
.auth-left h2{font-size:clamp(1.5rem,3vw,2rem);font-weight:900;color:#fff;letter-spacing:-.04em;margin-bottom:14px;position:relative;z-index:1}
.auth-left p{font-size:.9375rem;color:rgba(255,255,255,.75);line-height:1.75;margin-bottom:28px;position:relative;z-index:1;max-width:340px}
.auth-feat{display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:.875rem;color:rgba(255,255,255,.85);position:relative;z-index:1}
.auth-feat i{color:#34d399;font-size:14px;flex-shrink:0}
.auth-right{background:#fff;padding:52px 48px;display:flex;align-items:center}
.auth-form{width:100%;max-width:360px;margin:0 auto}
@media(max-width:768px){.auth-split{grid-template-columns:1fr}.auth-left{display:none}.auth-right{padding:40px 24px}}
</style>
<main class="auth-wrap">
  <div class="auth-split">
    <div class="auth-left">
      <div class="auth-left-bg" style="background-image:url('https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=700&q=80')"></div>
      <span class="portal-badge pb-blue" style="position:relative;z-index:1;margin-bottom:20px;display:inline-flex;align-items:center;gap:6px">
        <i class="fa-solid fa-user"></i>
        <span data-en="Patient Portal" data-sw="Lango la Mgonjwa">Patient Portal</span>
      </span>
      <h2 data-en="Your Direct Path to Better Healthcare" data-sw="Njia Yako ya Moja kwa Moja kwa Huduma Bora ya Afya">Your Direct Path to Better Healthcare</h2>
      <p data-en="Access your appointments, find doctors, and connect with healthcare providers across Kenya." data-sw="Fikia miadi yako, tafuta madaktari, na unganika na watoa huduma wa afya nchini Kenya.">Access your appointments, find doctors, and connect with healthcare providers across Kenya.</p>
      <?php foreach([
        ['fa-check-circle','KDPA &amp; KMPDC Compliant','Inazingatia KDPA &amp; KMPDC'],
        ['fa-check-circle','Book appointments in under 2 minutes','Weka miadi kwa chini ya dakika 2'],
        ['fa-check-circle','24/7 emergency ambulance dispatch','Kutuma ambulensi ya dharura 24/7'],
        ['fa-check-circle','HD telehealth video consultations','Mashauriano ya video ya HD ya telemedicine'],
      ] as [$ic,$en,$sw]): ?>
      <div class="auth-feat"><i class="fa-solid <?=$ic?>"></i><span data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></span></div>
      <?php endforeach; ?>
    </div>
    <div class="auth-right">
      <div class="auth-form">
        <div style="margin-bottom:8px">
          <a href="/" style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;color:var(--slate-500);text-decoration:none;padding:5px 0;transition:color .15s" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--slate-500)'">
            <i class="fa-solid fa-arrow-left" style="font-size:11px"></i> Back to Homepage
          </a>
        </div>
        <div style="margin-bottom:28px">
          <span class="portal-badge pb-blue" style="margin-bottom:14px;display:inline-flex;align-items:center;gap:6px">
            <i class="fa-solid fa-user"></i>
            <span data-en="Patient Portal" data-sw="Lango la Mgonjwa">Patient Portal</span>
          </span>
          <h2 style="font-size:1.625rem;font-weight:900;color:var(--slate-900);margin-bottom:6px;letter-spacing:-.03em"
              data-en="Welcome back" data-sw="Karibu tena">Welcome back</h2>
          <p style="font-size:.875rem;color:var(--slate-500)">
            <span data-en="Sign in to your Planeazzy health dashboard." data-sw="Ingia kwenye dashibodi yako ya afya ya Planeazzy.">Sign in to your Planeazzy health dashboard.</span>
          </p>
        </div>

        <?php if ($timeout): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i>
          <span data-en="Session expired. Please sign in again." data-sw="Kikao kimeisha. Tafadhali ingia tena.">Session expired. Please sign in again.</span>
        </div>
        <?php endif; ?>

        <div id="alertBox" class="alert hidden"></div>
        <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="form-group">
          <label class="form-label" data-en="Email Address" data-sw="Barua Pepe">Email Address</label>
          <div class="input-wrap">
            <i class="fa-solid fa-envelope input-ico"></i>
            <input type="email" id="email" class="form-input has-ico"
              data-en-placeholder="you@example.com" data-sw-placeholder="wewe@mfano.com"
              placeholder="you@example.com" autocomplete="email" required autofocus>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:24px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <label class="form-label" style="margin-bottom:0" data-en="Password" data-sw="Nenosiri">Password</label>
            <a href="#" style="font-size:.75rem;font-weight:600;color:var(--primary)"
               data-en="Forgot password?" data-sw="Umesahau nenosiri?">Forgot password?</a>
          </div>
          <div class="input-wrap">
            <i class="fa-solid fa-lock input-ico"></i>
            <input type="password" id="password" class="form-input has-ico"
              data-en-placeholder="••••••••" data-sw-placeholder="••••••••"
              placeholder="••••••••" autocomplete="current-password" required>
            <button type="button" class="eye-btn" id="ep1" onclick="togglePwd('password','ep1')"><i class="fa-solid fa-eye"></i></button>
          </div>
        </div>

        <button id="submitBtn" class="btn btn-primary btn-full btn-lg" onclick="doLogin()">
          <i class="fa-solid fa-arrow-right-to-bracket"></i>
          <span data-en="Sign In" data-sw="Ingia">Sign In</span>
        </button>

        <div class="divider"><span data-en="New to Planeazzy?" data-sw="Mpya kwa Planeazzy?">New to Planeazzy?</span></div>
        <a href="/patients/register.php" class="btn btn-ghost btn-full">
          <i class="fa-solid fa-user-plus"></i>
          <span data-en="Create Free Account" data-sw="Unda Akaunti ya Bure">Create Free Account</span>
        </a>

        <div style="margin-top:18px;padding:12px;border-radius:8px;background:var(--primary-10);border:1px solid var(--primary-20);display:flex;align-items:flex-start;gap:10px;font-size:.75rem;color:var(--slate-600);line-height:1.6">
          <i class="fa-solid fa-shield-halved" style="color:var(--primary);margin-top:1px;flex-shrink:0"></i>
          <span data-en="Your session is protected with end-to-end encryption." data-sw="Kikao chako kinalindwa na usimbuaji wa mwisho hadi mwisho.">Your session is protected with end-to-end encryption.</span>
        </div>


      </div>
    </div>
  </div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
<script>
async function doLogin(){
  const r=await post('/api/auth/login.php',{
    csrf_token:document.getElementById('csrf').value,
    email:document.getElementById('email').value.trim(),
    password:document.getElementById('password').value
  },'submitBtn','alertBox');
  if(!r)return;
  if(r.success){
    UI.alert('ok', document.documentElement.lang==='sw'?'Umeingia! Inaelekea…':'Login successful! Redirecting…','alertBox');
    const next=new URLSearchParams(window.location.search).get('next');
    setTimeout(()=>location.href=next&&next.startsWith('/')?next:'/patients/dashboard.php',800);
  } else if(r.needs_verification){
    sessionStorage.setItem('pz_pat_id',r.patient_id);
    UI.alert('info', document.documentElement.lang==='sw'?'Tafadhali thibitisha barua pepe yako…':'Please verify your email…','alertBox');
    setTimeout(()=>location.href='/patients/verify-email.php',1400);
  } else {
    UI.alert('err',r.message||'Login failed. Check your credentials.','alertBox');
  }
}
document.addEventListener('keydown',e=>{if(e.key==='Enter')doLogin();});
</script>
