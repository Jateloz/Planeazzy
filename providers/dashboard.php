<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Database.php';
Security::startSession();
if (empty($_SESSION['provider_id']) || empty($_SESSION['is_provider'])) {
    header('Location: /providers/login.php'); exit;
}
$pvid  = (int)$_SESSION['provider_id'];
$db    = Database::getInstance();
$tab   = $_GET['tab'] ?? 'overview';
$prov  = $db->fetchOne('SELECT * FROM providers WHERE id=:id', [':id' => $pvid]);
$ptype = $prov['type'] ?? 'doctor';

$appts = $db->fetchAll(
    'SELECT a.*, pt.first_name, pt.last_name, pt.phone pat_phone
     FROM appointments a
     LEFT JOIN patients pt ON a.patient_id = pt.id
     WHERE a.provider_id = :pid ORDER BY a.appointment_at DESC LIMIT 40',
    [':pid' => $pvid]
);
$today     = array_values(array_filter($appts, fn($a) => date('Y-m-d', strtotime($a['appointment_at'])) === date('Y-m-d')));
$upcoming  = array_values(array_filter($appts, fn($a) => $a['status'] === 'scheduled' && strtotime($a['appointment_at']) >= time()));
$completed = array_values(array_filter($appts, fn($a) => $a['status'] === 'completed'));

$pName    = htmlspecialchars($prov['name'] ?? 'Provider');
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', trim($pName)), 0, 2))));
$csrf     = Security::csrfToken();

$_SESSION['provider_name'] = $prov['name'] ?? 'Provider';
$noSidebar  = false;
$portalType = $ptype;
$pageTitle  = ucfirst($ptype) . ' Dashboard';
$activeTab  = $tab;
include dirname(__DIR__) . '/includes/header.php';

$typeColors = ['doctor' => 'var(--teal)', 'clinic' => 'var(--green)', 'ambulance' => 'var(--red)', 'hospital' => 'var(--primary)'];
$typeColor  = $typeColors[$ptype] ?? 'var(--primary)';
$typeIcons  = ['doctor' => 'fa-stethoscope', 'clinic' => 'fa-house-medical', 'ambulance' => 'fa-truck-medical', 'hospital' => 'fa-hospital'];
$typeIcon   = $typeIcons[$ptype] ?? 'fa-hospital';
?>

<!-- PAGE CONTENT -->
<div style="padding:32px;flex:1;max-width:1280px;margin:0 auto;width:100%">

<?php if ($tab === 'overview'): ?>
<!-- Welcome banner -->
<div class="welcome-banner" style="margin-bottom:24px">
  <div>
    <h2>Welcome back, <?= $pName ?> 👋</h2>
    <p>You have <strong><?= count($today) ?></strong> appointment<?= count($today) !== 1 ? 's' : '' ?> today and <strong><?= count($upcoming) ?></strong> upcoming.</p>
  </div>
  <div class="welcome-btns">
    <a href="?tab=appointments" class="btn-join" style="background:<?= $typeColor ?>"><i class="fa-solid fa-calendar-check"></i> <span data-en="View Appointments" data-sw="Ona Miadi">View Appointments</span></a>
    <?php if ($ptype !== 'ambulance'): ?>
    <a href="/patients/telehealth.php" class="btn-reschedule" style="display:inline-flex;align-items:center;gap:6px"><i class="fa-solid fa-video"></i> <span data-en="Start Telehealth" data-sw="Anza Telemedicine">Start Telehealth</span></a>
    <?php endif; ?>
  </div>
</div>

<!-- Status notice if pending -->
<?php if (($prov['status'] ?? 'pending') === 'pending'): ?>
<div style="background:rgba(217,119,6,.05);border:1.5px solid rgba(217,119,6,.2);border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px;margin-bottom:24px">
  <i class="fa-solid fa-clock" style="color:var(--yellow);font-size:22px;flex-shrink:0"></i>
  <div>
    <div style="font-size:15px;font-weight:700;color:var(--slate-900)" data-en="Account Under Review" data-sw="Akaunti Inapitiwa">Account Under Review</div>
    <div style="font-size:14px;color:var(--slate-500)" data-en="Your profile is being verified. This usually takes 24–48 hours." data-sw="Wasifu wako unakaguliwa. Hii kawaida huchukua masaa 24–48.">Your profile is being verified. This usually takes 24–48 hours.</div>
  </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-row" style="margin-bottom:24px">
  <?php foreach([
    ['fa-calendar-check','var(--primary)','rgba(25,120,229,.1)',count($upcoming),'Upcoming','Scheduled'],
    ['fa-calendar-day',  $typeColor,      'rgba(13,148,136,.1)',count($today),  'Today\'s Appointments','Scheduled today'],
    ['fa-circle-check',  'var(--green)',  'rgba(22,163,74,.1)', count($completed),'Completed','Total consultations'],
    ['fa-users',         'var(--yellow)', 'rgba(217,119,6,.1)', count(array_unique(array_column($appts,'patient_id'))),'Patients','Unique patients'],
  ] as [$ic,$col,$bg,$val,$lbl,$sub]): ?>
  <div class="stat-card">
    <div style="width:48px;height:48px;border-radius:10px;background:<?=$bg?>;color:<?=$col?>;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">
      <i class="fa-solid <?=$ic?>"></i>
    </div>
    <div>
      <div style="font-size:30px;font-weight:900;color:var(--slate-900);letter-spacing:-.04em;line-height:1;margin-bottom:4px"><?=$val?></div>
      <div style="font-size:13px;font-weight:500;color:var(--slate-500)"><?=$lbl?></div>
      <div style="font-size:12px;color:var(--slate-400);margin-top:2px"><?=$sub?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Today's appointments + profile -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
  <div>
    <div class="section-hdr"><h3><span data-en="Today's Appointments" data-sw="Miadi ya Leo">Today's Appointments</span><h3><a href="?tab=appointments">View all</a></div>
    <?php if (empty($today)): ?>
    <div style="background:var(--white);border-radius:12px;border:1px solid var(--slate-100);padding:36px 24px;text-align:center;box-shadow:var(--custom-shadow)">
      <i class="fa-regular fa-calendar" style="font-size:36px;color:var(--slate-200);display:block;margin-bottom:12px"></i>
      <p style="color:var(--slate-400);font-size:14px">No appointments today.</p>
    </div>
    <?php else: foreach ($today as $a):
      $d = strtotime($a['appointment_at']);
      $isTele = ($a['location_type'] ?? '') === 'telehealth';
      $patName = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
    ?>
    <div class="appt-card" style="margin-bottom:12px">
      <div class="appt-card-icon <?= $isTele ? 'telehealth' : 'in-person' ?>">
        <i class="fa-solid <?= $isTele ? 'fa-video' : 'fa-location-dot' ?>"></i>
      </div>
      <div class="appt-card-info">
        <div class="appt-card-badges">
          <span class="appt-badge <?= $isTele ? 'telehealth' : 'in-person' ?>"><?= $isTele ? 'Telehealth' : 'In-Person' ?></span>
        </div>
        <div class="appt-card-name"><?= htmlspecialchars($patName ?: 'Patient') ?></div>
        <div class="appt-card-time"><?= date('g:i A', $d) ?> · <?= htmlspecialchars($a['title'] ?? 'Appointment') ?></div>
      </div>
      <div class="appt-card-actions">
        <?php if ($isTele): ?>
        <a href="/patients/telehealth.php" class="btn-join" style="padding:7px 14px;font-size:12px;background:<?= $typeColor ?>">Join</a>
        <?php endif; ?>
        <button class="btn-appt-details" onclick="location.href='?tab=appointments'">View</button>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Profile card -->
  <div>
    <div class="section-hdr"><h3>Provider Profile</h3><a href="?tab=settings">Edit</a></div>
    <div style="background:var(--white);border-radius:16px;border:1px solid var(--slate-100);box-shadow:var(--custom-shadow);padding:24px">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
        <div style="width:64px;height:64px;border-radius:16px;background:<?= str_replace('var(--teal)','rgba(13,148,136,.1)',str_replace('var(--green)','rgba(5,150,105,.1)',str_replace('var(--red)','rgba(220,38,38,.1)',$typeColor))) ?>;color:<?= $typeColor ?>;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0">
          <i class="fa-solid <?= $typeIcon ?>"></i>
        </div>
        <div>
          <div style="font-size:18px;font-weight:700;color:var(--slate-900)"><?= $pName ?></div>
          <div style="font-size:13px;color:var(--slate-500)"><?= htmlspecialchars($prov['specialty'] ?? ucfirst($ptype)) ?></div>
          <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;margin-top:4px;background:<?= ($prov['status']??'pending')==='active'?'rgba(22,163,74,.1)':'rgba(217,119,6,.1)' ?>;color:<?= ($prov['status']??'pending')==='active'?'var(--green)':'var(--yellow)' ?>">
            <?= ucfirst($prov['status'] ?? 'pending') ?>
          </span>
        </div>
      </div>
      <?php foreach (['phone' => ['fa-phone','Phone'],'email' => ['fa-envelope','Email'],'address' => ['fa-location-dot','Location'],'license_number' => ['fa-certificate','License']] as $field => [$ic,$lbl]): ?>
      <?php if (!empty($prov[$field])): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--slate-100)">
        <i class="fa-solid <?= $ic ?>" style="color:var(--slate-400);font-size:14px;width:16px;text-align:center;flex-shrink:0"></i>
        <span style="font-size:13px;color:var(--slate-600)"><?= htmlspecialchars($prov[$field]) ?></span>
      </div>
      <?php endif; endforeach; ?>
      <a href="?tab=settings" class="btn btn-ghost btn-full btn-sm" style="margin-top:16px;justify-content:center"><i class="fa-solid fa-pen-to-square"></i> Edit Profile</a>
    </div>
  </div>
</div>

<?php elseif ($tab === 'appointments'): ?>
<!-- APPOINTMENTS TAB -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div><h2 style="font-size:22px;font-weight:700">Patient Appointments</h2><p style="color:var(--slate-500)">Manage your consultation schedule.</p></div>
</div>
<div class="tab-bar">
  <button class="tab-item <?= ($_GET['af']??'upcoming')==='upcoming'?'active':'' ?>" onclick="setTabF('upcoming')">Upcoming (<?= count($upcoming) ?>)</button>
  <button class="tab-item <?= ($_GET['af']??'')==='today'?'active':'' ?>"    onclick="setTabF('today')">Today (<?= count($today) ?>)</button>
  <button class="tab-item <?= ($_GET['af']??'')==='all'?'active':'' ?>"      onclick="setTabF('all')">All (<?= count($appts) ?>)</button>
</div>
<?php
$af   = $_GET['af'] ?? 'upcoming';
$show = $af === 'today' ? $today : ($af === 'all' ? $appts : $upcoming);
?>
<?php if (empty($show)): ?>
<div style="background:var(--white);border-radius:12px;border:1px solid var(--slate-100);padding:48px 24px;text-align:center;box-shadow:var(--custom-shadow)">
  <i class="fa-regular fa-calendar-xmark" style="font-size:44px;color:var(--slate-200);display:block;margin-bottom:16px"></i>
  <h3 style="font-size:18px;font-weight:700;color:var(--slate-500);margin-bottom:8px">No appointments found</h3>
</div>
<?php else: foreach ($show as $a):
  $d = strtotime($a['appointment_at']);
  $isTele = ($a['location_type'] ?? '') === 'telehealth';
  $st = $a['status'] ?? 'scheduled';
  $patName = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
?>
<div class="appt-card" style="margin-bottom:14px">
  <div class="appt-card-icon <?= $isTele ? 'telehealth' : 'in-person' ?>">
    <i class="fa-solid <?= $isTele ? 'fa-video' : 'fa-location-dot' ?>"></i>
  </div>
  <div class="appt-card-info">
    <div class="appt-card-badges">
      <span class="appt-badge <?= $isTele ? 'telehealth' : 'in-person' ?>"><?= $isTele ? 'Telehealth' : 'In-Person' ?></span>
    </div>
    <div class="appt-card-name"><?= htmlspecialchars($patName ?: 'Patient') ?></div>
    <div class="appt-card-time"><?= date('M j, Y', $d) ?> • <?= date('g:i A', $d) ?> · <?= htmlspecialchars($a['title'] ?? 'Appointment') ?></div>
    <div style="margin-top:6px">
      <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:9999px;font-size:12px;font-weight:600;background:<?= $st==='scheduled'?'rgba(25,120,229,.1)':($st==='completed'?'rgba(22,163,74,.1)':'var(--slate-100)') ?>;color:<?= $st==='scheduled'?'var(--primary)':($st==='completed'?'var(--green)':'var(--slate-500)') ?>">
        <?= ucfirst($st) ?>
      </span>
    </div>
  </div>
  <div class="appt-card-actions">
    <?php if ($isTele && $st === 'scheduled'): ?>
    <a href="/patients/telehealth.php" class="btn-join" style="padding:7px 14px;font-size:12px;background:<?= $typeColor ?>">Join Call</a>
    <?php endif; ?>
    <?php if ($st === 'scheduled'): ?>
    <button class="btn-appt-icon" onclick="updateAppt(<?= $a['id'] ?>,'completed')" title="Mark complete"><i class="fa-solid fa-circle-check"></i></button>
    <button class="btn-appt-icon" onclick="updateAppt(<?= $a['id'] ?>,'cancelled')" title="Cancel"><i class="fa-solid fa-xmark"></i></button>
    <?php endif; ?>
    <button class="btn-appt-details">Details</button>
  </div>
</div>
<?php endforeach; endif; ?>

<?php elseif ($tab === 'patients'): ?>
<!-- PATIENTS TAB -->
<div><h2 style="font-size:22px;font-weight:700;margin-bottom:20px">My Patients</h2></div>
<?php
$patMap = [];
foreach ($appts as $a) {
    $pid = $a['patient_id'];
    if (!isset($patMap[$pid])) $patMap[$pid] = ['name' => trim(($a['first_name']??'').(' '.$a['last_name']??'')), 'visits' => 0, 'last' => $a['appointment_at'], 'phone' => $a['pat_phone'] ?? ''];
    $patMap[$pid]['visits']++;
}
?>
<?php if (empty($patMap)): ?>
<div style="background:var(--white);border-radius:12px;border:1px solid var(--slate-100);padding:48px 24px;text-align:center;box-shadow:var(--custom-shadow)">
  <i class="fa-solid fa-users" style="font-size:44px;color:var(--slate-200);display:block;margin-bottom:16px"></i>
  <h3 style="font-size:18px;font-weight:700;color:var(--slate-500)">No patients yet</h3>
</div>
<?php else: ?>
<div style="background:var(--white);border-radius:12px;border:1px solid var(--slate-100);overflow:hidden;box-shadow:var(--custom-shadow)">
  <table style="width:100%;border-collapse:collapse;text-align:left">
    <thead><tr style="background:var(--slate-50)">
      <th style="padding:14px 20px;font-size:11px;font-weight:700;color:var(--slate-500);text-transform:uppercase;letter-spacing:.08em">Patient</th>
      <th style="padding:14px 20px;font-size:11px;font-weight:700;color:var(--slate-500);text-transform:uppercase;letter-spacing:.08em">Phone</th>
      <th style="padding:14px 20px;font-size:11px;font-weight:700;color:var(--slate-500);text-transform:uppercase;letter-spacing:.08em">Total Visits</th>
      <th style="padding:14px 20px;font-size:11px;font-weight:700;color:var(--slate-500);text-transform:uppercase;letter-spacing:.08em">Last Visit</th>
    </tr></thead>
    <tbody>
      <?php foreach ($patMap as $pid => $pat): ?>
      <tr style="border-top:1px solid var(--slate-100)">
        <td style="padding:14px 20px">
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-10);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0">
              <?= strtoupper(substr($pat['name'] ?: 'P', 0, 1)) ?>
            </div>
            <div style="font-size:14px;font-weight:600;color:var(--slate-900)"><?= htmlspecialchars($pat['name'] ?: 'Patient') ?></div>
          </div>
        </td>
        <td style="padding:14px 20px;font-size:14px;color:var(--slate-500)"><?= htmlspecialchars($pat['phone'] ?: '—') ?></td>
        <td style="padding:14px 20px"><span style="background:var(--primary-10);color:var(--primary);padding:3px 10px;border-radius:9999px;font-size:12px;font-weight:700"><?= $pat['visits'] ?></span></td>
        <td style="padding:14px 20px;font-size:14px;color:var(--slate-500)"><?= date('M j, Y', strtotime($pat['last'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'availability'): ?>
<!-- AVAILABILITY TAB -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div><h2 style="font-size:22px;font-weight:700">Set Your Availability</h2><p style="color:var(--slate-500)">Define when patients can book appointments with you.</p></div>
  <button class="btn-join" style="background:<?= $typeColor ?>" onclick="saveAvailability()"><i class="fa-solid fa-floppy-disk"></i> Save Availability</button>
</div>
<div id="availAlert" class="alert hidden"></div>
<input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf) ?>">
<div style="background:var(--white);border-radius:16px;border:1px solid var(--slate-100);box-shadow:var(--custom-shadow);overflow:hidden">
  <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
  <div class="day-row" data-day="<?= $day ?>" style="display:flex;align-items:flex-start;gap:20px;padding:20px;border-bottom:1px solid var(--slate-100)">
    <div style="width:100px;flex-shrink:0;font-size:15px;font-weight:700;color:var(--slate-900);padding-top:10px"><?= $day ?></div>
    <div style="flex:1">
      <div class="time-slot" style="display:flex;align-items:center;gap:12px;background:var(--slate-50);border:1px solid var(--slate-100);padding:12px;border-radius:8px">
        <input type="time" class="time-s form-input" value="08:00" style="width:110px;height:36px;padding:0 10px">
        <span style="font-size:14px;color:var(--slate-400)">to</span>
        <input type="time" class="time-e form-input" value="17:00" style="width:110px;height:36px;padding:0 10px">
        <div style="display:flex;background:var(--white);border:1px solid var(--slate-200);border-radius:6px;overflow:hidden">
          <button class="mode-btn active" data-mode="in_person" style="padding:6px 12px;font-size:11px;font-weight:700;border:none;cursor:pointer;font-family:'Inter',sans-serif;background:<?= $typeColor ?>;color:#fff">In-Person</button>
          <button class="mode-btn" data-mode="telehealth" style="padding:6px 12px;font-size:11px;font-weight:700;border:none;cursor:pointer;font-family:'Inter',sans-serif;background:transparent;color:var(--slate-500)">Telehealth</button>
          <button class="mode-btn" data-mode="both" style="padding:6px 12px;font-size:11px;font-weight:700;border:none;cursor:pointer;font-family:'Inter',sans-serif;background:transparent;color:var(--slate-500)">Both</button>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<script>
document.querySelectorAll('.day-row').forEach(row=>{
  row.querySelectorAll('.mode-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
      row.querySelectorAll('.mode-btn').forEach(b=>{b.style.background='transparent';b.style.color='var(--slate-500)';b.classList.remove('active');});
      btn.style.background='<?= $typeColor ?>';btn.style.color='#fff';btn.classList.add('active');
    });
  });
});
</script>

<?php elseif ($tab === 'settings'): ?>
<!-- SETTINGS TAB -->
<div><h2 style="font-size:22px;font-weight:700;margin-bottom:8px">Provider Settings</h2><p style="color:var(--slate-500);margin-bottom:24px">Manage your profile and account.</p></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
  <div class="settings-card">
    <div class="settings-card-head"><i class="fa-solid <?= $typeIcon ?>" style="color:<?= $typeColor ?>"></i><h3>Practice Information</h3></div>
    <div class="settings-card-body">
      <div class="form-group"><label class="form-label">Practice Name</label><input type="text" class="form-input" value="<?= htmlspecialchars($prov['name'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Specialty</label><input type="text" class="form-input" value="<?= htmlspecialchars($prov['specialty'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Email</label><input type="email" class="form-input" value="<?= htmlspecialchars($prov['email'] ?? '') ?>" readonly style="background:var(--slate-50);color:var(--slate-400)"></div>
      <div class="form-group"><label class="form-label">Phone</label><div class="input-wrap"><i class="fa-solid fa-phone input-ico"></i><input type="tel" class="form-input has-ico" value="<?= htmlspecialchars($prov['phone'] ?? '') ?>"></div></div>
      <div class="form-group"><label class="form-label">Address</label><div class="input-wrap"><i class="fa-solid fa-location-dot input-ico"></i><input type="text" class="form-input has-ico" value="<?= htmlspecialchars($prov['address'] ?? '') ?>"></div></div>
      <button class="btn-join" style="width:100%;background:<?= $typeColor ?>"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
    </div>
  </div>
  <div class="settings-card">
    <div class="settings-card-head"><i class="fa-solid fa-circle-info"></i><h3>Account Info</h3></div>
    <div class="settings-card-body">
      <?php foreach (['Provider Type' => ucfirst($ptype), 'Status' => ucfirst($prov['status'] ?? 'pending'), 'License #' => $prov['license_number'] ?? '—', 'Member Since' => date('M Y', strtotime($prov['created_at'] ?? 'now')), 'Rating' => number_format($prov['rating'] ?? 0, 1) . ' ⭐'] as $l => $v): ?>
      <div class="info-row"><span class="info-label"><?= $l ?></span><span class="info-value"><?= htmlspecialchars($v) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

</div><!-- /.page-content -->

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
<script>
function openModal(id){const el=document.getElementById(id);if(el)el.style.display='flex';}
function closeModal(id){const el=document.getElementById(id);if(el)el.style.display='none';}
function setTabF(t){const p=new URLSearchParams(window.location.search);p.set('af',t);window.location.href='?tab=appointments&'+p.toString();}
async function updateAppt(id,status){
  const r=await post('/api/provider/update-appointment.php',{appointment_id:id,status,csrf_token:document.getElementById('csrfToken')?.value||''},null,null);
  if(r?.success)location.reload();
  else alert(r?.message||'Failed to update.');
}
</script>
