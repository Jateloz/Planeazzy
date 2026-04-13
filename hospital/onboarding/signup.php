<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();

if (!empty($_SESSION['hospital_id']) && !empty($_SESSION['hospital_auth'])) {
    header('Location: /hospital/onboarding/dashboard.php'); exit;
}

$csrf  = Security::csrfToken();
$error = '';
$devError = ''; // detailed error for dev mode only

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok   = trim($_POST['csrf_token'] ?? '');
    $name  = trim(strip_tags($_POST['admin_name']  ?? ''));
    $email = filter_var(trim($_POST['admin_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = preg_replace('/[^0-9+\-\s()]/', '', trim($_POST['admin_phone'] ?? ''));
    $pw    = $_POST['password'] ?? '';
    $agree = !empty($_POST['agree']);

    if (!Security::verifyCsrf($tok)) {
        $error = 'Security token error. Please refresh the page and try again.';
    } elseif (!$name) {
        $error = 'Organisation name is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!$phone) {
        $error = 'Phone number is required.';
    } elseif (strlen($pw) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!$agree) {
        $error = 'You must agree to the Terms of Service to continue.';
    } else {
        try {
            $db = Database::getInstance();

            // Check duplicate email
            $existing = $db->fetchOne(
                'SELECT id FROM hospital_providers WHERE admin_email = :e',
                [':e' => strtolower($email)]
            );
            if ($existing) {
                $error = 'An account with this email already exists. Please sign in instead.';
            } else {
                $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

// --- UPDATED INSERT QUERY ---
$id = $db->insert(
    'INSERT INTO hospital_providers
     (admin_name, admin_email, admin_phone, password_hash,
      onboarding_step, status, created_at)
     VALUES (:n, :e, :p, :h, :step, :status, :at)', // Changed NOW() to :at
    [
        ':n'      => $name,
        ':e'      => strtolower($email),
        ':p'      => $phone,
        ':h'      => $hash,
        ':step'   => 2,
        ':status' => 'pending',
        ':at'     => date('Y-m-d H:i:s') // PHP sends Nairobi time here
    ]
);

                if (!$id) {
                    $error = 'Account could not be created. Please try again.';
                } else {
                    $_SESSION['hospital_id']    = $id;
                    $_SESSION['hospital_auth']  = false;
                    $_SESSION['hospital_name']  = $name;
                    $_SESSION['hospital_email'] = strtolower($email);
                    $_SESSION['hospital_step']  = 2;
                    header('Location: /hospital/onboarding/select-type.php'); exit;
                }
            }

        } catch (RuntimeException $e) {
            // DB unavailable (wrong credentials, DB not running)
            $devError = $e->getMessage();
            $error = 'Cannot connect to the database. Please check your database configuration.';
            error_log('[Hospital Signup] DB connection: ' . $e->getMessage());

        } catch (PDOException $e) {
            // SQL error (table missing, column wrong, etc.)
            $devError = $e->getMessage();
            if (strpos($e->getMessage(), 'hospital_providers') !== false &&
                strpos($e->getMessage(), "doesn't exist") !== false) {
                $error = 'Database tables are not set up. Please run the hospital schema SQL first (see README).';
            } elseif (strpos($e->getMessage(), 'Duplicate') !== false) {
                $error = 'An account with this email already exists.';
            } else {
                $error = 'A database error occurred. Please try again.';
            }
            error_log('[Hospital Signup] PDO: ' . $e->getMessage());

        } catch (Throwable $e) {
            $devError = $e->getMessage();
            $error = 'An unexpected error occurred. Please try again.';
            error_log('[Hospital Signup] ' . $e->getMessage());
        }
    }
}

$cpStep  = 2;
$cpTitle = 'Create Account';
include __DIR__ . '/_head.php';
?>
<style>
.su-layout { display:grid; grid-template-columns:1fr 1fr; min-height:100vh; }
.su-left   { background:var(--cp-surface-container-low); padding:64px; display:flex; flex-direction:column; justify-content:space-between; position:relative; overflow:hidden; }
.su-right  { padding:48px 64px; display:flex; align-items:center; justify-content:center; background:var(--cp-surface-container-lowest); }
.su-card   { width:100%; max-width:440px; }
.su-trust  { display:flex; align-items:flex-start; gap:12px; margin-bottom:16px; }
.cp-field  { margin-bottom:18px; }
.cp-label-text { display:block; font-size:.6875rem; font-weight:700; text-transform:uppercase; letter-spacing:.09em; color:var(--cp-on-surface-var); margin-bottom:6px; }
.cp-input  { width:100%; padding:12px 14px; background:var(--cp-surface-container-highest); border:2px solid transparent; border-radius:var(--cp-r); font-family:inherit; font-size:.9375rem; color:var(--cp-on-surface); outline:none; transition:all .2s; }
.cp-input:focus { background:var(--cp-surface-container-lowest); border-color:var(--cp-primary); box-shadow:0 0 0 4px rgba(0,90,180,.1); }
.cp-input::placeholder { color:var(--cp-outline); }
.cp-eye-btn { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--cp-outline); display:flex; align-items:center; }
.cp-check-row { display:flex; align-items:flex-start; gap:10px; cursor:pointer; line-height:1.5; }
.cp-alert { display:flex; align-items:flex-start; gap:10px; padding:13px 16px; border-radius:var(--cp-r); font-size:.875rem; font-weight:500; margin-bottom:18px; }
.cp-alert-error { background:rgba(186,26,26,.08); color:#6b0000; border:1px solid rgba(186,26,26,.2); }
.cp-alert-dev   { background:#fefce8; border:1px solid #d97706; color:#92400e; font-size:.8125rem; font-family:monospace; margin-top:8px; padding:12px; border-radius:8px; word-break:break-all; }
@media(max-width:900px){ .su-layout{grid-template-columns:1fr} .su-left{display:none} .su-right{padding:32px 20px} }
@media(max-width:480px){ .su-right{padding:24px 14px} }
</style>

<div class="su-layout">

  <!-- ── Left branding panel ─────────────────────────── -->
  <div class="su-left">
    <div style="position:absolute;bottom:-80px;left:-80px;width:320px;height:320px;background:rgba(0,90,180,.05);border-radius:50%;filter:blur(80px);pointer-events:none"></div>
    <div style="position:absolute;top:-80px;right:-80px;width:260px;height:260px;background:rgba(0,106,106,.05);border-radius:50%;filter:blur(80px);pointer-events:none"></div>
    <div style="position:relative;z-index:1">
      <a href="/hospital/onboarding/join.php"
         style="font-size:1.375rem;font-weight:900;color:var(--cp-primary);text-decoration:none;letter-spacing:-.04em;display:block;margin-bottom:48px"
         data-en="Clinical Precision" data-sw="Usahihi wa Kliniki">Clinical Precision</a>
      <h1 style="font-size:clamp(1.625rem,3.5vw,2.5rem);font-weight:900;color:var(--cp-on-surface);letter-spacing:-.04em;line-height:1.1;margin-bottom:20px">
        <span data-en="The Digital" data-sw="Hifadhi ya">The Digital</span>
        <span style="color:var(--cp-primary)" data-en=" Sanctuary" data-sw=" Kidijitali"> Sanctuary</span>
        <span data-en=" for Modern Healthcare." data-sw=" kwa Afya ya Kisasa."> for Modern Healthcare.</span>
      </h1>
      <p class="cp-body-lg" style="margin-bottom:40px;max-width:380px"
         data-en="Join a network of elite providers using Kenya's most advanced KEPDA-compliant clinical management framework."
         data-sw="Jiunge na mtandao wa watoa huduma bora wanaotumia mfumo wa kisasa zaidi wa usimamizi wa kliniki unaozingatia KEPDA wa Kenya.">
        Join a network of elite providers using Kenya's most advanced KEPDA-compliant clinical management framework.
      </p>
      <?php foreach([
        ['verified_user','Secure Infrastructure','Miundo ya Usalama','Tier-4 data centers with real-time encryption.','Vituo vya data vya Tier-4 na usimbuaji wa wakati halisi.'],
        ['clinical_notes','Editorial Precision','Usahihi wa Uhariri','Designed for high-end medical journal readability.','Imezapwa kwa usomaji wa jarida la matibabu la hali ya juu.'],
        ['diversity_3','500+ Elite Facilities','Vituo 500+ Bora','Join Kenya\'s leading medical provider network.','Jiunge na mtandao wa watoa matibabu wanaongoza Kenya.'],
      ] as [$ic,$en,$sw,$desc,$descSw]): ?>
      <div class="su-trust">
        <span class="material-symbols-outlined msf" style="color:var(--cp-secondary);flex-shrink:0;margin-top:2px"><?=$ic?></span>
        <div>
          <p style="font-weight:600;color:var(--cp-on-surface);margin-bottom:2px" data-en="<?=htmlspecialchars($en)?>" data-sw="<?=htmlspecialchars($sw)?>"><?=$en?></p>
          <p style="font-size:.8125rem;color:var(--cp-on-surface-var)" data-en="<?=htmlspecialchars($desc)?>" data-sw="<?=htmlspecialchars($descSw)?>"><?=$desc?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="position:relative;z-index:1;font-size:.75rem;color:var(--cp-outline)">
      © 2025 Clinical Precision — Planeazzy
    </div>
  </div>

  <!-- ── Right form panel ────────────────────────────── -->
  <div class="su-right">
    <div class="su-card">

      <!-- Mobile brand -->
      <a href="/hospital/onboarding/join.php"
         style="font-size:1.125rem;font-weight:900;color:var(--cp-primary);text-decoration:none;display:none;margin-bottom:24px;letter-spacing:-.03em"
         id="mobileBrand">Clinical Precision</a>

      <!-- Lang toggle -->
      <div style="display:flex;justify-content:flex-end;margin-bottom:20px">
        <button class="cp-lang-btn" id="langToggle">
          <span class="material-symbols-outlined" style="font-size:15px">language</span>
          <span id="langLabel">SW</span>
        </button>
      </div>

      <h2 style="font-size:1.625rem;font-weight:800;color:var(--cp-on-surface);margin-bottom:6px;letter-spacing:-.03em"
          data-en="Create Account" data-sw="Unda Akaunti">Create Account</h2>
      <p style="font-size:.875rem;color:var(--cp-on-surface-var);margin-bottom:24px"
         data-en="Set up your facility in the Clinical Precision network."
         data-sw="Sanidi kituo chako katika mtandao wa Clinical Precision.">
        Set up your facility in the Clinical Precision network.
      </p>

      <!-- Error display -->
      <?php if ($error): ?>
      <div class="cp-alert cp-alert-error">
        <span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0">error</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php if ($devError && APP_ENV === 'development'): ?>
      <div class="cp-alert-dev">
        <strong>Dev Error Detail:</strong><br><?= htmlspecialchars($devError) ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <!-- Setup instructions (collapsed by default) -->
       <!--
      <details style="margin-bottom:20px;background:rgba(0,90,180,.04);border:1px solid rgba(0,90,180,.12);border-radius:10px;padding:12px 14px">
        <summary style="font-size:.8125rem;font-weight:700;color:var(--cp-primary);cursor:pointer"
                 data-en="⚙ First time setup? Click here" data-sw="⚙ Mara ya kwanza? Bonyeza hapa">
          ⚙ First time setup? Click here
        </summary>
        <div style="margin-top:10px;font-size:.8125rem;color:var(--cp-on-surface-var);line-height:1.7">
          <p data-en="Before creating an account, make sure you have run both SQL schema files in phpMyAdmin:" data-sw="Kabla ya kuunda akaunti, hakikisha umetekeleza faili zote mbili za SQL schema katika phpMyAdmin:">
            Before creating an account, run both SQL files in phpMyAdmin:
          </p>
          <ol style="margin:8px 0 0 16px;font-family:monospace;font-size:.8125rem">
            <li style="margin-bottom:4px"><code>config/schema.sql</code></li>
            <li><code>hospital/onboarding/schema.sql</code></li>
          </ol>
          <p style="margin-top:8px" data-en="Also verify your DB credentials in config/config.php match your phpMyAdmin settings." data-sw="Pia thibitisha vitambulisho vya DB katika config/config.php vinalingana na mipangilio yako ya phpMyAdmin.">
            Also verify your DB credentials in <code>config/config.php</code>.
          </p>
        </div>
      </details>-->

      <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Organisation Name -->
        <div class="cp-field">
          <label class="cp-label-text" for="admin_name"
                 data-en="Organization Name" data-sw="Jina la Shirika">Organization Name</label>
          <input class="cp-input" type="text" id="admin_name" name="admin_name"
            data-en-placeholder="e.g., Nairobi Medical Center"
            data-sw-placeholder="mf., Kituo cha Matibabu Nairobi"
            placeholder="e.g., Nairobi Medical Center"
            required autocomplete="organization"
            value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>">
        </div>

        <!-- Email + Phone -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="cp-field">
            <label class="cp-label-text" for="admin_email"
                   data-en="Official Email" data-sw="Barua Pepe Rasmi">Official Email</label>
            <input class="cp-input" type="email" id="admin_email" name="admin_email"
              data-en-placeholder="admin@facility.com"
              data-sw-placeholder="msimamizi@kituo.com"
              placeholder="admin@facility.com"
              required autocomplete="email"
              value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>">
          </div>
          <div class="cp-field">
            <label class="cp-label-text" for="admin_phone"
                   data-en="Phone Number" data-sw="Nambari ya Simu">Phone Number</label>
            <input class="cp-input" type="tel" id="admin_phone" name="admin_phone"
              data-en-placeholder="+254 700 000000"
              data-sw-placeholder="+254 700 000000"
              placeholder="+254 700 000000"
              required autocomplete="tel"
              value="<?= htmlspecialchars($_POST['admin_phone'] ?? '') ?>">
          </div>
        </div>

        <!-- Password -->
        <div class="cp-field">
          <label class="cp-label-text" for="pw"
                 data-en="Password" data-sw="Nenosiri">Password</label>
          <div style="position:relative">
            <input class="cp-input" type="password" id="pw" name="password"
              data-en-placeholder="Min 8 characters"
              data-sw-placeholder="Angalau herufi 8"
              placeholder="Min 8 characters"
              required autocomplete="new-password"
              style="padding-right:44px">
            <button type="button" class="cp-eye-btn" onclick="togglePw()" aria-label="Show/hide password">
              <span class="material-symbols-outlined" id="eyeIcon" style="font-size:19px">visibility</span>
            </button>
          </div>
          <!-- Strength bar -->
          <div style="height:4px;background:var(--cp-surface-container-highest);border-radius:9999px;margin-top:8px;overflow:hidden">
            <div id="strBar" style="height:100%;border-radius:9999px;width:0;transition:all .3s"></div>
          </div>
          <div id="strTxt" style="font-size:.6875rem;color:var(--cp-outline);margin-top:4px;height:14px"></div>
        </div>

        <!-- Terms checkbox -->
        <div class="cp-field">
          <label class="cp-check-row" id="agreeRow">
            <input type="checkbox" name="agree" id="agreeChk"
              style="width:17px;height:17px;flex-shrink:0;margin-top:2px;accent-color:var(--cp-primary)"
              <?= !empty($_POST['agree']) ? 'checked' : '' ?>>
            <span style="font-size:.875rem;color:var(--cp-on-surface-var)">
              <span data-en="I agree to the" data-sw="Nakubaliana na">I agree to the</span>
              <a href="#" style="color:var(--cp-primary);font-weight:600"
                 data-en="Terms of Service" data-sw="Masharti ya Huduma">Terms of Service</a>
              <span data-en="and" data-sw="na">and</span>
              <a href="#" style="color:var(--cp-primary);font-weight:600"
                 data-en="Privacy Policy" data-sw="Sera ya Faragha">Privacy Policy</a>
            </span>
          </label>
        </div>

        <!-- Submit -->
        <button type="submit" id="submitBtn"
                class="cp-btn cp-btn-primary cp-btn-full cp-btn-round"
                style="font-size:.9375rem;padding:14px">
          <span class="material-symbols-outlined" style="font-size:18px">how_to_reg</span>
          <span data-en="Create Account" data-sw="Unda Akaunti">Create Account</span>
        </button>
      </form>

      <!-- Wrong portal notice -->
      <div style="margin-top:18px;padding:13px;background:rgba(0,106,106,.06);border-radius:10px;border:1px solid rgba(0,106,106,.12)">
        <p style="font-size:.75rem;color:var(--cp-on-surface-var);margin-bottom:7px"
           data-en="Registering as a Doctor or individual clinic? Use:" data-sw="Unajisajili kama Daktari au kliniki binafsi? Tumia:">
          Registering as a Doctor or individual clinic? Use:
        </p>
        <a href="/providers/register.php"
           style="display:inline-flex;align-items:center;gap:6px;font-size:.8125rem;font-weight:700;color:var(--cp-secondary);text-decoration:none">
          <span class="material-symbols-outlined" style="font-size:15px">stethoscope</span>
          <span data-en="Doctor / Provider Registration →" data-sw="Usajili wa Daktari / Mtoa Huduma →">Doctor / Provider Registration →</span>
        </a>
      </div>

      <!-- Sign in link -->
      <p style="text-align:center;margin-top:20px;font-size:.875rem;color:var(--cp-on-surface-var)">
        <span data-en="Already have an account?" data-sw="Una akaunti tayari?">Already have an account?</span>
        <a href="/hospital/onboarding/login.php"
           style="color:var(--cp-primary);font-weight:700;margin-left:4px"
           data-en="Sign In →" data-sw="Ingia →">Sign In →</a>
      </p>
    </div>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (typeof Lang !== 'undefined') Lang.init();
  document.getElementById('langToggle')?.addEventListener('click', () => Lang.toggle());
  if (window.innerWidth <= 900) document.getElementById('mobileBrand').style.display = 'block';

  // Password strength meter
  const pw = document.getElementById('pw');
  const bar = document.getElementById('strBar');
  const txt = document.getElementById('strTxt');
  const isSw = () => document.documentElement.lang === 'sw';
  if (pw) pw.addEventListener('input', function() {
    const v = this.value;
    let s = 0, label = '';
    if (v.length >= 8) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    const colors = ['#ba1a1a','#d97706','#16a34a','#005ab4'];
    const widths  = ['25%','50%','75%','100%'];
    const labelsEn = ['Weak','Fair','Good','Strong'];
    const labelsSw = ['Dhaifu','Wastani','Nzuri','Imara'];
    if (v.length === 0) { bar.style.width='0'; txt.textContent=''; return; }
    bar.style.width  = s > 0 ? widths[s-1]  : '5%';
    bar.style.background = s > 0 ? colors[s-1] : '#ba1a1a';
    txt.textContent  = s > 0 ? (isSw() ? labelsSw[s-1] : labelsEn[s-1]) : '';
    txt.style.color  = s > 0 ? colors[s-1] : '';
  });

  // Form submit loading state
  document.querySelector('form')?.addEventListener('submit', () => {
    const btn = document.getElementById('submitBtn');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;animation:spin .7s linear infinite">refresh</span> <span data-en="Creating…" data-sw="Inaunda…">Creating…</span>';
    }
  });
});

function togglePw() {
  const pw = document.getElementById('pw');
  const ic = document.getElementById('eyeIcon');
  const show = pw.type === 'password';
  pw.type  = show ? 'text' : 'password';
  ic.textContent = show ? 'visibility_off' : 'visibility';
}
</script>
</body>
</html>
