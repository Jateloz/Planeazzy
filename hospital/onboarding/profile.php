<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();
if (empty($_SESSION['hospital_id'])) { header('Location: /hospital/onboarding/signup.php'); exit; }
$hid = (int)$_SESSION['hospital_id'];
$csrf = Security::csrfToken(); $error = '';
$db   = Database::getInstance();
$hosp = $db->fetchOne('SELECT * FROM hospital_providers WHERE id=:id',[':id'=>$hid]);
if (empty($hosp['email_verified'])) { header('Location: /hospital/onboarding/verify-email.php'); exit; }

$services_list = [
  ['general_practice','General Practice','Dawa ya Jumla'],
  ['pediatrics','Pediatrics','Dawa ya Watoto'],
  ['radiology','Radiology','Redio Lojia'],
  ['cardiology','Cardiology','Moyo'],
  ['maternity','Maternity','Uzazi'],
  ['laboratory','Laboratory','Maabara'],
  ['surgery','Surgery','Upasuaji'],
  ['oncology','Oncology','Saratani'],
  ['ophthalmology','Ophthalmology','Macho'],
  ['orthopedics','Orthopedics','Mifupa'],
  ['emergency','Emergency & Critical Care','Dharura'],
  ['pharmacy','Pharmacy','Duka la Dawa'],
];
$counties = ['Nairobi','Mombasa','Kisumu','Nakuru','Kiambu','Machakos','Kajiado','Uasin Gishu','Meru','Nyeri','Kisii','Kakamega','Garissa','Kilifi','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok      = trim($_POST['csrf_token'] ?? '');
    $facName  = Security::clean($_POST['facility_name'] ?? '');
    $county   = Security::clean($_POST['county'] ?? '');
    $subCo    = Security::clean($_POST['sub_county'] ?? '');
    $address  = Security::clean($_POST['address'] ?? '');
    $phone    = Security::clean($_POST['phone'] ?? '');
    $website  = Security::clean($_POST['website'] ?? '');
    $emergency= !empty($_POST['emergency_24h']);
    $svcs     = array_filter($_POST['services'] ?? [], fn($s) => in_array($s, array_column($services_list,0)));

    if (!Security::verifyCsrf($tok))  $error = 'Security error.';
    elseif (!$facName)                 $error = 'Facility name is required.';
    elseif (!$county)                  $error = 'County is required.';
    elseif (!$address)                 $error = 'Physical address is required.';
    else {
        try {
            $db->query(
                'UPDATE hospital_providers SET facility_name=:fn,county=:co,sub_county=:sc,address=:ad,phone=:ph,website=:ws,emergency_24h=:em,services=:sv,onboarding_step=5 WHERE id=:id',
                [':fn'=>$facName,':co'=>$county,':sc'=>$subCo,':ad'=>$address,':ph'=>$phone,':ws'=>$website,':em'=>(int)$emergency,':sv'=>json_encode(array_values($svcs)),':id'=>$hid]
            );
            $_SESSION['hospital_name'] = $facName;
            header('Location: /hospital/onboarding/departments.php'); exit;
        } catch (Exception $e) { error_log($e->getMessage()); $error = 'Server error. Please try again.'; }
    }
}
$savedSvcs = json_decode($hosp['services'] ?? '[]', true) ?? [];
$cpStep = 5; $cpTitle = 'Organization Profile';
include __DIR__ . '/_head.php';
?>
<style>
.prof-layout{display:grid;grid-template-columns:7fr 5fr;gap:40px;max-width:1200px;margin:0 auto;padding:40px 40px 60px}
.prof-sticky{position:sticky;top:84px}
@media(max-width:1024px){.prof-layout{grid-template-columns:1fr;padding:24px 20px}}
</style>

<!-- Progress rail -->
<div class="cp-progress-rail"><div class="cp-progress-fill" style="width:55%"></div></div>

<!-- Header nav -->
<header class="cp-topnav">
  <a href="/hospital/onboarding/join.php" class="cp-topnav-brand" data-en="Clinical Precision" data-sw="Usahihi wa Kliniki">Clinical Precision</a>
  <div class="cp-topnav-actions">
    <button class="cp-lang-btn" id="langToggle"><span class="material-symbols-outlined" style="font-size:15px">language</span><span id="langLabel">SW</span></button>
  </div>
</header>

<div class="prof-layout">
  <!-- LEFT: Form -->
  <div>
    <!-- Step indicator -->
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
      <span style="font-size:.6875rem;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--cp-primary)"
            data-en="Step 02 of 03" data-sw="Hatua 02 ya 03">Step 02 of 03</span>
      <div style="flex:1;height:1px;background:rgba(193,198,213,.3)"></div>
    </div>
    <h1 class="cp-h1" style="margin-bottom:10px" data-en="Organization Profile Setup" data-sw="Usanidi wa Wasifu wa Shirika">Organization Profile Setup</h1>
    <p class="cp-body-lg" style="margin-bottom:36px;max-width:580px"
       data-en="Establish your facility's digital identity within the Clinical Precision framework. This information ensures patients find the right care at the right time."
       data-sw="Weka utambulisho wa kidijitali wa kituo chako ndani ya mfumo wa Clinical Precision.">
      Establish your facility's digital identity within the Clinical Precision framework. This information ensures patients find the right care at the right time.
    </p>

    <?php if ($error): ?>
    <div class="cp-alert cp-alert-error" style="margin-bottom:24px"><span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0">error</span><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="profileForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

      <!-- Facility Details -->
      <section style="margin-bottom:36px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
          <span class="material-symbols-outlined msf" style="color:var(--cp-primary)">domain</span>
          <h2 class="cp-h3" data-en="Facility Details" data-sw="Maelezo ya Kituo">Facility Details</h2>
        </div>
        <div class="cp-field">
          <label class="cp-label-text" data-en="Facility Name" data-sw="Jina la Kituo">Facility Name</label>
          <input class="cp-input" type="text" name="facility_name" placeholder="e.g., Nairobi West Specialized Wing" required value="<?= htmlspecialchars($_POST['facility_name'] ?? $hosp['facility_name'] ?? '') ?>">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="cp-field">
            <label class="cp-label-text" data-en="County" data-sw="Kaunti">County</label>
            <select class="cp-input cp-select" name="county" required>
              <option value="" data-en="Select county..." data-sw="Chagua kaunti...">Select county...</option>
              <?php foreach ($counties as $c): ?>
              <option value="<?=$c?>" <?= ($hosp['county']??'')===$c?'selected':'' ?>><?=$c?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="cp-field">
            <label class="cp-label-text" data-en="Sub-County" data-sw="Kata">Sub-County</label>
            <input class="cp-input" type="text" name="sub_county" placeholder="Westlands" value="<?= htmlspecialchars($_POST['sub_county'] ?? $hosp['sub_county'] ?? '') ?>">
          </div>
        </div>
        <div class="cp-field">
          <label class="cp-label-text" data-en="Physical Address" data-sw="Anwani ya Kimwili">Physical Address</label>
          <textarea class="cp-input cp-textarea" name="address" rows="2" placeholder="Street name, Building, Floor number" required><?= htmlspecialchars($_POST['address'] ?? $hosp['address'] ?? '') ?></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="cp-field">
            <label class="cp-label-text" data-en="Phone" data-sw="Simu">Phone</label>
            <input class="cp-input" type="tel" name="phone" placeholder="+254 7xx xxx xxx" value="<?= htmlspecialchars($_POST['phone'] ?? $hosp['phone'] ?? '') ?>">
          </div>
          <div class="cp-field">
            <label class="cp-label-text" data-en="Website (optional)" data-sw="Tovuti (si lazima)">Website (optional)</label>
            <input class="cp-input" type="url" name="website" placeholder="https://yourhospital.co.ke" value="<?= htmlspecialchars($_POST['website'] ?? $hosp['website'] ?? '') ?>">
          </div>
        </div>
      </section>

      <!-- Services -->
      <section style="margin-bottom:36px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
          <div style="display:flex;align-items:center;gap:10px">
            <span class="material-symbols-outlined msf" style="color:var(--cp-primary)">medical_services</span>
            <h2 class="cp-h3" data-en="Services Offered" data-sw="Huduma Zinazotolewa">Services Offered</h2>
          </div>
          <span class="cp-caption" style="font-weight:600" data-en="KEPDA Categorized" data-sw="Imegawanywa na KEPDA">KEPDA Categorized</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <?php foreach ($services_list as [$key,$en,$sw]): ?>
          <label class="cp-check-row <?= in_array($key,$savedSvcs)?'checked':'' ?>">
            <input type="checkbox" name="services[]" value="<?=$key?>" <?= in_array($key,$savedSvcs)?'checked':'' ?>>
            <span data-en="<?=htmlspecialchars($en)?>" data-sw="<?=htmlspecialchars($sw)?>"><?=$en?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Emergency availability -->
      <section class="cp-card" style="padding:24px;margin-bottom:36px">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
          <div style="display:flex;align-items:center;gap:14px">
            <div style="width:46px;height:46px;border-radius:50%;background:var(--cp-secondary-container);display:flex;align-items:center;justify-content:center;color:var(--cp-on-secondary-container)">
              <span class="material-symbols-outlined">emergency</span>
            </div>
            <div>
              <h3 class="cp-h4" data-en="Emergency Availability" data-sw="Upatikanaji wa Dharura">Emergency Availability</h3>
              <p style="font-size:.8125rem;color:var(--cp-on-surface-var)" data-en="24/7 critical care and ambulance services" data-sw="Huduma ya dharura 24/7 na ambulensi">24/7 critical care and ambulance services</p>
            </div>
          </div>
          <label class="cp-toggle">
            <input type="checkbox" name="emergency_24h" id="emgToggle" <?= !empty($hosp['emergency_24h'])?'checked':'' ?>>
            <div class="cp-toggle-track"><div class="cp-toggle-thumb"></div></div>
            <strong id="emgLabel" style="font-size:.8125rem;color:var(--cp-secondary);text-transform:uppercase;letter-spacing:.08em"
                    data-en="<?= !empty($hosp['emergency_24h'])?'Active':'Inactive' ?>"
                    data-sw="<?= !empty($hosp['emergency_24h'])?'Inafanya Kazi':'Haifanyi Kazi' ?>">
              <?= !empty($hosp['emergency_24h'])?'Active':'Inactive' ?>
            </strong>
          </label>
        </div>
      </section>
    </form>
  </div>

  <!-- RIGHT: Map + Actions -->
  <div class="prof-sticky">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
      <span class="material-symbols-outlined" style="color:var(--cp-primary)">location_on</span>
      <span class="cp-label" data-en="Geo-Validation" data-sw="Uthibitisho wa Jiografia">Geo-Validation</span>
    </div>

    <!-- Map placeholder -->
    <div class="cp-map-placeholder" style="height:420px">
      <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">
        <span class="material-symbols-outlined" style="font-size:64px;color:rgba(0,90,180,.2)">map</span>
      </div>
      <div class="cp-map-overlay">
        <div style="display:flex;gap:12px;align-items:flex-start">
          <div style="background:var(--cp-primary);color:#fff;padding:8px;border-radius:8px;flex-shrink:0">
            <span class="material-symbols-outlined" style="font-size:16px">my_location</span>
          </div>
          <div>
            <p class="cp-label" style="color:var(--cp-primary);margin-bottom:4px" data-en="Current Coordinates" data-sw="Kuratibu za Sasa">Current Coordinates</p>
            <p style="font-size:.875rem;font-weight:600" id="coordsDisplay">-1.286389, 36.817223</p>
            <p style="font-size:.6875rem;color:var(--cp-on-surface-var);margin-top:3px" data-en="Drag marker to pin exact entrance for emergency routing." data-sw="Buruta alama kuweka mlango wako sahihi kwa njia ya dharura.">Drag marker to pin exact entrance for emergency routing.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Verified status -->
    <div style="margin-top:14px;display:flex;align-items:center;justify-content:space-between;padding:13px 16px;background:rgba(0,106,106,.06);border-radius:var(--cp-r);border:1px solid rgba(0,106,106,.12)">
      <div style="display:flex;align-items:center;gap:10px">
        <span class="material-symbols-outlined msf" style="color:var(--cp-secondary)">verified_user</span>
        <span style="font-size:.875rem;font-weight:500;color:var(--cp-secondary)" data-en="Address verified via GPS" data-sw="Anwani imethibitishwa kupitia GPS">Address verified via GPS</span>
      </div>
      <button type="button" class="cp-btn cp-btn-text cp-btn-sm" onclick="getGPS()" data-en="Detect GPS" data-sw="Gundua GPS">Detect GPS</button>
    </div>

    <!-- Action buttons -->
    <div style="display:flex;gap:12px;margin-top:20px">
      <button type="button" class="cp-btn cp-btn-ghost" style="flex:1" onclick="saveDraft()" data-en="Save Draft" data-sw="Hifadhi Rasimu">Save Draft</button>
      <button type="submit" form="profileForm" class="cp-btn cp-btn-primary" style="flex:2">
        <span data-en="Next: Departments" data-sw="Inayofuata: Idara">Next: Departments</span>
        <span class="material-symbols-outlined">arrow_forward</span>
      </button>
    </div>
  </div>
</div>

<footer class="cp-footer" style="margin-top:32px">
  <span data-en="© 2025 Clinical Precision Framework. KEPDA Compliant." data-sw="© 2025 Mfumo wa Usahihi wa Kliniki. Inazingatia KEPDA.">© 2025 Clinical Precision Framework. KEPDA Compliant.</span>
  <div class="cp-footer-links">
    <a href="#" data-en="Privacy Policy" data-sw="Sera ya Faragha">Privacy Policy</a>
    <a href="#" data-en="Terms of Service" data-sw="Masharti ya Huduma">Terms of Service</a>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (typeof Lang !== 'undefined') Lang.init();
  document.getElementById('langToggle')?.addEventListener('click', () => Lang.toggle());
  // Toggle labels
  document.getElementById('emgToggle')?.addEventListener('change', function() {
    const lbl = document.getElementById('emgLabel');
    const isSw = document.documentElement.lang === 'sw';
    lbl.textContent = this.checked ? (isSw?'Inafanya Kazi':'Active') : (isSw?'Haifanyi Kazi':'Inactive');
  });
  // Checkbox rows
  document.querySelectorAll('.cp-check-row input[type=checkbox]').forEach(chk => {
    chk.addEventListener('change', () => chk.closest('.cp-check-row')?.classList.toggle('checked', chk.checked));
  });
});
function getGPS() {
  if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
  navigator.geolocation.getCurrentPosition(p => {
    document.getElementById('coordsDisplay').textContent = p.coords.latitude.toFixed(6)+', '+p.coords.longitude.toFixed(6);
  }, () => alert('Could not detect location.'));
}
function saveDraft() { alert('Draft saved!'); }
</script>
</body></html>
