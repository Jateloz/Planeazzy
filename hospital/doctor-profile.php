<?php
/**
 * Planeazzy — /hospital/doctor-profile.php
 * Full page for hospital to manage a doctor's profile, photo, and details.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Database.php';
Security::startSession();

// Auth check
if (empty($_SESSION['provider_id']) || empty($_SESSION['is_provider'])) {
    header('Location: /hospital/login.php'); exit;
}
$pvid  = (int)$_SESSION['provider_id'];
$db    = Database::getInstance();
$csrf  = Security::csrfToken();
$hName = htmlspecialchars($_SESSION['provider_name'] ?? 'Hospital');

// Get doctor ID from URL
$docId = (int)($_GET['id'] ?? 0);
$isNew = ($docId === 0);
$doc   = null;

if (!$isNew) {
    $doc = $db->fetchOne('SELECT * FROM providers WHERE id=:id AND type="doctor"', [':id'=>$docId]);
    if (!$doc) {
        header('Location: /hospital/dashboard.php?tab=doctors&error=not_found'); exit;
    }
    // Ensure avatar_path column
    try { $db->query('ALTER TABLE providers ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) DEFAULT NULL'); } catch(Exception $e) {}
}

$hasAvatar = !empty($doc['avatar_path']);
$avatarSrc = $hasAvatar ? htmlspecialchars($doc['avatar_path']).'?t='.time() : '';
$dName     = htmlspecialchars($doc['name'] ?? '');
$dInitials = strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', trim($dName ?: 'DR')), 0, 2))));

// Appointment history for this doctor
$apptHistory = [];
if (!$isNew && $docId) {
    try {
        $apptHistory = $db->fetchAll(
            'SELECT a.*, pt.first_name, pt.last_name FROM appointments a
             LEFT JOIN patients pt ON a.patient_id = pt.id
             WHERE a.provider_id=:did ORDER BY a.appointment_at DESC LIMIT 20',
            [':did'=>$docId]
        );
    } catch(Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= $isNew ? 'Add Doctor' : 'Dr. '.$dName ?> — <?= $hName ?> · Planeazzy</title>
  <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/hospital.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;min-height:100vh;font-family:'Inter',sans-serif;background:#f0f4ff;color:#0f172a}
a{text-decoration:none;color:inherit}
button,input,select,textarea{font-family:inherit}

/*  Top nav  */
.dp-nav{background:linear-gradient(135deg,#0f172a 0%,#1462c4 60%,#0d9488 100%);padding:0}
.dp-nav-inner{max-width:100%;padding:0 24px;height:56px;display:flex;align-items:center;gap:14px}
.dp-nav img{height:32px;width:auto;filter:brightness(0) invert(1)}
.dp-nav-back{display:flex;align-items:center;gap:7px;padding:7px 14px;border-radius:8px;background:rgba(255,255,255,.1);color:#fff;font-size:13px;font-weight:700;border:1.5px solid rgba(255,255,255,.2);cursor:pointer;transition:background .15s;margin-left:auto}
.dp-nav-back:hover{background:rgba(255,255,255,.2)}
.dp-nav-back i{font-size:11px}

/*  Layout  */
.dp-body{max-width:100%;padding:24px 28px;display:grid;grid-template-columns:300px 1fr;gap:22px;align-items:start}
.dp-left{display:flex;flex-direction:column;gap:16px}
.dp-right{display:flex;flex-direction:column;gap:16px}

/*  Card  */
.dp-card{background:#fff;border-radius:16px;border:1.5px solid #f1f5f9;box-shadow:0 1px 4px rgba(0,0,0,.04),0 4px 16px rgba(0,0,0,.03);overflow:hidden}
.dp-card-hdr{padding:14px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between}
.dp-card-title{font-size:13.5px;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px}
.dp-card-title i{font-size:13px;color:#1978e5}
.dp-card-body{padding:20px 18px}

/*  Profile photo section  */
.dp-photo-wrap{text-align:center;padding:28px 20px 20px}
.dp-av{width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,#0d9488,#059669);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:900;color:#fff;margin:0 auto 16px;overflow:hidden;border:4px solid #fff;box-shadow:0 4px 20px rgba(0,0,0,.12);position:relative}
.dp-av img{width:100%;height:100%;object-fit:cover}
.dp-av-name{font-size:16px;font-weight:800;color:#0f172a;margin-bottom:4px}
.dp-av-spec{font-size:13px;color:#1978e5;font-weight:600;margin-bottom:4px}
.dp-av-status{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:9999px}
.dp-av-status.on{background:#f0fdf4;color:#16a34a}
.dp-av-status.off{background:#f8fafc;color:#64748b}

/*  Upload zone  */
.dp-upload-zone{border:2px dashed #e2e8f0;border-radius:12px;padding:20px;text-align:center;cursor:pointer;transition:all .2s;background:#fafbfd;margin:0 18px 18px}
.dp-upload-zone:hover{border-color:#1978e5;background:rgba(25,120,229,.03)}
.dp-upload-zone i{font-size:24px;color:#94a3b8;display:block;margin-bottom:8px}
.dp-upload-zone-title{font-size:13px;font-weight:700;color:#475569;margin-bottom:4px}
.dp-upload-zone-sub{font-size:11.5px;color:#94a3b8}

/*  Form  */
.dp-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.dp-grp{margin-bottom:14px}
.dp-lbl{display:block;font-size:10.5px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px}
.dp-inp{width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13.5px;color:#0f172a;background:#fff;outline:none;transition:border-color .18s,box-shadow .18s}
.dp-inp:focus{border-color:#1978e5;box-shadow:0 0 0 3px rgba(25,120,229,.1)}
.dp-inp::placeholder{color:#94a3b8;font-weight:400}
.dp-inp.has-ico{padding-left:36px}
.dp-wrap{position:relative}
.dp-ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;pointer-events:none}
.dp-sel{appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 11px center;padding-right:32px}
.dp-ta{resize:vertical;min-height:90px}

/*  Buttons  */
.dp-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 22px;border-radius:10px;font-size:13.5px;font-weight:700;cursor:pointer;border:none;transition:all .2s;font-family:inherit}
.dp-btn i{font-size:.72em;display:inline-flex;align-items:center}
.dp-btn.primary{background:linear-gradient(135deg,#1462c4,#1978e5);color:#fff;box-shadow:0 3px 10px rgba(25,120,229,.3)}
.dp-btn.primary:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(25,120,229,.42)}
.dp-btn.primary:disabled{opacity:.65;transform:none;cursor:not-allowed}
.dp-btn.ghost{background:#f8fafc;color:#475569;border:1.5px solid #e2e8f0}
.dp-btn.ghost:hover{background:#f1f5f9}
.dp-btn.teal{background:linear-gradient(135deg,#0d9488,#059669);color:#fff;box-shadow:0 3px 10px rgba(13,148,136,.3)}
.dp-btn.full{width:100%}

/*  Alert  */
.dp-alert{display:none;padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px;align-items:flex-start;gap:7px;line-height:1.5}
.dp-alert i{font-size:12px;flex-shrink:0;margin-top:2px}
.dp-alert.err{background:#fef2f2;color:#991b1b;border:1px solid rgba(220,38,38,.2)}
.dp-alert.ok{background:#f0fdf4;color:#065f46;border:1px solid rgba(22,163,74,.2)}

/*  Stats mini  */
.dp-mini-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:4px}
.dp-mini-stat{background:#f8fafc;border-radius:10px;padding:12px;text-align:center;border:1px solid #f1f5f9}
.dp-mini-val{font-size:1.25rem;font-weight:900;color:#0f172a;line-height:1;margin-bottom:3px}
.dp-mini-lbl{font-size:10.5px;color:#64748b;font-weight:500}

/*  Appointment table  */
.dp-table{width:100%;border-collapse:collapse}
.dp-table th{font-size:10.5px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.08em;padding:9px 12px;background:#f8fafc;border-bottom:1.5px solid #e2e8f0;text-align:left}
.dp-table td{padding:11px 12px;border-bottom:1px solid #f8fafc;font-size:13px;color:#334155;vertical-align:middle}
.dp-table tr:last-child td{border-bottom:none}
.dp-table tr:hover td{background:#f8fafc}
.dp-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:10.5px;font-weight:700;text-transform:uppercase}
.dp-pill.scheduled{background:rgba(25,120,229,.1);color:#1978e5}
.dp-pill.completed{background:rgba(22,163,74,.1);color:#16a34a}
.dp-pill.cancelled{background:rgba(220,38,38,.1);color:#dc2626}
.dp-pill.confirmed{background:rgba(13,148,136,.1);color:#0d9488}

/*  Responsive  */
@media(max-width:900px){
  .dp-body{grid-template-columns:1fr;padding:16px}
}
@media(max-width:600px){
  .dp-body{padding:10px}
  .dp-row{grid-template-columns:1fr}
  .dp-nav-inner{padding:0 14px}
  .dp-mini-stats{grid-template-columns:repeat(2,1fr)}
}
</style>
</head>
<body>

<!-- Nav -->
<nav class="dp-nav">
  <div class="dp-nav-inner">
    <a href="/hospital/dashboard.php"><img src="/assets/images/logo.svg" alt="Planeazzy"></a>
    <span style="color:rgba(255,255,255,.5);font-size:13px;font-weight:500"><?=$hName?> · <?=$isNew?'Add Doctor':'Doctor Profile'?></span>
    <a href="/hospital/dashboard.php?tab=doctors" class="dp-nav-back">
      <i class="fa-solid fa-arrow-left"></i> Back to Doctors
    </a>
  </div>
</nav>

<?php if ($isNew || $doc): ?>
<div class="dp-body">

  <!-- LEFT COLUMN -->
  <div class="dp-left">

    <!-- Photo + identity card -->
    <div class="dp-card">
      <div class="dp-photo-wrap">
        <div class="dp-av" id="dpAvPreview">
          <?php if($hasAvatar):?>
            <img src="<?=$avatarSrc?>" alt="<?=$dName?>">
          <?php else:?>
            <span id="dpAvInitials"><?=$dInitials?></span>
          <?php endif;?>
        </div>
        <div class="dp-av-name" id="dpAvName"><?=$dName?:''?></div>
        <div class="dp-av-spec" id="dpAvSpec"><?=htmlspecialchars($doc['specialty']??'')?></div>
        <?php if(!$isNew):?>
        <div class="dp-av-status <?= ($doc['is_available']??0)?'on':'off' ?>">
          <i class="fa-solid fa-circle" style="font-size:7px"></i>
          <?= ($doc['is_available']??0) ? 'Available' : 'Unavailable' ?>
        </div>
        <?php endif;?>
      </div>

      <!-- Upload zone -->
      <div class="dp-upload-zone" onclick="document.getElementById('dpPhotoInput').click()">
        <i class="fa-solid fa-cloud-arrow-up"></i>
        <div class="dp-upload-zone-title">Upload Profile Photo</div>
        <div class="dp-upload-zone-sub">JPG, PNG or WebP · Max 10 MB</div>
        <div id="dpPhotoMsg" style="font-size:12px;margin-top:8px;display:none"></div>
      </div>
      <input type="file" id="dpPhotoInput" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="uploadDpPhoto(this)">

      <?php if(!$isNew):?>
      <!-- Mini stats -->
      <div style="padding:0 18px 18px">
        <?php
        $totalAppts = count($apptHistory);
        $doneAppts  = count(array_filter($apptHistory, fn($a)=>$a['status']==='completed'));
        $thisMonth  = count(array_filter($apptHistory, fn($a)=>date('Y-m',strtotime($a['appointment_at']))===date('Y-m')));
        ?>
        <div class="dp-mini-stats">
          <div class="dp-mini-stat">
            <div class="dp-mini-val"><?=$totalAppts?></div>
            <div class="dp-mini-lbl">Total Appts</div>
          </div>
          <div class="dp-mini-stat">
            <div class="dp-mini-val"><?=$doneAppts?></div>
            <div class="dp-mini-lbl">Completed</div>
          </div>
          <div class="dp-mini-stat">
            <div class="dp-mini-val"><?=$thisMonth?></div>
            <div class="dp-mini-lbl">This Month</div>
          </div>
          <div class="dp-mini-stat">
            <div class="dp-mini-val"><?=number_format($doc['rating']??0,1)?></div>
            <div class="dp-mini-lbl">Rating &#9733;</div>
          </div>
        </div>
      </div>
      <?php endif;?>
    </div>

    <!-- Quick links -->
    <div class="dp-card">
      <div class="dp-card-body" style="display:flex;flex-direction:column;gap:8px;padding:14px">
        <a href="/patients/book.php" class="dp-btn primary full">
          <i class="fa-solid fa-calendar-plus"></i> Book Appointment
        </a>
        <a href="/hospital/dashboard.php?tab=appointments" class="dp-btn ghost full">
          <i class="fa-solid fa-calendar-check"></i> View All Appointments
        </a>
        <a href="/hospital/dashboard.php?tab=doctors" class="dp-btn ghost full">
          <i class="fa-solid fa-arrow-left"></i> Back to Doctors List
        </a>
      </div>
    </div>
  </div>

  <!-- RIGHT COLUMN -->
  <div class="dp-right">

    <!-- Profile form -->
    <div class="dp-card">
      <div class="dp-card-hdr">
        <div class="dp-card-title"><i class="fa-solid fa-user-doctor"></i> <?=$isNew?'Add New Doctor':'Edit Doctor Profile'?></div>
      </div>
      <div class="dp-card-body">
        <div class="dp-alert" id="dpAlert"></div>
        <input type="hidden" id="dpDocId" value="<?=$docId?>">
        <input type="hidden" id="dpCsrf" value="<?=htmlspecialchars($csrf)?>">

        <div class="dp-row">
          <div>
            <label class="dp-lbl">Full Name <span style="color:#dc2626">*</span></label>
            <div class="dp-wrap"><i class="fa-solid fa-user dp-ico"></i>
              <input class="dp-inp has-ico" type="text" id="dpName" placeholder="Dr. Jane Kamau"
                     value="<?=htmlspecialchars($doc['name']??'')?>" oninput="updatePreview()">
            </div>
          </div>
          <div>
            <label class="dp-lbl">Specialty</label>
            <div class="dp-wrap"><i class="fa-solid fa-stethoscope dp-ico"></i>
              <input class="dp-inp has-ico" type="text" id="dpSpec" placeholder="e.g. Cardiologist"
                     value="<?=htmlspecialchars($doc['specialty']??'')?>" oninput="updatePreview()">
            </div>
          </div>
        </div>

        <div class="dp-row">
          <div>
            <label class="dp-lbl">Phone</label>
            <div class="dp-wrap"><i class="fa-solid fa-phone dp-ico"></i>
              <input class="dp-inp has-ico" type="tel" id="dpPhone" placeholder="+254 700 000000"
                     value="<?=htmlspecialchars($doc['phone']??'')?>">
            </div>
          </div>
          <div>
            <label class="dp-lbl">Email</label>
            <div class="dp-wrap"><i class="fa-solid fa-envelope dp-ico"></i>
              <input class="dp-inp has-ico" type="email" id="dpEmail" placeholder="doctor@hospital.com"
                     value="<?=htmlspecialchars($doc['email']??'')?>">
            </div>
          </div>
        </div>

        <div class="dp-row">
          <div>
            <label class="dp-lbl">License Number</label>
            <div class="dp-wrap"><i class="fa-solid fa-id-card dp-ico"></i>
              <input class="dp-inp has-ico" type="text" id="dpLicense" placeholder="KMP-123456"
                     value="<?=htmlspecialchars($doc['license_number']??'')?>">
            </div>
          </div>
          <div>
            <label class="dp-lbl">Availability</label>
            <div class="dp-wrap"><i class="fa-solid fa-circle-check dp-ico"></i>
              <select class="dp-inp has-ico dp-sel" id="dpAvail">
                <option value="1" <?=($doc['is_available']??1)?'selected':''?>>Available</option>
                <option value="0" <?=!($doc['is_available']??1)?'selected':''?>>Unavailable</option>
              </select>
            </div>
          </div>
        </div>

        <div class="dp-row">
          <div>
            <label class="dp-lbl">Years of Experience</label>
            <div class="dp-wrap"><i class="fa-solid fa-clock-rotate-left dp-ico"></i>
              <input class="dp-inp has-ico" type="number" id="dpExp" placeholder="5" min="0" max="60"
                     value="<?=htmlspecialchars($doc['years_experience']??'')?>">
            </div>
          </div>
          <div>
            <label class="dp-lbl">Consultation Fee (KES)</label>
            <div class="dp-wrap"><i class="fa-solid fa-money-bill dp-ico"></i>
              <input class="dp-inp has-ico" type="number" id="dpFee" placeholder="2500" min="0"
                     value="<?=htmlspecialchars($doc['consultation_fee']??'')?>">
            </div>
          </div>
        </div>

        <div class="dp-grp">
          <label class="dp-lbl">Languages Spoken</label>
          <div class="dp-wrap"><i class="fa-solid fa-language dp-ico"></i>
            <input class="dp-inp has-ico" type="text" id="dpLangs" placeholder="English, Swahili, Kikuyu"
                   value="<?=htmlspecialchars($doc['languages']??'')?>">
          </div>
        </div>

        <div class="dp-grp">
          <label class="dp-lbl">Services Offered</label>
          <div class="dp-wrap"><i class="fa-solid fa-list-check dp-ico"></i>
            <input class="dp-inp has-ico" type="text" id="dpServices" placeholder="e.g. Telehealth, In-Person, Home Visits"
                   value="<?=htmlspecialchars(is_array($doc['services']??null) ? implode(', ', $doc['services']) : ($doc['services']??''))?>">
          </div>
        </div>

        <div class="dp-grp">
          <label class="dp-lbl">Bio / About</label>
          <textarea class="dp-inp dp-ta" id="dpBio" placeholder="Describe this doctor's expertise, approach to care, and what patients can expect…"><?=htmlspecialchars($doc['description']??'')?></textarea>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button class="dp-btn primary" id="dpSaveBtn" onclick="saveDpProfile()" style="flex:1;min-width:160px">
            <i class="fa-solid fa-floppy-disk"></i> Save Doctor Profile
          </button>
          <a href="/hospital/dashboard.php?tab=doctors" class="dp-btn ghost">
            <i class="fa-solid fa-xmark"></i> Cancel
          </a>
        </div>
      </div>
    </div>

    <?php if (!$isNew && !empty($apptHistory)): ?>
    <!-- Appointment history -->
    <div class="dp-card">
      <div class="dp-card-hdr">
        <div class="dp-card-title"><i class="fa-solid fa-calendar-check"></i> Appointment History</div>
        <span style="font-size:12px;color:#64748b"><?=count($apptHistory)?> records</span>
      </div>
      <div style="overflow-x:auto">
        <table class="dp-table">
          <thead>
            <tr>
              <th>Patient</th>
              <th>Date &amp; Time</th>
              <th>Service</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach(array_slice($apptHistory,0,15) as $a):
              $pName = trim(($a['first_name']??'').' '.($a['last_name']??'')) ?: 'Patient';
              $dt    = strtotime($a['appointment_at']);
              $st    = $a['status']??'scheduled';
            ?>
            <tr>
              <td>
                <div style="font-weight:600"><?=htmlspecialchars($pName)?></div>
              </td>
              <td>
                <div><?=date('M j, Y',$dt)?></div>
                <div style="font-size:11px;color:#94a3b8"><?=date('g:i A',$dt)?></div>
              </td>
              <td><?=htmlspecialchars($a['title']??ucfirst($a['service_type']??''))?></td>
              <td><span class="dp-pill <?=$st?>"><?=ucfirst($st)?></span></td>
            </tr>
            <?php endforeach;?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif;?>

  </div>
</div>
<?php else: ?>
<div style="max-width:500px;margin:60px auto;text-align:center;padding:0 20px">
  <i class="fa-solid fa-user-slash" style="font-size:48px;color:#e2e8f0;display:block;margin-bottom:16px"></i>
  <h2 style="font-size:1.25rem;font-weight:800;color:#0f172a;margin-bottom:8px">Doctor not found</h2>
  <p style="font-size:14px;color:#64748b;margin-bottom:20px">This doctor profile doesn't exist or was removed.</p>
  <a href="/hospital/dashboard.php?tab=doctors" class="dp-btn primary" style="display:inline-flex"><i class="fa-solid fa-arrow-left"></i> Back to Doctors</a>
</div>
<?php endif;?>

<script>
const DP_DOC_ID = <?= $docId ?>;
const DP_CSRF   = <?= json_encode($csrf) ?>;

function updatePreview() {
  const name = document.getElementById('dpName')?.value || '';
  const spec = document.getElementById('dpSpec')?.value || '';
  const initials = name.split(' ').map(w=>w[0]||'').slice(0,2).join('').toUpperCase() || 'DR';
  const namEl = document.getElementById('dpAvName');
  const spEl  = document.getElementById('dpAvSpec');
  const inEl  = document.getElementById('dpAvInitials');
  if (namEl) namEl.textContent = name;
  if (spEl)  spEl.textContent  = spec;
  if (inEl)  inEl.textContent  = initials;
}

function showAlert(type, msg) {
  const el = document.getElementById('dpAlert');
  el.className = 'dp-alert ' + type;
  el.innerHTML = '<i class="fa-solid fa-'+(type==='err'?'circle-exclamation':'circle-check')+'"></i><span>'+msg+'</span>';
  el.style.display = 'flex';
  el.scrollIntoView({ behavior:'smooth', block:'nearest' });
}

async function saveDpProfile() {
  const name   = document.getElementById('dpName')?.value?.trim();
  const spec   = document.getElementById('dpSpec')?.value?.trim();
  const phone  = document.getElementById('dpPhone')?.value?.trim();
  const email  = document.getElementById('dpEmail')?.value?.trim();
  const lic    = document.getElementById('dpLicense')?.value?.trim();
  const avail  = document.getElementById('dpAvail')?.value;
  const bio    = document.getElementById('dpBio')?.value?.trim();
  const exp    = document.getElementById('dpExp')?.value;
  const fee    = document.getElementById('dpFee')?.value;
  const langs  = document.getElementById('dpLangs')?.value?.trim();
  const svc    = document.getElementById('dpServices')?.value?.trim();

  if (!name) { showAlert('err','Doctor name is required.'); return; }

  const btn = document.getElementById('dpSaveBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving…';
  document.getElementById('dpAlert').style.display = 'none';

  try {
    const resp = await fetch('/api/hospital/save-doctor.php', {
      method:  'POST',
      headers: { 'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest' },
      body: JSON.stringify({
        doc_id:         DP_DOC_ID || null,
        name, specialty: spec, phone, email,
        license_number: lic,
        is_available:   parseInt(avail),
        description:    bio,
        hospital_id:    <?=$pvid?>,
        years_experience: exp ? parseInt(exp) : null,
        consultation_fee: fee ? parseFloat(fee) : null,
        languages:      langs,
        services:       svc,
        csrf_token:     DP_CSRF,
      }),
      credentials: 'same-origin',
    });
    const raw = await resp.text();
    let r; try { r = JSON.parse(raw); } catch(e) { throw new Error('Server error: '+raw.substring(0,100)); }

    if (r.success) {
      showAlert('ok', r.message || 'Doctor saved!');
      // Redirect to edit page if newly created
      if (!DP_DOC_ID && r.doc_id) {
        setTimeout(() => { location.href = '/hospital/doctor-profile.php?id='+r.doc_id; }, 1200);
      }
    } else {
      showAlert('err', r.message || 'Save failed.');
    }
  } catch(e) {
    showAlert('err', 'Error: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Doctor Profile';
  }
}

async function uploadDpPhoto(input) {
  const file = input.files[0]; if (!file) return;
  const msg = document.getElementById('dpPhotoMsg');
  msg.style.display = 'block'; msg.style.color = '#94a3b8'; msg.textContent = 'Uploading…';

  if (!DP_DOC_ID) {
    msg.style.color = '#dc2626';
    msg.textContent = ' Please save the doctor profile first, then upload a photo.';
    input.value = ''; return;
  }

  const fd = new FormData();
  fd.append('avatar', file);
  fd.append('type', 'profile');
  fd.append('target_doc_id', DP_DOC_ID);

  try {
    const resp = await fetch('/api/hospital/upload-doc-avatar.php', {method:'POST',body:fd,credentials:'same-origin'});
    const raw  = await resp.text();
    let r; try { r = JSON.parse(raw); } catch(e) { throw new Error('Server error'); }
    if (r.success) {
      msg.style.color = '#16a34a'; msg.textContent = ' Photo updated!';
      const url = r.url + '?t=' + Date.now();
      const prev = document.getElementById('dpAvPreview');
      if (prev) prev.innerHTML = '<img src="'+url+'" style="width:100%;height:100%;object-fit:cover">';
    } else {
      msg.style.color = '#dc2626'; msg.textContent = ' ' + (r.message || 'Upload failed');
    }
  } catch(e) {
    msg.style.color = '#dc2626'; msg.textContent = ' ' + e.message;
  }
  input.value = '';
}
</script>
</body>
</html>
