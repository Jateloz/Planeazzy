<?php
/**
 * Clinical Precision — Hospital / Clinic Partner Login
 * /hospital/onboarding/login.php
 * Handles: hospital_providers table (NOT the same as providers table)
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();

if (!empty($_SESSION['hospital_id']) && !empty($_SESSION['hospital_auth'])) {
    header('Location: /hospital/onboarding/dashboard.php'); exit;
}

$error  = '';
$csrf   = Security::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = trim($_POST['csrf_token'] ?? '');
    $email    = Security::cleanEmail($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (!Security::verifyCsrf($token)) {
        $error = 'Security token invalid. Please refresh.';
    } elseif (!$email || !$password) {
        $error = 'Email and password are required.';
    } else {
        try {
            $db   = Database::getInstance();
            $hosp = $db->fetchOne(
                'SELECT * FROM hospital_providers WHERE admin_email=:e',
                [':e' => $email]
            );

            // Rate limit
            $attempts = $db->fetchOne(
                'SELECT COUNT(*) c FROM hospital_login_attempts
                 WHERE ip_address=:ip AND attempted_at > DATE_SUB(NOW(),INTERVAL 15 MINUTE)',
                [':ip' => $ip]
            );
            if (($attempts['c'] ?? 0) >= MAX_LOGIN_ATTEMPTS) {
                $error = 'Too many login attempts. Please wait 15 minutes.';
            } elseif (!$hosp || !password_verify($password, $hosp['password_hash'])) {
                $db->query(
                    'INSERT INTO hospital_login_attempts (email,ip_address,attempted_at) VALUES (:e,:ip,NOW())',
                    [':e'=>$email,':ip'=>$ip]
                );
                $error = 'Incorrect email or password.';
            } else {
                // Update last login
                $db->query('UPDATE hospital_providers SET last_login=NOW() WHERE id=:id', [':id'=>$hosp['id']]);

                // Set session
                $_SESSION['hospital_id']    = $hosp['id'];
                $_SESSION['hospital_name']  = $hosp['admin_name'];
                $_SESSION['hospital_email'] = $hosp['admin_email'];
                $_SESSION['hospital_type']  = $hosp['facility_type'];
                $_SESSION['hospital_step']  = $hosp['onboarding_step'];

                // Route by status
                if (!$hosp['email_verified']) {
                    header('Location: /hospital/onboarding/verify-email.php'); exit;
                }
                if ($hosp['status'] === 'approved' && $hosp['is_active']) {
                    $_SESSION['hospital_auth'] = true;
                    header('Location: /hospital/onboarding/dashboard.php'); exit;
                }
                if ($hosp['onboarding_step'] < 3) {
                    header('Location: /hospital/onboarding/select-type.php'); exit;
                }
                if ($hosp['onboarding_step'] < 5) {
                    header('Location: /hospital/onboarding/profile.php'); exit;
                }
                if ($hosp['onboarding_step'] < 6) {
                    header('Location: /hospital/onboarding/departments.php'); exit;
                }
                if ($hosp['onboarding_step'] < 7) {
                    header('Location: /hospital/onboarding/regulatory.php'); exit;
                }
                header('Location: /hospital/onboarding/pending.php'); exit;
            }
        } catch (Exception $e) {
            error_log('Hospital login: ' . $e->getMessage());
            $error = 'Server error. Please try again.';
        }
    }
}

$cpStep  = 0;
$cpTitle = 'Sign In — Clinical Precision';
include __DIR__ . '/_head.php';
?>
<style>
.hl-wrap   { min-height:100vh;display:grid;grid-template-columns:1fr 1fr; }
.hl-left   { background:linear-gradient(155deg,#005ab4 0%,#0873df 60%,#0d9488 100%);padding:64px;display:flex;flex-direction:column;justify-content:center;position:relative;overflow:hidden; }
.hl-right  { padding:64px 72px;display:flex;align-items:center;justify-content:center;background:var(--cp-surface-container-lowest); }
.hl-card   { width:100%;max-width:400px; }
.hl-feat   { display:flex;align-items:center;gap:12px;margin-bottom:18px; }
.hl-feat-ic{ width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
@media(max-width:900px){
  .hl-wrap{grid-template-columns:1fr}
  .hl-left{display:none}
  .hl-right{padding:40px 24px}
}
</style>

<div class="hl-wrap">
  <!-- Left branding panel -->
  <div class="hl-left">
    <div style="position:absolute;top:-60px;right:-60px;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,.05)"></div>
    <div style="position:absolute;bottom:-80px;left:-80px;width:340px;height:340px;border-radius:50%;background:rgba(255,255,255,.04)"></div>
    <div style="position:relative;z-index:1">
      <h1 style="font-size:1.875rem;font-weight:900;color:#fff;letter-spacing:-.04em;margin-bottom:16px"
          data-en="Clinical Precision" data-sw="Usahihi wa Kliniki">Clinical Precision</h1>
      <p style="font-size:1.0625rem;color:rgba(255,255,255,.8);margin-bottom:40px;line-height:1.7"
         data-en="The Digital Sanctuary for Kenyan Healthcare. Manage your facility with surgical efficiency."
         data-sw="Hifadhi ya Kidijitali kwa Afya ya Kenya. Simamia kituo chako kwa ufanisi wa kisayansi.">
        The Digital Sanctuary for Kenyan Healthcare. Manage your facility with surgical efficiency.
      </p>
      <?php foreach([
        ['verified_user','KEPDA &amp; KMPDC Compliance','Utiifu wa KEPDA &amp; KMPDC'],
        ['lock','AES-256 Data Encryption','Usimbuaji wa Data wa AES-256'],
        ['calendar_month','Real-time Booking Management','Usimamizi wa Miadi wa Wakati Halisi'],
        ['analytics','Full Analytics Dashboard','Dashibodi Kamili ya Takwimu'],
      ] as [$ic,$en,$sw]): ?>
      <div class="hl-feat">
        <div class="hl-feat-ic">
          <span class="material-symbols-outlined msf" style="font-size:18px;color:rgba(255,255,255,.85)"><?=$ic?></span>
        </div>
        <span style="font-size:.9375rem;color:rgba(255,255,255,.85)" data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></span>
      </div>
      <?php endforeach; ?>

      <!-- Differentiate from provider login -->
      <div style="margin-top:40px;padding:18px;background:rgba(255,255,255,.1);border-radius:14px;border:1px solid rgba(255,255,255,.18)">
        <p style="font-size:.8125rem;color:rgba(255,255,255,.7);margin-bottom:10px;font-weight:600;text-transform:uppercase;letter-spacing:.08em"
           data-en="This portal is for:" data-sw="Lango hili ni kwa:">This portal is for:</p>
        <?php foreach([
          ['Hospitals &amp; Medical Centres','Hospitali na Vituo vya Matibabu'],
          ['Clinics &amp; Diagnostic Centres','Kliniki na Vituo vya Uchunguzi'],
          ['Ambulance Service Providers','Watoa Huduma za Ambulensi'],
        ] as [$en,$sw]): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <span class="material-symbols-outlined msf" style="font-size:14px;color:rgba(144,239,239,.9)">check_circle</span>
          <span style="font-size:.8125rem;color:rgba(255,255,255,.85)" data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></span>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.15)">
          <p style="font-size:.75rem;color:rgba(255,255,255,.6);margin-bottom:6px"
             data-en="Are you a Doctor or Individual Provider?" data-sw="Je, wewe ni Daktari au Mtoa Huduma Binafsi?">
            Are you a Doctor or Individual Provider?
          </p>
          <a href="/providers/login.php"
             style="font-size:.8125rem;font-weight:700;color:rgba(144,239,239,.9);text-decoration:underline"
             data-en="Go to Provider Login →" data-sw="Nenda kwa Kuingia kwa Mtoa Huduma →">
            Go to Provider Login →
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: login form -->
  <div class="hl-right">
    <div class="hl-card">
      <!-- Brand mobile -->
      <a href="/hospital/onboarding/join.php"
         style="font-size:1.0625rem;font-weight:900;color:var(--cp-primary);text-decoration:none;display:block;margin-bottom:36px;letter-spacing:-.03em"
         data-en="Clinical Precision" data-sw="Usahihi wa Kliniki">Clinical Precision</a>

      <h2 style="font-size:1.625rem;font-weight:900;margin-bottom:6px;letter-spacing:-.03em;color:var(--cp-on-surface)"
          data-en="Welcome back" data-sw="Karibu tena">Welcome back</h2>
      <p style="font-size:.9375rem;color:var(--cp-on-surface-var);margin-bottom:28px">
        <span data-en="Sign in to your Clinical Precision hospital dashboard." data-sw="Ingia kwenye dashibodi yako ya hospitali ya Clinical Precision.">
          Sign in to your Clinical Precision hospital dashboard.
        </span>
      </p>

      <!-- Portal badge -->
      <div style="display:inline-flex;align-items:center;gap:7px;background:rgba(0,106,106,.1);padding:5px 14px;border-radius:9999px;margin-bottom:24px">
        <span class="material-symbols-outlined msf" style="font-size:14px;color:var(--cp-secondary)">local_hospital</span>
        <span style="font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--cp-secondary)"
              data-en="Hospital &amp; Clinic Portal" data-sw="Lango la Hospitali &amp; Kliniki">Hospital &amp; Clinic Portal</span>
      </div>

      <?php if ($error): ?>
      <div class="cp-alert cp-alert-error" style="margin-bottom:20px">
        <span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0">error</span>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <div style="margin-bottom:16px">
          <label class="cp-form-label" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--cp-on-surface-var);display:block;margin-bottom:6px"
                 data-en="Admin Email" data-sw="Barua Pepe ya Msimamizi">Admin Email</label>
          <div style="position:relative">
            <span class="material-symbols-outlined" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--cp-outline);font-size:18px;pointer-events:none">mail</span>
            <input class="cp-form-input" type="email" name="email"
              style="padding-left:42px;width:100%;padding-top:13px;padding-bottom:13px;background:var(--cp-surface-container-highest);border:2px solid transparent;border-radius:var(--cp-r);font-family:inherit;font-size:.9375rem;outline:none;transition:all .2s"
              data-en-placeholder="admin@yourhospital.co.ke"
              data-sw-placeholder="msimamizi@hospitali.co.ke"
              placeholder="admin@yourhospital.co.ke"
              autocomplete="email" required autofocus
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div style="margin-bottom:24px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <label class="cp-form-label" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--cp-on-surface-var)"
                   data-en="Password" data-sw="Nenosiri">Password</label>
            <a href="#" style="font-size:.75rem;font-weight:600;color:var(--cp-primary)"
               data-en="Forgot password?" data-sw="Umesahau nenosiri?">Forgot password?</a>
          </div>
          <div style="position:relative">
            <span class="material-symbols-outlined" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--cp-outline);font-size:18px;pointer-events:none">lock</span>
            <input class="cp-form-input" type="password" name="password" id="pwField"
              style="padding-left:42px;width:100%;padding-top:13px;padding-bottom:13px;background:var(--cp-surface-container-highest);border:2px solid transparent;border-radius:var(--cp-r);font-family:inherit;font-size:.9375rem;outline:none;transition:all .2s;padding-right:44px"
              placeholder="••••••••" autocomplete="current-password" required>
            <button type="button" onclick="togglePw()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:var(--cp-outline)" id="pwEye">
              <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
            </button>
          </div>
        </div>

        <button type="submit" class="cp-btn cp-btn-primary cp-btn-full" style="padding:15px">
          <span class="material-symbols-outlined">login</span>
          <span data-en="Sign In to Dashboard" data-sw="Ingia kwenye Dashibodi">Sign In to Dashboard</span>
        </button>
      </form>

      <div style="text-align:center;margin-top:20px">
        <p style="font-size:.875rem;color:var(--cp-on-surface-var)">
          <span data-en="Don't have an account?" data-sw="Huna akaunti?">Don't have an account?</span>
          <a href="/hospital/onboarding/signup.php" style="color:var(--cp-primary);font-weight:700;margin-left:4px"
             data-en="Register your facility →" data-sw="Sajili kituo chako →">Register your facility →</a>
        </p>
      </div>

      <!-- Separator -->
      <div style="display:flex;align-items:center;gap:12px;margin:20px 0">
        <div style="flex:1;height:1px;background:rgba(193,198,213,.3)"></div>
        <span style="font-size:.75rem;color:var(--cp-outline)" data-en="or" data-sw="au">or</span>
        <div style="flex:1;height:1px;background:rgba(193,198,213,.3)"></div>
      </div>

      <!-- Provider login link -->
      <div style="background:var(--cp-surface-container-low);border-radius:var(--cp-r);padding:14px 16px;border:1px solid rgba(193,198,213,.2)">
        <p style="font-size:.8125rem;color:var(--cp-on-surface-var);margin-bottom:8px"
           data-en="Looking for a different portal?" data-sw="Unatafuta lango tofauti?">Looking for a different portal?</p>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <a href="/patients/login.php" style="display:inline-flex;align-items:center;gap:5px;font-size:.8125rem;font-weight:600;color:var(--cp-primary);padding:6px 12px;border-radius:8px;background:rgba(0,90,180,.08);text-decoration:none">
            <span class="material-symbols-outlined" style="font-size:15px">person</span>
            <span data-en="Patient Login" data-sw="Ingia kama Mgonjwa">Patient Login</span>
          </a>
          <a href="/providers/login.php" style="display:inline-flex;align-items:center;gap:5px;font-size:.8125rem;font-weight:600;color:var(--cp-secondary);padding:6px 12px;border-radius:8px;background:rgba(0,106,106,.08);text-decoration:none">
            <span class="material-symbols-outlined" style="font-size:15px">stethoscope</span>
            <span data-en="Doctor / Provider Login" data-sw="Ingia kama Daktari / Mtoa Huduma">Doctor / Provider Login</span>
          </a>
        </div>
      </div>

      <div style="margin-top:18px;display:flex;align-items:center;gap:7px;justify-content:center">
        <span class="material-symbols-outlined msf" style="font-size:14px;color:var(--cp-secondary)">lock</span>
        <span style="font-size:.75rem;color:var(--cp-outline)" data-en="Enterprise-grade encryption — KEPDA compliant" data-sw="Usimbuaji wa kiwango cha biashara — Unazingatia KEPDA">
          Enterprise-grade encryption — KEPDA compliant
        </span>
      </div>
    </div>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (typeof Lang !== 'undefined') Lang.init();
  document.getElementById('langToggle')?.addEventListener('click', () => Lang.toggle());

  // Style inputs on focus
  document.querySelectorAll('.cp-form-input').forEach(inp => {
    inp.addEventListener('focus', function(){
      this.style.background = 'var(--cp-surface-container-lowest)';
      this.style.borderColor = 'var(--cp-primary)';
      this.style.boxShadow = '0 0 0 4px rgba(0,90,180,.1)';
    });
    inp.addEventListener('blur', function(){
      this.style.background = 'var(--cp-surface-container-highest)';
      this.style.borderColor = 'transparent';
      this.style.boxShadow = '';
    });
  });
});

function togglePw() {
  const f = document.getElementById('pwField');
  const e = document.getElementById('pwEye');
  const ic = e?.querySelector('.material-symbols-outlined');
  if (f) { f.type = f.type === 'password' ? 'text' : 'password'; }
  if (ic) ic.textContent = f?.type === 'password' ? 'visibility' : 'visibility_off';
}

document.addEventListener('keydown', e => {
  if (e.key === 'Enter') document.querySelector('button[type=submit]')?.click();
});
</script>

<!-- Add cp-alert CSS if not in clinical.css -->
<style>
.cp-alert { display:flex;align-items:flex-start;gap:10px;padding:13px 16px;border-radius:var(--cp-r);font-size:.875rem;font-weight:500;margin-bottom:0; }
.cp-alert-error { background:rgba(186,26,26,.08);color:#6b0000;border:1px solid rgba(186,26,26,.2); }
</style>
</body>
</html>
