<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();
if (empty($_SESSION['hospital_id'])) { header('Location: /hospital/onboarding/signup.php'); exit; }
$hid = (int)$_SESSION['hospital_id'];
$csrf = Security::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok  = trim($_POST['csrf_token'] ?? '');
    $type = Security::clean($_POST['facility_type'] ?? '');
    if (Security::verifyCsrf($tok) && in_array($type,['hospital','clinic','diagnostic','ambulance'])) {
        try {
            Database::getInstance()->query('UPDATE hospital_providers SET facility_type=:t,onboarding_step=3 WHERE id=:id',[':t'=>$type,':id'=>$hid]);
            $_SESSION['hospital_type'] = $type; $_SESSION['hospital_step'] = 3;
            header('Location: /hospital/onboarding/verify-email.php'); exit;
        } catch (Exception $e) { error_log($e->getMessage()); }
    }
}
$cpStep = 3; $cpTitle = 'Define Your Clinical Presence';
include __DIR__ . '/_head.php';

$types = [
  ['hospital',   'apartment',        'Hospital',          'Hospitali',
   'Large-scale multi-specialty facilities with emergency response and inpatient wards.',
   'Vituo vikubvyu vya utaalamu wa pande nyingi na majibu ya dharura na wodi za kulazwa.',
   'Full Tier Access','Ufikiaji Kamili wa Daraja'],
  ['clinic',     'medical_services', 'Clinic',            'Kliniki',
   'Private practices, specialized outpatient centers, and community health units.',
   'Mazoezi ya kibinafsi, vituo vya nje ya hospitali vya utaalamu, na vitengo vya afya vya jamii.',
   'Standard Tier','Daraja la Kawaida'],
  ['diagnostic', 'biotech',          'Diagnostic Center', 'Kituo cha Uchunguzi',
   'Labs, imaging centers, and specialized testing facilities providing clinical results.',
   'Maabara, vituo vya picha, na vituo maalum vya upimaji vinavyotoa matokeo ya kliniki.',
   'Data Integration','Muundo wa Data'],
  ['ambulance',  'ambulance',        'Ambulance Provider','Mtoa Ambulensi',
   'Emergency medical response units and mobile healthcare transportation services.',
   'Vitengo vya majibu ya dharura ya matibabu na huduma za usafirishaji wa afya zinazohamia.',
   'Real-time Logistics','Usimamizi wa Wakati Halisi'],
];
?>
<style>
.st-wrap{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px;background:linear-gradient(180deg,#f7f9fb 0%,#f2f4f6 100%)}
.st-glow{position:fixed;pointer-events:none}
</style>

<!-- Decorative blobs -->
<div class="st-glow" style="top:-80px;left:-80px;width:350px;height:350px;background:rgba(0,90,180,.05);border-radius:50%;filter:blur(100px)"></div>
<div class="st-glow" style="bottom:-80px;right:-80px;width:450px;height:450px;background:rgba(0,106,106,.05);border-radius:50%;filter:blur(120px)"></div>

<!-- Lang toggle fixed -->
<div style="position:fixed;top:20px;right:20px;z-index:100">
  <button class="cp-lang-btn" id="langToggle"><span class="material-symbols-outlined" style="font-size:15px">language</span><span id="langLabel">SW</span></button>
</div>

<div class="st-wrap">
  <!-- Header -->
  <div style="max-width:760px;width:100%;text-align:center;margin-bottom:56px">
    <div class="cp-badge cp-badge-secondary" style="margin-bottom:20px">
      <span class="material-symbols-outlined msf" style="font-size:13px">shield_person</span>
      <span data-en="Provider Enrollment" data-sw="Usajili wa Mtoa Huduma">Provider Enrollment</span>
    </div>
    <h1 class="cp-h1" style="margin-bottom:16px" data-en="Define your clinical presence." data-sw="Fafanua uwepo wako wa kliniki.">Define your clinical presence.</h1>
    <p class="cp-body-lg" data-en="Select your facility type to begin integration with our precision network. Your choice determines the specialized toolset available for your practice."
       data-sw="Chagua aina ya kituo chako kuanza muundo na mtandao wetu wa usahihi. Chaguo lako linaelezea seti ya zana maalum inayopatikana kwa mazoezi yako.">
      Select your facility type to begin integration with our precision network. Your choice determines the specialized toolset available for your practice.
    </p>
  </div>

  <!-- Type cards -->
  <form method="POST" id="typeForm" style="width:100%;max-width:1100px">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="facility_type" id="selectedType" value="">

    <div class="cp-type-grid">
      <?php foreach ($types as [$key,$icon,$en,$sw,$desc,$descSw,$tier,$tierSw]): ?>
      <div class="cp-type-card" data-type="<?= $key ?>" onclick="selectType('<?= $key ?>')">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:32px">
          <div class="cp-type-card-icon">
            <span class="material-symbols-outlined"><?= $icon ?></span>
          </div>
          <span class="material-symbols-outlined cp-type-card-arrow">arrow_forward</span>
        </div>
        <div>
          <h3 style="font-size:1.125rem;font-weight:700;color:var(--cp-on-surface);margin-bottom:8px"
              data-en="<?= htmlspecialchars($en) ?>" data-sw="<?= htmlspecialchars($sw) ?>"><?= $en ?></h3>
          <p style="font-size:.8125rem;color:var(--cp-on-surface-var);line-height:1.6"
             data-en="<?= htmlspecialchars($desc) ?>" data-sw="<?= htmlspecialchars($descSw) ?>"><?= $desc ?></p>
        </div>
        <div style="padding-top:18px;margin-top:20px;border-top:1px solid rgba(193,198,213,.12)">
          <span class="cp-type-tier" data-en="<?= htmlspecialchars($tier) ?>" data-sw="<?= htmlspecialchars($tierSw) ?>"><?= $tier ?></span>
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
    <p class="cp-caption" style="font-weight:600" data-en="Step 1 of 4: Account Configuration" data-sw="Hatua 1 ya 4: Usanidi wa Akaunti">Step 1 of 4: Account Configuration</p>
  </div>

  <!-- Support button -->
  <div style="position:fixed;bottom:24px;right:24px">
    <button style="display:flex;align-items:center;gap:10px;background:var(--cp-surface-container-lowest);padding:12px 22px;border-radius:9999px;border:1px solid rgba(193,198,213,.15);box-shadow:var(--cp-shadow-xl);font-family:inherit;font-size:.875rem;font-weight:600;color:var(--cp-on-surface-var);cursor:pointer;transition:background .15s"
            onmouseover="this.style.background='var(--cp-surface-container-low)'" onmouseout="this.style.background='var(--cp-surface-container-lowest)'">
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
  document.querySelectorAll('.cp-type-card').forEach(c => c.classList.remove('selected'));
  document.querySelector('[data-type="'+type+'"]')?.classList.add('selected');
  document.getElementById('selectedType').value = type;
  setTimeout(() => document.getElementById('typeForm').submit(), 350);
}
</script>
</body></html>
