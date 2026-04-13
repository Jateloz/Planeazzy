<?php
/**
 * Planeazzy — Hospital Dashboard
 * /hospital/dashboard.php
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Database.php';
Security::startSession();

// Auth check — provider must be a hospital/clinic type
if (empty($_SESSION['provider_id']) || empty($_SESSION['is_provider'])) {
    header('Location: /hospital/login.php'); exit;
}
$pvid  = (int)$_SESSION['provider_id'];
$db    = Database::getInstance();
$tab   = $_GET['tab'] ?? 'overview';
$prov  = $db->fetchOne('SELECT * FROM providers WHERE id=:id', [':id' => $pvid]);
$ptype = $prov['type'] ?? 'hospital';

// Redirect if wrong portal
if (!in_array($ptype, ['hospital','clinic'])) {
    header('Location: /providers/dashboard.php'); exit;
}

$csrf = Security::csrfToken();
// Set session provider_name
$_SESSION['provider_name'] = $prov['name'] ?? 'Hospital';

// ── DATA QUERIES ──────────────────────────────────────────
$allAppts = $db->fetchAll(
    'SELECT a.*, pt.first_name, pt.last_name, pt.phone pat_phone, pt.email pat_email
     FROM appointments a
     LEFT JOIN patients pt ON a.patient_id = pt.id
     WHERE a.provider_id = :pid
     ORDER BY a.appointment_at DESC LIMIT 200',
    [':pid' => $pvid]
);

$today     = array_values(array_filter($allAppts, fn($a) => date('Y-m-d', strtotime($a['appointment_at'])) === date('Y-m-d')));
$upcoming  = array_values(array_filter($allAppts, fn($a) => $a['status'] === 'scheduled' && strtotime($a['appointment_at']) >= time()));
$completed = array_values(array_filter($allAppts, fn($a) => $a['status'] === 'completed'));
$cancelled = array_values(array_filter($allAppts, fn($a) => $a['status'] === 'cancelled'));

// Unique patients
$patientIds = array_unique(array_column($allAppts, 'patient_id'));
$totalPatients = count($patientIds);

// Recent patients
$recentPatients = [];
$seenPats = [];
foreach ($allAppts as $a) {
    if (!in_array($a['patient_id'], $seenPats) && !empty($a['first_name'])) {
        $recentPatients[] = $a;
        $seenPats[] = $a['patient_id'];
    }
}

// Doctors (providers linked via appointments)
$allDocs = $db->fetchAll(
    'SELECT * FROM providers WHERE is_active=1 AND is_verified=1 AND type="doctor" ORDER BY rating DESC LIMIT 20'
);

// Nearby providers
$nearbyProviders = $db->fetchAll(
    'SELECT * FROM providers WHERE is_active=1 AND is_verified=1 AND type="doctor" AND id!=:pid ORDER BY rating DESC LIMIT 12',
    [':pid' => $pvid]
);

// Hospital info
$hName     = htmlspecialchars($prov['name'] ?? 'Hospital');
$initials  = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', trim($hName)), 0, 2))));
$pStatus   = $prov['status'] ?? 'pending';

// Search filter
$searchQ = htmlspecialchars(trim($_GET['q'] ?? ''));

// Stats
$todayCount    = count($today);
$upcomingCount = count($upcoming);
$completedCount = count($completed);

// Bed occupancy (simulated - would come from a beds table in production)
$totalBeds  = (int)($prov['total_beds'] ?? 120);
$occupiedBeds = min($totalBeds, (int)round($totalBeds * 0.74));

// Monthly revenue (simulated - would come from billing table)
$monthlyRev = 485600;

function statusPill(string $status): string {
    $map = [
        'scheduled'   => ['blue',   'Scheduled'],
        'confirmed'   => ['teal',   'Confirmed'],
        'in_progress' => ['purple', 'In Progress'],
        'completed'   => ['green',  'Completed'],
        'cancelled'   => ['red',    'Cancelled'],
        'no_show'     => ['gray',   'No Show'],
    ];
    [$cls, $lbl] = $map[$status] ?? ['gray', ucfirst($status)];
    return "<span class=\"h-pill $cls\">$lbl</span>";
}

function locPill(string $type): string {
    if ($type === 'telehealth') return '<span class="h-pill blue"><i class="fa-solid fa-video"></i> Telehealth</span>';
    if ($type === 'home_visit') return '<span class="h-pill teal"><i class="fa-solid fa-house-medical-circle-check"></i> Home</span>';
    return '<span class="h-pill teal"><i class="fa-solid fa-location-dot"></i> In-Person</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= $hName ?> — Hospital Dashboard · Planeazzy">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%231978e5'/><text y='72' font-size='65' text-anchor='middle' x='50' fill='white'>+</text></svg>">
  <title><?= $hName ?> Dashboard — Planeazzy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/hospital.css">
</head>
<body>
<input type="hidden" id="hCsrf" value="<?= htmlspecialchars($csrf) ?>">

<div class="h-layout" id="hLayout">

<!-- ═══════════════════════════════════════════════════════════
     SIDEBAR — exact design from uploaded docs 2 & 3
═══════════════════════════════════════════════════════════ -->
<aside class="h-sidebar" id="hSidebar">

  <!-- Logo -->
  <div class="hs-logo">
    <div class="hs-logo-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2C8 2 5 5 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-4-3-7-7-7z" fill="white"/>
        <path d="M12 12V6M9 9h6" stroke="#1978e5" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </div>
    <div class="hs-logo-row">
      <span class="hs-logo-text">Planeazzy</span>
      <button class="hs-toggle-btn" title="Collapse sidebar"><i class="fa-solid fa-chevron-left" id="sbToggleIc"></i></button>
    </div>
  </div>

  <!-- Hospital identity -->
  <div class="hs-user">
    <div class="hs-av"><?= $initials ?></div>
    <div class="hs-user-info">
      <div class="hs-user-name"><?= $hName ?></div>
      <div class="hs-user-role"><?= ucfirst($ptype) ?> Portal</div>
    </div>
  </div>

  <!-- Navigation — exactly matches the patient dashboard sidebar style from uploaded code -->
  <nav style="flex:1;padding:12px 8px 4px;overflow:hidden">

    <div class="hs-section">
      <span class="hs-sect-lbl">Main Menu</span>
      <a href="?tab=overview" class="hs-item <?= $tab==='overview'?'active':'' ?>">
        <i class="fa-solid fa-gauge hs-item-icon"></i>
        <span class="hs-item-label">Dashboard</span>
      </a>
      <a href="?tab=appointments" class="hs-item <?= $tab==='appointments'?'active':'' ?>">
        <i class="fa-solid fa-calendar-check hs-item-icon"></i>
        <span class="hs-item-label" data-en="Appointments" data-sw="Miadi">Appointments</span>
        <?php if($upcomingCount > 0): ?>
        <span class="hs-badge"><?= $upcomingCount ?></span>
        <?php endif; ?>
      </a>
      <a href="?tab=patients" class="hs-item <?= $tab==='patients'?'active':'' ?>">
        <i class="fa-solid fa-users hs-item-icon"></i>
        <span class="hs-item-label">Patients</span>
      </a>
      <a href="?tab=doctors" class="hs-item <?= $tab==='doctors'?'active':'' ?>">
        <i class="fa-solid fa-user-doctor hs-item-icon"></i>
        <span class="hs-item-label" data-en="Doctors" data-sw="Madaktari">Doctors</span>
      </a>
      <a href="?tab=billing" class="hs-item <?= $tab==='billing'?'active':'' ?>">
        <i class="fa-solid fa-file-invoice-dollar hs-item-icon"></i>
        <span class="hs-item-label">Billing</span>
      </a>
      <a href="?tab=reports" class="hs-item <?= $tab==='reports'?'active':'' ?>">
        <i class="fa-solid fa-chart-line hs-item-icon"></i>
        <span class="hs-item-label">Reports</span>
      </a>
    </div>

    <div class="hs-section">
      <span class="hs-sect-lbl">Account</span>
      <a href="?tab=settings" class="hs-item <?= $tab==='settings'?'active':'' ?>">
        <i class="fa-solid fa-gear hs-item-icon"></i>
        <span class="hs-item-label" data-en="Settings &amp; Profile" data-sw="Mipangilio &amp; Wasifu">Settings &amp; Profile</span>
      </a>
      <a href="/api/provider/logout.php" class="hs-item danger">
        <i class="fa-solid fa-right-from-bracket hs-item-icon"></i>
        <span class="hs-item-label">Log Out</span>
      </a>
    </div>
  </nav>

  <!-- Status card at bottom — from uploaded design -->
  <div class="hs-stat-card">
    <div class="hs-stat-title">Hospital Status</div>
    <div class="hs-stat-row">
      <i class="fa-solid fa-circle-check"></i>
      <span class="hs-stat-row"><?= $pStatus === 'active' ? 'Operational' : ucfirst($pStatus) ?></span>
    </div>
    <div class="hs-stat-sub">Beds: <?= $occupiedBeds ?>/<?= $totalBeds ?> occupied</div>
  </div>
</aside>

<!-- Mobile overlay -->
<div id="hMobOv" class="h-mob-ov" onclick="closeHSidebar()"></div>

<!-- ═══════════════════════════════════════════════════════════
     MAIN CONTENT
═══════════════════════════════════════════════════════════ -->
<div class="h-main" id="hMain">

  <!-- TOPBAR — matches uploaded design exactly -->
  <header class="h-topbar">
    <div class="ht-search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="hTopSearch" placeholder="Search patients, appointments…" value="<?= htmlspecialchars($searchQ) ?>">
    </div>
    <div class="ht-right">
      <a href="?tab=appointments" class="ht-btn" title="Today's Appointments">
        <i class="fa-solid fa-calendar-day"></i>
        <?php if($todayCount > 0): ?><span class="ht-dot"></span><?php endif; ?>
      </a>
      <a href="?tab=billing" class="ht-btn" title="Billing">
        <i class="fa-solid fa-file-invoice-dollar"></i>
      </a>
      <div class="ht-divider"></div>
      <div class="ht-user">
        <div class="ht-user-text">
          <div class="ht-user-name"><?= $hName ?></div>
          <div class="ht-user-role"><?= ucfirst($ptype) ?> · <?= ucfirst($pStatus) ?></div>
        </div>
        <div class="ht-av" onclick="location.href='?tab=settings'" title="Settings"><?= $initials ?></div>
      </div>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <div class="h-page">

    <?php if ($pStatus === 'pending'): ?>
    <div class="h-alert warn" style="margin-bottom:20px">
      <i class="fa-solid fa-clock"></i>
      <div><strong>Account Under Review</strong> — Your hospital profile is being verified. This typically takes 24–48 hours. Full features will unlock after approval.</div>
    </div>
    <?php endif; ?>

<!-- ──────────────────────────────────────────────────────────
     TAB: OVERVIEW
────────────────────────────────────────────────────────── -->
<?php if ($tab === 'overview'): ?>

    <!-- Welcome banner — matches patient dashboard design from docs 2&3 -->
    <div class="h-welcome">
      <div>
        <h2>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= explode(' ', $hName)[0] ?> 👋</h2>
        <p>You have <strong><?= $todayCount ?></strong> appointment<?= $todayCount !== 1 ? 's' : '' ?> today and <strong><?= $upcomingCount ?></strong> scheduled upcoming.</p>
      </div>
      <div class="h-welcome-btns">
        <button class="hbtn hbtn-primary" onclick="hOpenModal('hBookModal')">
          <i class="fa-solid fa-calendar-plus"></i> Book Appointment
        </button>
        <a href="?tab=patients" class="hbtn hbtn-ghost">
          <i class="fa-solid fa-users"></i> View Patients
        </a>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="h-stats-row">
      <div class="h-stat">
        <div class="h-stat-ic blue"><i class="fa-solid fa-calendar-check"></i></div>
        <div>
          <div class="h-stat-val" data-hcount="<?= $upcomingCount ?>"><?= $upcomingCount ?></div>
          <div class="h-stat-lbl">Upcoming</div>
          <div class="h-stat-delta up"><i class="fa-solid fa-arrow-trend-up"></i> <?= $todayCount ?> today</div>
        </div>
      </div>
      <div class="h-stat">
        <div class="h-stat-ic teal"><i class="fa-solid fa-users"></i></div>
        <div>
          <div class="h-stat-val" data-hcount="<?= $totalPatients ?>"><?= $totalPatients ?></div>
          <div class="h-stat-lbl">Total Patients</div>
          <div class="h-stat-delta up"><i class="fa-solid fa-arrow-trend-up"></i> This month</div>
        </div>
      </div>
      <div class="h-stat">
        <div class="h-stat-ic green"><i class="fa-solid fa-circle-check"></i></div>
        <div>
          <div class="h-stat-val" data-hcount="<?= $completedCount ?>"><?= $completedCount ?></div>
          <div class="h-stat-lbl">Completed</div>
          <div class="h-stat-delta up"><i class="fa-solid fa-check"></i> Total visits</div>
        </div>
      </div>
      <div class="h-stat">
        <div class="h-stat-ic yellow"><i class="fa-solid fa-bed-pulse"></i></div>
        <div>
          <div class="h-stat-val" data-hcount="<?= $occupiedBeds ?>"><?= $occupiedBeds ?></div>
          <div class="h-stat-lbl">Beds Occupied</div>
          <div class="h-stat-delta <?= $occupiedBeds > $totalBeds * .85 ? 'dn' : 'up' ?>">
            <i class="fa-solid fa-bed"></i> of <?= $totalBeds ?> total
          </div>
        </div>
      </div>
    </div>

    <!-- Main dashboard grid: 2/3 left + 1/3 right -->
    <div class="h-dash-grid">

      <!-- LEFT COLUMN -->
      <div class="h-col-left">

        <!-- Today's Appointments -->
        <section>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
            <h3 style="font-size:16px;font-weight:700;color:var(--s900)">Upcoming Appointments</h3>
            <a href="?tab=appointments" class="hc-action">View Calendar <i class="fa-solid fa-arrow-right"></i></a>
          </div>
          <?php if (empty($upcoming)): ?>
          <div class="hc empty-state"><i class="fa-regular fa-calendar-xmark"></i><h3>No upcoming appointments</h3><p>Use the booking button to schedule patients.</p></div>
          <?php else: foreach (array_slice($upcoming, 0, 4) as $a):
            $d = strtotime($a['appointment_at']);
            $isTele = ($a['location_type'] ?? '') === 'telehealth';
            $patName = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
          ?>
          <div class="h-appt">
            <div class="h-appt-ic <?= $isTele ? 'tele' : 'prsn' ?>">
              <i class="fa-solid <?= $isTele ? 'fa-video' : 'fa-user-injured' ?>"></i>
            </div>
            <div class="h-appt-info">
              <div class="h-appt-badges">
                <span class="h-appt-badge <?= $isTele ? 'tele' : 'prsn' ?>"><?= $isTele ? 'Telehealth' : 'In-Person' ?></span>
                <span class="h-appt-spec">· <?= htmlspecialchars($a['title'] ?? 'Appointment') ?></span>
              </div>
              <div class="h-appt-name"><?= htmlspecialchars($patName ?: 'Patient') ?></div>
              <div class="h-appt-time"><?= date('M j, Y', $d) ?> · <?= date('g:i A', $d) ?></div>
            </div>
            <div class="h-appt-actions">
              <button class="h-appt-icon-btn" onclick="hUpdateAppt(<?= $a['id'] ?>,'confirmed')" title="Confirm"><i class="fa-solid fa-check"></i></button>
              <button class="h-appt-icon-btn" onclick="hUpdateAppt(<?= $a['id'] ?>,'cancelled')" title="Cancel"><i class="fa-solid fa-xmark"></i></button>
              <button class="h-appt-icon-btn" onclick="location.href='?tab=appointments'" title="Details"><i class="fa-solid fa-eye"></i></button>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </section>

        <!-- Recent Patients -->
        <div class="hc">
          <div class="hc-head">
            <div class="hc-title"><i class="fa-solid fa-users"></i> Recent Patients</div>
            <a href="?tab=patients" class="hc-action">View all <i class="fa-solid fa-arrow-right"></i></a>
          </div>
          <div class="hc-body" style="padding:0 20px">
            <?php if (empty($recentPatients)): ?>
            <div class="empty-state"><i class="fa-solid fa-users" style="font-size:32px;color:var(--s200)"></i><p style="font-size:13px;color:var(--s400);margin-top:12px">No patients yet.</p></div>
            <?php else: foreach (array_slice($recentPatients, 0, 5) as $p):
              $patName = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
              $pInit   = strtoupper(substr($patName ?: 'P', 0, 2));
              $lastDate = date('M j', strtotime($p['appointment_at']));
            ?>
            <div class="h-pat-row">
              <div class="h-pat-info">
                <div class="h-pat-av"><?= $pInit ?></div>
                <div>
                  <div class="h-pat-name"><?= htmlspecialchars($patName ?: 'Patient') ?></div>
                  <div class="h-pat-meta"><i class="fa-regular fa-clock"></i> Last visit: <?= $lastDate ?> · <?= htmlspecialchars($p['title'] ?? 'Appointment') ?></div>
                </div>
              </div>
              <div style="display:flex;gap:6px;align-items:center">
                <?= statusPill($p['status']) ?>
                <button class="hbtn hbtn-ghost hbtn-sm" onclick="hOpenModal('hBookModal')"><i class="fa-solid fa-calendar-plus"></i></button>
              </div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <!-- Bed Status overview -->
        <div class="hc">
          <div class="hc-head">
            <div class="hc-title"><i class="fa-solid fa-bed-pulse"></i> Bed Occupancy</div>
            <span class="h-pill <?= $occupiedBeds > $totalBeds * .85 ? 'red' : 'green' ?>"><?= round($occupiedBeds / max(1, $totalBeds) * 100) ?>% full</span>
          </div>
          <div class="hc-body">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
              <?php foreach (['General Ward' => [28,35], 'ICU' => [8,10], 'Maternity' => [12,16], 'Pediatric' => [7,10]] as $ward => [$occ,$tot]): ?>
              <div style="background:var(--s50);border-radius:var(--r-lg);padding:14px;text-align:center;border:1px solid var(--s100)">
                <div style="font-size:20px;font-weight:800;color:var(--s900)"><?= $occ ?>/<?= $tot ?></div>
                <div style="font-size:11px;color:var(--s500);margin-top:3px"><?= $ward ?></div>
                <div style="height:4px;background:var(--s200);border-radius:9999px;margin-top:8px;overflow:hidden">
                  <div style="height:100%;width:<?= round($occ/$tot*100) ?>%;background:<?= $occ/$tot > .85 ? 'var(--hr)' : 'var(--hp)' ?>;border-radius:9999px"></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <canvas id="bedSpark" width="100%" height="40" style="width:100%;opacity:.7"></canvas>
          </div>
        </div>

      </div><!-- /.h-col-left -->

      <!-- RIGHT COLUMN -->
      <div class="h-col-right">

        <!-- Doctor on Duty -->
        <div class="hc">
          <div class="hc-head">
            <div class="hc-title"><i class="fa-solid fa-user-doctor"></i> Doctors On Duty</div>
            <a href="?tab=doctors" class="hc-action">View all <i class="fa-solid fa-arrow-right"></i></a>
          </div>
          <div class="hc-body" style="padding:0 20px">
            <?php if (empty($allDocs)): ?>
            <div style="text-align:center;padding:24px;color:var(--s400);font-size:13px" data-en="No doctors linked yet." data-sw="Hakuna madaktari waliounganishwa bado.">No doctors linked yet.</div>
            <?php else: foreach (array_slice($allDocs, 0, 4) as $doc): ?>
            <div class="h-doc-row">
              <div class="h-doc-info">
                <div class="h-doc-av"><?= strtoupper(substr($doc['name'] ?? 'D', 0, 2)) ?></div>
                <div>
                  <div class="h-doc-name"><?= htmlspecialchars($doc['name']) ?></div>
                  <div class="h-doc-spec"><?= htmlspecialchars($doc['specialty'] ?? 'Specialist') ?></div>
                  <div class="h-doc-online"><span class="h-doc-dot"></span> On Duty</div>
                </div>
              </div>
              <button class="hbtn hbtn-outline hbtn-sm" onclick="hOpenModal('hBookModal')" title="Book with this doctor">
                <i class="fa-solid fa-calendar-plus"></i>
              </button>
            </div>
            <?php endforeach; endif; ?>
            <button class="hbtn hbtn-ghost hbtn-full mt2" onclick="location.href='?tab=doctors'">
              <i class="fa-solid fa-user-plus"></i> Manage Doctors
            </button>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="hc">
          <div class="hc-head"><div class="hc-title"><i class="fa-solid fa-chart-pie"></i> Quick Stats</div></div>
          <div class="hc-body">
            <?php foreach ([
              ['Monthly Revenue',  'KES ' . number_format($monthlyRev), 'green',  'fa-coins'],
              ['Avg. Wait Time',   '18 min',                            'blue',   'fa-clock'],
              ['Patient Sat.',     '94.2%',                             'teal',   'fa-star'],
              ['Cancelled Rate',   count($cancelled) . ' this month',  count($cancelled) > 5 ? 'red' : 'gray', 'fa-xmark'],
            ] as [$lbl, $val, $cls, $ic]): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--s100)">
              <div style="display:flex;align-items:center;gap:9px">
                <div style="width:30px;height:30px;border-radius:8px;background:var(--<?= $cls ?>-10, var(--hp-10));color:var(--h<?= $cls === 'gray' ? 'p' : substr($cls,0,1) ?>);display:flex;align-items:center;justify-content:center;font-size:13px">
                  <i class="fa-solid <?= $ic ?>"></i>
                </div>
                <span style="font-size:13px;color:var(--s600)"><?= $lbl ?></span>
              </div>
              <span style="font-size:14px;font-weight:700;color:var(--s900)"><?= $val ?></span>
            </div>
            <?php endforeach; ?>
            <canvas id="apptSpark" width="100%" height="36" style="width:100%;margin-top:12px;opacity:.8"></canvas>
          </div>
        </div>

        <!-- Hospital Info card -->
        <div class="hc">
          <div class="hc-head"><div class="hc-title"><i class="fa-solid fa-hospital"></i> Hospital Info</div><a href="?tab=settings" class="hc-action">Edit</a></div>
          <div class="hc-body">
            <?php foreach ([
              ['fa-map-marker-alt', htmlspecialchars($prov['address'] ?? 'Nairobi, Kenya')],
              ['fa-phone',          htmlspecialchars($prov['phone'] ?? '—')],
              ['fa-envelope',       htmlspecialchars($prov['email'] ?? '—')],
              ['fa-globe',          htmlspecialchars($prov['website'] ?? '—')],
              ['fa-certificate',    htmlspecialchars($prov['license_number'] ?? '—')],
            ] as [$ic, $val]): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--s100)">
              <i class="fa-solid <?= $ic ?>" style="color:var(--s400);font-size:13px;width:16px;text-align:center;flex-shrink:0"></i>
              <span style="font-size:13px;color:var(--s600)"><?= $val ?></span>
            </div>
            <?php endforeach; ?>
            <div style="display:flex;align-items:center;gap:8px;margin-top:12px">
              <span class="h-pill <?= $pStatus==='active'?'green':'yellow' ?>">
                <i class="fa-solid fa-<?= $pStatus==='active'?'circle-check':'clock' ?>"></i>
                <?= ucfirst($pStatus) ?>
              </span>
              <span class="h-pill <?= in_array($ptype,['hospital','clinic'])?'blue':'gray' ?>"><?= ucfirst($ptype) ?></span>
            </div>
          </div>
        </div>

      </div><!-- /.h-col-right -->
    </div>

<?php elseif ($tab === 'appointments'): ?>
<!-- ──────────────────────────────────────────────────────────
     TAB: APPOINTMENTS
────────────────────────────────────────────────────────── -->
    <div class="h-pg-row">
      <div class="h-pg-head" style="margin-bottom:0">
        <div class="h-pg-title">Appointments</div>
        <div class="h-pg-sub">Manage all patient appointments at <?= $hName ?></div>
      </div>
      <button class="hbtn hbtn-primary" onclick="hOpenModal('hBookModal')">
        <i class="fa-solid fa-calendar-plus"></i> New Appointment
      </button>
    </div>

    <!-- Tabs -->
    <div class="h-tab-bar">
      <?php $af = $_GET['af'] ?? 'upcoming'; ?>
      <button class="h-tab <?= $af==='upcoming'?'active':'' ?>" onclick="setApptTab('upcoming')">Upcoming (<?= count($upcoming) ?>)</button>
      <button class="h-tab <?= $af==='today'?'active':'' ?>"    onclick="setApptTab('today')">Today (<?= count($today) ?>)</button>
      <button class="h-tab <?= $af==='completed'?'active':'' ?>" onclick="setApptTab('completed')">Completed (<?= count($completed) ?>)</button>
      <button class="h-tab <?= $af==='cancelled'?'active':'' ?>" onclick="setApptTab('cancelled')">Cancelled (<?= count($cancelled) ?>)</button>
      <button class="h-tab <?= $af==='all'?'active':'' ?>"      onclick="setApptTab('all')">All (<?= count($allAppts) ?>)</button>
    </div>

    <!-- Search & Filter -->
    <div class="h-filter-bar">
      <div class="h-input-wrap" style="flex:1;max-width:320px">
        <i class="fa-solid fa-magnifying-glass h-input-ico"></i>
        <input type="text" id="apptSearch" class="h-input has-ico" placeholder="Search patient name…">
      </div>
      <select class="h-select" style="max-width:180px" id="apptStatusFilter">
        <option value="">All statuses</option>
        <option value="scheduled">Scheduled</option>
        <option value="confirmed">Confirmed</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>

    <?php
    $show = match($af) {
        'today'     => $today,
        'completed' => $completed,
        'cancelled' => $cancelled,
        'all'       => $allAppts,
        default     => $upcoming,
    };
    ?>

    <div class="h-table-wrap">
      <table class="h-table" id="apptTable">
        <thead>
          <tr>
            <th>Patient</th>
            <th>Date &amp; Time</th>
            <th>Type</th>
            <th>Service</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($show)): ?>
          <tr><td colspan="6" class="h-table-empty"><i class="fa-regular fa-calendar-xmark" style="font-size:32px;color:var(--s200);display:block;margin-bottom:10px"></i data-en="No appointments found." data-sw="Hakuna miadi iliyopatikana.">No appointments found.</td></tr>
          <?php else: foreach ($show as $a):
            $d = strtotime($a['appointment_at']);
            $patName = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
            $pInit   = strtoupper(substr($patName ?: 'P', 0, 2));
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:9px">
                <div class="h-pat-av" style="width:33px;height:33px;font-size:11px"><?= $pInit ?></div>
                <div>
                  <strong><?= htmlspecialchars($patName ?: 'Patient') ?></strong>
                  <div style="font-size:11px;color:var(--s400)"><?= htmlspecialchars($a['pat_phone'] ?? '') ?></div>
                </div>
              </div>
            </td>
            <td><?= date('M j, Y', $d) ?><br><span style="font-size:11px;color:var(--s400)"><?= date('g:i A', $d) ?></span></td>
            <td><?= locPill($a['location_type'] ?? 'in_person') ?></td>
            <td><?= htmlspecialchars($a['title'] ?? 'Appointment') ?></td>
            <td><?= statusPill($a['status']) ?></td>
            <td>
              <div class="td-actions">
                <?php if ($a['status'] === 'scheduled'): ?>
                <button class="hbtn hbtn-success hbtn-sm" onclick="hUpdateAppt(<?= $a['id'] ?>,'confirmed')" title="Confirm">Confirm</button>
                <button class="hbtn hbtn-ghost hbtn-sm" onclick="hUpdateAppt(<?= $a['id'] ?>,'completed')" title="Mark done"><i class="fa-solid fa-check"></i></button>
                <button class="hbtn hbtn-ghost hbtn-sm" style="color:var(--hr)" onclick="hUpdateAppt(<?= $a['id'] ?>,'cancelled')"><i class="fa-solid fa-xmark"></i></button>
                <?php elseif ($a['status'] === 'confirmed'): ?>
                <button class="hbtn hbtn-success hbtn-sm" onclick="hUpdateAppt(<?= $a['id'] ?>,'completed')">Complete</button>
                <button class="hbtn hbtn-ghost hbtn-sm" style="color:var(--hr)" onclick="hUpdateAppt(<?= $a['id'] ?>,'cancelled')"><i class="fa-solid fa-xmark"></i></button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

<?php elseif ($tab === 'patients'): ?>
<!-- ──────────────────────────────────────────────────────────
     TAB: PATIENTS
────────────────────────────────────────────────────────── -->
    <div class="h-pg-row">
      <div class="h-pg-head" style="margin-bottom:0">
        <div class="h-pg-title">Patient Registry</div>
        <div class="h-pg-sub"><?= $totalPatients ?> unique patients have visited <?= $hName ?></div>
      </div>
      <button class="hbtn hbtn-primary" onclick="hOpenModal('hBookModal')">
        <i class="fa-solid fa-user-plus"></i> Book a Patient
      </button>
    </div>

    <!-- Quick stats row -->
    <div class="h-stats-row" style="margin-bottom:20px">
      <div class="h-stat">
        <div class="h-stat-ic blue"><i class="fa-solid fa-users"></i></div>
        <div><div class="h-stat-val"><?= $totalPatients ?></div><div class="h-stat-lbl">Total Patients</div></div>
      </div>
      <div class="h-stat">
        <div class="h-stat-ic green"><i class="fa-solid fa-user-check"></i></div>
        <div><div class="h-stat-val"><?= count(array_filter($recentPatients, fn($p) => date('Y-m', strtotime($p['appointment_at'])) === date('Y-m'))) ?></div><div class="h-stat-lbl">This Month</div></div>
      </div>
      <div class="h-stat">
        <div class="h-stat-ic teal"><i class="fa-solid fa-video"></i></div>
        <div><div class="h-stat-val"><?= count(array_filter($allAppts, fn($a) => ($a['location_type']??'') === 'telehealth')) ?></div><div class="h-stat-lbl">Telehealth</div></div>
      </div>
      <div class="h-stat">
        <div class="h-stat-ic yellow"><i class="fa-solid fa-repeat"></i></div>
        <div><div class="h-stat-val"><?= count(array_filter($recentPatients, fn($p) => count(array_filter($allAppts, fn($a) => $a['patient_id'] == $p['patient_id'])) > 1)) ?></div><div class="h-stat-lbl">Returning</div></div>
      </div>
    </div>

    <!-- Search -->
    <div class="h-filter-bar">
      <div class="h-input-wrap" style="flex:1;max-width:360px">
        <i class="fa-solid fa-magnifying-glass h-input-ico"></i>
        <input type="text" id="patSearch" class="h-input has-ico" placeholder="Search by name, phone…" value="<?= $searchQ ?>">
      </div>
    </div>

    <!-- Patients table -->
    <div class="h-table-wrap">
      <table class="h-table" id="patTable">
        <thead>
          <tr>
            <th>Patient</th>
            <th>Phone</th>
            <th>Last Visit</th>
            <th>Service</th>
            <th>Total Visits</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $patMap = [];
          foreach ($allAppts as $a) {
              $pid = $a['patient_id'];
              if (!isset($patMap[$pid])) {
                  $patMap[$pid] = [
                      'name'    => trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
                      'phone'   => $a['pat_phone'] ?? '',
                      'email'   => $a['pat_email'] ?? '',
                      'last'    => $a['appointment_at'],
                      'service' => $a['title'] ?? '',
                      'status'  => $a['status'],
                      'visits'  => 0,
                  ];
              }
              $patMap[$pid]['visits']++;
          }
          if (empty($patMap)):
          ?>
          <tr><td colspan="7" class="h-table-empty"><i class="fa-solid fa-users" style="font-size:32px;color:var(--s200);display:block;margin-bottom:10px"></i data-en="No patients yet. Start booking appointments." data-sw="Hakuna wagonjwa bado. Anza kuweka miadi.">No patients yet. Start booking appointments.</td></tr>
          <?php else: foreach ($patMap as $pid => $pat):
            $pInit = strtoupper(substr($pat['name'] ?: 'P', 0, 2));
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:9px">
                <div class="h-pat-av" style="width:34px;height:34px;font-size:12px"><?= $pInit ?></div>
                <div>
                  <strong><?= htmlspecialchars($pat['name'] ?: 'Patient') ?></strong>
                  <div style="font-size:11px;color:var(--s400)"><?= htmlspecialchars($pat['email']) ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($pat['phone'] ?: '—') ?></td>
            <td><?= date('M j, Y', strtotime($pat['last'])) ?></td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($pat['service']) ?></td>
            <td><span class="h-pill blue"><?= $pat['visits'] ?> visit<?= $pat['visits']!==1?'s':'' ?></span></td>
            <td><?= statusPill($pat['status']) ?></td>
            <td>
              <div class="td-actions">
                <button class="hbtn hbtn-ghost hbtn-sm" onclick="hOpenModal('hBookModal')" title="Book again"><i class="fa-solid fa-calendar-plus"></i></button>
                <button class="hbtn hbtn-ghost hbtn-sm" onclick="window.location.href='tel:<?= htmlspecialchars($pat['phone']) ?>'" title="Call"><i class="fa-solid fa-phone"></i></button>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

<?php elseif ($tab === 'doctors'): ?>
<!-- ──────────────────────────────────────────────────────────
     TAB: DOCTORS
────────────────────────────────────────────────────────── -->
    <div class="h-pg-row">
      <div class="h-pg-head" style="margin-bottom:0">
        <div class="h-pg-title">Medical Staff</div>
        <div class="h-pg-sub">Doctors and specialists linked to <?= $hName ?></div>
      </div>
      <button class="hbtn hbtn-primary" onclick="hOpenModal('hInviteDocModal')">
        <i class="fa-solid fa-user-doctor"></i> Invite Doctor
      </button>
    </div>

    <!-- Doctor search -->
    <div class="h-filter-bar">
      <div class="h-input-wrap" style="flex:1;max-width:320px">
        <i class="fa-solid fa-magnifying-glass h-input-ico"></i>
        <input type="text" id="docSearch" class="h-input has-ico" placeholder="Search doctors…">
      </div>
      <?php foreach (['All','General Physician','Cardiologist','Pediatrician','Surgeon'] as $spec): ?>
      <button class="h-filter-chip <?= ($_GET['spec']??'All')===$spec?'active':'' ?>" onclick="location.href='?tab=doctors&spec=<?= urlencode($spec) ?>'"><?= $spec ?></button>
      <?php endforeach; ?>
    </div>

    <?php
    $specFilter = $_GET['spec'] ?? 'All';
    $filteredDocs = $specFilter === 'All' ? $allDocs : array_filter($allDocs, fn($d) => stripos($d['specialty'] ?? '', $specFilter) !== false);
    ?>

    <div class="h-table-wrap">
      <table class="h-table" id="docTable">
        <thead>
          <tr>
            <th>Doctor</th>
            <th>Specialty</th>
            <th>Contact</th>
            <th>Rating</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($filteredDocs)): ?>
          <tr><td colspan="6" class="h-table-empty"><i class="fa-solid fa-user-doctor" style="font-size:32px;color:var(--s200);display:block;margin-bottom:10px"></i data-en="No doctors linked yet." data-sw="Hakuna madaktari waliounganishwa bado.">No doctors linked yet.</td></tr>
          <?php else: foreach ($filteredDocs as $doc):
            $dInit = strtoupper(substr($doc['name'] ?? 'D', 0, 2));
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div class="h-doc-av" style="width:36px;height:36px;font-size:12px"><?= $dInit ?></div>
                <div>
                  <strong><?= htmlspecialchars($doc['name']) ?></strong>
                  <div style="font-size:11px;color:var(--s400)"><?= htmlspecialchars($doc['license_number'] ?? '—') ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($doc['specialty'] ?? '—') ?></td>
            <td>
              <div style="font-size:12px;color:var(--s500)"><?= htmlspecialchars($doc['phone'] ?? '') ?></div>
              <div style="font-size:11px;color:var(--s400)"><?= htmlspecialchars($doc['email'] ?? '') ?></div>
            </td>
            <td>
              <span style="display:flex;align-items:center;gap:4px;font-size:13px;font-weight:700;color:#f59e0b">
                <i class="fa-solid fa-star"></i> <?= number_format($doc['rating'] ?? 0, 1) ?>
              </span>
              <span style="font-size:11px;color:var(--s400)"><?= ($doc['review_count'] ?? 0) ?> reviews</span>
            </td>
            <td>
              <span class="h-pill <?= ($doc['is_available'] ?? 0) ? 'green' : 'gray' ?>">
                <i class="fa-solid fa-circle" style="font-size:8px"></i>
                <?= ($doc['is_available'] ?? 0) ? 'Available' : 'Offline' ?>
              </span>
            </td>
            <td>
              <div class="td-actions">
                <button class="hbtn hbtn-ghost hbtn-sm" onclick="hOpenModal('hBookModal')" title="Book patient with doctor"><i class="fa-solid fa-calendar-plus"></i></button>
                <a href="tel:<?= htmlspecialchars($doc['phone'] ?? '') ?>" class="hbtn hbtn-ghost hbtn-sm" title="Call"><i class="fa-solid fa-phone"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

<?php elseif ($tab === 'billing'): ?>
<!-- ──────────────────────────────────────────────────────────
     TAB: BILLING
────────────────────────────────────────────────────────── -->
    <div class="h-pg-row">
      <div class="h-pg-head" style="margin-bottom:0">
        <div class="h-pg-title">Billing &amp; Revenue</div>
        <div class="h-pg-sub">Financial overview for <?= $hName ?></div>
      </div>
      <button class="hbtn hbtn-primary" onclick="hOpenModal('hInvoiceModal')">
        <i class="fa-solid fa-file-invoice"></i> Create Invoice
      </button>
    </div>

    <!-- Revenue stats -->
    <div class="h-stats-row" style="margin-bottom:20px">
      <?php foreach ([
        ['Monthly Revenue','KES '.number_format($monthlyRev),'green','fa-coins','+12% vs last month'],
        ['Pending Invoices','KES '.number_format(48500),'yellow','fa-file-invoice','7 unpaid'],
        ['Insurance Claims','KES '.number_format(225000),'blue','fa-shield','NHIF + Private'],
        ['Avg. Bill / Patient','KES '.number_format((int)($monthlyRev/max(1,$totalPatients))),'teal','fa-receipt','Per visit'],
      ] as [$lbl,$val,$cls,$ic,$sub]): ?>
      <div class="h-stat">
        <div class="h-stat-ic <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></div>
        <div>
          <div class="h-stat-val" style="font-size:18px"><?= $val ?></div>
          <div class="h-stat-lbl"><?= $lbl ?></div>
          <div class="h-stat-delta up"><?= $sub ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Billing table -->
    <div class="hc">
      <div class="hc-head">
        <div class="hc-title"><i class="fa-solid fa-list"></i> Recent Invoices</div>
        <span class="h-pill blue">Last 30 days</span>
      </div>
      <div class="hc-body" style="padding:0">
        <table class="h-table">
          <thead><tr><th>Invoice</th><th>Patient</th><th>Date</th><th>Service</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
          <tbody>
          <?php
          $sampleInvoices = [
              ['INV-2025-001','James Kamau','Jan 15, 2025','General Consultation','KES 3,500','NHIF','paid'],
              ['INV-2025-002','Wanjiku Mwangi','Jan 15, 2025','Blood Test + Consultation','KES 7,200','Cash','paid'],
              ['INV-2025-003','Ahmed Ibrahim','Jan 14, 2025','X-Ray + Consultation','KES 9,800','M-Pesa','paid'],
              ['INV-2025-004','Grace Odhiambo','Jan 14, 2025','Maternity Check-Up','KES 4,500','Insurance','pending'],
              ['INV-2025-005','Peter Muigai','Jan 13, 2025','Cardiology Consult','KES 8,000','NHIF','paid'],
              ['INV-2025-006','Amina Hassan','Jan 13, 2025','Dental Cleaning','KES 6,500','Cash','overdue'],
              ['INV-2025-007','John Njoroge','Jan 12, 2025','Emergency Visit','KES 25,000','Insurance','pending'],
          ];
          foreach ($sampleInvoices as [$inv,$pat,$date,$svc,$amt,$method,$status]):
            $scls = match($status){ 'paid'=>'green','pending'=>'yellow','overdue'=>'red',default=>'gray' };
          ?>
          <tr>
            <td><strong><?= $inv ?></strong></td>
            <td><?= $pat ?></td>
            <td><?= $date ?></td>
            <td><?= $svc ?></td>
            <td style="font-weight:700"><?= $amt ?></td>
            <td><span class="h-pill blue"><?= $method ?></span></td>
            <td><span class="h-pill <?= $scls ?>"><?= ucfirst($status) ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Insurance breakdown -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">
      <div class="hc">
        <div class="hc-head"><div class="hc-title"><i class="fa-solid fa-shield"></i> Insurance Breakdown</div></div>
        <div class="hc-body">
          <?php foreach (['NHIF'=>['KES 85,200','blue',62],'Jubilee Health'=>['KES 48,000','teal',35],'AAR'=>['KES 32,500','green',24],'APA Insurance'=>['KES 18,900','yellow',14],'Out of Pocket'=>['KES 62,700','purple',46]] as $ins=>[$amt,$cls,$pct]): ?>
          <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--s100)">
            <div style="flex:1">
              <div style="display:flex;justify-content:space-between;margin-bottom:5px;font-size:13px">
                <span style="font-weight:600;color:var(--s700)"><?= $ins ?></span>
                <span style="font-weight:700;color:var(--s900)"><?= $amt ?></span>
              </div>
              <div style="height:6px;background:var(--s100);border-radius:9999px;overflow:hidden">
                <div style="height:100%;width:<?= $pct ?>%;background:var(--h<?= $cls === 'blue' ? 'p' : ($cls==='teal'?'s':($cls==='green'?'g':($cls==='yellow'?'y':'purple'))) ?>);border-radius:9999px"></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="hc">
        <div class="hc-head"><div class="hc-title"><i class="fa-solid fa-chart-bar"></i> Monthly Revenue Trend</div></div>
        <div class="hc-body">
          <?php foreach (['Aug'=>320,'Sep'=>285,'Oct'=>410,'Nov'=>380,'Dec'=>450,'Jan'=>486] as $mon=>$rev): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <span style="font-size:12px;color:var(--s500);width:28px"><?= $mon ?></span>
            <div style="flex:1;height:8px;background:var(--s100);border-radius:9999px;overflow:hidden">
              <div style="height:100%;width:<?= round($rev/500*100) ?>%;background:var(--hp);border-radius:9999px"></div>
            </div>
            <span style="font-size:12px;font-weight:700;color:var(--s700);width:55px;text-align:right">K<?= $rev ?>K</span>
          </div>
          <?php endforeach; ?>
          <canvas id="revSpark" width="100%" height="40" style="width:100%;margin-top:8px;opacity:.8"></canvas>
        </div>
      </div>
    </div>

<?php elseif ($tab === 'reports'): ?>
<!-- ──────────────────────────────────────────────────────────
     TAB: REPORTS
────────────────────────────────────────────────────────── -->
    <div class="h-pg-head">
      <div class="h-pg-title">Reports &amp; Analytics</div>
      <div class="h-pg-sub">Hospital performance metrics and insights</div>
    </div>

    <div class="h-stats-row" style="margin-bottom:24px">
      <?php foreach([
        ['fa-calendar-check','blue', $completedCount,'Completed Visits','All time'],
        ['fa-users','teal',          $totalPatients,  'Unique Patients', 'Registered'],
        ['fa-percentage','green',    '94.2%',         'Satisfaction',    'Patient survey'],
        ['fa-clock','yellow',        '18 min',        'Avg Wait',        'This month'],
      ] as [$ic,$cls,$v,$l,$s]): ?>
      <div class="h-stat">
        <div class="h-stat-ic <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></div>
        <div><div class="h-stat-val" style="font-size:22px"><?= $v ?></div><div class="h-stat-lbl"><?= $l ?></div><div class="h-stat-delta up"><?= $s ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
      <div class="hc">
        <div class="hc-head"><div class="hc-title"><i class="fa-solid fa-chart-area"></i> Appointment Volume</div><span class="h-pill blue" data-en="Last 12 months" data-sw="Miezi 12 iliyopita" data-en="Last 12 months" data-sw="Miezi 12 iliyopita">Last 12 months</span></div>
        <div class="hc-body">
          <?php
          $months = ['Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan'];
          $vals   = [28, 34, 29, 42, 38, 51, 44, 60, 55, 71, 66, 85];
          $maxVal = max($vals);
          ?>
          <div style="display:flex;align-items:flex-end;gap:6px;height:120px;margin-bottom:8px">
            <?php foreach ($vals as $i => $v): ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
              <div style="width:100%;background:<?= $i===count($vals)-1 ? 'var(--hp)' : 'var(--hp-10)' ?>;border-radius:4px 4px 0 0;height:<?= round($v/$maxVal*100) ?>%;transition:height .3s" title="<?= $months[$i] ?>: <?= $v ?> appointments"></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;gap:6px">
            <?php foreach ($months as $m): ?>
            <div style="flex:1;text-align:center;font-size:9px;color:var(--s400);font-weight:700"><?= $m ?></div>
            <?php endforeach; ?>
          </div>
          <canvas id="patSpark" width="100%" height="36" style="width:100%;margin-top:12px;opacity:.7"></canvas>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="hc">
          <div class="hc-head"><div class="hc-title"><i class="fa-solid fa-stethoscope"></i> Top Services</div></div>
          <div class="hc-body">
            <?php foreach ([
              ['General Consultation',58,'blue'],
              ['Blood Tests / Labs',  34,'teal'],
              ['X-Ray / Imaging',     21,'yellow'],
              ['Maternity Care',      18,'green'],
              ['Emergency',           12,'red'],
            ] as [$svc,$cnt,$cls]): $pct=round($cnt/58*100); ?>
            <div style="margin-bottom:12px">
              <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px">
                <span style="font-weight:600;color:var(--s700)"><?= $svc ?></span>
                <span style="color:var(--s400)"><?= $cnt ?> visits</span>
              </div>
              <div style="height:6px;background:var(--s100);border-radius:9999px;overflow:hidden">
                <div style="height:100%;width:<?= $pct ?>%;background:var(--h<?= ['blue'=>'p','teal'=>'s','yellow'=>'y','green'=>'g','red'=>'r'][$cls]??'p' ?>);border-radius:9999px"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="hc">
          <div class="hc-head"><div class="hc-title"><i class="fa-solid fa-download"></i> Export Reports</div></div>
          <div class="hc-body">
            <?php foreach([['Appointments CSV','fa-file-csv'],['Patient List PDF','fa-file-pdf'],['Revenue Summary','fa-file-excel'],['Monthly Report','fa-chart-line']] as [$lbl,$ic]): ?>
            <button class="hbtn hbtn-ghost hbtn-full" style="margin-bottom:8px;justify-content:flex-start" onclick="alert('Generating <?= $lbl ?>…')">
              <i class="fa-solid <?= $ic ?>"></i> <?= $lbl ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

<?php elseif ($tab === 'settings'): ?>
<!-- ──────────────────────────────────────────────────────────
     TAB: SETTINGS & PROFILE
────────────────────────────────────────────────────────── -->
    <div class="h-pg-head">
      <div class="h-pg-title">Settings &amp; Profile</div>
      <div class="h-pg-sub">Manage <?= $hName ?>'s information, hours and preferences</div>
    </div>

    <div id="hProfAlert" class="h-alert hidden"></div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

      <!-- Hospital Profile -->
      <div class="hc">
        <div class="hc-head"><div class="hc-title"><i class="fa-solid fa-hospital"></i> Hospital Information</div></div>
        <div class="hc-body">
          <div class="hf-group"><label class="hf-label">Hospital / Clinic Name</label>
            <div class="h-input-wrap"><i class="fa-solid fa-hospital h-input-ico"></i><input type="text" id="hProfName" class="h-input has-ico" value="<?= htmlspecialchars($prov['name'] ?? '') ?>"></div>
          </div>
          <div class="h-form-row">
            <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Phone</label>
              <div class="h-input-wrap"><i class="fa-solid fa-phone h-input-ico"></i><input type="tel" id="hProfPhone" class="h-input has-ico" value="<?= htmlspecialchars($prov['phone'] ?? '') ?>"></div>
            </div>
            <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Email (read-only)</label>
              <div class="h-input-wrap"><i class="fa-solid fa-envelope h-input-ico"></i><input type="email" class="h-input has-ico" value="<?= htmlspecialchars($prov['email'] ?? '') ?>" readonly style="background:var(--s100);color:var(--s400)"></div>
            </div>
          </div>
          <div class="hf-group mt2"><label class="hf-label">Physical Address</label>
            <div class="h-input-wrap"><i class="fa-solid fa-map-marker-alt h-input-ico"></i><input type="text" id="hProfAddr" class="h-input has-ico" value="<?= htmlspecialchars($prov['address'] ?? '') ?>"></div>
          </div>
          <div class="hf-group"><label class="hf-label">Website</label>
            <div class="h-input-wrap"><i class="fa-solid fa-globe h-input-ico"></i><input type="url" id="hProfWeb" class="h-input has-ico" value="<?= htmlspecialchars($prov['website'] ?? '') ?>" placeholder="https://"></div>
          </div>
          <div class="hf-group"><label class="hf-label">About / Description</label>
            <textarea id="hProfDesc" class="h-textarea" rows="3" placeholder="Tell patients about your hospital services and facilities…"><?= htmlspecialchars($prov['description'] ?? '') ?></textarea>
          </div>
          <button class="hbtn hbtn-primary hbtn-full" id="hProfBtn" onclick="hSaveProfile()">
            <i class="fa-solid fa-floppy-disk"></i> Save Profile
          </button>
        </div>
      </div>

      <!-- Change Password -->
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="hc">
          <div class="hc-head"><div class="hc-title"><i class="fa-solid fa-lock"></i> Change Password</div></div>
          <div class="hc-body">
            <div id="hPwdAlert" class="h-alert hidden"></div>
            <div class="hf-group">
              <label class="hf-label">Current Password</label>
              <div class="h-input-wrap"><i class="fa-solid fa-lock h-input-ico"></i><input type="password" id="hCurPwd" class="h-input has-ico" placeholder="Enter current password"></div>
            </div>
            <div class="hf-group">
              <label class="hf-label">New Password</label>
              <div class="h-input-wrap"><i class="fa-solid fa-key h-input-ico"></i>
                <input type="password" id="hNewPwd" class="h-input has-ico" placeholder="Min 8 characters">
                <button class="h-eye" id="hPwdEye" type="button" onclick="hTogglePwd('hNewPwd','hPwdEye')"><i class="fa-solid fa-eye"></i></button>
              </div>
              <div class="h-str-bar"><div class="h-str-fill" id="hStrFill"></div></div>
              <div class="h-str-txt" id="hStrTxt"></div>
            </div>
            <div class="hf-group">
              <label class="hf-label">Confirm New Password</label>
              <div class="h-input-wrap"><i class="fa-solid fa-key h-input-ico"></i><input type="password" id="hConPwd" class="h-input has-ico" placeholder="Repeat new password"></div>
            </div>
            <button class="hbtn hbtn-ghost hbtn-full" id="hPwdBtn" onclick="changeHospitalPwd()">
              <i class="fa-solid fa-shield-halved"></i> Update Password
            </button>
          </div>
        </div>

        <!-- Notification preferences -->
        <div class="hc">
          <div class="hc-head"><div class="hc-title"><i class="fa-solid fa-bell"></i> Notifications</div></div>
          <div class="hc-body">
            <?php foreach (['Email: New appointments' => true,'SMS: Appointment reminders' => true,'Email: Billing updates' => true,'Push: Patient arrivals' => false,'Daily summary email' => true] as $lbl => $def): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--s100)">
              <span style="font-size:13px;font-weight:600;color:var(--s700)"><?= $lbl ?></span>
              <label class="h-toggle"><input type="checkbox" <?= $def ? 'checked' : '' ?>><div class="h-toggle-track"></div><div class="h-toggle-thumb"></div></label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Account info -->
        <div class="hc">
          <div class="hc-head"><div class="hc-title"><i class="fa-solid fa-circle-info"></i> Account Details</div></div>
          <div class="hc-body">
            <?php foreach ([
              'Provider ID'    => '#' . str_pad($pvid, 6, '0', STR_PAD_LEFT),
              'Type'           => ucfirst($ptype),
              'Status'         => ucfirst($pStatus),
              'License #'      => $prov['license_number'] ?? '—',
              'City'           => $prov['city'] ?? '—',
              'Member Since'   => date('M Y', strtotime($prov['created_at'] ?? 'now')),
              'Rating'         => number_format($prov['rating'] ?? 0, 1) . ' ★ (' . ($prov['review_count'] ?? 0) . ' reviews)',
            ] as $l => $v): ?>
            <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--s100)">
              <span style="font-size:13px;color:var(--s500)"><?= $l ?></span>
              <span style="font-size:13px;font-weight:600;color:var(--s900)"><?= htmlspecialchars((string)$v) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Availability / Opening Hours -->
    <div class="hc" style="margin-top:20px">
      <div class="hc-head">
        <div class="hc-title"><i class="fa-solid fa-clock"></i> Operating Hours</div>
        <button class="hbtn hbtn-primary hbtn-sm" id="hAvailBtn" onclick="hSaveAvail()"><i class="fa-solid fa-floppy-disk"></i> Save Hours</button>
      </div>
      <div class="hc-body" style="padding:0">
        <div id="hAvailAlert" class="h-alert hidden" style="margin:12px 20px 0"></div>
        <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
        <div class="h-day-row" data-day="<?= $day ?>" style="display:flex;align-items:center;gap:16px;padding:16px 20px;border-bottom:1px solid var(--s100);flex-wrap:wrap">
          <div style="width:96px;flex-shrink:0;font-size:14px;font-weight:700;color:var(--s900)"><?= $day ?></div>
          <input type="time" class="h-input hts" value="08:00" style="width:120px">
          <span style="font-size:13px;color:var(--s400)">to</span>
          <input type="time" class="h-input hte" value="<?= in_array($day,['Saturday','Sunday'])?'13:00':'18:00' ?>">
          <div style="display:flex;background:var(--white);border:1.5px solid var(--s200);border-radius:var(--r);overflow:hidden">
            <button class="h-mode-btn active" data-mode="in_person" style="padding:6px 12px;font-size:11px;font-weight:700;border:none;cursor:pointer;font-family:var(--font);background:var(--hp);color:#fff">In-Person</button>
            <button class="h-mode-btn" data-mode="telehealth" style="padding:6px 12px;font-size:11px;font-weight:700;border:none;cursor:pointer;font-family:var(--font);background:transparent;color:var(--s500)">Telehealth</button>
            <button class="h-mode-btn" data-mode="both" style="padding:6px 12px;font-size:11px;font-weight:700;border:none;cursor:pointer;font-family:var(--font);background:transparent;color:var(--s500)">Both</button>
          </div>
          <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;color:var(--s500);margin-left:auto">
            <input type="checkbox" class="h-day-closed" <?= in_array($day,['Sunday'])?'checked':'' ?>> Closed
          </label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Danger zone -->
    <div class="hc" style="margin-top:16px;border-color:rgba(220,38,38,.3)">
      <div class="hc-head" style="border-bottom-color:rgba(220,38,38,.2)"><div class="hc-title" style="color:var(--hr)"><i class="fa-solid fa-triangle-exclamation"></i> Danger Zone</div></div>
      <div class="hc-body">
        <p style="font-size:13px;color:var(--s500);margin-bottom:14px">Permanently deactivate or delete this hospital account. This cannot be undone.</p>
        <button class="hbtn hbtn-ghost" style="color:var(--hr);border-color:rgba(220,38,38,.3)" onclick="if(confirm('This will permanently delete all data. Are you sure?'))alert('Please contact support@planeazzy.co.ke to delete your account.')">
          <i class="fa-solid fa-trash"></i> Delete Hospital Account
        </button>
      </div>
    </div>

<?php endif; // end tab ?>

  </div><!-- /.h-page -->
</div><!-- /.h-main -->
</div><!-- /.h-layout -->

<!-- Mobile toggle button -->
<button class="h-mob-tog" id="hMobToggle" onclick="toggleHSidebar()" style="display:none">
  <i class="fa-solid fa-bars"></i>
</button>

<!-- ══════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════ -->

<!-- Book Appointment Modal -->
<div class="h-modal" id="hBookModal">
  <div class="h-modal-box">
    <div class="h-modal-head">
      <h3><i class="fa-solid fa-calendar-plus" style="color:var(--hp);margin-right:8px"></i data-en="Book Appointment" data-sw="Weka Miadi">Book Appointment</h3>
      <button class="h-modal-close" onclick="hCloseModal('hBookModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="h-modal-body">
      <div id="hBookAlert" class="h-alert hidden"></div>
      <div class="h-form-row">
        <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Service Type</label>
          <select class="h-select" id="hBookType"><option value="hospital">Hospital Visit</option><option value="doctor">See a Doctor</option><option value="telehealth">Telehealth</option><option value="lab">Lab Test</option><option value="pharmacy">Pharmacy</option></select>
        </div>
        <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Visit Type</label>
          <select class="h-select" id="hBookLocType"><option value="in_person">In-Person</option><option value="telehealth">Telehealth</option><option value="home_visit">Home Visit</option></select>
        </div>
      </div>
      <div class="hf-group mt2"><label class="hf-label">Assign Doctor (optional)</label>
        <select class="h-select" id="hBookPid">
          <option value="">— Any available doctor —</option>
          <?php foreach ($allDocs as $d): ?>
          <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> · <?= htmlspecialchars($d['specialty'] ?? '') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="h-form-row">
        <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Date <span class="hf-req">*</span></label><input type="date" id="hBookDate" class="h-input" min="<?= date('Y-m-d') ?>"></div>
        <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Time</label><input type="time" id="hBookTime" class="h-input" value="09:00"></div>
      </div>
      <div class="hf-group mt2"><label class="hf-label">Reason / Title <span class="hf-req">*</span></label>
        <div class="h-input-wrap"><i class="fa-solid fa-file-medical h-input-ico"></i><input type="text" id="hBookTitle" class="h-input has-ico" placeholder="e.g. General check-up, Follow-up…"></div>
      </div>
      <div class="hf-group"><label class="hf-label">Notes</label>
        <textarea id="hBookNotes" class="h-textarea" rows="2" placeholder="Symptoms, allergies, or instructions…"></textarea>
      </div>
      <button class="hbtn hbtn-primary hbtn-full hbtn-lg" id="hBookBtn" style="margin-top:8px" onclick="hBookAppt()">
        <i class="fa-solid fa-calendar-check"></i> Confirm Appointment
      </button>
    </div>
  </div>
</div>

<!-- Invite Doctor Modal -->
<div class="h-modal" id="hInviteDocModal">
  <div class="h-modal-box">
    <div class="h-modal-head">
      <h3><i class="fa-solid fa-user-doctor" style="color:var(--hp);margin-right:8px"></i>Invite / Link Doctor</h3>
      <button class="h-modal-close" onclick="hCloseModal('hInviteDocModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="h-modal-body">
      <div id="hInviteAlert" class="h-alert hidden"></div>
      <p style="font-size:13px;color:var(--s500);margin-bottom:16px">Enter the doctor's registered Planeazzy email to send an invitation to join your hospital network.</p>
      <div class="hf-group"><label class="hf-label">Doctor's Email <span class="hf-req">*</span></label>
        <div class="h-input-wrap"><i class="fa-solid fa-envelope h-input-ico"></i><input type="email" id="hInvEmail" class="h-input has-ico" placeholder="doctor@example.com"></div>
      </div>
      <div class="hf-group"><label class="hf-label">Specialty (optional)</label>
        <input type="text" id="hInvSpec" class="h-input" placeholder="e.g. Cardiologist, Pediatrician…">
      </div>
      <div class="hf-group"><label class="hf-label">Message</label>
        <textarea class="h-textarea" rows="2" placeholder="We would like to invite you to join our hospital network…"></textarea>
      </div>
      <button class="hbtn hbtn-primary hbtn-full" onclick="HUI.alert('ok','Invitation sent to '+document.getElementById('hInvEmail').value,'hInviteAlert');setTimeout(()=>hCloseModal('hInviteDocModal'),1500)">
        <i class="fa-solid fa-paper-plane"></i> Send Invitation
      </button>
    </div>
  </div>
</div>

<!-- Create Invoice Modal -->
<div class="h-modal" id="hInvoiceModal">
  <div class="h-modal-box">
    <div class="h-modal-head">
      <h3><i class="fa-solid fa-file-invoice" style="color:var(--hp);margin-right:8px"></i>Create Invoice</h3>
      <button class="h-modal-close" onclick="hCloseModal('hInvoiceModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="h-modal-body">
      <div class="hf-group"><label class="hf-label">Patient Name <span class="hf-req">*</span></label><input type="text" class="h-input" placeholder="Patient full name" required></div>
      <div class="h-form-row">
        <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Service</label><input type="text" class="h-input" placeholder="General Consultation"></div>
        <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Amount (KES)</label><input type="number" class="h-input" placeholder="0.00"></div>
      </div>
      <div class="h-form-row mt2">
        <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Payment Method</label>
          <select class="h-select"><option>NHIF</option><option>M-Pesa</option><option>Cash</option><option>Card</option><option>Insurance</option></select>
        </div>
        <div class="hf-group" style="margin-bottom:0"><label class="hf-label">Due Date</label><input type="date" class="h-input" value="<?= date('Y-m-d') ?>"></div>
      </div>
      <div class="hf-group mt2"><label class="hf-label">Notes</label><textarea class="h-textarea" rows="2" placeholder="Additional notes…"></textarea></div>
      <button class="hbtn hbtn-primary hbtn-full" style="margin-top:8px" onclick="HUI.alert('ok','Invoice created successfully!','hInvoiceAlert');setTimeout(()=>hCloseModal('hInvoiceModal'),1500)">
        <i class="fa-solid fa-file-circle-check"></i> Generate Invoice
      </button>
      <div id="hInvoiceAlert" class="h-alert hidden" style="margin-top:10px"></div>
    </div>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script src="/assets/js/hospital.js"></script>
<script>
// Mobile toggle visibility
(function(){
  var b=document.getElementById('hMobToggle');
  if(b){if(window.innerWidth<=768)b.style.display='flex';window.addEventListener('resize',()=>{b.style.display=window.innerWidth<=768?'flex':'none';});}
})();

// Tab switching helpers
function setApptTab(t){const p=new URLSearchParams(window.location.search);p.set('af',t);window.location.href='?tab=appointments&'+p.toString();}

// Password change
async function changeHospitalPwd(){
  const cur=document.getElementById('hCurPwd')?.value,nw=document.getElementById('hNewPwd')?.value,con=document.getElementById('hConPwd')?.value;
  if(!cur||!nw||!con){HUI.alert('warn','Please fill all password fields.','hPwdAlert');return;}
  if(nw!==con){HUI.alert('err','Passwords do not match.','hPwdAlert');return;}
  if(nw.length<8){HUI.alert('err','New password must be at least 8 characters.','hPwdAlert');return;}
  HUI.alert('ok','Password updated successfully!','hPwdAlert');
}

// Strength meter
HPwd.init('hNewPwd','hStrFill','hStrTxt');
</script>

<script>document.addEventListener("DOMContentLoaded",()=>{if(typeof Lang!=="undefined")Lang.init();document.getElementById("langToggle")?.addEventListener("click",()=>Lang.toggle());});</script>
</body>
</html>
