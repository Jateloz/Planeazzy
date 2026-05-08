<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();

date_default_timezone_set('Africa/Nairobi');

// FIX 1: Guard — must be logged in
if (empty($_SESSION['hospital_id'])) {
    header('Location: /hospital/onboarding/signup.php');
    exit;
}

// FIX 2: Guard — email must be verified before reaching this page
if (empty($_SESSION['hospital_auth']) && empty($_SESSION['email_verified'])) {
    // Double-check DB in case session flag was lost
    $db  = Database::getInstance();
    $row = $db->fetchOne('SELECT email_verified FROM hospital_providers WHERE id=:id', [':id' => (int)$_SESSION['hospital_id']]);
    if (empty($row['email_verified'])) {
        header('Location: /hospital/onboarding/verify-email.php');
        exit;
    }
    // DB says verified — restore session flags
    $_SESSION['hospital_auth']  = true;
    $_SESSION['email_verified'] = true;
}

$hid  = (int)$_SESSION['hospital_id'];
$csrf = Security::csrfToken();
$db   = Database::getInstance();

// FIX 3: If facility_type already chosen, skip straight to next step
$hosp = $db->fetchOne('SELECT facility_type, onboarding_step FROM hospital_providers WHERE id=:id', [':id' => $hid]);
if (!empty($hosp['facility_type']) && (int)($hosp['onboarding_step'] ?? 0) >= 3) {
    $_SESSION['hospital_type'] = $hosp['facility_type'];
    $_SESSION['hospital_step'] = (int)$hosp['onboarding_step'];
    session_write_close();
    header('Location: /hospital/onboarding/profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok  = trim($_POST['csrf_token'] ?? '');
    $type = Security::clean($_POST['facility_type'] ?? '');

    if (!Security::verifyCsrf($tok)) {
        $postError = 'Security error. Please refresh and try again.';
    } elseif (!in_array($type, ['hospital', 'clinic', 'diagnostic', 'ambulance'], true)) {
        $postError = 'Invalid facility type selected.';
    } else {
        try {
            $db->query(
                'UPDATE hospital_providers SET facility_type=:t, onboarding_step=3 WHERE id=:id',
                [':t' => $type, ':id' => $hid]
            );

            $_SESSION['hospital_type'] = $type;
            $_SESSION['hospital_step'] = 3;

            // FIX 4: session_write_close() before redirect so next page reads fresh session
            session_write_close();

            // FIX 5: Redirect to the CORRECT next step, not back to verify-email.php
            header('Location: /hospital/onboarding/profile.php');
            exit;
        } catch (Exception $e) {
            error_log('select-type.php DB error: ' . $e->getMessage());
            $postError = 'Something went wrong. Please try again.';
        }
    }
}

$cpStep  = 3;
$cpTitle = 'Define Your Clinical Presence';
include __DIR__ . '/_head.php';

$types = [
  ['hospital',   'apartment',        'Hospital',           'Hospitali',
   'Large-scale multi-specialty facilities with emergency response and inpatient wards.',
   'Vituo vikubvyu vya utaalamu wa pande nyingi na majibu ya dharura na wodi za kulazwa.',
   'Full Tier Access', 'Ufikiaji Kamili wa Daraja'],
  ['clinic',     'medical_services', 'Clinic',             'Kliniki',
   'Private practices, specialized outpatient centers, and community health units.',
   'Mazoezi ya kibinafsi, vituo vya nje ya hospitali vya utaalamu, na vitengo vya afya vya jamii.',
   'Standard Tier', 'Daraja la Kawaida'],
  ['diagnostic', 'biotech',          'Diagnostic Center',  'Kituo cha Uchunguzi',
   'Labs, imaging centers, and specialized testing facilities providing clinical results.',
   'Maabara, vituo vya picha, na vituo maalum vya upimaji vinavyotoa matokeo ya kliniki.',
   'Data Integration', 'Muundo wa Data'],
  ['ambulance',  'ambulance',        'Ambulance Provider', 'Mtoa Ambulensi',
   'Emergency medical response units and mobile healthcare transportation services.',
   'Vitengo vya majibu ya dharura ya matibabu na huduma za usafirishaji wa afya zinazohamia.',
   'Real-time Logistics', 'Usimamizi wa Wakati Halisi'],
];
?>
<style>
.st-wrap{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px;background:linear-gradient(180deg,#f7f9fb 0%,#f2f4f6 100%)}
.st-glow{position:fixed;pointer-events:none}

/* Loading overlay shown while form submits */
#loadingOverlay{
  display:none;position:fixed;inset:0;z-index:9999;
  background:rgba(247,249,251,.85);backdrop-filter:blur(4px);
  flex-direction:column;align-items:center;justify-content:center;gap:18px
}
#loadingOverlay.active{display:flex}
.cp-spinner{
  width:48px;height:48px;border-radius:50%;
  border:3px solid rgba(0,90,180,.15);
  border-top-color:var(--cp-primary);
  animation:spin .8s linear infinite
}
@keyframes spin{to{transform:rotate(360deg)}}
</style>

<!-- Loading overlay -->
<div id="loadingOverlay" role="status" aria-live="polite">
  <div class="cp-spinner"></div>
  <p style="font-size:.9375rem;font-weight:600;color:var(--cp-on-surface)"
     data-en="Setting up your workspace…" data-sw="Inaandaa eneo lako la kazi…">Setting up your workspace…</p>
</div>

<!-- Decorative blobs -->
<div class="st-glow" style="top:-80px;left:-80px;width:350px;height:350px;background:rgba(0,90,180,.05);border-radius:50%;filter:blur(100px)"></div>
<div class="st-glow" style="bottom:-80px;right:-80px;width:450px;height:450px;background:rgba(0,106,106,.05);border-radius:50%;filter:blur(120px)"></div>

<!-- Lang toggle -->
<div style="position:fixed;top:20px;right:20px;z-index:100">
  <button class="cp-lang-btn" id="langToggle">
    <span class="material-symbols-outlined" style="font-size:15px">language</span>
    <span id="langLabel">SW</span>
  </button>
</div>

<div class="st-wrap">

  <!-- Header -->
  <div style="max-width:760px;width:100%;text-align:center;margin-bottom:56px">
    <div class="cp-badge cp-badge-secondary" style="margin-bottom:20px">
      <span class="material-symbols-outlined msf" style="font-size:13px">shield_person</span>
      <span data-en="Provider Enrollment" data-sw="Usajili wa Mtoa Huduma">Provider Enrollment</span>
    </div>
    <h1 class="cp-h1" style="margin-bottom:16px"
        data-en="Define your clinical presence."
        data-sw="Fafanua uwepo wako wa kliniki.">Define your clinical presence.</h1>
    <p class="cp-body-lg"
       data-en="Select your facility type to begin integration with our precision network. Your choice determines the specialized toolset available for your practice."
       data-sw="Chagua aina ya kituo chako kuanza muundo na mtandao wetu wa usahihi. Chaguo lako linaelezea seti ya zana maalum inayopatikana kwa mazoezi yako.">
      Select your facility type to begin integration with our precision network. Your choice determines the specialized toolset available for your practice.
    </p>

    <?php if (!empty($postError)): ?>
    <div style="margin-top:24px;background:rgba(186,26,26,.08);border:1px solid rgba(186,26,26,.2);border-radius:10px;padding:12px 16px;display:flex;gap:10px;align-items:center;font-size:.875rem;color:#6b0000" role="alert">
      <span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0">error</span>
      <?= htmlspecialchars($postError) ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Type cards -->
  <form method="POST" id="typeForm" style="width:100%;max-width:1100px">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="facility_type" id="selectedType" value="">

    <div class="cp-type-grid">
      <?php foreach ($types as [$key, $icon, $en, $sw, $desc, $descSw, $tier, $tierSw]): ?>
      <div class="cp-type-card" data-type="<?= $key ?>" onclick="selectType('<?= $key ?>')" role="button" tabindex="0"
           aria-label="<?= htmlspecialchars($en) ?>" onkeydown="if(event.key==='Enter'||event.key===' ')selectType('<?= $key ?>')">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:32px">
          <div class="cp-type-card-icon">
            <span class="material-symbols-outlined"><?= $icon ?></span>
          </div>
          <span class="material-symbols-outlined cp-type-card-arrow">arrow_forward</span>
        </div>
        <div>
          <h3 style="font-size:1.125rem;font-weight:700;color:var(--cp-on-surface);margin-bottom:8px"
              data-en="<?= htmlspecialchars($en) ?>"
              data-sw="<?= htmlspecialchars($sw) ?>"><?= htmlspecialchars($en) ?></h3>
          <p style="font-size:.8125rem;color:var(--cp-on-surface-var);line-height:1.6"
             data-en="<?= htmlspecialchars($desc) ?>"
             data-sw="<?= htmlspecialchars($descSw) ?>"><?= htmlspecialchars($desc) ?></p>
        </div>
        <div style="padding-top:18px;margin-top:20px;border-top:1px solid rgba(193,198,213,.12)">
          <span class="cp-type-tier"
                data-en="<?= htmlspecialchars($tier) ?>"
                data-sw="<?= htmlspecialchars($tierSw) ?>"><?= htmlspecialchars($tier) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </form>

  <!-- Progress dots -->
  <div style="margin-top:56px;display:flex;flex-direction:column;align-items:center;gap:12px">
    <div class="cp-step-dots">
      <div class="cp-dot active"></div>
      <div class="cp-dot"></div>
      <div class="cp-dot"></div>
      <div class="cp-dot"></div>
    </div>
    <p class="cp-caption" style="font-weight:600"
       data-en="Step 1 of 4: Account Configuration"
       data-sw="Hatua 1 ya 4: Usanidi wa Akaunti">Step 1 of 4: Account Configuration</p>
  </div>

  <!-- Support button -->
  <div style="position:fixed;bottom:24px;right:24px">
    <button style="display:flex;align-items:center;gap:10px;background:var(--cp-surface-container-lowest);padding:12px 22px;border-radius:9999px;border:1px solid rgba(193,198,213,.15);box-shadow:var(--cp-shadow-xl);font-family:inherit;font-size:.875rem;font-weight:600;color:var(--cp-on-surface-var);cursor:pointer;transition:background .15s"
            onmouseover="this.style.background='var(--cp-surface-container-low)'"
            onmouseout="this.style.background='var(--cp-surface-container-lowest)'">
      <span class="material-symbols-outlined" style="color:var(--cp-primary)">help_center</span>
      <span data-en="Need assistance?" data-sw="Unahitaji msaada?">Need assistance?</span>
    </button>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (typeof Lang !== 'undefined') Lang.init();
  document.getElementById('langToggle')?.addEventListener('click', () => Lang.toggle());
});

function selectType(type) {
  const valid = ['hospital', 'clinic', 'diagnostic', 'ambulance'];
  if (!valid.includes(type)) return;

  // Highlight selected card
  document.querySelectorAll('.cp-type-card').forEach(c => c.classList.remove('selected'));
  const card = document.querySelector('[data-type="' + type + '"]');
  if (card) card.classList.add('selected');

  // Set hidden field
  document.getElementById('selectedType').value = type;

  // Show loading overlay
  document.getElementById('loadingOverlay').classList.add('active');

  // Submit after brief visual delay so user sees selection
  setTimeout(() => {
    document.getElementById('typeForm').submit();
  }, 380);
}
</script>
</body>
</html>
