<?php
/**
 * Planeazzy — providers/dashboard.php
 * Provider portal dashboard (doctors, clinics, hospitals, ambulance)
 */
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
require_once dirname(__DIR__). '/services/Database.php';
require_once dirname(__DIR__). '/services/ProviderService.php';

Security::startSession();
if (empty($_SESSION['provider_id'])) { header('Location: /providers/login.php'); exit; }

$db  = Database::getInstance();
$pid = (int)$_SESSION['provider_id'];
$svc = new ProviderService();
$prov = $svc->get($pid);

if (!$prov) { session_destroy(); header('Location: /providers/login.php'); exit; }

$pName    = htmlspecialchars($prov['name']);
$pType    = $prov['type'];
$pStatus  = $prov['status'];
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', trim($pName)), 0, 2))));

$tab = Security::clean($_GET['tab'] ?? 'overview');
$validTabs = ['overview','appointments','patients','availability','telehealth','settings'];
if (!in_array($tab, $validTabs)) $tab = 'overview';

$tabMap = [
    'overview'     => ['icon'=>'dashboard',      'label'=>'Overview'],
    'appointments' => ['icon'=>'calendar_month', 'label'=>'Appointments'],
    'patients'     => ['icon'=>'group',          'label'=>'Patients'],
    'availability' => ['icon'=>'schedule',       'label'=>'Availability'],
    'telehealth'   => ['icon'=>'video_chat',     'label'=>'Telehealth'],
    'settings'     => ['icon'=>'settings',       'label'=>'Settings'],
];

// Data
$todayAppts = $db->fetchAll(
    'SELECT a.*,p.first_name pfn,p.last_name pln,p.phone pphone
     FROM appointments a JOIN patients p ON a.patient_id=p.id
     WHERE a.provider_id=:pid AND DATE(a.appointment_at)=CURDATE() ORDER BY a.appointment_at ASC',
    [':pid' => $pid]
);
$upcomingAppts = $db->fetchAll(
    'SELECT a.*,p.first_name pfn,p.last_name pln FROM appointments a JOIN patients p ON a.patient_id=p.id
     WHERE a.provider_id=:pid AND a.appointment_at>NOW() AND a.status IN("scheduled","confirmed") ORDER BY a.appointment_at ASC LIMIT 10',
    [':pid' => $pid]
);
$totalAppts    = (int)($db->fetchOne('SELECT COUNT(*) c FROM appointments WHERE provider_id=:pid',[':pid'=>$pid])['c']??0);
$completedAppts= (int)($db->fetchOne('SELECT COUNT(*) c FROM appointments WHERE provider_id=:pid AND status="completed"',[':pid'=>$pid])['c']??0);
$totalPatients = (int)($db->fetchOne('SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE provider_id=:pid',[':pid'=>$pid])['c']??0);

// Active telehealth sessions
$activeSessions = $db->fetchAll(
    'SELECT ts.*,p.first_name pfn,p.last_name pln FROM telehealth_sessions ts JOIN patients p ON ts.patient_id=p.id
     WHERE ts.provider_id=:pid AND ts.status IN("waiting","active") ORDER BY ts.created_at DESC LIMIT 5',
    [':pid' => $pid]
);

send_security_headers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $tabMap[$tab]['label'] ?> — <?= $pName ?> · Planeazzy Provider</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/app.css">
  <style>
    /* Provider portal colour accent overrides — teal primary */
    .s-item.active       { background: var(--teal); }
    .s-logo-name span    { color: var(--teal); }
    .s-logo-mark         { background: var(--teal); }
    .s-emergency         { background: var(--red); }
    .prog-fill           { background: linear-gradient(90deg, var(--teal), var(--blue)); }
    .btn-primary         { background: var(--teal); }
    .btn-primary:active  { background: #0c6377; }
    .topbar-title .material-symbols-outlined { color: var(--teal); }
    .stat-ic.teal        { background: var(--teal-l); color: var(--teal); }
    .avail-toggle {
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 18px; background: var(--white);
      border: 1px solid var(--border); border-radius: var(--r-lg);
      margin-bottom: 16px;
    }
    .toggle-sw { position: relative; width: 48px; height: 26px; cursor: pointer; }
    .toggle-sw input { opacity: 0; width: 0; height: 0; }
    .toggle-track {
      position: absolute; inset: 0; border-radius: 99px;
      background: var(--border); transition: background .25s;
    }
    .toggle-sw input:checked ~ .toggle-track { background: var(--green); }
    .toggle-thumb {
      position: absolute; top: 3px; left: 3px;
      width: 20px; height: 20px; border-radius: 50%;
      background: #fff; transition: left .25s;
      box-shadow: 0 1px 4px rgba(0,0,0,.2);
    }
    .toggle-sw input:checked ~ .toggle-track ~ .toggle-thumb { left: 25px; }
    .pending-banner {
      background: var(--yellow-l); border: 1px solid var(--yellow-b);
      border-radius: var(--r-lg); padding: 14px 18px; margin-bottom: 22px;
      display: flex; align-items: flex-start; gap: 12px; font-size: 13px; color: #78350f;
    }
    .pending-banner .material-symbols-outlined { color: var(--yellow); font-size: 20px; flex-shrink: 0; margin-top: 1px; }
  </style>
</head>
<body>
<div class="app-layout">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <a class="s-logo" href="/providers/dashboard.php" style="text-decoration:none">
    <div class="s-logo-mark"><span class="material-symbols-outlined">health_and_safety</span></div>
    <span class="s-logo-name">Plane<span>azzy</span></span>
  </a>

  <div class="s-user">
    <div class="s-user-av"><?= $initials ?></div>
    <div>
      <div class="s-user-name"><?= $pName ?></div>
      <div class="s-user-role"><?= ucfirst($pType) ?> Portal</div>
    </div>
  </div>

  <?php if ($pStatus === 'pending'): ?>
  <div style="margin:6px 10px;padding:10px 12px;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.25);border-radius:var(--r);font-size:11px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:6px">
    <span class="material-symbols-outlined" style="font-size:14px;color:#fbbf24">schedule</span>
    Account under review
  </div>
  <?php endif; ?>

  <div class="s-section">
    <span class="s-label">Provider Portal</span>
    <?php
    $nav = [
      ['key'=>'overview',     'icon'=>'dashboard',      'label'=>'Overview'],
      ['key'=>'appointments', 'icon'=>'calendar_month', 'label'=>'Appointments'],
      ['key'=>'patients',     'icon'=>'group',          'label'=>'Patients'],
      ['key'=>'availability', 'icon'=>'schedule',       'label'=>'Availability'],
      ['key'=>'telehealth',   'icon'=>'video_chat',     'label'=>'Telehealth'],
      ['key'=>'settings',     'icon'=>'settings',       'label'=>'Settings'],
    ];
    foreach ($nav as $n): ?>
    <a href="?tab=<?= $n['key'] ?>" class="s-item <?= $tab===$n['key']?'active':'' ?>">
      <span class="material-symbols-outlined"><?= $n['icon'] ?></span>
      <?= $n['label'] ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="s-section">
    <span class="s-label">Account</span>
    <a href="/api/provider/logout.php" class="s-item">
      <span class="material-symbols-outlined">logout</span> Sign Out
    </a>
  </div>

  <!-- Availability quick toggle -->
  <div style="margin:8px 10px 16px;padding:12px 14px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:var(--r-lg)">
    <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Availability</div>
    <label class="toggle-sw" id="availToggle" title="Toggle availability">
      <input type="checkbox" id="availCheck" <?= $prov['is_available']?'checked':'' ?> onchange="toggleAvailability(this.checked)">
      <span class="toggle-track"></span>
      <span class="toggle-thumb"></span>
    </label>
    <div style="font-size:12px;font-weight:600;margin-top:6px;color:<?= $prov['is_available']?'#34d399':'rgba(255,255,255,.4)' ?>" id="availLabel">
      <?= $prov['is_available'] ? 'Available' : 'Unavailable' ?>
    </div>
  </div>
</aside>

<div class="mob-overlay" id="mobOverlay"></div>

<div class="main-wrap">
<!-- TOPBAR -->
<header class="topbar">
  <div class="topbar-left">
    <div>
      <div class="topbar-title">
        <span class="material-symbols-outlined"><?= $tabMap[$tab]['icon'] ?></span>
        <?= $tabMap[$tab]['label'] ?>
      </div>
      <div class="topbar-crumb">Provider Portal › <?= $pName ?> › <?= $tabMap[$tab]['label'] ?></div>
    </div>
  </div>
  <div class="topbar-right">
    <div class="t-avatar" style="background:linear-gradient(135deg,var(--teal),var(--blue))"><?= $initials ?></div>
    <span style="font-size:13px;font-weight:700;color:var(--navy);padding:0 4px"><?= $pName ?></span>
    <?php if ($prov['is_available']): ?>
    <span class="pill pill-green"><span style="width:6px;height:6px;border-radius:50%;background:var(--green);display:inline-block;margin-right:3px"></span>Available</span>
    <?php else: ?>
    <span class="pill pill-gray">Unavailable</span>
    <?php endif; ?>
  </div>
</header>

<!-- CONTENT -->
<main class="page-content fade-up">
<?php

if ($pStatus === 'pending' && $tab !== 'settings'):
?>
<div class="pending-banner">
  <span class="material-symbols-outlined">pending</span>
  <div>
    <strong>Account Under Review</strong> — Your account is verified and pending admin activation. You can configure your settings now. We'll email you within 24 hours once activated.
  </div>
</div>
<?php endif; ?>

<?php
function provStatusPill(string $s): string {
    $m=['scheduled'=>'blue','confirmed'=>'green','completed'=>'gray','cancelled'=>'red','in_progress'=>'teal'];
    return '<span class="pill pill-'.($m[$s]??'gray').'">'.ucfirst($s).'</span>';
}

if ($tab === 'overview'):
  $greet = (int)date('H') < 12 ? 'Good Morning' : ((int)date('H') < 17 ? 'Good Afternoon' : 'Good Evening');
?>
<div class="pg-head-row">
  <div><h1><?= $greet ?>, <?= htmlspecialchars(explode(' ',$pName)[0]) ?>!</h1><p><?= ucfirst($pType) ?> Dashboard · <?= date('l, F j, Y') ?></p></div>
  <div style="display:flex;gap:8px">
    <a href="/patients/telehealth.php" class="btn btn-teal btn-sm"><span class="material-symbols-outlined">video_chat</span> Start Telehealth</a>
    <a href="?tab=appointments" class="btn btn-ghost btn-sm"><span class="material-symbols-outlined">calendar_month</span> View Schedule</a>
  </div>
</div>

<div class="stats-row mb3">
  <div class="stat-card"><div class="stat-ic teal"><span class="material-symbols-outlined">calendar_today</span></div><div><div class="stat-val"><?= count($todayAppts) ?></div><div class="stat-lbl">Today's Appointments</div><div class="stat-sub">Scheduled for today</div></div></div>
  <div class="stat-card"><div class="stat-ic blue"><span class="material-symbols-outlined">event_upcoming</span></div><div><div class="stat-val"><?= count($upcomingAppts) ?></div><div class="stat-lbl">Upcoming</div><div class="stat-sub">Next appointments</div></div></div>
  <div class="stat-card"><div class="stat-ic green"><span class="material-symbols-outlined">group</span></div><div><div class="stat-val"><?= $totalPatients ?></div><div class="stat-lbl">Total Patients</div><div class="stat-sub">All time</div></div></div>
  <div class="stat-card"><div class="stat-ic <?= $prov['is_available']?'green':'red' ?>"><span class="material-symbols-outlined"><?= $prov['is_available']?'check_circle':'cancel' ?></span></div><div><div class="stat-val" style="font-size:16px;margin-top:4px"><?= $prov['is_available']?'Available':'Unavailable' ?></div><div class="stat-lbl">Status</div><div class="stat-sub">Current availability</div></div></div>
</div>

<div class="grid-2 mb3">
  <!-- Today's schedule -->
  <div class="card">
    <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">today</span>Today's Schedule</span></div>
    <div class="card-body" style="padding:0">
      <?php if (empty($todayAppts)): ?>
      <div class="empty-state"><span class="material-symbols-outlined">event_available</span><h3>No appointments today</h3><p>Your schedule is clear for today.</p></div>
      <?php else: foreach($todayAppts as $a): $dt=new DateTime($a['appointment_at']); ?>
      <div style="display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid var(--border)">
        <div style="width:46px;height:50px;border-radius:var(--r);background:var(--teal-l);color:var(--teal);display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;font-family:var(--ff-head)">
          <span style="font-size:16px;font-weight:900;line-height:1"><?= $dt->format('g') ?></span>
          <span style="font-size:9px;font-weight:700;text-transform:uppercase"><?= $dt->format('A') ?></span>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:800"><?= htmlspecialchars($a['pfn'].' '.$a['pln']) ?></div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px"><?= htmlspecialchars($a['title']??ucfirst($a['service_type']).' Appointment') ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <?= provStatusPill($a['status']) ?>
          <?php if($a['location_type']==='telehealth'): ?>
          <a href="/patients/telehealth.php?appt=<?=$a['id']?>" class="btn btn-teal btn-sm"><span class="material-symbols-outlined">video_chat</span></a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Active telehealth + profile summary -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <?php if (!empty($activeSessions)): ?>
    <div class="card">
      <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">video_chat</span>Active Telehealth</span></div>
      <div class="card-body" style="padding:0">
        <?php foreach($activeSessions as $ts): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid var(--border)">
          <div class="stat-ic teal" style="width:38px;height:38px;border-radius:var(--r)"><span class="material-symbols-outlined" style="font-size:18px">video_chat</span></div>
          <div style="flex:1"><div style="font-size:13px;font-weight:800"><?= htmlspecialchars($ts['pfn'].' '.$ts['pln']) ?></div><div style="font-size:11px;color:var(--muted)">Room: <?= htmlspecialchars($ts['room_token']) ?></div></div>
          <a href="/patients/telehealth.php?room=<?= htmlspecialchars($ts['room_token']) ?>" class="btn btn-teal btn-sm">Join</a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Profile summary card -->
    <div class="card">
      <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">account_circle</span>Profile Summary</span></div>
      <div class="card-body">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
          <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--teal),var(--blue));display:flex;align-items:center;justify-content:center;font-family:var(--ff-head);font-size:18px;font-weight:900;color:#fff;flex-shrink:0"><?= $initials ?></div>
          <div>
            <div style="font-size:15px;font-weight:900"><?= $pName ?></div>
            <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($prov['specialty']??ucfirst($pType)) ?></div>
            <span class="pill <?= $pStatus==='active'?'pill-green':'pill-yellow' ?>" style="margin-top:4px"><?= ucfirst($pStatus) ?></span>
          </div>
        </div>
        <?php if($prov['address']): ?>
        <div style="font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px"><span class="material-symbols-outlined" style="font-size:15px;color:var(--teal)">location_on</span><?= htmlspecialchars($prov['address']) ?></div>
        <?php endif; ?>
        <?php if($prov['phone']): ?>
        <div style="font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px;margin-top:6px"><span class="material-symbols-outlined" style="font-size:15px;color:var(--teal)">call</span><?= htmlspecialchars($prov['phone']) ?></div>
        <?php endif; ?>
        <div style="display:flex;align-items:center;gap:6px;margin-top:8px">
          <span class="material-symbols-outlined" style="font-size:15px;color:var(--yellow)">star</span>
          <span style="font-size:13px;font-weight:700"><?= number_format($prov['rating'],1) ?></span>
          <span style="font-size:12px;color:var(--muted)">(<?= number_format($prov['review_count']) ?> reviews)</span>
        </div>
        <a href="?tab=settings" class="btn btn-outline btn-sm mt2"><span class="material-symbols-outlined">edit</span> Edit Profile</a>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'appointments'): ?>
<div class="pg-head"><h1>Appointments</h1><p>Your full appointment schedule.</p></div>
<?php $apptFilter = Security::clean($_GET['af'] ?? 'upcoming'); ?>
<div class="tab-bar mb3">
  <a href="?tab=appointments&af=today"    class="tab-item <?=$apptFilter==='today'?'active':''?>">Today (<?=count($todayAppts)?>)</a>
  <a href="?tab=appointments&af=upcoming" class="tab-item <?=$apptFilter==='upcoming'?'active':''?>">Upcoming</a>
  <a href="?tab=appointments&af=all"      class="tab-item <?=$apptFilter==='all'?'active':''?>">All</a>
</div>
<div class="card">
  <div class="card-body" style="padding:0">
    <?php
    $showAppts = match($apptFilter) {
      'today'    => $todayAppts,
      'upcoming' => $upcomingAppts,
      default    => $db->fetchAll('SELECT a.*,p.first_name pfn,p.last_name pln,p.phone pphone FROM appointments a JOIN patients p ON a.patient_id=p.id WHERE a.provider_id=:pid ORDER BY a.appointment_at DESC LIMIT 30',[':pid'=>$pid]),
    };
    if (empty($showAppts)): ?>
    <div class="empty-state"><span class="material-symbols-outlined">calendar_today</span><h3>No appointments</h3></div>
    <?php else: foreach($showAppts as $a): $dt=new DateTime($a['appointment_at']); ?>
    <div style="display:flex;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid var(--border)">
      <div style="width:46px;height:52px;border-radius:var(--r);background:var(--teal-l);color:var(--teal);display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;font-family:var(--ff-head)">
        <span style="font-size:18px;font-weight:900;line-height:1"><?=$dt->format('d')?></span>
        <span style="font-size:9px;font-weight:700;text-transform:uppercase"><?=$dt->format('M')?></span>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:14px;font-weight:800;margin-bottom:3px"><?= htmlspecialchars($a['pfn'].' '.$a['pln']) ?></div>
        <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:7px;flex-wrap:wrap">
          <span style="display:flex;align-items:center;gap:3px"><span class="material-symbols-outlined" style="font-size:12px">schedule</span><?=$dt->format('g:i A')?></span>
          <?php if($a['title']??null): ?><span>·</span><span><?=htmlspecialchars($a['title'])?></span><?php endif;?>
          <?php if($a['location_type']==='telehealth'): ?><span class="pill pill-teal" style="font-size:10px">Video</span><?php endif;?>
          <?php if(!empty($a['pphone'])): ?><span>·</span><span class="material-symbols-outlined" style="font-size:12px">call</span><span><?=htmlspecialchars($a['pphone']??'')?></span><?php endif;?>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <?= provStatusPill($a['status']) ?>
        <?php if(in_array($a['status'],['scheduled','confirmed'])): ?>
        <button class="btn btn-ghost btn-sm" onclick="updateApptStatus(<?=$a['id']?>,'completed')">Done</button>
        <button class="btn btn-ghost btn-sm" onclick="updateApptStatus(<?=$a['id']?>,'cancelled')">Cancel</button>
        <?php endif; ?>
        <?php if($a['location_type']==='telehealth' && in_array($a['status'],['scheduled','confirmed'])): ?>
        <a href="/patients/telehealth.php?appt=<?=$a['id']?>" class="btn btn-teal btn-sm"><span class="material-symbols-outlined">video_chat</span></a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php elseif ($tab === 'patients'): ?>
<div class="pg-head"><h1>Patients</h1><p>Patients who have booked with you.</p></div>
<?php
$patients = $db->fetchAll(
    'SELECT p.id,p.first_name,p.last_name,p.email,p.phone,p.created_at,COUNT(a.id) as appt_count,MAX(a.appointment_at) as last_visit
     FROM patients p JOIN appointments a ON p.id=a.patient_id
     WHERE a.provider_id=:pid GROUP BY p.id ORDER BY last_visit DESC LIMIT 30',
    [':pid'=>$pid]
);
?>
<div class="card">
  <div class="card-body" style="padding:0">
    <?php if(empty($patients)): ?>
    <div class="empty-state"><span class="material-symbols-outlined">group</span><h3>No patients yet</h3><p>Patients will appear here after they book with you.</p></div>
    <?php else: foreach($patients as $pat): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid var(--border)">
      <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--teal));display:flex;align-items:center;justify-content:center;font-family:var(--ff-head);font-size:13px;font-weight:800;color:#fff;flex-shrink:0">
        <?= strtoupper(substr($pat['first_name'],0,1).substr($pat['last_name'],0,1)) ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:800"><?= htmlspecialchars($pat['first_name'].' '.$pat['last_name']) ?></div>
        <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($pat['email']) ?><?= $pat['phone']?' · '.htmlspecialchars($pat['phone']):'' ?></div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-size:12px;font-weight:700;color:var(--navy)"><?= $pat['appt_count'] ?> visit<?= $pat['appt_count']!=1?'s':'' ?></div>
        <div style="font-size:11px;color:var(--muted)"><?= $pat['last_visit'] ? date('M j, Y',strtotime($pat['last_visit'])) : '—' ?></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php elseif ($tab === 'availability'): ?>
<div class="pg-head"><h1>Availability</h1><p>Control when patients can book with you.</p></div>
<div class="avail-toggle">
  <div>
    <div style="font-size:14px;font-weight:800">Currently <?= $prov['is_available']?'Available':'Unavailable' ?></div>
    <div style="font-size:12px;color:var(--muted)">Toggle to instantly update your availability for patients</div>
  </div>
  <label class="toggle-sw">
    <input type="checkbox" <?= $prov['is_available']?'checked':'' ?> onchange="toggleAvailability(this.checked)">
    <span class="toggle-track"></span>
    <span class="toggle-thumb"></span>
  </label>
</div>
<div class="card">
  <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">schedule</span>Opening Hours</span>
    <button class="btn btn-teal btn-sm"><span class="material-symbols-outlined">edit</span> Edit Hours</button>
  </div>
  <div class="card-body">
    <?php
    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $hours = json_decode($prov['opening_hours']??'{}', true) ?: [];
    foreach ($days as $day): $key = strtolower(substr($day,0,3)); $h = $hours[$key] ?? null; ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)">
      <span style="font-size:13px;font-weight:700;width:100px"><?= $day ?></span>
      <?php if ($h): ?>
      <span class="pill pill-green"><?= htmlspecialchars($h) ?></span>
      <?php else: ?>
      <span class="pill pill-gray">Closed</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php elseif ($tab === 'telehealth'): ?>
<div class="pg-head-row">
  <div><h1>Telehealth Sessions</h1><p>Active and past video consultations.</p></div>
  <a href="/patients/telehealth.php" class="btn btn-teal btn-sm"><span class="material-symbols-outlined">add_call</span> New Session</a>
</div>
<div class="card">
  <div class="card-body" style="padding:0">
    <?php
    $allSessions = $db->fetchAll(
        'SELECT ts.*,p.first_name pfn,p.last_name pln FROM telehealth_sessions ts JOIN patients p ON ts.patient_id=p.id WHERE ts.provider_id=:pid ORDER BY ts.created_at DESC LIMIT 20',
        [':pid'=>$pid]
    );
    if (empty($allSessions)): ?>
    <div class="empty-state"><span class="material-symbols-outlined">video_chat</span><h3>No telehealth sessions</h3><p>Video consultation sessions will appear here.</p></div>
    <?php else: foreach($allSessions as $ts):
      $scl = match($ts['status']) { 'active'=>'teal','waiting'=>'yellow','ended'=>'gray',default=>'gray' };
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid var(--border)">
      <div class="stat-ic teal" style="width:38px;height:38px;border-radius:var(--r)"><span class="material-symbols-outlined" style="font-size:18px">video_chat</span></div>
      <div style="flex:1">
        <div style="font-size:13px;font-weight:800"><?= htmlspecialchars($ts['pfn'].' '.$ts['pln']) ?></div>
        <div style="font-size:11px;color:var(--muted)"><?= date('M j, Y g:i A',strtotime($ts['created_at'])) ?><?= $ts['duration_sec']?' · '.round($ts['duration_sec']/60).' min':'' ?></div>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <span class="pill pill-<?=$scl?>"><?=ucfirst($ts['status'])?></span>
        <?php if($ts['status']==='waiting'||$ts['status']==='active'): ?>
        <a href="/patients/telehealth.php?room=<?=htmlspecialchars($ts['room_token'])?>" class="btn btn-teal btn-sm">Join</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php elseif ($tab === 'settings'): ?>
<div class="pg-head"><h1>Provider Settings</h1><p>Update your practice profile and details.</p></div>
<div class="grid-2">
  <div class="card">
    <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">business</span>Practice Details</span></div>
    <div class="card-body">
      <div class="form-group"><label class="form-label">Provider Type</label><input type="text" class="form-input" value="<?= ucfirst($pType) ?>" readonly></div>
      <div class="form-group"><label class="form-label">Name / Organisation</label><input type="text" class="form-input" value="<?= $pName ?>" readonly></div>
      <div class="form-group"><label class="form-label">Email</label><input type="email" class="form-input" value="<?= htmlspecialchars($prov['email']) ?>" readonly></div>
      <div class="form-group"><label class="form-label">Phone</label><input type="text" class="form-input" value="<?= htmlspecialchars($prov['phone']??'') ?>"></div>
      <div class="form-group"><label class="form-label">Specialty</label><input type="text" class="form-input" value="<?= htmlspecialchars($prov['specialty']??'') ?>"></div>
      <div class="form-group"><label class="form-label">Address</label><input type="text" class="form-input" value="<?= htmlspecialchars($prov['address']??'') ?>"></div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" rows="3"><?= htmlspecialchars($prov['description']??'') ?></textarea></div>
      <button class="btn btn-primary btn-sm"><span class="material-symbols-outlined">save</span> Save Changes</button>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">verified</span>Verification Status</span></div>
    <div class="card-body">
      <div style="text-align:center;padding:20px 0">
        <span class="material-symbols-outlined" style="font-size:52px;color:<?= $prov['is_verified']?'var(--green)':'var(--yellow)' ?>"><?= $prov['is_verified']?'verified':'pending' ?></span>
        <div style="font-size:16px;font-weight:900;margin:10px 0 5px"><?= $prov['is_verified']?'Email Verified':'Email Not Verified' ?></div>
        <div style="font-size:13px;color:var(--muted)"><?= $prov['is_verified']?'Your email address is verified.':'Please verify your email address.' ?></div>
        <div style="margin-top:14px"><span class="pill <?= $pStatus==='active'?'pill-green':'pill-yellow' ?>"><?= ucfirst($pStatus) ?></span></div>
        <?php if($pStatus==='pending'): ?>
        <div style="font-size:12px;color:var(--muted);margin-top:10px">Account under admin review. Usually activated within 24 hours.</div>
        <?php endif; ?>
      </div>
      <div style="border-top:1px solid var(--border);padding-top:16px;margin-top:8px">
        <div class="form-group"><label class="form-label">License Number</label><input type="text" class="form-input" value="<?= htmlspecialchars($prov['license_number']??'') ?>" readonly></div>
        <?php if($prov['verified_at']): ?>
        <p style="font-size:12px;color:var(--muted)">Verified on <?= date('M j, Y', strtotime($prov['verified_at'])) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

</main>
</div><!-- /.main-wrap -->
</div><!-- /.app-layout -->

<button class="mob-tog" id="mobToggle" onclick="toggleSidebar()">
  <span class="material-symbols-outlined">menu</span>
</button>

<input type="hidden" id="csrfToken" value="<?= htmlspecialchars(Security::csrfToken()) ?>">
<script src="/assets/js/app.js"></script>
<script>
async function toggleAvailability(val) {
  const lbl = document.getElementById('availLabel');
  const r = await post('/api/provider/set-availability.php', {
    available: val,
    csrf_token: document.getElementById('csrfToken').value
  }, null, null);
  if (lbl) {
    lbl.textContent   = val ? 'Available' : 'Unavailable';
    lbl.style.color   = val ? '#34d399' : 'rgba(255,255,255,.4)';
  }
}

async function updateApptStatus(id, status) {
  if (!confirm(`Mark appointment as "${status}"?`)) return;
  const r = await post('/api/provider/update-appointment.php', {
    appointment_id: id, status,
    csrf_token: document.getElementById('csrfToken').value
  }, null, null);
  if (r?.success) location.reload();
  else alert(r?.message || 'Failed to update.');
}
</script>
</body>
</html>
