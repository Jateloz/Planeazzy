<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
$noSidebar = true; $pageTitle = 'Choose Your Service';
include dirname(__DIR__). '/includes/header.php';
$csrf = Security::csrfToken();
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--bg-light)">
  <div style="width:100%;max-width:560px">
    <div class="auth-card slide-up">
      <div class="step-wrap">
        <div class="step-row"><span class="step-badge"><i class="fa-solid fa-heart-pulse"></i> <span data-en="Choose Service" data-sw="Chagua Huduma">Choose Service</span></span><span class="step-pct" data-en="Step 3 of 3" data-sw="Hatua 3 ya 3">Step 3 of 3</span></div>
        <div class="prog-track"><div class="prog-fill" style="width:90%"></div></div>
      </div>
      <h2 style="font-size:22px;font-weight:900;color:var(--slate-900);margin-bottom:6px;letter-spacing:-.03em" data-en="What brings you to Planeazzy?" data-sw="Ni nini kinakukuleta kwa Planeazzy?">What brings you to Planeazzy?</h2>
      <p style="font-size:14px;color:var(--slate-500);margin-bottom:22px" data-en="Choose your primary healthcare need. You can change this anytime in Settings." data-sw="Chagua hitaji lako kuu la afya. Unaweza kubadilisha hii wakati wowote katika Mipangilio.">Choose your primary healthcare need. You can change this anytime in Settings.</p>
      <div id="alertBox" class="alert hidden"><i class="fa-solid fa-circle-exclamation"></i><span id="alertMsg"></span></div>
      <div class="svc-grid" id="svcGrid">
        <?php
        $svcs = [
          ['healthcare','fa-hospital',     'General Healthcare',  'Doctor visits, check-ups, and general consultations'],
          ['doctors',   'fa-stethoscope',  'Find Specialists',    'Cardiologists, dermatologists and specialist doctors'],
          ['clinics',   'fa-house-medical','Clinic Services',     'Local clinics, outpatient care and diagnostic centers'],
          ['ambulance', 'fa-truck-medical','Emergency Services',  '24/7 ambulance dispatch and emergency care'],
        ];
        foreach ($svcs as [$val, $icon, $title, $desc]):
        ?>
        <label class="svc-label">
          <input type="radio" name="svc" value="<?= $val ?>" onchange="document.getElementById('selVal').value=this.value">
          <div class="svc-card">
            <div class="svc-ico"><i class="fa-solid <?= $icon ?>"></i></div>
            <div class="svc-title"><?= $title ?></div>
            <div class="svc-desc"><?= $desc ?></div>
            <i class="fa-solid fa-check-circle svc-check"></i>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <input type="hidden" id="selVal" value="">
      <div style="display:flex;gap:12px;margin-top:22px;padding-top:20px;border-top:1px solid var(--slate-100)">
        <a href="/patients/login.php" class="btn btn-ghost" style="flex:0 0 auto"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <button id="prefBtn" class="btn btn-primary btn-full btn-lg" onclick="savePref()"><i class="fa-solid fa-arrow-right"></i> Continue</button>
      </div>
    </div>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
async function savePref(){
  const v=document.getElementById('selVal').value;
  if(!v){UI.alert('warn','Please select a service.','alertBox');return;}
  const r=await post('/api/patient/save-preferences.php',{service:v,csrf_token:document.getElementById('csrfToken').value},'prefBtn','alertBox');
  if(r?.success)location.href='/patients/account-ready.php';
  else UI.alert('err',r?.message||'Could not save preferences.','alertBox');
}
</script>
