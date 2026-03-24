<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Database.php';
Security::requireAuth('/patients/login.php');

$pid    = (int)$_SESSION['patient_id'];
$db     = Database::getInstance();
$tab    = $_GET['tab'] ?? 'overview';
$pat    = $db->fetchOne('SELECT * FROM patients WHERE id=:id', [':id' => $pid]);
$appts  = $db->fetchAll(
    'SELECT a.*, p.name prov_name, p.type prov_type, p.specialty
     FROM appointments a
     LEFT JOIN providers p ON a.provider_id = p.id
     WHERE a.patient_id = :pid
     ORDER BY a.appointment_at DESC LIMIT 40',
    [':pid' => $pid]
);
$notifs = $db->fetchAll(
    'SELECT * FROM notifications WHERE patient_id = :pid ORDER BY created_at DESC LIMIT 30',
    [':pid' => $pid]
);
$nearby = $db->fetchAll('SELECT * FROM providers WHERE is_active=1 AND is_verified=1 ORDER BY rating DESC LIMIT 20');

$upcoming  = array_values(array_filter($appts, fn($a) => $a['status'] === 'scheduled' && strtotime($a['appointment_at']) >= time()));
$past      = array_values(array_filter($appts, fn($a) => $a['status'] === 'completed' || strtotime($a['appointment_at']) < time()));
$unread    = count(array_filter($notifs, fn($n) => !$n['is_read']));
$fname     = htmlspecialchars($pat['first_name'] ?? 'Patient');
$lname     = htmlspecialchars($pat['last_name']  ?? '');
$fullName  = trim("$fname $lname");
$initials  = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', trim($fullName)), 0, 2))));
$patId     = '#' . str_pad($pid, 5, '0', STR_PAD_LEFT);
$nextAppt  = $upcoming[0] ?? null;
$csrf      = Security::csrfToken();

// My doctors list
$myDoctors = [];
$seen = [];
foreach ($appts as $a) {
    if (!empty($a['prov_name']) && !in_array($a['prov_name'], $seen)) {
        $myDoctors[] = $a;
        $seen[] = $a['prov_name'];
        if (count($myDoctors) >= 3) break;
    }
}

// Header vars
$_SESSION['patient_name'] = $fullName;
$noSidebar  = false;
$portalType = 'patient';
$pageTitle  = 'Patient Dashboard';
$activeTab  = $tab;
include dirname(__DIR__) . '/includes/header.php';
?>

<!-- ── PAGE CONTENT ─────────────────────────────────────── -->
<div style="padding:32px;flex:1;max-width:1280px;margin:0 auto;width:100%">

<?php if ($tab === 'overview'): ?>
<!-- Welcome Banner -->
<div class="welcome-banner">
  <div>
    <h2>Welcome back, <?= $fname ?> 👋</h2>
    <?php if ($nextAppt):
      $nd = strtotime($nextAppt['appointment_at']);
      $isToday = date('Y-m-d', $nd) === date('Y-m-d');
      $isTomorrow = date('Y-m-d', $nd) === date('Y-m-d', time() + 86400);
      $when = $isToday ? 'today' : ($isTomorrow ? 'tomorrow' : 'on ' . date('M j', $nd));
    ?>
    <p>You have an upcoming <?= ($nextAppt['location_type'] ?? '') === 'telehealth' ? 'telehealth' : '' ?> appointment <?= $when ?> at <?= date('g:i A', $nd) ?> with <?= htmlspecialchars($nextAppt['prov_name'] ?? 'your doctor') ?>.</p>
    <?php else: ?>
    <p>No upcoming appointments. Book one to get started with your healthcare journey.</p>
    <?php endif; ?>
  </div>
  <div class="welcome-btns">
    <?php if ($nextAppt && ($nextAppt['location_type'] ?? '') === 'telehealth'): ?>
    <a href="/patients/telehealth.php" class="btn-join">Join Call</a>
    <button class="btn-reschedule" onclick="location.href='?tab=appointments'">Reschedule</button>
    <?php else: ?>
    <button class="btn-join" onclick="openModal('bookModal')"><i class="fa-solid fa-calendar-plus"></i> Book Appointment</button>
    <a href="/patients/search.php" class="btn-reschedule" style="display:inline-flex;align-items:center;gap:6px"><i class="fa-solid fa-magnifying-glass"></i> Find a Doctor</a>
    <?php endif; ?>
  </div>
</div>

<!-- Main Grid: 2/3 left + 1/3 right -->
<div class="dash-grid">
  <!-- LEFT COLUMN -->
  <div class="dash-left">

    <!-- Upcoming Appointments -->
    <section>
      <div class="section-hdr">
        <h3>Upcoming Appointments</h3>
        <a href="?tab=appointments">View Calendar</a>
      </div>
      <?php if (empty($upcoming)): ?>
      <div style="background:var(--white);border-radius:12px;border:1px solid var(--slate-100);box-shadow:var(--custom-shadow);padding:40px 24px;text-align:center">
        <i class="fa-regular fa-calendar-xmark" style="font-size:44px;color:var(--slate-200);display:block;margin-bottom:16px"></i>
        <h3 style="font-size:16px;font-weight:700;color:var(--slate-500);margin-bottom:8px">No upcoming appointments</h3>
        <p style="font-size:14px;color:var(--slate-400);margin-bottom:18px">Book your first appointment to get started.</p>
        <button class="btn-join" onclick="openModal('bookModal')"><i class="fa-solid fa-plus"></i> Book Now</button>
      </div>
      <?php else: foreach (array_slice($upcoming, 0, 3) as $a):
        $d = strtotime($a['appointment_at']);
        $isTele = ($a['location_type'] ?? '') === 'telehealth';
      ?>
      <div class="appt-card">
        <div class="appt-card-icon <?= $isTele ? 'telehealth' : 'in-person' ?>">
          <i class="fa-solid <?= $isTele ? 'fa-video' : 'fa-location-dot' ?>"></i>
        </div>
        <div class="appt-card-info">
          <div class="appt-card-badges">
            <span class="appt-badge <?= $isTele ? 'telehealth' : 'in-person' ?>"><?= $isTele ? 'Telehealth' : 'In-Person' ?></span>
            <span class="appt-card-specialty">• <?= htmlspecialchars($a['specialty'] ?? ($a['prov_type'] ?? 'General')) ?></span>
          </div>
          <div class="appt-card-name"><?= htmlspecialchars($a['prov_name'] ?? $a['title'] ?? 'Appointment') ?></div>
          <div class="appt-card-time"><?= date('M j, Y', $d) ?> • <?= date('g:i A', $d) ?></div>
        </div>
        <div class="appt-card-actions">
          <?php if ($isTele): ?>
          <a href="/patients/telehealth.php" class="btn-join" style="padding:8px 16px;font-size:13px">Join</a>
          <?php endif; ?>
          <button class="btn-appt-icon" title="Reschedule" onclick="location.href='?tab=appointments'"><i class="fa-solid fa-calendar-pen"></i></button>
          <button class="btn-appt-details" onclick="location.href='?tab=appointments'">Details</button>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </section>

    <!-- Medical History table -->
    <section>
      <div class="section-hdr">
        <h3>Recent Medical History</h3>
        <a href="?tab=appointments">View All</a>
      </div>
      <div class="history-table-wrap">
        <table class="history-table">
          <thead><tr><th>Visit Date</th><th>Diagnosis/Reason</th><th>Physician</th><th>Action</th></tr></thead>
          <tbody>
            <?php if (empty($past)): ?>
            <tr><td colspan="4" style="padding:32px;text-align:center;color:var(--slate-400)">No past visits yet.</td></tr>
            <?php else: foreach (array_slice($past, 0, 5) as $a): ?>
            <tr>
              <td style="white-space:nowrap"><?= date('M j, Y', strtotime($a['appointment_at'])) ?></td>
              <td>
                <div class="history-dx"><?= htmlspecialchars($a['title'] ?? 'Consultation') ?></div>
                <div class="history-note"><?= htmlspecialchars($a['notes'] ?? ($a['status'] ?? 'Completed')) ?></div>
              </td>
              <td><?= htmlspecialchars($a['prov_name'] ?? '—') ?></td>
              <td><button class="btn-download" title="Download"><i class="fa-solid fa-download"></i></button></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div><!-- /.dash-left -->

  <!-- RIGHT COLUMN -->
  <div class="dash-right">

    <!-- My Doctors -->
    <div class="doctors-card">
      <h3>My Doctors</h3>
      <?php if (empty($myDoctors)): ?>
      <p style="font-size:14px;color:var(--slate-400);margin-bottom:16px">Book your first appointment to add doctors here.</p>
      <?php else: foreach ($myDoctors as $m): ?>
      <div class="doctor-row">
        <div class="doctor-row-info">
          <div class="doctor-av-init"><?= strtoupper(substr($m['prov_name'] ?? 'Dr', 0, 2)) ?></div>
          <div>
            <div class="doctor-name"><?= htmlspecialchars($m['prov_name'] ?? 'Doctor') ?></div>
            <div class="doctor-spec"><?= htmlspecialchars($m['specialty'] ?? ucfirst($m['prov_type'] ?? 'Specialist')) ?></div>
          </div>
        </div>
        <button class="btn-book-doc" title="Book again" onclick="openModal('bookModal')"><i class="fa-solid fa-calendar-plus"></i></button>
      </div>
      <?php endforeach; endif; ?>
      <button class="btn-find-doctor" onclick="location.href='/patients/search.php'">Find New Doctor</button>
    </div>

    <!-- Quick Actions -->
    <div style="background:var(--white);border-radius:16px;border:1px solid var(--slate-100);box-shadow:var(--custom-shadow);padding:24px">
      <h3 style="font-size:18px;font-weight:700;margin-bottom:16px">Quick Actions</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <?php foreach ([
          ['fa-stethoscope', 'rgba(25,120,229,.1)', 'var(--primary)',   'Find Doctor',    '/patients/search.php'],
          ['fa-calendar-plus','rgba(25,120,229,.1)','var(--primary)',   'Book Appt',      '#book'],
          ['fa-video',        'rgba(13,148,136,.1)','var(--teal)',      'Video Call',     '/patients/telehealth.php'],
          ['fa-truck-medical','rgba(220,38,38,.1)', 'var(--red)',       'Ambulance',      '/patients/search.php?type=ambulance'],
          ['fa-pills',        'rgba(217,119,6,.1)', 'var(--yellow)',    'Pharmacy',       '/patients/search.php?type=pharmacy'],
          ['fa-bell',         'rgba(25,120,229,.1)','var(--primary)',   'Alerts',         '?tab=notifications'],
        ] as [$ic, $bg, $col, $lb, $lk]): ?>
        <a href="<?= $lk === '#book' ? 'javascript:void(0)' : $lk ?>"
           onclick="<?= $lk === '#book' ? "openModal('bookModal')" : 'null' ?>"
           style="display:flex;flex-direction:column;align-items:center;gap:7px;padding:14px 8px;background:var(--slate-50);border:1px solid var(--slate-100);border-radius:10px;text-decoration:none;text-align:center;cursor:pointer">
          <div style="width:42px;height:42px;border-radius:10px;background:<?= $bg ?>;color:<?= $col ?>;display:flex;align-items:center;justify-content:center;font-size:18px">
            <i class="fa-solid <?= $ic ?>"></i>
          </div>
          <span style="font-size:12px;font-weight:700;color:var(--slate-700)"><?= $lb ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Insurance status card -->
    <div style="background:var(--white);border-radius:16px;border:1px solid var(--slate-100);box-shadow:var(--custom-shadow);padding:24px">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px"><i class="fa-solid fa-shield" style="color:var(--primary)"></i> Insurance</h3>
      <div style="background:var(--primary-10);border-radius:12px;padding:16px">
        <div style="font-size:11px;font-weight:600;color:var(--primary);text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px">Insurance Status</div>
        <div style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;margin-bottom:4px">
          <i class="fa-solid fa-circle-check" style="color:var(--green)"></i> Active Coverage
        </div>
        <div style="font-size:12px;color:var(--slate-500)">Provider: BlueShield Health</div>
      </div>
    </div>
  </div><!-- /.dash-right -->
</div><!-- /.dash-grid -->

<?php elseif ($tab === 'appointments'): ?>
<!-- APPOINTMENTS TAB -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div><h2 style="font-size:22px;font-weight:700;color:var(--slate-900)">My Appointments</h2><p style="color:var(--slate-500);margin-top:4px">Manage your upcoming and past appointments.</p></div>
  <button class="btn-join" onclick="openModal('bookModal')"><i class="fa-solid fa-calendar-plus"></i> Book New</button>
</div>
<div class="tab-bar">
  <button class="tab-item <?= ($_GET['af'] ?? 'upcoming') === 'upcoming' ? 'active' : '' ?>" onclick="setTabFilter('upcoming')">Upcoming (<?= count($upcoming) ?>)</button>
  <button class="tab-item <?= ($_GET['af'] ?? '') === 'past' ? 'active' : '' ?>"     onclick="setTabFilter('past')">Past (<?= count($past) ?>)</button>
  <button class="tab-item <?= ($_GET['af'] ?? '') === 'all'  ? 'active' : '' ?>"     onclick="setTabFilter('all')">All (<?= count($appts) ?>)</button>
</div>
<?php
$af   = $_GET['af'] ?? 'upcoming';
$show = $af === 'past' ? $past : ($af === 'all' ? $appts : $upcoming);
?>
<?php if (empty($show)): ?>
<div style="background:var(--white);border-radius:12px;border:1px solid var(--slate-100);box-shadow:var(--custom-shadow);padding:48px 24px;text-align:center">
  <i class="fa-regular fa-calendar-xmark" style="font-size:48px;color:var(--slate-200);display:block;margin-bottom:16px"></i>
  <h3 style="font-size:18px;font-weight:700;color:var(--slate-500);margin-bottom:8px">No appointments found</h3>
  <p style="font-size:14px;color:var(--slate-400);margin-bottom:18px">Book your first appointment to get started.</p>
  <button class="btn-join" onclick="openModal('bookModal')"><i class="fa-solid fa-plus"></i> Book Now</button>
</div>
<?php else: foreach ($show as $a):
  $d = strtotime($a['appointment_at']);
  $isTele = ($a['location_type'] ?? '') === 'telehealth';
  $st = $a['status'] ?? 'scheduled';
?>
<div class="appt-card" style="margin-bottom:14px">
  <div class="appt-card-icon <?= $isTele ? 'telehealth' : 'in-person' ?>">
    <i class="fa-solid <?= $isTele ? 'fa-video' : 'fa-location-dot' ?>"></i>
  </div>
  <div class="appt-card-info">
    <div class="appt-card-badges">
      <span class="appt-badge <?= $isTele ? 'telehealth' : 'in-person' ?>"><?= $isTele ? 'Telehealth' : 'In-Person' ?></span>
      <span class="appt-card-specialty">• <?= htmlspecialchars($a['specialty'] ?? ($a['prov_type'] ?? 'General')) ?></span>
    </div>
    <div class="appt-card-name"><?= htmlspecialchars($a['prov_name'] ?? $a['title'] ?? 'Appointment') ?></div>
    <div class="appt-card-time"><?= date('M j, Y', $d) ?> • <?= date('g:i A', $d) ?></div>
    <div style="margin-top:6px">
      <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:9999px;font-size:12px;font-weight:600;background:<?= $st === 'scheduled' ? 'rgba(25,120,229,.1)' : ($st === 'completed' ? 'rgba(22,163,74,.1)' : 'var(--slate-100)') ?>;color:<?= $st === 'scheduled' ? 'var(--primary)' : ($st === 'completed' ? 'var(--green)' : 'var(--slate-500)') ?>">
        <?= ucfirst($st) ?>
      </span>
    </div>
  </div>
  <div class="appt-card-actions">
    <?php if ($isTele && $st === 'scheduled'): ?><a href="/patients/telehealth.php" class="btn-join" style="padding:8px 16px;font-size:13px">Join Call</a><?php endif; ?>
    <?php if ($st === 'scheduled'): ?><button class="btn-appt-icon" title="Reschedule"><i class="fa-solid fa-calendar-pen"></i></button><?php endif; ?>
    <button class="btn-appt-details">Details</button>
  </div>
</div>
<?php endforeach; endif; ?>

<?php elseif ($tab === 'nearby'): ?>
<!-- NEARBY TAB -->
<?php $typeFilter = $_GET['type'] ?? 'all'; ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div><h2 style="font-size:22px;font-weight:700">Nearby Services</h2><p style="color:var(--slate-500)">Healthcare providers near Nairobi, Kenya.</p></div>
  <a href="/patients/search.php" style="font-size:14px;font-weight:600;color:var(--primary);display:flex;align-items:center;gap:4px"><i class="fa-solid fa-arrow-up-right-from-square"></i> Full Search</a>
</div>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
  <?php foreach ([['all','All'],['doctor','Doctors'],['clinic','Clinics'],['hospital','Hospitals'],['ambulance','Ambulance'],['pharmacy','Pharmacy']] as [$k,$lb]): ?>
  <a href="?tab=nearby&type=<?= $k ?>" style="padding:6px 16px;border-radius:9999px;font-size:13px;font-weight:700;border:1.5px solid <?= $typeFilter===$k?'var(--primary)':'var(--slate-200)' ?>;background:<?= $typeFilter===$k?'var(--primary)':'var(--white)' ?>;color:<?= $typeFilter===$k?'#fff':'var(--slate-500)' ?>;text-decoration:none"><?= $lb ?></a>
  <?php endforeach; ?>
</div>
<div class="nearby-grid">
  <?php
  $filtered = $typeFilter === 'all' ? $nearby : array_filter($nearby, fn($p) => $p['type'] === $typeFilter);
  $icons  = ['doctor'=>'fa-stethoscope','clinic'=>'fa-house-medical','hospital'=>'fa-hospital','ambulance'=>'fa-truck-medical','pharmacy'=>'fa-pills'];
  $bgs    = ['doctor'=>'rgba(13,148,136,.1)','clinic'=>'rgba(5,150,105,.1)','hospital'=>'rgba(25,120,229,.1)','ambulance'=>'rgba(220,38,38,.1)','pharmacy'=>'rgba(217,119,6,.1)'];
  $cols   = ['doctor'=>'var(--teal)','clinic'=>'var(--green)','hospital'=>'var(--primary)','ambulance'=>'var(--red)','pharmacy'=>'var(--yellow)'];
  if (empty($filtered)): ?>
  <div style="grid-column:1/-1;text-align:center;padding:48px 24px"><i class="fa-solid fa-magnifying-glass-location" style="font-size:48px;color:var(--slate-200);display:block;margin-bottom:16px"></i><h3 style="color:var(--slate-500)">No providers found</h3></div>
  <?php else: foreach ($filtered as $p): $pt = $p['type'] ?? 'clinic'; ?>
  <div class="nearby-card">
    <div class="nearby-card-icon" style="background:<?= $bgs[$pt] ?? 'rgba(25,120,229,.1)' ?>;color:<?= $cols[$pt] ?? 'var(--primary)' ?>">
      <i class="fa-solid <?= $icons[$pt] ?? 'fa-hospital' ?>"></i>
    </div>
    <div class="nearby-card-name"><?= htmlspecialchars($p['name']) ?></div>
    <div class="nearby-card-spec"><?= htmlspecialchars($p['specialty'] ?? ucfirst($pt)) ?></div>
    <div class="nearby-card-meta">
      <span class="nearby-card-dist"><i class="fa-solid fa-location-dot"></i> <?= round(rand(5, 30) / 10, 1) ?> km</span>
      <span class="nearby-card-rating"><i class="fa-solid fa-star"></i> <?= number_format($p['rating'] ?? 4.5, 1) ?></span>
    </div>
    <div class="nearby-avail"><span class="avail-dot"></span> Available today</div>
    <button class="btn-book-nearby" onclick="openModal('bookModal')"><i class="fa-solid fa-calendar-plus"></i> Book Now</button>
  </div>
  <?php endforeach; endif; ?>
</div>

<?php elseif ($tab === 'insurance'): ?>
<!-- INSURANCE TAB -->
<div><h2 style="font-size:22px;font-weight:700;margin-bottom:8px">Insurance</h2><p style="color:var(--slate-500);margin-bottom:24px">Manage your health insurance coverage and claims.</p></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
  <div class="settings-card">
    <div class="settings-card-head"><i class="fa-solid fa-shield"></i><h3>Current Coverage</h3></div>
    <div class="settings-card-body">
      <?php foreach (['Provider'=>'BlueShield Health','Plan'=>'Gold Plus','Policy #'=>'BSH-2025-'.str_pad($pid,5,'0',STR_PAD_LEFT),'Valid Until'=>'Dec 31, 2025','Annual Limit'=>'KES 5,000,000','Co-Pay'=>'KES 500 per visit'] as $l=>$v): ?>
      <div class="info-row"><span class="info-label"><?= $l ?></span><span class="info-value"><?= $v ?></span></div>
      <?php endforeach; ?>
      <div style="margin-top:16px;padding:12px;background:rgba(22,163,74,.08);border-radius:8px;border:1px solid rgba(22,163,74,.2);display:flex;align-items:center;gap:8px">
        <i class="fa-solid fa-circle-check" style="color:var(--green)"></i>
        <span style="font-size:14px;font-weight:600;color:var(--green)">Active Coverage</span>
      </div>
    </div>
  </div>
  <div class="settings-card">
    <div class="settings-card-head"><i class="fa-solid fa-file-invoice"></i><h3>Recent Claims</h3></div>
    <div class="settings-card-body">
      <?php foreach ([['Oct 12, 2024','Dr. Sarah Jenkins','KES 8,500','Approved'],['Sep 05, 2024','Nairobi Hospital','KES 22,000','Approved'],['Aug 18, 2024','Dr. Michael Chen','KES 4,200','Pending']] as $c): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--slate-100)">
        <div><div style="font-size:13px;font-weight:600;color:var(--slate-900)"><?= $c[1] ?></div><div style="font-size:12px;color:var(--slate-400)"><?= $c[0] ?></div></div>
        <div style="text-align:right"><div style="font-size:13px;font-weight:700"><?= $c[2] ?></div>
          <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;background:<?= $c[3]==='Approved'?'rgba(22,163,74,.1)':'var(--slate-100)' ?>;color:<?= $c[3]==='Approved'?'var(--green)':'var(--slate-500)' ?>"><?= $c[3] ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'notifications'): ?>
<!-- NOTIFICATIONS TAB -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div><h2 style="font-size:22px;font-weight:700">Notifications</h2><p style="color:var(--slate-500)"><?= $unread ?> unread notification<?= $unread !== 1 ? 's' : '' ?>.</p></div>
  <button class="btn-reschedule" onclick="markAllRead()"><i class="fa-solid fa-check-double"></i> Mark all read</button>
</div>
<div style="background:var(--white);border-radius:16px;border:1px solid var(--slate-100);box-shadow:var(--custom-shadow);padding:0 24px">
  <?php if (empty($notifs)): ?>
  <div style="padding:48px 24px;text-align:center"><i class="fa-regular fa-bell-slash" style="font-size:44px;color:var(--slate-200);display:block;margin-bottom:16px"></i><h3 style="color:var(--slate-500)">No notifications yet</h3></div>
  <?php else: foreach ($notifs as $n): ?>
  <div class="notif-list-item <?= !$n['is_read'] ? 'unread' : '' ?>" id="ni-<?= $n['id'] ?>" onclick="markRead(<?= $n['id'] ?>)">
    <div class="notif-list-icon" style="background:rgba(25,120,229,.1);color:var(--primary)"><i class="fa-solid fa-bell"></i></div>
    <div style="flex:1;min-width:0">
      <div class="notif-list-title"><?= htmlspecialchars($n['title'] ?? 'Notification') ?></div>
      <div class="notif-list-msg"><?= htmlspecialchars($n['message'] ?? '') ?></div>
      <div class="notif-list-time"><i class="fa-regular fa-clock"></i> <?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></div>
    </div>
    <?php if (!$n['is_read']): ?><div class="notif-unread-dot"></div><?php endif; ?>
  </div>
  <?php endforeach; endif; ?>
</div>

<?php elseif ($tab === 'emergency'): ?>
<!-- EMERGENCY TAB -->
<div><h2 style="font-size:22px;font-weight:700;color:var(--red);margin-bottom:8px"><i class="fa-solid fa-siren-on"></i> Emergency Services</h2><p style="color:var(--slate-500);margin-bottom:24px">Activate SOS or contact emergency services immediately.</p></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
  <div style="background:var(--white);border-radius:16px;border:1px solid var(--slate-200);box-shadow:var(--custom-shadow);padding:36px;text-align:center">
    <div style="width:80px;height:80px;border-radius:50%;background:rgba(220,38,38,.1);color:var(--red);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:36px"><i class="fa-solid fa-siren-on"></i></div>
    <h3 style="color:var(--red);font-size:20px;margin-bottom:10px">Emergency SOS</h3>
    <p style="color:var(--slate-500);font-size:14px;margin-bottom:24px">Dispatch the nearest ambulance to your GPS location. Average response: under 4 minutes.</p>
    <button class="sos-btn" onclick="requestLocation()"><i class="fa-solid fa-location-arrow"></i> Activate Emergency SOS</button>
  </div>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ([
      ['tel:999', 'fa-phone', 'rgba(22,163,74,.1)', 'var(--green)', 'Call Emergency Line', 'Dial 999 · Immediate response'],
      ['/patients/search.php?type=ambulance', 'fa-truck-medical', 'rgba(220,38,38,.1)', 'var(--red)', 'Nearest Ambulance', 'Track live dispatches near you'],
      ['/patients/search.php?type=hospital', 'fa-hospital', 'rgba(25,120,229,.1)', 'var(--primary)', 'Nearest ER', 'Find the closest emergency room'],
      ['/patients/telehealth.php', 'fa-video', 'rgba(13,148,136,.1)', 'var(--teal)', 'Emergency Telehealth', 'Speak with a doctor instantly'],
    ] as [$href, $ic, $bg, $col, $t, $s]): ?>
    <a href="<?= $href ?>" style="display:flex;align-items:center;gap:16px;padding:20px;background:var(--white);border:1px solid var(--slate-200);border-radius:12px;text-decoration:none;box-shadow:var(--shadow-sm)">
      <div style="width:48px;height:48px;border-radius:10px;background:<?= $bg ?>;color:<?= $col ?>;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0"><i class="fa-solid <?= $ic ?>"></i></div>
      <div><div style="font-size:15px;font-weight:700;color:var(--slate-900)"><?= $t ?></div><div style="font-size:13px;color:var(--slate-500)"><?= $s ?></div></div>
      <i class="fa-solid fa-chevron-right" style="margin-left:auto;color:var(--slate-300);font-size:14px"></i>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php elseif ($tab === 'settings'): ?>
<!-- SETTINGS TAB -->
<div><h2 style="font-size:22px;font-weight:700;margin-bottom:8px">Account Settings</h2><p style="color:var(--slate-500);margin-bottom:24px">Manage your profile, preferences and security.</p></div>
<div id="settAlert" class="alert hidden"></div>
<div class="settings-grid">
  <div class="settings-card">
    <div class="settings-card-head"><i class="fa-solid fa-user"></i><h3>Personal Information</h3></div>
    <div class="settings-card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div class="form-group" style="margin-bottom:0"><label class="form-label">First Name</label><input type="text" id="sfname" class="form-input" value="<?= htmlspecialchars($pat['first_name'] ?? '') ?>"></div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Last Name</label><input type="text" id="slname" class="form-input" value="<?= htmlspecialchars($pat['last_name'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label class="form-label">Email Address</label><input type="email" class="form-input" value="<?= htmlspecialchars($pat['email'] ?? '') ?>" readonly style="background:var(--slate-50);color:var(--slate-400)"><small style="font-size:11px;color:var(--slate-400);display:block;margin-top:4px">Email cannot be changed.</small></div>
      <div class="form-group"><label class="form-label">Phone Number</label><div class="input-wrap"><i class="fa-solid fa-phone input-ico"></i><input type="tel" class="form-input has-ico" value="<?= htmlspecialchars($pat['phone'] ?? '') ?>"></div></div>
      <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" class="form-input" value="<?= htmlspecialchars($pat['date_of_birth'] ?? '') ?>"></div>
      <button class="btn-join" style="width:100%;margin-top:4px"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
    </div>
  </div>
  <div class="settings-card">
    <div class="settings-card-head"><i class="fa-solid fa-lock"></i><h3>Change Password</h3></div>
    <div class="settings-card-body">
      <div class="form-group"><label class="form-label">Current Password</label><div class="input-wrap"><i class="fa-solid fa-lock input-ico"></i><input type="password" class="form-input has-ico" placeholder="Current password"></div></div>
      <div class="form-group"><label class="form-label">New Password</label><div class="input-wrap"><i class="fa-solid fa-key input-ico"></i><input type="password" id="np" class="form-input has-ico" placeholder="Min 8 characters"><button type="button" class="eye-btn" id="ep" onclick="togglePwd('np','ep')"><i class="fa-solid fa-eye"></i></button></div><div class="str-bar"><div class="str-fill" id="sf"></div></div><div class="str-txt" id="st"></div></div>
      <div class="form-group"><label class="form-label">Confirm New Password</label><div class="input-wrap"><i class="fa-solid fa-key input-ico"></i><input type="password" class="form-input has-ico" placeholder="Repeat new password"></div></div>
      <button class="btn-reschedule" style="width:100%;margin-top:4px;display:flex;align-items:center;justify-content:center;gap:6px"><i class="fa-solid fa-shield-halved"></i> Update Password</button>
    </div>
  </div>
  <div class="settings-card">
    <div class="settings-card-head"><i class="fa-solid fa-bell"></i><h3>Notification Preferences</h3></div>
    <div class="settings-card-body">
      <?php foreach (['Email appointment reminders'=>true,'SMS alerts'=>true,'Push notifications'=>false,'Emergency SOS alerts'=>true,'Weekly health summary'=>false,'Nearby offers &amp; deals'=>false] as $lbl => $def): ?>
      <div class="toggle-row"><span class="toggle-label"><?= $lbl ?></span><label class="toggle-sw"><input type="checkbox" <?= $def ? 'checked' : '' ?>><div class="toggle-track"></div><div class="toggle-thumb"></div></label></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="settings-card">
    <div class="settings-card-head"><i class="fa-solid fa-circle-info"></i><h3>Account Information</h3></div>
    <div class="settings-card-body">
      <div class="info-row"><span class="info-label">Account Status</span><span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:9999px;font-size:12px;font-weight:600;background:rgba(22,163,74,.1);color:var(--green)"><i class="fa-solid fa-circle-check"></i> Active</span></div>
      <div class="info-row"><span class="info-label">Email Verified</span><span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:9999px;font-size:12px;font-weight:600;background:rgba(22,163,74,.1);color:var(--green)">Verified</span></div>
      <div class="info-row"><span class="info-label">Member Since</span><span class="info-value"><?= date('M Y', strtotime($pat['created_at'] ?? 'now')) ?></span></div>
      <div class="info-row"><span class="info-label">Patient ID</span><span class="info-value"><?= $patId ?></span></div>
      <div class="info-row"><span class="info-label">Preferred Service</span><span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:9999px;font-size:12px;font-weight:600;background:rgba(25,120,229,.1);color:var(--primary)"><?= ucfirst($pat['preferred_service'] ?? 'Healthcare') ?></span></div>
      <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--slate-100)">
        <button style="font-size:13px;font-weight:600;color:var(--red);background:none;border:1px solid rgba(220,38,38,.3);padding:8px 16px;border-radius:8px;cursor:pointer;font-family:'Inter',sans-serif" onclick="if(confirm('This cannot be undone. Contact support to delete your account.'))alert('Please email support@planeazzy.co.ke')"><i class="fa-solid fa-trash"></i> Delete Account</button>
      </div>
    </div>
  </div>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>PwdStrength.init('np','sf','st'));</script>
<?php endif; ?>

</div><!-- /.page-content -->

<!-- Book Appointment Modal -->
<div id="bookModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:500;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)closeModal('bookModal')">
  <div style="background:var(--white);border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);max-height:90vh;overflow-y:auto;width:100%;max-width:520px">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px 16px;border-bottom:1px solid var(--slate-100);position:sticky;top:0;background:var(--white);border-radius:20px 20px 0 0">
      <h2 style="font-size:18px;font-weight:700;color:var(--slate-900)"><i class="fa-solid fa-calendar-plus" style="color:var(--primary);margin-right:8px"></i>Book Appointment</h2>
      <button onclick="closeModal('bookModal')" style="width:30px;height:30px;border-radius:50%;background:var(--slate-100);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--slate-500)"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div style="padding:20px 24px 28px">
      <div id="bookAlertBox" class="alert hidden"></div>
      <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf) ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Service Type</label><select class="form-select" id="bookServiceType"><option value="doctor">See a Doctor</option><option value="hospital">Hospital Visit</option><option value="telehealth">Telehealth (Video)</option><option value="lab">Lab Test</option><option value="pharmacy">Pharmacy</option></select></div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Visit Type</label><select class="form-select" id="bookLocType"><option value="in_person">In-Person</option><option value="telehealth">Telehealth</option><option value="home_visit">Home Visit</option></select></div>
      </div>
      <div class="form-group"><label class="form-label">Provider (optional)</label>
        <select class="form-select" id="bookProvider">
          <option value="">— Any available provider —</option>
          <?php foreach (array_slice($nearby, 0, 15) as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= ucfirst($p['type']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Date</label><input type="date" id="bookDate" class="form-input" min="<?= date('Y-m-d') ?>"></div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Time</label><input type="time" id="bookTime" class="form-input" value="09:00"></div>
      </div>
      <div class="form-group"><label class="form-label">Reason / Title</label><input type="text" id="bookTitle" class="form-input" placeholder="e.g. General check-up, Follow-up consultation…"></div>
      <div class="form-group"><label class="form-label">Notes (optional)</label><textarea id="bookNotes" class="form-textarea" rows="2" placeholder="Any symptoms or additional information…"></textarea></div>
      <button id="bookBtn" style="width:100%;margin-top:8px;height:48px;border-radius:8px;font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;background:var(--primary);color:#fff;border:none;cursor:pointer;font-family:'Inter',sans-serif" onclick="submitBooking()"><i class="fa-solid fa-calendar-check"></i> Confirm Booking</button>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
<script>
function openModal(id){const el=document.getElementById(id);if(el)el.style.display='flex';}
function closeModal(id){const el=document.getElementById(id);if(el)el.style.display='none';}
function setTabFilter(t){const p=new URLSearchParams(window.location.search);p.set('af',t);window.location.href='?tab=appointments&'+p.toString();}
function markRead(id){const el=document.getElementById('ni-'+id);if(el){el.classList.remove('unread');const d=el.querySelector('.notif-unread-dot');if(d)d.remove();}fetch('/api/patient/mark-notification-read.php?id='+id,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'}}).catch(()=>{});}
function markAllRead(){document.querySelectorAll('.notif-list-item').forEach(i=>{i.classList.remove('unread');const d=i.querySelector('.notif-unread-dot');if(d)d.remove();});fetch('/api/patient/mark-notifications-read.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'}}).catch(()=>{});}
</script>
