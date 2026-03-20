<?php
/**
 * Planeazzy — includes/header.php  v4
 * Set before including:
 *   $pageTitle  string  — browser tab title
 *   $activeTab  string  — dashboard sidebar active key
 *   $noSidebar  bool    — public pages (no sidebar layout)
 *   $bodyClass  string  — extra class on <body>
 *   $portalType string  — 'patient'|'doctor'|'ambulance'|'clinic' (for sidebar colour)
 */
if (!defined('APP_NAME')) require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
send_security_headers();

$pageTitle  = isset($pageTitle)  ? htmlspecialchars($pageTitle) . ' — ' . APP_NAME : APP_NAME;
$bodyClass  = $bodyClass  ?? '';
$noSidebar  = $noSidebar  ?? false;
$activeTab  = $activeTab  ?? 'overview';
$portalType = $portalType ?? 'patient';
$csrf       = Security::csrfToken();

$isPatient  = !empty($_SESSION['patient_id'])  && !empty($_SESSION['authenticated']);
$isProvider = !empty($_SESSION['provider_id']) && !empty($_SESSION['is_provider']);

$patName   = htmlspecialchars($_SESSION['patient_name']  ?? 'Patient');
$provName  = htmlspecialchars($_SESSION['provider_name'] ?? 'Provider');
$initials  = $isPatient
    ? strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', trim($patName)), 0, 2))))
    : strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', trim($provName)), 0, 2))));

// Portal colour accent
$portalAccents = [
    'patient'   => ['--blue', '#1978e5'],
    'doctor'    => ['--teal', '#0e7490'],
    'ambulance' => ['--red',  '#dc2626'],
    'clinic'    => ['--green','#059669'],
];
$accent = $portalAccents[$portalType] ?? $portalAccents['patient'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Planeazzy — Your direct path to better healthcare in Kenya.">
  <title><?= $pageTitle ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/app.css">
  <?php if ($portalType !== 'patient'): ?>
  <style>
    /* Portal-specific accent colour override */
    :root { --portal: <?= $accent[1] ?>; }
    .s-item.active,.s-logo-mark,.s-emergency,
    .btn-primary,.step-badge,.card-icon,
    .prog-fill,.prog-badge { background: <?= $accent[1] ?> !important; }
    .loc-pill,.topbar-title .material-symbols-outlined,
    .card-title .material-symbols-outlined { color: <?= $accent[1] ?> !important; }
    .step-badge,.prog-badge { color: <?= $accent[1] ?> !important; background: <?= $accent[1] ?>18 !important; border-color: <?= $accent[1] ?>30 !important; }
    a { color: <?= $accent[1] ?>; }
    .form-input:focus,.form-select:focus { border-color: <?= $accent[1] ?>; box-shadow: 0 0 0 3px <?= $accent[1] ?>18; }
  </style>
  <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>" style="display:flex;flex-direction:column;min-height:100vh">

<?php if ($noSidebar): ?>
<!-- ── PUBLIC HEADER ────────────────────────────────────────── -->
<header class="portal-header">
  <a class="portal-logo" href="/">
    <div class="portal-logo-mark"><span class="material-symbols-outlined">health_and_safety</span></div>
    <span class="portal-logo-name">Plane<span>azzy</span></span>
  </a>

  <!-- 4 Portal navigation links -->
  <nav style="display:flex;align-items:center;gap:2px" class="portal-nav">
    <a href="/patients/login.php" style="display:flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;font-size:13px;font-weight:600;color:var(--muted);text-decoration:none;transition:all .18s<?= ($portalType==='patient')?' background:var(--blue-l);color:var(--blue)':'' ?>">
      <span class="material-symbols-outlined" style="font-size:17px">person</span>
      <span class="nav-text">Patients</span>
    </a>
    <a href="/providers/doctor/login.php" style="display:flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;font-size:13px;font-weight:600;color:var(--muted);text-decoration:none;transition:all .18s<?= ($portalType==='doctor')?' background:var(--teal-l);color:var(--teal)':'' ?>">
      <span class="material-symbols-outlined" style="font-size:17px">stethoscope</span>
      <span class="nav-text">Doctors</span>
    </a>
    <a href="/providers/clinic/login.php" style="display:flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;font-size:13px;font-weight:600;color:var(--muted);text-decoration:none;transition:all .18s<?= ($portalType==='clinic')?' background:var(--green-l);color:var(--green)':'' ?>">
      <span class="material-symbols-outlined" style="font-size:17px">local_pharmacy</span>
      <span class="nav-text">Clinics</span>
    </a>
    <a href="/providers/ambulance/login.php" style="display:flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;font-size:13px;font-weight:600;color:var(--muted);text-decoration:none;transition:all .18s<?= ($portalType==='ambulance')?' background:var(--red-l);color:var(--red)':'' ?>">
      <span class="material-symbols-outlined" style="font-size:17px">ambulance</span>
      <span class="nav-text">Ambulance</span>
    </a>
  </nav>

  <div style="display:flex;align-items:center;gap:8px">
    <?php if (!$isPatient && !$isProvider): ?>
    <a href="/patients/register.php" class="btn btn-primary btn-sm">
      <span class="material-symbols-outlined">person_add</span> Get Started
    </a>
    <?php elseif ($isPatient): ?>
    <a href="/patients/dashboard.php" class="btn btn-primary btn-sm">
      <span class="material-symbols-outlined">dashboard</span> Dashboard
    </a>
    <?php else: ?>
    <a href="/providers/dashboard.php" class="btn btn-primary btn-sm">
      <span class="material-symbols-outlined">dashboard</span> Dashboard
    </a>
    <?php endif; ?>
    <button class="t-btn" id="langToggle" title="Language">
      <span class="material-symbols-outlined">translate</span>
    </button>
  </div>
</header>

<?php else: ?>
<!-- ── DASHBOARD LAYOUT ──────────────────────────────────────── -->
<div class="app-layout">
<aside class="sidebar" id="sidebar">

  <a class="s-logo" href="/" style="text-decoration:none">
    <div class="s-logo-mark"><span class="material-symbols-outlined">health_and_safety</span></div>
    <span class="s-logo-name">Plane<span>azzy</span></span>
  </a>

  <?php if ($isPatient || $isProvider): ?>
  <div class="s-user">
    <div class="s-user-av"><?= $initials ?></div>
    <div style="min-width:0">
      <div class="s-user-name"><?= $isPatient ? $patName : $provName ?></div>
      <div class="s-user-role"><?= ucfirst($portalType) ?> Portal</div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($isPatient): ?>
  <!-- Patient sidebar nav -->
  <div class="s-section">
    <span class="s-label">Main Menu</span>
    <?php foreach ([
      ['overview',      'dashboard',       'Overview'],
      ['appointments',  'calendar_month',  'Appointments'],
      ['nearby',        'near_me',         'Nearby Services'],
      ['records',       'folder_health',   'Health Records'],
      ['vitals',        'monitor_heart',   'Vitals'],
      ['prescriptions', 'medication',      'Prescriptions'],
    ] as [$k,$ic,$lb]): ?>
    <a href="/patients/dashboard.php?tab=<?=$k?>" class="s-item <?=$activeTab===$k?'active':''?>">
      <span class="material-symbols-outlined"><?=$ic?></span> <?=$lb?>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="s-section">
    <span class="s-label">Services</span>
    <a href="/patients/dashboard.php?tab=nearby&type=doctor"    class="s-item"><span class="material-symbols-outlined">stethoscope</span> Find a Doctor</a>
    <a href="/patients/dashboard.php?tab=nearby&type=clinic"    class="s-item"><span class="material-symbols-outlined">local_pharmacy</span> Clinics</a>
    <a href="/patients/dashboard.php?tab=nearby&type=hospital"  class="s-item"><span class="material-symbols-outlined">domain</span> Hospitals</a>
    <a href="/patients/dashboard.php?tab=nearby&type=ambulance" class="s-item"><span class="material-symbols-outlined">ambulance</span> Ambulance</a>
    <a href="/patients/telehealth.php"                          class="s-item <?=$activeTab==='telehealth'?'active':''?>"><span class="material-symbols-outlined">video_chat</span> Telehealth</a>
    <a href="/patients/dashboard.php?tab=nearby&type=pharmacy"  class="s-item"><span class="material-symbols-outlined">pill</span> Pharmacy</a>
  </div>
  <div class="s-section">
    <span class="s-label">Account</span>
    <a href="/patients/dashboard.php?tab=notifications" class="s-item <?=$activeTab==='notifications'?'active':''?>"><span class="material-symbols-outlined">notifications</span> Notifications</a>
    <a href="/patients/dashboard.php?tab=settings"      class="s-item <?=$activeTab==='settings'?'active':''?>"><span class="material-symbols-outlined">manage_accounts</span> Settings</a>
    <a href="/api/auth/logout.php" class="s-item"><span class="material-symbols-outlined">logout</span> Sign Out</a>
  </div>
  <a href="/patients/dashboard.php?tab=emergency" class="s-emergency"><span class="material-symbols-outlined">emergency</span> Emergency</a>

  <?php elseif ($isProvider): ?>
  <!-- Provider sidebar nav -->
  <div class="s-section">
    <span class="s-label">Provider Portal</span>
    <?php foreach ([
      ['overview',     'dashboard',      'Overview'],
      ['appointments', 'calendar_month', 'Appointments'],
      ['patients',     'group',          'Patients'],
      ['availability', 'schedule',       'Availability'],
      ['telehealth',   'video_chat',     'Telehealth'],
      ['settings',     'settings',       'Settings'],
    ] as [$k,$ic,$lb]): ?>
    <a href="/providers/dashboard.php?tab=<?=$k?>" class="s-item <?=$activeTab===$k?'active':''?>">
      <span class="material-symbols-outlined"><?=$ic?></span> <?=$lb?>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="s-section">
    <span class="s-label">Account</span>
    <a href="/api/provider/logout.php" class="s-item"><span class="material-symbols-outlined">logout</span> Sign Out</a>
  </div>
  <?php endif; ?>

</aside>

<div class="mob-overlay" id="mobOverlay"></div>
<div class="main-wrap">

<!-- TOPBAR -->
<header class="topbar">
  <div class="topbar-left">
    <div>
      <div class="topbar-title">
        <span class="material-symbols-outlined"><?= $tabIcon ?? 'dashboard' ?></span>
        <?= $tabLabel ?? 'Dashboard' ?>
      </div>
      <div class="topbar-crumb">Planeazzy › <?= ucfirst($portalType) ?> › <?= $tabLabel ?? 'Dashboard' ?></div>
    </div>
  </div>
  <div class="topbar-right">
    <!-- Portal switcher pills in topbar -->
    <div style="display:flex;gap:4px;margin-right:8px" class="portal-switcher">
      <a href="/patients/login.php"          title="Patient Portal"   style="width:32px;height:32px;border-radius:8px;background:<?= $portalType==='patient' ? 'var(--blue-l)' : 'var(--bg)' ?>;border:1px solid <?= $portalType==='patient' ? 'var(--blue-b)' : 'var(--border)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $portalType==='patient' ? 'var(--blue)' : 'var(--muted)' ?>;">
        <span class="material-symbols-outlined" style="font-size:16px">person</span>
      </a>
      <a href="/providers/doctor/login.php"    title="Doctor Portal"    style="width:32px;height:32px;border-radius:8px;background:<?= $portalType==='doctor' ? 'var(--teal-l)' : 'var(--bg)' ?>;border:1px solid <?= $portalType==='doctor' ? 'var(--teal-b)' : 'var(--border)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $portalType==='doctor' ? 'var(--teal)' : 'var(--muted)' ?>;">
        <span class="material-symbols-outlined" style="font-size:16px">stethoscope</span>
      </a>
      <a href="/providers/clinic/login.php"    title="Clinic Portal"    style="width:32px;height:32px;border-radius:8px;background:<?= $portalType==='clinic' ? 'var(--green-l)' : 'var(--bg)' ?>;border:1px solid <?= $portalType==='clinic' ? 'var(--green-b)' : 'var(--border)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $portalType==='clinic' ? 'var(--green)' : 'var(--muted)' ?>;">
        <span class="material-symbols-outlined" style="font-size:16px">local_pharmacy</span>
      </a>
      <a href="/providers/ambulance/login.php" title="Ambulance Portal" style="width:32px;height:32px;border-radius:8px;background:<?= $portalType==='ambulance' ? 'var(--red-l)' : 'var(--bg)' ?>;border:1px solid <?= $portalType==='ambulance' ? 'var(--red-b)' : 'var(--border)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $portalType==='ambulance' ? 'var(--red)' : 'var(--muted)' ?>;">
        <span class="material-symbols-outlined" style="font-size:16px">ambulance</span>
      </a>
    </div>
    <div class="loc-pill" onclick="openModal('locationModal')">
      <span class="material-symbols-outlined">near_me</span>
      <span class="loc-dot"></span>
      <span id="locLabel">Nairobi, Kenya</span>
    </div>
    <?php if ($isPatient): ?>
    <a href="/patients/dashboard.php?tab=notifications" class="t-btn">
      <span class="material-symbols-outlined">notifications</span>
      <?php
      try {
        require_once dirname(__DIR__). '/services/Database.php';
        $db = Database::getInstance();
        $uc = $db->fetchOne('SELECT COUNT(*) c FROM notifications WHERE patient_id=:pid AND is_read=0',[':pid'=>$_SESSION['patient_id']??0]);
        if (($uc['c']??0)>0) echo '<span class="notif-dot"></span>';
      } catch(Exception $e) {}
      ?>
    </a>
    <?php endif; ?>
    <a href="<?= $isPatient ? '/patients/dashboard.php?tab=settings' : '/providers/dashboard.php?tab=settings' ?>" class="t-avatar"><?= $initials ?></a>
    <button class="t-btn" id="langToggle" title="Language"><span class="material-symbols-outlined">translate</span></button>
  </div>
</header>

<?php endif; // noSidebar ?>
