<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();
if (empty($_SESSION['hospital_id'])) { header('Location: /hospital/onboarding/signup.php'); exit; }
$hid  = (int)$_SESSION['hospital_id'];
$csrf = Security::csrfToken();
$db   = Database::getInstance();
$depts = $db->fetchAll('SELECT * FROM hospital_departments WHERE hospital_id=:id AND is_active=1 ORDER BY sort_order,name',[':id'=>$hid]);
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = trim($_POST['csrf_token'] ?? '');
    $act = $_POST['action'] ?? '';
    if (!Security::verifyCsrf($tok)) { $error = 'Security error.'; }
    elseif ($act === 'add') {
        $n = Security::clean($_POST['dept_name'] ?? '');
        $ic = Security::clean($_POST['dept_icon'] ?? 'stethoscope');
        if (!$n) { $error = 'Department name is required.'; }
        else {
            $db->query('INSERT INTO hospital_departments (hospital_id,name,icon,sort_order,created_at) VALUES (:h,:n,:i,:s,NOW())',
                [':h'=>$hid,':n'=>$n,':i'=>$ic,':s'=>count($depts)]);
            $success = "Department \"$n\" added!";
            $depts = $db->fetchAll('SELECT * FROM hospital_departments WHERE hospital_id=:id AND is_active=1 ORDER BY sort_order,name',[':id'=>$hid]);
        }
    } elseif ($act === 'delete') {
        $did = (int)($_POST['dept_id'] ?? 0);
        if ($did) { $db->query('UPDATE hospital_departments SET is_active=0 WHERE id=:d AND hospital_id=:h',[':d'=>$did,':h'=>$hid]); }
        header('Location: /hospital/onboarding/departments.php'); exit;
    } elseif ($act === 'continue') {
        $db->query('UPDATE hospital_providers SET onboarding_step=6 WHERE id=:id',[':id'=>$hid]);
        header('Location: /hospital/onboarding/regulatory.php'); exit;
    }
}

$deptIcons = ['stethoscope','child_care','pregnant_woman','cardiology','biotech','visibility','orthopedics','psychology','local_pharmacy','emergency','science','medical_services'];
$cpStep = 6; $cpTitle = 'Facility Activation';
include __DIR__ . '/_head.php';
?>
<style>
.act-layout{max-width:1100px;margin:0 auto;padding:40px 40px 60px}
.act-grid{display:grid;grid-template-columns:240px 1fr;gap:40px;align-items:start}
.dept-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:900px){.act-grid{grid-template-columns:1fr}.dept-grid{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.dept-grid{grid-template-columns:1fr}}
</style>

<!-- Progress rail -->
<div class="cp-progress-rail"><div class="cp-progress-fill" style="width:66%"></div></div>

<!-- Topnav -->
<header class="cp-topnav">
  <a href="/hospital/onboarding/join.php" class="cp-topnav-brand" data-en="Clinical Precision" data-sw="Usahihi wa Kliniki">Clinical Precision</a>
  <div class="cp-topnav-actions">
    <a href="#" class="cp-topnav-link" data-en="Setup Guide" data-sw="Mwongozo wa Usanidi">Setup Guide</a>
    <a href="#" class="cp-topnav-link" data-en="Support" data-sw="Msaada">Support</a>
    <button class="cp-lang-btn" id="langToggle"><span class="material-symbols-outlined" style="font-size:15px">language</span><span id="langLabel">SW</span></button>
  </div>
</header>

<div class="act-layout">
  <!-- Page header -->
  <header style="margin-bottom:36px">
    <h1 class="cp-h2" style="margin-bottom:8px" data-en="Facility Activation" data-sw="Uanzishaji wa Kituo">Facility Activation</h1>
    <p class="cp-body" data-en="Complete your first-time clinical setup to begin accepting patients. This configuration will define your operational workflow and compliance standards." data-sw="Kamilisha usanidi wako wa kwanza wa kliniki kuanza kukubali wagonjwa. Usanidi huu utafafanua mtiririko wako wa kazi na viwango vya utiifu.">Complete your first-time clinical setup to begin accepting patients. This configuration will define your operational workflow and compliance standards.</p>
  </header>

  <!-- Progress bar -->
  <div style="width:100%;height:4px;background:rgba(193,198,213,.2);border-radius:9999px;margin-bottom:48px;overflow:hidden">
    <div style="height:100%;background:var(--cp-primary);width:25%;transition:width .5s ease"></div>
  </div>

  <div class="act-grid">
    <!-- Sidebar steps -->
    <aside style="position:sticky;top:84px">
      <?php foreach([
        [1,'domain','Departments','Idara',true,false],
        [2,'medical_services','Doctors','Madaktari',false,false],
        [3,'schedule','Global Shifts','Zamu za Jumla',false,false],
        [4,'settings_suggest','Appointments','Miadi',false,false],
      ] as [$n,$ic,$en,$sw,$active,$done]): ?>
      <div class="cp-setup-step <?=$active?'active':($done?'done':'')?>">
        <div class="cp-setup-step-num"><?=$done?'✓':$n?></div>
        <div>
          <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:<?=$active?'var(--cp-primary)':'var(--cp-outline)'?>;margin-bottom:2px"
               data-en="Step <?=$n?>" data-sw="Hatua <?=$n?>">Step <?=$n?></div>
          <div style="font-weight:<?=$active?700:500?>;color:<?=$active?'var(--cp-on-surface)':'var(--cp-on-surface-var)'?>"
               data-en="<?=htmlspecialchars($en)?>" data-sw="<?=htmlspecialchars($sw)?>"><?=$en?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </aside>

    <!-- Main content -->
    <main>
      <?php if ($error): ?>
      <div class="cp-alert cp-alert-error" style="margin-bottom:18px"><span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0">error</span><?= htmlspecialchars($error) ?></div>
      <?php elseif ($success): ?>
      <div class="cp-alert cp-alert-success" style="margin-bottom:18px"><span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0">check_circle</span><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <!-- Departments section -->
      <section>
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:24px;flex-wrap:wrap;gap:12px">
          <div>
            <h2 class="cp-h3" style="margin-bottom:4px" data-en="Manage Departments" data-sw="Simamia Idara">Manage Departments</h2>
            <p class="cp-body-sm" data-en="Define the specialized units within your facility." data-sw="Fafanua vitengo maalum ndani ya kituo chako.">Define the specialized units within your facility.</p>
          </div>
          <button class="cp-btn cp-btn-primary cp-btn-sm" onclick="openModal('addDeptModal')">
            <span class="material-symbols-outlined">add</span>
            <span data-en="Add Department" data-sw="Ongeza Idara">Add Department</span>
          </button>
        </div>

        <div class="dept-grid">
          <?php foreach ($depts as $d): ?>
          <div class="cp-dept-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
              <div class="cp-dept-icon">
                <span class="material-symbols-outlined"><?= htmlspecialchars($d['icon']) ?></span>
              </div>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this department?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="dept_id" value="<?= (int)$d['id'] ?>">
                <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--cp-outline-var);padding:4px;transition:color .15s" title="Delete"
                        onmouseover="this.style.color='var(--cp-primary)'" onmouseout="this.style.color='var(--cp-outline-var)'">
                  <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                </button>
              </form>
            </div>
            <h3 class="cp-h4" style="margin-bottom:4px"><?= htmlspecialchars($d['name']) ?></h3>
            <p style="font-size:.8125rem;color:var(--cp-on-surface-var)">
              <?= (int)($d['capacity'] ?? 0) ?> <span data-en="Doctors Assigned" data-sw="Madaktari Waliowekwa">Doctors Assigned</span>
            </p>
          </div>
          <?php endforeach; ?>

          <!-- Add more card -->
          <div class="cp-dept-add" onclick="openModal('addDeptModal')">
            <span class="material-symbols-outlined" style="font-size:28px;color:var(--cp-outline-var)">add_circle</span>
            <span style="font-size:.875rem;font-weight:700;color:var(--cp-on-surface-var)" data-en="Add More" data-sw="Ongeza Zaidi">Add More</span>
          </div>
        </div>
      </section>

      <!-- KEPDA security section -->
      <section style="background:var(--cp-surface-container-low);border-radius:var(--cp-r-xl);padding:24px;margin-top:28px;display:flex;align-items:center;gap:16px">
        <div style="width:42px;height:42px;border-radius:50%;background:var(--cp-primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <span class="material-symbols-outlined msf" style="color:#fff;font-size:20px">security</span>
        </div>
        <div>
          <h4 class="cp-h4" style="margin-bottom:2px" data-en="KEPDA Data Encryption Active" data-sw="Usimbuaji wa Data wa KEPDA Umewashwa">KEPDA Data Encryption Active</h4>
          <p style="font-size:.8125rem;color:var(--cp-on-surface-var)" data-en="All provider credentials and patient records are encrypted at rest." data-sw="Vitambulisho vyote vya watoa huduma na rekodi za wagonjwa vimesimbwa wakati wa mapumziko.">All provider credentials and patient records are encrypted at rest.</p>
        </div>
      </section>

      <!-- Feature cards -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:48px">
        <?php foreach([
          ['clinical_notes','Automated Roster','Roster ya Kiotomatiki','Clinical Precision handles overlapping shifts and emergency on-call rotations automatically.','Clinical Precision inashughulikia zamu zinazoingiliana na mzunguko wa dharura wa simu kiotomatiki.'],
          ['verified_user','Board Verification','Uthibitisho wa Bodi','Medical license numbers are cross-referenced with national medical boards for instant credentialing.','Nambari za leseni za matibabu zinarejelewa na bodi za kitaifa za matibabu.'],
          ['speed','Efficiency Tuning','Urekebishaji wa Ufanisi','Optimize patient throughput with per-department consultation limits and smart interval scheduling.','Boresha mtiririko wa wagonjwa na mipaka ya mashauriano kwa idara na ratiba ya akili.'],
        ] as [$ic,$en,$sw,$desc,$descSw]): ?>
        <div class="cp-glass" style="padding:28px;border-radius:var(--cp-r-xl);border:1px solid rgba(255,255,255,.2)">
          <span class="material-symbols-outlined" style="color:var(--cp-secondary);font-size:28px;display:block;margin-bottom:14px"><?=$ic?></span>
          <h3 class="cp-h4" style="margin-bottom:8px" data-en="<?=htmlspecialchars($en)?>" data-sw="<?=htmlspecialchars($sw)?>"><?=$en?></h3>
          <p style="font-size:.8125rem;color:var(--cp-on-surface-var);line-height:1.6" data-en="<?=htmlspecialchars($desc)?>" data-sw="<?=htmlspecialchars($descSw)?>"><?=$desc?></p>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Continue button -->
      <div style="display:flex;justify-content:flex-end;margin-top:40px">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="continue">
          <button type="submit" class="cp-btn cp-btn-primary cp-btn-lg">
            <span data-en="Continue to Verification" data-sw="Endelea kwa Uthibitisho">Continue to Verification</span>
            <span class="material-symbols-outlined">arrow_forward</span>
          </button>
        </form>
      </div>
    </main>
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

<!-- Add Dept Modal -->
<div class="cp-modal-overlay" id="addDeptModal">
  <div class="cp-modal">
    <div class="cp-modal-header">
      <h2 class="cp-h3" data-en="Add Department" data-sw="Ongeza Idara">Add Department</h2>
      <button onclick="closeModal('addDeptModal')" style="background:none;border:none;cursor:pointer;padding:4px">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="add">
      <div class="cp-field">
        <label class="cp-label-text" data-en="Department Name" data-sw="Jina la Idara">Department Name</label>
        <input class="cp-input" type="text" name="dept_name" placeholder="e.g., Cardiology, Neurology" required autofocus>
      </div>
      <div class="cp-field">
        <label class="cp-label-text" data-en="Icon" data-sw="Ikoni">Icon</label>
        <select class="cp-input cp-select" name="dept_icon">
          <?php foreach ($deptIcons as $i): ?><option value="<?=$i?>"><?=$i?></option><?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="cp-btn cp-btn-primary cp-btn-full">
        <span class="material-symbols-outlined">add</span>
        <span data-en="Add Department" data-sw="Ongeza Idara">Add Department</span>
      </button>
    </form>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  if(typeof Lang!=='undefined')Lang.init();
  document.getElementById('langToggle')?.addEventListener('click',()=>Lang.toggle());
});
function openModal(id){document.getElementById(id)?.classList.add('open');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id)?.classList.remove('open');document.body.style.overflow='';}
// Close modal on overlay click
document.querySelectorAll('.cp-modal-overlay').forEach(o=>o.addEventListener('click',e=>{if(e.target===o)closeModal(o.id);}));
</script>
</body></html>
