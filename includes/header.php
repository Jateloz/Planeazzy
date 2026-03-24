<?php
/**
 * Planeazzy v5 — includes/header.php
 * Unified header used by ALL pages — same design as homepage
 * Set before including:
 *   $pageTitle   string  — page title
 *   $noSidebar   bool    — true = public page (homepage/login/register)
 *   $activeTab   string  — dashboard sidebar active key
 *   $portalType  string  — patient|doctor|clinic|ambulance (for sidebar accent)
 *   $tabLabel    string  — topbar heading text
 */
if (!defined('APP_NAME')) require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
if (function_exists('send_security_headers')) send_security_headers();

$pageTitle  = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' . APP_NAME : APP_NAME;
$noSidebar  = $noSidebar  ?? true;
$activeTab  = $activeTab  ?? 'overview';
$portalType = $portalType ?? 'patient';
$tabLabel   = $tabLabel   ?? 'Dashboard';
$csrf       = Security::csrfToken();

$isPatient  = !empty($_SESSION['patient_id'])  && !empty($_SESSION['authenticated']);
$isProvider = !empty($_SESSION['provider_id']) && !empty($_SESSION['is_provider']);
$userName   = $isPatient
    ? htmlspecialchars($_SESSION['patient_name'] ?? 'Patient')
    : htmlspecialchars($_SESSION['provider_name'] ?? 'Provider');
$provType   = $_SESSION['provider_type'] ?? 'doctor';
$initials   = strtoupper(implode('', array_map(
    fn($w) => $w[0],
    array_slice(explode(' ', trim($userName)), 0, 2)
)));
$unreadCount = 0;
if ($isPatient) {
    try {
        require_once dirname(__DIR__). '/services/Database.php';
        $db = Database::getInstance();
        $r  = $db->fetchOne('SELECT COUNT(*) c FROM notifications WHERE patient_id=:p AND is_read=0', [':p' => $_SESSION['patient_id']]);
        $unreadCount = (int)($r['c'] ?? 0);
    } catch (Exception $e) {}
}
// Determine current page for active nav highlighting
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Planeazzy — Your direct path to better healthcare in Kenya. Book doctors, hospitals, clinics and emergency services online.">
  <meta name="theme-color" content="#1978e5">
  <!-- Favicon & App icons -->
  <link rel="icon" type="image/png" href="images/plan main logo .png">
  <link rel="apple-touch-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%231978e5'/><text y='72' font-size='65' text-anchor='middle' x='50' fill='white'>❤</text></svg>">
  <title><?= $pageTitle ?></title>
  <!-- Font Awesome 6 Free -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <!-- App CSS -->
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body style="display:flex;flex-direction:column;min-height:100vh">

<!-- ══════════════════════════════════════════════════════════
     UNIFIED PUBLIC HEADER — same on every page
══════════════════════════════════════════════════════════ -->
<header class="pub-header">
  <div class="pub-header-inner">

    <!-- Logo — icon + name -->
<a class="pub-logo" href="/" style="text-decoration:none">
  <div class="pub-logo-icon">
    <!-- Replace the SVG with this img tag -->
    <img src="images\plan platform logo.png" alt="Planeazzy Logo" width="22" height="22" style="display: block;">
  </div>
  <span class="pub-logo-name">Planeazzy</span>
</a>

    <!-- Nav links — same on all public pages -->
    <nav class="pub-nav" id="pubNav">
      <a href="/patients/search.php?type=hospital"
         style="color:<?= (strpos($requestUri,'hospital') !== false) ? 'var(--primary)' : 'var(--slate-700)' ?>">
        Hospitals near you
      </a>
      <a href="/patients/search.php?type=doctor"
         style="color:<?= (strpos($requestUri,'doctor') !== false) ? 'var(--primary)' : 'var(--slate-700)' ?>">
        Doctors Near You
      </a>
      <a href="/providers/register.php"
         style="color:<?= (strpos($requestUri,'providers') !== false) ? 'var(--primary)' : 'var(--slate-700)' ?>">
        Planeazzy For Hospitals
      </a>
      <div class="pub-nav-divider"></div>
      <button class="pub-nav-lang" id="langToggle"><i class="fa-solid fa-language"></i> EN/SW</button>
      <?php if ($isPatient): ?>
        <a href="/patients/dashboard.php" style="font-size:14px;font-weight:600;color:var(--primary)">
          <i class="fa-solid fa-gauge" style="margin-right:4px"></i> Dashboard
        </a>
      <?php elseif ($isProvider): ?>
        <a href="/providers/dashboard.php" style="font-size:14px;font-weight:600;color:var(--primary)">
          <i class="fa-solid fa-gauge" style="margin-right:4px"></i> Dashboard
        </a>
      <?php else: ?>
        <a href="/patients/login.php" style="font-size:14px;font-weight:600;color:var(--slate-700)">Log in</a>
      <?php endif; ?>
    </nav>

    <!-- Right action -->
    <div style="display:flex;align-items:center;gap:10px">
      <?php if ($isPatient || $isProvider): ?>
        <!-- Avatar with initials when logged in -->
        <div style="display:flex;align-items:center;gap:10px">
          <a href="<?= $isPatient ? '/patients/dashboard.php?tab=notifications' : '/providers/dashboard.php?tab=settings' ?>"
             style="position:relative;padding:8px;border-radius:50%;color:var(--slate-500);background:none;border:none;cursor:pointer;text-decoration:none">
            <i class="fa-solid fa-bell" style="font-size:18px"></i>
            <?php if ($unreadCount > 0): ?>
            <span style="position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:var(--red);border:2px solid var(--white)"></span>
            <?php endif; ?>
          </a>
          <a href="<?= $isPatient ? '/patients/dashboard.php?tab=settings' : '/providers/dashboard.php?tab=settings' ?>"
             style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--teal));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#fff;text-decoration:none;border:2px solid rgba(25,120,229,.2)">
            <?= $initials ?>
          </a>
          <a href="<?= $isPatient ? '/api/auth/logout.php' : '/api/provider/logout.php' ?>"
             style="font-size:13px;font-weight:600;color:var(--slate-500)">
            <i class="fa-solid fa-right-from-bracket"></i>
          </a>
        </div>
      <?php else: ?>
        <button class="btn-get-started" onclick="location.href='/patients/register.php'">Get started</button>
      <?php endif; ?>
      <!-- Mobile hamburger -->
      <button onclick="document.getElementById('pubNav').classList.toggle('mob-nav-open')"
              style="display:none;background:none;border:none;cursor:pointer;font-size:20px;color:var(--slate-700);padding:6px"
              id="navHamburger">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
  </div>
</header>

<!-- Mobile nav styles -->
<style>
@media(max-width:768px){
  #navHamburger{display:block!important}
  .pub-nav{
    display:none;position:fixed;top:80px;left:0;right:0;
    background:var(--white);border-bottom:1px solid var(--slate-200);
    padding:16px 24px;flex-direction:column;gap:16px;z-index:99;
    box-shadow:var(--shadow-md);
  }
  .pub-nav.mob-nav-open{display:flex}
}
</style>

<?php if (!$noSidebar): ?>
<!-- ══════════════════════════════════════════════════════════
     DASHBOARD SIDEBAR LAYOUT (non-public pages)
══════════════════════════════════════════════════════════ -->
<div style="display:flex;flex:1;min-height:calc(100vh - 80px)">
<aside class="sidebar" id="sidebar">
  <!-- Logo row with collapse button -->
  <div class="s-logo" style="border-bottom:1px solid var(--slate-100)">
    <div class="s-logo-icon" style="background:var(--primary)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M3.5 12h3l2-6 3 12 2-8 2 4h4" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <div class="s-logo-row">
      <span class="s-logo-text">Planeazzy</span>
      <button class="s-toggle-btn" onclick="Sidebar.toggle()" title="Collapse sidebar">
        <i class="fa-solid fa-chevron-left" id="sToggleIcon"></i>
      </button>
    </div>
  </div>

  <!-- User info -->
  <?php if ($isPatient || $isProvider): ?>
  <div style="padding:12px 16px 4px">
    <div style="display:flex;align-items:center;gap:10px;padding:12px;background:var(--slate-50);border-radius:10px;overflow:hidden">
      <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--teal));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#fff;flex-shrink:0">
        <?= $initials ?>
      </div>
      <div class="s-logo-text" style="min-width:0">
        <div style="font-size:13px;font-weight:700;color:var(--slate-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= $userName ?></div>
        <div style="font-size:11px;color:var(--slate-400)"><?= ucfirst($portalType) ?> Portal</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <nav class="s-nav">
    <?php if ($isPatient): ?>
    <a href="/patients/dashboard.php?tab=overview"     class="s-nav-item <?= $activeTab==='overview'?'active':'' ?>"><i class="fa-solid fa-grid-2 s-item-icon"></i><span class="s-nav-label">Dashboard</span></a>
    <a href="/patients/dashboard.php?tab=appointments" class="s-nav-item <?= $activeTab==='appointments'?'active':'' ?>"><i class="fa-solid fa-calendar s-item-icon"></i><span class="s-nav-label">Appointments</span></a>
    <a href="/patients/dashboard.php?tab=nearby"       class="s-nav-item <?= $activeTab==='nearby'?'active':'' ?>"><i class="fa-solid fa-location-dot s-item-icon"></i><span class="s-nav-label">Find Care</span></a>
    <a href="/patients/dashboard.php?tab=insurance"    class="s-nav-item <?= $activeTab==='insurance'?'active':'' ?>"><i class="fa-solid fa-shield s-item-icon"></i><span class="s-nav-label">Insurance</span></a>
    <a href="/patients/dashboard.php?tab=notifications" class="s-nav-item <?= $activeTab==='notifications'?'active':'' ?>"><i class="fa-solid fa-bell s-item-icon"></i><span class="s-nav-label">Notifications</span><?php if($unreadCount>0):?><span class="s-badge"><?=$unreadCount?></span><?php endif;?></a>
    <a href="/patients/dashboard.php?tab=emergency"    class="s-nav-item <?= $activeTab==='emergency'?'active':'' ?>"><i class="fa-solid fa-truck-medical s-item-icon"></i><span class="s-nav-label">Emergency</span></a>
    <a href="/patients/telehealth.php"                  class="s-nav-item <?= $activeTab==='telehealth'?'active':'' ?>"><i class="fa-solid fa-video s-item-icon"></i><span class="s-nav-label">Telehealth</span></a>
    <a href="/patients/dashboard.php?tab=settings"     class="s-nav-item <?= $activeTab==='settings'?'active':'' ?>"><i class="fa-solid fa-gear s-item-icon"></i><span class="s-nav-label">Settings</span></a>
    <a href="/api/auth/logout.php"                      class="s-nav-item"><i class="fa-solid fa-right-from-bracket s-item-icon"></i><span class="s-nav-label">Sign Out</span></a>
    <?php elseif ($isProvider): ?>
    <?php
    $nav = [
        'overview'     => ['fa-gauge',         'Overview'],
        'appointments' => ['fa-calendar-check','Appointments'],
        'patients'     => ['fa-users',         'Patients'],
        'availability' => ['fa-clock',         'Availability'],
        'telehealth'   => ['fa-video',         'Telehealth'],
        'settings'     => ['fa-gear',          'Settings'],
    ];
    if ($provType === 'ambulance') {
        $nav = [
            'overview'   => ['fa-gauge',         'Overview'],
            'dispatches' => ['fa-truck-medical', 'Dispatches'],
            'requests'   => ['fa-bell',          'SOS Requests'],
            'fleet'      => ['fa-car-side',      'Fleet'],
            'settings'   => ['fa-gear',          'Settings'],
        ];
    } elseif ($provType === 'clinic') {
        $nav = [
            'overview'     => ['fa-gauge',           'Overview'],
            'appointments' => ['fa-calendar-check',  'Appointments'],
            'doctors'      => ['fa-user-doctor',      'Our Doctors'],
            'patients'     => ['fa-users',           'Patients'],
            'availability' => ['fa-clock',           'Hours'],
            'settings'     => ['fa-gear',            'Settings'],
        ];
    }
    foreach ($nav as $k => [$ic, $lb]):
    ?>
    <a href="/providers/dashboard.php?tab=<?= $k ?>" class="s-nav-item <?= $activeTab===$k?'active':'' ?>">
      <i class="fa-solid <?= $ic ?> s-item-icon"></i><span class="s-nav-label"><?= $lb ?></span>
    </a>
    <?php endforeach; ?>
    <a href="/api/provider/logout.php" class="s-nav-item"><i class="fa-solid fa-right-from-bracket s-item-icon"></i><span class="s-nav-label">Sign Out</span></a>
    <?php endif; ?>
  </nav>

  <?php if ($isPatient): ?>
  <!-- Insurance status at bottom (from dashboard design) -->
  <div class="s-ins-box">
    <div class="s-ins-inner">
      <div class="s-ins-title s-ins-text">Insurance Status</div>
      <div class="s-ins-status"><i class="fa-solid fa-circle-check"></i><span class="s-ins-text">Active Coverage</span></div>
      <div class="s-ins-provider s-ins-text">Provider: BlueShield Health</div>
    </div>
  </div>
  <?php endif; ?>
</aside>

<!-- Mobile overlay -->
<div id="mobOv" onclick="closeSidebar()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:199"></div>

<!-- Main wrap -->
<div class="main-wrap" id="mainWrap" style="flex:1;display:flex;flex-direction:column;margin-left:var(--sidebar-w);transition:margin-left .15s ease;min-height:calc(100vh - 80px)">

<!-- Dashboard topbar (only for sidebar pages) -->
<div style="height:64px;border-bottom:1px solid var(--slate-200);background:rgba(255,255,255,.8);backdrop-filter:blur(12px);position:sticky;top:80px;z-index:40;padding:0 16px 0 32px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
  <div style="flex:1;max-width:448px;position:relative">
    <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--slate-400);font-size:14px"></i>
    <input type="text" placeholder="Search doctors, records, or clinics..." id="dashSearch"
      style="width:100%;padding:8px 16px 8px 38px;background:var(--slate-100);border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:14px;color:var(--slate-900);outline:none"
      onkeydown="if(event.key==='Enter')location.href='/patients/search.php?q='+encodeURIComponent(this.value)">
  </div>
  <div style="display:flex;align-items:center;gap:12px;padding-left:16px">
    <a href="<?= $isPatient ? '/patients/dashboard.php?tab=notifications' : '#' ?>"
       style="position:relative;padding:8px;border-radius:50%;color:var(--slate-500);text-decoration:none">
      <i class="fa-solid fa-bell" style="font-size:20px"></i>
      <?php if ($unreadCount > 0): ?>
      <span style="position:absolute;top:8px;right:8px;width:8px;height:8px;border-radius:50%;background:var(--red);border:2px solid var(--white)"></span>
      <?php endif; ?>
    </a>
    <div style="width:1px;height:32px;background:var(--slate-200)"></div>
    <div style="display:flex;align-items:center;gap:10px">
      <div style="text-align:right">
        <div style="font-size:14px;font-weight:600;color:var(--slate-900)"><?= $userName ?></div>
        <div style="font-size:12px;color:var(--slate-400)">
          <?= $isPatient ? 'Patient ID: #' . str_pad($_SESSION['patient_id'] ?? 0, 5, '0', STR_PAD_LEFT) : ucfirst($provType) ?>
        </div>
      </div>
      <a href="<?= $isPatient ? '/patients/dashboard.php?tab=settings' : '/providers/dashboard.php?tab=settings' ?>"
         style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--teal));display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;text-decoration:none;border:2px solid rgba(25,120,229,.2)">
        <?= $initials ?>
      </a>
    </div>
  </div>
</div>

<?php endif; // !$noSidebar ?>
