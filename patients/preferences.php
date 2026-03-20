<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
$noSidebar = true;
$pageTitle = 'Choose Your Service';
include dirname(__DIR__). '/includes/header.php';
$csrf = Security::csrfToken();
$services = [
  ['value'=>'healthcare','icon'=>'medical_information','title'=>'Planeazzy for Healthcare','desc'=>'Comprehensive digital health records and personalised insights.'],
  ['value'=>'doctors',   'icon'=>'stethoscope',        'title'=>'Planeazzy for Doctors',   'desc'=>'Find and book verified specialist doctors near you.'],
  ['value'=>'clinics',   'icon'=>'local_pharmacy',     'title'=>'Planeazzy for Clinics',   'desc'=>'Browse and book nearby outpatient clinics and hospitals.'],
  ['value'=>'ambulance', 'icon'=>'ambulance',           'title'=>'Planeazzy for Ambulance', 'desc'=>'Request emergency ambulance dispatch with real-time tracking.'],
];
?>
<main class="page-main">
  <div style="width:100%;max-width:680px">
    <div class="step-wrap">
      <div class="step-row">
        <span class="step-badge"><span class="material-symbols-outlined">tune</span> Step 4 of 5</span>
        <span class="pct">60% Complete</span>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:60%"></div></div>
    </div>
    <div class="auth-card" style="max-width:100%;padding:32px 28px">
      <div class="card-icon"><span class="material-symbols-outlined">tune</span></div>
      <h1 class="card-title">What brings you to Planeazzy?</h1>
      <p class="card-sub">Choose your primary service. You can access all services from your dashboard anytime.</p>
      <div id="alertBox" class="alert hidden"><span class="material-symbols-outlined">error</span><span id="alertMsg"></span></div>
      <div class="svc-grid" style="margin-bottom:24px">
        <?php foreach($services as $i=>$s): ?>
        <label class="svc-label">
          <input type="radio" name="service" value="<?=$s['value']?>" <?=$i===0?'checked':''?>>
          <div class="svc-card">
            <div class="svc-ico"><span class="material-symbols-outlined"><?=$s['icon']?></span></div>
            <div>
              <div class="svc-title"><?=htmlspecialchars($s['title'])?></div>
              <div class="svc-desc"><?=htmlspecialchars($s['desc'])?></div>
            </div>
            <span class="material-symbols-outlined svc-check">check_circle</span>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="foot-actions">
        <a href="/patients/verify-email.php" class="btn btn-ghost btn-sm">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg> Back
        </a>
        <button id="continueBtn" class="btn btn-primary" onclick="doContinue()">
          Continue <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </button>
      </div>
    </div>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
async function doContinue(){
  const sel=document.querySelector('input[name="service"]:checked');
  if(!sel){
    document.getElementById('alertBox').className='alert alert-warn';
    document.getElementById('alertMsg').textContent='Please select a service.';
    document.getElementById('alertBox').classList.remove('hidden'); return;
  }
  const pid=sessionStorage.getItem('pz_pid_pref')||0;
  const r=await post('/api/patient/save-preferences.php',{patient_id:parseInt(pid),service:sel.value,csrf_token:'<?=htmlspecialchars($csrf)?>'},'continueBtn','alertBox');
  if(r?.success||!pid){sessionStorage.removeItem('pz_pid_pref');location.href='/patients/account-ready.php';}
  else{
    document.getElementById('alertBox').className='alert alert-err';
    document.getElementById('alertMsg').textContent=r?.message||'Failed to save.';
    document.getElementById('alertBox').classList.remove('hidden');
  }
}
</script>
