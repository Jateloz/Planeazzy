<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();
if (empty($_SESSION['hospital_id'])) { header('Location: /hospital/onboarding/signup.php'); exit; }
$hid  = (int)$_SESSION['hospital_id'];
$db   = Database::getInstance();
$hosp = $db->fetchOne('SELECT * FROM hospital_providers WHERE id=:id',[':id'=>$hid]);
if (($hosp['status']??'') === 'approved' && $hosp['is_active']) {
    $_SESSION['hospital_auth'] = true;
    header('Location: /hospital/onboarding/dashboard.php'); exit;
}
$cpStep = 8; $cpTitle = 'Verification in Progress';
include __DIR__ . '/_head.php';

$verSteps = [
  ['check_circle','done','Application Submitted','Maombi Yamewasilishwa',true,
   'Initial registration data received and logged in secure vault.',
   'Data ya usajili wa awali ilipokewa na kuingia kwenye vault salama.'],
  ['check_circle','done','Initial Review','Mapitio ya Awali',!empty($hosp['kmpdc_number']),
   'Identity documents and facility licence validated against registry.',
   'Hati za utambulisho na leseni ya kituo zilithibitishwa dhidi ya rejista.'],
  ['clinical_notes','active','Compliance Audit','Ukaguzi wa Utiifu',false,
   'Deep verification of operational standards and KEPDA data protocols.',
   'Uthibitisho wa kina wa viwango vya uendeshaji na itifaki za data za KEPDA.'],
  ['lock','pending','Account Activation','Uanzishaji wa Akaunti',false,
   'Final handover of provider dashboard and cryptographic keys.',
   'Ugawaji wa mwisho wa dashibodi ya mtoa huduma na funguo za siri.'],
];
$status = $hosp['status'] ?? 'pending';
$refId  = 'CP-' . str_pad($hid,6,'0',STR_PAD_LEFT);
?>
<style>
.pnd-wrap{max-width:1100px;margin:0 auto;padding:48px 40px}
.timeline-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:40px}
.info-grid{display:grid;grid-template-columns:7fr 5fr;gap:32px;align-items:start}
@media(max-width:1024px){.info-grid{grid-template-columns:1fr}.pnd-wrap{padding:28px 20px}}
@media(max-width:768px){.timeline-grid{grid-template-columns:1fr 1fr}}
@media(max-width:480px){.timeline-grid{grid-template-columns:1fr}}
</style>

<!-- Progress rail -->
<div class="cp-progress-rail"><div class="cp-progress-fill" style="width:88%"></div></div>

<!-- Topnav -->
<header class="cp-topnav">
  <a href="/hospital/onboarding/join.php" class="cp-topnav-brand" data-en="Clinical Precision" data-sw="Usahihi wa Kliniki">Clinical Precision</a>
  <div class="cp-topnav-actions">
    <button onclick="location.reload()" class="cp-btn cp-btn-ghost cp-btn-sm">
      <span class="material-symbols-outlined" style="font-size:16px">refresh</span>
      <span data-en="Refresh" data-sw="Onyesha Upya">Refresh</span>
    </button>
    <button class="cp-lang-btn" id="langToggle"><span class="material-symbols-outlined" style="font-size:15px">language</span><span id="langLabel">SW</span></button>
  </div>
</header>

<div class="pnd-wrap">
  <!-- Header -->
  <header style="margin-bottom:36px">
    <div style="display:flex;align-items:center;gap:10px;color:var(--cp-primary);margin-bottom:14px">
      <span class="material-symbols-outlined msf">verified_user</span>
      <span class="cp-label" style="color:var(--cp-primary)" data-en="KEPDA Compliance Hub" data-sw="Kituo cha Utiifu wa KEPDA">KEPDA Compliance Hub</span>
    </div>
    <h1 class="cp-h1" style="margin-bottom:14px">
      <span data-en="Verification in Progress" data-sw="Uthibitisho Unaendelea">Verification in Progress</span>
      <span style="color:var(--cp-on-surface-var);font-weight:500"> (24–72 </span><span style="color:var(--cp-on-surface-var);font-weight:500" data-en="hours)" data-sw="masaa)">hours)</span>
    </h1>
    <p class="cp-body-lg" style="max-width:560px"
       data-en="Your facility credentials are currently under clinical audit. Our compliance team is cross-referencing your medical registry data to ensure full security vault integration."
       data-sw="Vitambulisho vya kituo chako viko chini ya ukaguzi wa kliniki. Timu yetu ya utiifu inarejelea data yako ya usajili wa matibabu kuhakikisha muundo kamili wa vault ya usalama.">
      Your facility credentials are currently under clinical audit. Our compliance team is cross-referencing your medical registry data to ensure full security vault integration.
    </p>
  </header>

  <!-- Timeline cards -->
  <div class="timeline-grid">
    <?php foreach ($verSteps as [$icon,$state,$en,$sw,$done,$desc,$descSw]): ?>
    <div class="cp-verify-step <?= $state==='active'?'active':($state==='pending'?'pending':'') ?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div class="cp-verify-icon" style="background:<?= $done?'var(--cp-secondary-container)':($state==='active'?'var(--cp-primary-fixed)':'var(--cp-surface-container-highest)') ?>">
          <span class="material-symbols-outlined msf" style="color:<?= $done?'var(--cp-secondary)':($state==='active'?'var(--cp-primary)':'var(--cp-on-surface-var)') ?>"><?= $icon ?></span>
        </div>
        <?php if ($done): ?>
        <span class="cp-badge cp-badge-success" data-en="Completed" data-sw="Imekamilika">Completed</span>
        <?php elseif ($state === 'active'): ?>
        <div class="cp-verify-ping">
          <div class="cp-verify-ping-dot"></div>
          <span class="cp-badge cp-badge-primary" data-en="Active Audit" data-sw="Ukaguzi Unaoendelea">Active Audit</span>
        </div>
        <?php else: ?>
        <span class="cp-badge cp-badge-warning" data-en="Locked" data-sw="Imefungwa">Locked</span>
        <?php endif; ?>
      </div>
      <div>
        <h3 class="cp-h4" style="margin-bottom:6px" data-en="<?= htmlspecialchars($en) ?>" data-sw="<?= htmlspecialchars($sw) ?>"><?= $en ?></h3>
        <p style="font-size:.8125rem;color:var(--cp-on-surface-var);line-height:1.55" data-en="<?= htmlspecialchars($desc) ?>" data-sw="<?= htmlspecialchars($descSw) ?>"><?= $desc ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Information + Support grid -->
  <div class="info-grid">
    <!-- Why Verification Matters -->
    <div>
      <div class="cp-card" style="padding:32px;border-left:4px solid var(--cp-primary);border-radius:0 var(--cp-r-xl) var(--cp-r-xl) 0">
        <h2 class="cp-h3" style="margin-bottom:20px" data-en="Why Verification Matters" data-sw="Kwa Nini Uthibitisho Ni Muhimu">Why Verification Matters</h2>
        <?php foreach([
          ['security','End-to-End Encryption','Usimbuaji wa Mwisho hadi Mwisho','We ensure your patient data is isolated in a localized sovereign vault before access is granted.','Tunahakikisha data yako ya wagonjwa imewekwa katika vault ya uhuru wa ndani kabla ya ufikiaji kutolewa.'],
          ['verified','Legal Compliance','Utiifu wa Kisheria','Automated validation against the latest Kenyan Ministry of Health electronic standards.','Uthibitisho wa kiotomatiki dhidi ya viwango vya hivi karibuni vya elektroniki vya Wizara ya Afya ya Kenya.'],
          ['diversity_3','National Registry Sync','Muundo na Rejista ya Kitaifa','All doctor licences and facility accreditations are live-synced with national health databases.','Leseni zote za madaktari na ithibati za kituo zinafanya muundo wa moja kwa moja na hifadhidata za afya za kitaifa.'],
        ] as [$ic,$en,$sw,$desc,$descSw]): ?>
        <div style="display:flex;gap:14px;margin-bottom:20px">
          <span class="material-symbols-outlined" style="color:var(--cp-secondary);flex-shrink:0;margin-top:1px"><?=$ic?></span>
          <div>
            <h4 class="cp-h4" style="margin-bottom:4px" data-en="<?=htmlspecialchars($en)?>" data-sw="<?=htmlspecialchars($sw)?>"><?=$en?></h4>
            <p class="cp-body-sm" data-en="<?=htmlspecialchars($desc)?>" data-sw="<?=htmlspecialchars($descSw)?>"><?=$desc?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Reference -->
      <div style="margin-top:20px;padding:18px 20px;background:var(--cp-surface-container-low);border-radius:var(--cp-r-lg);display:flex;align-items:center;gap:14px">
        <span class="material-symbols-outlined msf" style="color:var(--cp-primary)">info</span>
        <div>
          <span class="cp-label" data-en="Application Reference" data-sw="Rejea ya Maombi">Application Reference</span>
          <div style="font-size:1.125rem;font-weight:800;font-family:monospace;color:var(--cp-primary);margin-top:2px"><?= $refId ?></div>
        </div>
        <div style="margin-left:auto;text-align:right">
          <div style="font-size:.75rem;color:var(--cp-on-surface-var)"><?= date('d M Y, H:i') ?></div>
          <span class="cp-badge cp-badge-warning" style="margin-top:4px" data-en="<?= ucfirst($status) ?>" data-sw="<?= ucfirst($status) ?>"><?= ucfirst($status) ?></span>
        </div>
      </div>

      <!-- Action bar -->
      <div style="margin-top:24px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:14px;padding-top:24px;border-top:1px solid rgba(193,198,213,.18)">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:36px;height:36px;border-radius:50%;background:rgba(0,106,106,.1);display:flex;align-items:center;justify-content:center">
            <span class="material-symbols-outlined" style="color:var(--cp-secondary);font-size:18px">info</span>
          </div>
          <span style="font-size:.8125rem;color:var(--cp-on-surface-var)" data-en="Your progress is automatically synced with the Precision Hub." data-sw="Maendeleo yako yanafanya muundo wa kiotomatiki na Precision Hub.">Your progress is automatically synced with the Precision Hub.</span>
        </div>
        <div style="display:flex;gap:10px">
          <a href="/hospital/onboarding/regulatory.php" class="cp-btn cp-btn-ghost cp-btn-sm" data-en="Edit Details" data-sw="Hariri Maelezo">Edit Details</a>
          <button class="cp-btn cp-btn-primary cp-btn-sm" onclick="location.reload()">
            <span class="material-symbols-outlined" style="font-size:16px">refresh</span>
            <span data-en="Refresh Status" data-sw="Onyesha Upya Hali">Refresh Status</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Need Assistance -->
    <div>
      <div class="cp-card" style="padding:28px;overflow:hidden;position:relative;min-height:260px;display:flex;flex-direction:column;justify-content:flex-end">
        <div style="position:absolute;inset:0;background:url('https://images.unsplash.com/photo-1551190822-a9333d879b1f?w=500&q=60') center/cover;opacity:.08;mix-blend-mode:multiply"></div>
        <div style="position:relative;z-index:1">
          <h3 class="cp-h4" style="margin-bottom:10px" data-en="Need Assistance?" data-sw="Unahitaji Msaada?">Need Assistance?</h3>
          <p class="cp-body-sm" style="margin-bottom:18px" data-en="If you believe there is a discrepancy in your documentation, contact our clinical audit desk directly." data-sw="Ikiwa unaamini kuna tofauti katika hati zako, wasiliana moja kwa moja na dawati letu la ukaguzi wa kliniki.">If you believe there is a discrepancy in your documentation, contact our clinical audit desk directly.</p>
          <button class="cp-btn cp-btn-ghost cp-btn-full">
            <span class="material-symbols-outlined" style="font-size:18px">support_agent</span>
            <span data-en="Contact Support" data-sw="Wasiliana na Msaada">Contact Support</span>
          </button>
        </div>
      </div>

      <!-- Email notification -->
      <div style="margin-top:14px;padding:16px 18px;background:var(--cp-surface-container-low);border-radius:var(--cp-r-lg)">
        <p style="font-size:.875rem;color:var(--cp-on-surface-var);margin-bottom:4px"
           data-en="You'll receive an email notification at:" data-sw="Utapokea arifa ya barua pepe kwa:">You'll receive an email notification at:</p>
        <p style="font-size:.9375rem;font-weight:700;color:var(--cp-primary)"><?= htmlspecialchars($hosp['admin_email'] ?? '') ?></p>
      </div>
    </div>
  </div>
</div>

<footer class="cp-footer">
  <span data-en="© 2025 Clinical Precision Framework. KEPDA Compliant." data-sw="© 2025 Mfumo wa Usahihi wa Kliniki. Inazingatia KEPDA.">© 2025 Clinical Precision Framework. KEPDA Compliant.</span>
  <div class="cp-footer-links">
    <a href="#" data-en="Privacy Policy" data-sw="Sera ya Faragha">Privacy Policy</a>
    <a href="#" data-en="Terms of Service" data-sw="Masharti ya Huduma">Terms of Service</a>
    <a href="#" data-en="Security Vault" data-sw="Vault ya Usalama">Security Vault</a>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  if(typeof Lang!=='undefined')Lang.init();
  document.getElementById('langToggle')?.addEventListener('click',()=>Lang.toggle());
  // Auto-refresh every 60s
  setTimeout(()=>location.reload(), 60000);
});
</script>
</body></html>
