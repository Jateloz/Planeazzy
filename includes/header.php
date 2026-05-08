<?php
/**
 * Planeazzy v5 — includes/header.php
 * Unique visual header for each portal:
 *   - Patient / Public (blue/teal Planeazzy brand)
 *   - Hospital (Planeazzy Hospital Portal)
 *   - Provider (teal accent)
 */
if (!defined('APP_NAME')) require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::startSession();
if (function_exists('send_security_headers')) send_security_headers();

$pageTitle  = isset($pageTitle)  ? htmlspecialchars($pageTitle) . ' — ' . APP_NAME : APP_NAME;
$noSidebar  = $noSidebar  ?? true;
$activeTab  = $activeTab  ?? 'overview';
$portalType = $portalType ?? 'patient';
$tabLabel   = $tabLabel   ?? 'Dashboard';
$csrf       = Security::csrfToken();

$isPatient  = !empty($_SESSION['patient_id'])  && !empty($_SESSION['authenticated']);
$isProvider = !empty($_SESSION['provider_id']) && !empty($_SESSION['is_provider']);
$isHospital = !empty($_SESSION['hospital_id']) && !empty($_SESSION['hospital_auth']);

$userName   = $isPatient
    ? htmlspecialchars($_SESSION['patient_name'] ?? 'Patient')
    : htmlspecialchars($_SESSION['provider_name'] ?? 'Provider');
$provType   = $_SESSION['provider_type'] ?? 'doctor';
$initials   = strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ',trim($userName)),0,2))));
$unreadCount = 0;
if ($isPatient) {
    try {
        require_once dirname(__DIR__) . '/services/Database.php';
        $db = Database::getInstance();
        $r  = $db->fetchOne('SELECT COUNT(*) c FROM notifications WHERE patient_id=:p AND is_read=0',[':p'=>$_SESSION['patient_id']]);
        $unreadCount = (int)($r['c'] ?? 0);
    } catch (Exception $e) {}
}
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$isHospitalPage = strpos($requestUri, '/hospital/') !== false;
$isProviderPage = strpos($requestUri, '/hospital/') !== false;
?>
<!DOCTYPE html>
<html lang="en" id="htmlRoot">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Planeazzy — Your direct path to better healthcare in Kenya.">
  <meta name="theme-color" content="<?= $isHospitalPage ? '#005ab4' : '#1978e5' ?>">
  <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.png">
  <link rel="apple-touch-icon" href="/assets/images/favicon.png">
  <title><?= $pageTitle ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="/assets/css/upgrade.css">
<style>
/* 
   UNIFIED HEADER — Patient, Hospital & Provider
 */

/*  Base reset for all headers  */
.pz-hdr {
  position: sticky; top: 0; z-index: 200;
  width: 100%;
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(255,255,255,.12);
}
.pz-hdr-inner {
  max-width: 1280px; margin: 0 auto;
  height: 58px; display: flex; align-items: center;
  justify-content: space-between; padding: 0 20px; gap: 12px;
}
.pz-hdr-logo {
  display: flex; align-items: center; gap: 0;
  text-decoration: none; flex-shrink: 0;
}
.pz-hdr-logo img { height: 38px; width: auto; display: block; }

.pz-hdr-nav {
  display: flex; align-items: center; gap: 4px; flex: 1; justify-content: flex-start;
  margin-left: 20px;
}
.pz-nav-link {
  display: flex; align-items: center; gap: 5px;
  padding: 6px 11px; border-radius: 8px;
  font-size: 12.5px; font-weight: 500; text-decoration: none;
  white-space: nowrap; transition: background .15s, color .15s;
  background: none; border: none; cursor: pointer; font-family: inherit;
}
.pz-hdr-actions {
  display: flex; align-items: center; gap: 8px; flex-shrink: 0;
}
.pz-hdr-lang {
  display: flex; align-items: center; gap: 4px;
  padding: 5px 10px; border-radius: 20px;
  font-size: 11.5px; font-weight: 700;
  background: none; border: 1.5px solid; cursor: pointer;
  font-family: inherit; transition: all .15s;
}
.pz-hdr-cta {
  padding: 7px 16px; border-radius: 8px;
  font-size: 12.5px; font-weight: 700; border: none;
  cursor: pointer; font-family: inherit; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px;
  white-space: nowrap; transition: all .15s;
}
.pz-hdr-cta:active { transform: scale(.97); }

/*  Hamburger  */
.pz-hamburger {
  display: none; align-items: center; justify-content: center;
  width: 36px; height: 36px; border: none; background: none;
  cursor: pointer; border-radius: 8px; flex-shrink: 0;
  font-size: 17px; transition: background .15s;
}

/*  Dropdown menu  */
.pz-dropdown { position: relative; }
.pz-dropdown-menu {
  display: none; position: absolute; top: calc(100% + 8px); left: 0;
  background: #fff; border-radius: 14px;
  box-shadow: 0 16px 48px rgba(0,0,0,.13);
  border: 1px solid rgba(0,0,0,.06);
  min-width: 248px; z-index: 300; padding: 7px;
}
.pz-dropdown-menu.open { display: block; }
.pz-dropdown-section {
  padding: 5px 10px 3px;
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .1em; color: #94a3b8;
}
.pz-dropdown-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 11px; border-radius: 9px;
  text-decoration: none; color: #1e293b;
  transition: background .14s; cursor: pointer;
}
.pz-dropdown-item:hover { background: #f2f4f6; }
.pz-dropdown-icon {
  width: 30px; height: 30px; border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: 13px;
}
.pz-dropdown-label { font-size: 12.5px; font-weight: 700; }
.pz-dropdown-sub   { font-size: 10.5px; color: #64748b; margin-top: 1px; }
.pz-dropdown-divider { height: 1px; background: #f1f5f9; margin: 5px 0; }

/*  PATIENT HEADER (blue/teal brand)  */
.pz-hdr--patient {
  background: rgba(255,255,255,.92);
}
.pz-hdr--patient .pz-nav-link {
  color: #475569;
}
.pz-hdr--patient .pz-nav-link:hover {
  background: #f1f5f9; color: #1e293b;
}
.pz-hdr--patient .pz-nav-link.active {
  background: rgba(25,120,229,.08); color: #1978e5;
}
.pz-hdr--patient .pz-hdr-lang {
  color: #1978e5; border-color: rgba(25,120,229,.25);
}
.pz-hdr--patient .pz-hdr-lang:hover {
  background: rgba(25,120,229,.06);
}
.pz-hdr--patient .pz-hdr-cta {
  background: #1978e5; color: #fff;
  box-shadow: 0 4px 12px rgba(25,120,229,.28);
}
.pz-hdr--patient .pz-hdr-cta:hover {
  background: #1462c4;
}
.pz-hdr--patient .pz-hamburger {
  color: #475569;
}
.pz-hdr--patient .pz-hamburger:hover { background: #f1f5f9; }
.pz-hdr--patient .pz-dropdown-btn {
  color: #475569;
}
.pz-hdr--patient .pz-login-link {
  font-size: 12.5px; font-weight: 600; color: #475569;
  text-decoration: none; padding: 6px 11px; border-radius: 8px;
  transition: background .15s, color .15s;
}
.pz-hdr--patient .pz-login-link:hover { background: #f1f5f9; color: #1e293b; }
.pz-hdr--patient .pz-user-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: linear-gradient(135deg,#1978e5,#0d9488);
  display: flex; align-items: center; justify-content: center;
  font-size: 11.5px; font-weight: 800; color: #fff;
  text-decoration: none; border: 2px solid rgba(25,120,229,.2);
  flex-shrink: 0;
}

/*  HOSPITAL HEADER (Planeazzy — deep teal) */
.pz-hdr--hospital {
  background: linear-gradient(135deg, #0d1b2e 0%, #0d2d4a 60%, #093342 100%);
  border-bottom-color: rgba(255,255,255,.08);
}
.pz-hdr--hospital .pz-hdr-logo-text {
  font-size: 15px; font-weight: 900; color: #e0f2fe;
  letter-spacing: -.04em; text-decoration: none; white-space: nowrap;
}
.pz-hdr--hospital .pz-hdr-logo-sub {
  font-size: 9.5px; color: rgba(144,213,234,.6);
  text-transform: uppercase; letter-spacing: .14em;
  font-weight: 600; margin-top: 1px;
}
.pz-hdr--hospital .pz-nav-link {
  color: rgba(255,255,255,.65);
}
.pz-hdr--hospital .pz-nav-link:hover {
  background: rgba(255,255,255,.07); color: #fff;
}
.pz-hdr--hospital .pz-nav-link.active {
  background: rgba(255,255,255,.12); color: #fff; font-weight: 600;
}
.pz-hdr--hospital .pz-hdr-lang {
  color: rgba(144,239,239,.85);
  border-color: rgba(144,239,239,.25);
}
.pz-hdr--hospital .pz-hdr-lang:hover {
  background: rgba(144,239,239,.08);
}
.pz-hdr--hospital .pz-hdr-cta {
  background: rgba(255,255,255,.1);
  color: #fff;
  border: 1.5px solid rgba(255,255,255,.2);
}
.pz-hdr--hospital .pz-hdr-cta:hover {
  background: rgba(255,255,255,.16);
}
.pz-hdr--hospital .pz-hdr-cta.primary {
  background: linear-gradient(135deg,#0873df,#0d9488);
  border-color: transparent;
  box-shadow: 0 4px 12px rgba(8,115,223,.35);
}
.pz-hdr--hospital .pz-hdr-cta.primary:hover { opacity: .92; }
.pz-hdr--hospital .pz-hamburger { color: rgba(255,255,255,.75); }
.pz-hdr--hospital .pz-hamburger:hover { background: rgba(255,255,255,.08); }
.pz-hdr--hospital .pz-hdr-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 20px;
  background: rgba(8,115,223,.25); border: 1px solid rgba(8,115,223,.4);
  font-size: 9.5px; font-weight: 700; color: #7dd3fc;
  text-transform: uppercase; letter-spacing: .08em;
}
.pz-hdr--hospital .pz-user-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: linear-gradient(135deg,#0873df,#0d9488);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 800; color: #fff;
  text-decoration: none; border: 2px solid rgba(255,255,255,.15);
  flex-shrink: 0;
}
.pz-hdr--hospital .pz-login-link {
  font-size: 12.5px; font-weight: 600; color: rgba(255,255,255,.7);
  text-decoration: none; padding: 6px 11px; border-radius: 8px;
  transition: all .15s;
}
.pz-hdr--hospital .pz-login-link:hover { background: rgba(255,255,255,.07); color: #fff; }

/* Dropdown on dark header */
.pz-hdr--hospital .pz-dropdown-menu {
  background: #0d1f30; border-color: rgba(255,255,255,.1);
}
.pz-hdr--hospital .pz-dropdown-item { color: rgba(255,255,255,.8); }
.pz-hdr--hospital .pz-dropdown-item:hover { background: rgba(255,255,255,.07); color: #fff; }
.pz-hdr--hospital .pz-dropdown-label { color: inherit; }
.pz-hdr--hospital .pz-dropdown-sub { color: rgba(255,255,255,.45); }
.pz-hdr--hospital .pz-dropdown-section { color: rgba(255,255,255,.3); }
.pz-hdr--hospital .pz-dropdown-divider { background: rgba(255,255,255,.08); }

/*  PROVIDER HEADER (teal)  */
.pz-hdr--provider {
  background: rgba(255,255,255,.93);
}
.pz-hdr--provider .pz-nav-link { color: #475569; }
.pz-hdr--provider .pz-nav-link:hover { background: #f1f5f9; color: #1e293b; }
.pz-hdr--provider .pz-nav-link.active { background: rgba(13,148,136,.08); color: #0d9488; }
.pz-hdr--provider .pz-hdr-lang { color: #0d9488; border-color: rgba(13,148,136,.25); }
.pz-hdr--provider .pz-hdr-lang:hover { background: rgba(13,148,136,.06); }
.pz-hdr--provider .pz-hdr-cta { background: #0d9488; color: #fff; box-shadow: 0 4px 12px rgba(13,148,136,.28); }
.pz-hdr--provider .pz-hdr-cta:hover { background: #0a7d74; }
.pz-hdr--provider .pz-hamburger { color: #475569; }
.pz-hdr--provider .pz-hamburger:hover { background: #f1f5f9; }
.pz-hdr--provider .pz-login-link { font-size:12.5px;font-weight:600;color:#475569;text-decoration:none;padding:6px 11px;border-radius:8px;transition:all .15s; }
.pz-hdr--provider .pz-login-link:hover { background:#f1f5f9;color:#1e293b; }
.pz-hdr--provider .pz-user-avatar { width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#0d9488,#059669);display:flex;align-items:center;justify-content:center;font-size:11.5px;font-weight:800;color:#fff;text-decoration:none;border:2px solid rgba(13,148,136,.2);flex-shrink:0; }

/*  Mobile nav drawer  */
.pz-nav-drawer {
  display: none; flex-direction: column; gap: 0;
  position: fixed; top: 58px; left: 0; right: 0;
  z-index: 197; padding: 10px 14px 16px;
  box-shadow: 0 12px 32px rgba(0,0,0,.14);
  max-height: calc(100vh - 58px); overflow-y: auto;
}
.pz-nav-drawer.open { display: flex; }
.pz-nav-drawer .pz-nav-link {
  padding: 12px 10px; border-radius: 8px; width: 100%;
  font-size: 13.5px; border-bottom: 1px solid;
}
.pz-nav-drawer--patient { background: #fff; }
.pz-nav-drawer--patient .pz-nav-link { border-bottom-color: #f8fafc; color: #334155; }
.pz-nav-drawer--patient .pz-nav-link:hover { background: #f1f5f9; }
.pz-nav-drawer--hospital { background: #0d1b2e; }
.pz-nav-drawer--hospital .pz-nav-link { border-bottom-color: rgba(255,255,255,.06); color: rgba(255,255,255,.8); }
.pz-nav-drawer--hospital .pz-nav-link:hover { background: rgba(255,255,255,.06); }
.pz-nav-drawer--provider { background: #fff; }
.pz-nav-drawer--provider .pz-nav-link { border-bottom-color: #f8fafc; color: #334155; }

/* Drawer dropdown always visible */
.pz-nav-drawer .pz-dropdown-menu {
  position: static !important; display: block !important;
  box-shadow: none !important; padding: 4px 0 4px 12px;
  margin: 0 !important; background: transparent !important;
  border: none !important; min-width: 0 !important;
}
.pz-nav-drawer .pz-dropdown-section { padding-left: 0; }
.pz-nav-drawer .pz-dropdown-item { padding: 8px 6px; border-radius: 8px; }

/*  RESPONSIVE  */
@media (max-width: 1024px) {
  .pz-hdr-inner  { padding: 0 16px; }
  .pz-hdr-nav    { margin-left: 14px; gap: 2px; }
  .pz-nav-link   { font-size: 12px; padding: 5px 9px; }
}

@media (max-width: 768px) {
  .pz-hdr-inner  { height: 52px; padding: 0 14px; }
  .pz-hdr-logo img { height: 32px; }
  .pz-hdr-nav    { display: none; }
  .pz-hamburger  { display: flex; }
  .pz-nav-drawer { top: 52px; max-height: calc(100vh - 52px); }
  .pz-hdr-cta    { padding: 6px 13px; font-size: 12px; }
  .pz-hdr-lang   { padding: 4px 9px; font-size: 11px; }
  .pz-hdr--hospital .pz-hdr-logo-text { font-size: 13.5px; }
  .pz-hdr--hospital .pz-hdr-logo-sub  { display: none; }
}

@media (max-width: 480px) {
  .pz-hdr-inner  { height: 48px; padding: 0 10px; gap: 8px; }
  .pz-hdr-logo img { height: 28px; }
  .pz-hdr--hospital .pz-hdr-logo-text { font-size: 12.5px; }
  .pz-hdr-cta    { padding: 5px 11px; font-size: 11.5px; }
  .pz-hdr-lang   { padding: 4px 8px; font-size: 10.5px; }
  .pz-nav-drawer { top: 48px; max-height: calc(100vh - 48px); padding: 8px 12px 14px; }
}
</style>
</head>
<body style="display:flex;flex-direction:column;min-height:100vh" class="<?= !$noSidebar ? 'has-sidebar' : '' ?>">

<?php
/*  Decide which header variant to render  */
if ($isHospitalPage || $isHospital):
  /*  HOSPITAL HEADER (dark teal/slate — Planeazzy)  */
?>
<header class="pz-hdr pz-hdr--hospital">
  <div class="pz-hdr-inner">

    <!-- Brand -->
    <a href="/hospital/onboarding/join.php" class="pz-hdr-logo" style="display:flex;flex-direction:column;gap:1px;text-decoration:none">
      <img src="/assets/images/favicon1.png" alt="Planeazzy" style="height:34px;width:auto;display:block">
      
    </a>

    <!-- Desktop nav -->
    <nav class="pz-hdr-nav">
      <?php if ($isHospital): ?>
      <a href="/hospital/onboarding/dashboard.php" class="pz-nav-link <?= strpos($requestUri,'dashboard')!==false?'active':'' ?>">
        <i class="fa-solid fa-gauge" style="font-size:12px"></i>
        <span data-en="Dashboard" data-sw="Dashibodi">Dashboard</span>
      </a>
      <a href="/hospital/onboarding/dashboard.php?tab=appointments" class="pz-nav-link">
        <i class="fa-solid fa-calendar-check" style="font-size:12px"></i>
        <span data-en="Appointments" data-sw="Miadi">Appointments</span>
      </a>
      <a href="/hospital/onboarding/dashboard.php?tab=analytics" class="pz-nav-link">
        <i class="fa-solid fa-chart-bar" style="font-size:12px"></i>
        <span data-en="Analytics" data-sw="Takwimu">Analytics</span>
      </a>
      <?php else: ?>
      <a href="/hospital/onboarding/join.php" class="pz-nav-link <?= strpos($requestUri,'join')!==false?'active':'' ?>" data-en="Overview" data-sw="Muhtasari">Overview</a>
      <a href="#compliance" class="pz-nav-link" data-en="Compliance" data-sw="Utiifu">Compliance</a>
      <a href="#cta" class="pz-nav-link" data-en="Get Started" data-sw="Anza">Get Started</a>
      <?php endif; ?>
    </nav>

    <!-- Actions -->
    <div class="pz-hdr-actions">
      <!-- KEPDA badge — desktop only -->
      <div class="pz-hdr-badge" style="display:none;align-items:center;gap:4px" id="kpdaBadge">
        <i class="fa-solid fa-shield-halved" style="font-size:10px"></i>
        <span>KEPDA Compliant</span>
      </div>

      <!-- Lang toggle-->
      <!--<button class="pz-hdr-lang" id="langToggle"
              data-en-title="Switch to Swahili" data-sw-title="Switch to English"
              title="Switch Language">
        <i class="fa-solid fa-language"></i>
        <span id="langLabel">SW</span>
      </button>-->

      <?php if ($isHospital): ?>
      <!-- Notifications -->
      <a href="/hospital/onboarding/dashboard.php" style="position:relative;color:rgba(255,255,255,.65);font-size:15px;padding:5px;text-decoration:none" title="Notifications">
        <i class="fa-solid fa-bell"></i>
      </a>
      <!-- User avatar -->
      <a href="/hospital/onboarding/dashboard.php?tab=settings" class="pz-user-avatar" title="Settings">
        <?= strtoupper(substr($_SESSION['hospital_name']??'H',0,2)) ?>
      </a>
      <a href="/hospital/onboarding/logout.php" class="pz-login-link" data-en="Sign Out" data-sw="Toka">Sign Out</a>
      <?php else: ?>
      <a href="/hospital/onboarding/login.php" class="pz-login-link" data-en="Sign In" data-sw="Ingia">Sign In</a>
      <a href="/hospital/onboarding/signup.php" class="pz-hdr-cta primary">
        <i class="fa-solid fa-hospital" style="font-size:11px"></i>
        <span data-en="Register Facility" data-sw="Sajili Kituo">Register Facility</span>
      </a>
      <?php endif; ?>

      <!-- Hamburger -->
      <button class="pz-hamburger" id="pzHamburger" aria-label="Open menu" aria-expanded="false">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
  </div>
</header>

<!-- Hospital mobile nav drawer -->
<nav class="pz-nav-drawer pz-nav-drawer--hospital" id="pzNavDrawer" aria-hidden="true">
  <?php if ($isHospital): ?>
  <a href="/hospital/onboarding/dashboard.php" class="pz-nav-link" data-en="Dashboard" data-sw="Dashibodi"><i class="fa-solid fa-gauge" style="margin-right:7px;font-size:13px"></i>Dashboard</a>
  <a href="/hospital/onboarding/dashboard.php?tab=appointments" class="pz-nav-link" data-en="Appointments" data-sw="Miadi"><i class="fa-solid fa-calendar-check" style="margin-right:7px;font-size:13px"></i>Appointments</a>
  <a href="/hospital/onboarding/dashboard.php?tab=analytics" class="pz-nav-link" data-en="Analytics" data-sw="Takwimu"><i class="fa-solid fa-chart-bar" style="margin-right:7px;font-size:13px"></i>Analytics</a>
  <a href="/hospital/onboarding/dashboard.php?tab=settings" class="pz-nav-link" data-en="Settings" data-sw="Mipangilio"><i class="fa-solid fa-gear" style="margin-right:7px;font-size:13px"></i>Settings</a>
  <a href="/hospital/onboarding/logout.php" class="pz-nav-link" style="color:rgba(220,38,38,.8)" data-en="Sign Out" data-sw="Toka"><i class="fa-solid fa-right-from-bracket" style="margin-right:7px;font-size:13px"></i>Sign Out</a>
  <?php else: ?>
  <a href="/hospital/onboarding/join.php" class="pz-nav-link" data-en="Overview" data-sw="Muhtasari">Overview</a>
  <a href="/hospital/onboarding/signup.php" class="pz-nav-link" data-en="Register Facility" data-sw="Sajili Kituo">Register Facility</a>
  <a href="/hospital/onboarding/login.php" class="pz-nav-link" data-en="Sign In" data-sw="Ingia">Sign In</a>
  
  <?php endif; ?>
</nav>

<?php elseif ($isProviderPage || $isProvider): ?>
<?php
  /*  PROVIDER HEADER (white/teal)  */
?>
<header class="pz-hdr pz-hdr--provider">
  <div class="pz-hdr-inner">

    <!-- Brand -->
    <a href="/" class="pz-hdr-logo">
      <img src="/assets/images/logo.svg" alt="Planeazzy" style="height:34px">
    </a>

    <!-- Desktop nav -->
    <nav class="pz-hdr-nav">
      <?php if ($isProvider): ?>
      
      
      
      <?php else: ?>
      
      <a href="/hospital/onboarding/login.php" class="pz-nav-link" data-en="Hospital Portal" data-sw="Lango la Hospitali">Hospital Portal</a>
      <?php endif; ?>
    </nav>

    <div class="pz-hdr-actions">
      <!--<button class="pz-hdr-lang" id="langToggle" title="Switch Language">
        <i class="fa-solid fa-language"></i>
        <span id="langLabel">SW</span>
      </button>-->
      <?php if ($isProvider): ?>
      
      <a href="/api/hospital/logout.php" class="pz-login-link" data-en="Sign Out" data-sw="Toka">Sign Out</a>
      <?php else: ?>
      
      
      <?php endif; ?>
      <button class="pz-hamburger" id="pzHamburger" aria-label="Menu" aria-expanded="false">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
  </div>
</header>

<nav class="pz-nav-drawer pz-nav-drawer--provider" id="pzNavDrawer">
  <?php if ($isProvider): ?>
  
  
  
  <a href="/api/hospital/logout.php" class="pz-nav-link" style="color:#dc2626" data-en="Sign Out" data-sw="Toka">Sign Out</a>
  <?php else: ?>
  
  
  <a href="/hospital/onboarding/login.php" class="pz-nav-link" data-en="Hospital Portal →" data-sw="Lango la Hospitali →">Hospital Portal →</a>
  <?php endif; ?>
</nav>

<?php else: ?>
<?php
  /*  PATIENT / PUBLIC HEADER (white/blue — Planeazzy brand)  */
?>
<header class="pz-hdr pz-hdr--patient">
  <div class="pz-hdr-inner">

    <!-- Brand -->
    <a href="/" class="pz-hdr-logo">
      <img src="/assets/images/favicon1.png" alt="Planeazzy" style="height:36px">
    </a>

    <!-- Desktop nav -->
    <nav class="pz-hdr-nav">
      <a href="/patients/search.php?geo=1&type=hospital"
         class="pz-nav-link <?= strpos($requestUri,'hospital')!==false&&strpos($requestUri,'onboarding')===false?'active':'' ?>"
         data-en="Hospitals Near You" data-sw="Hospitali Karibu Nawe">
        <i class="fa-solid fa-hospital" style="font-size:11px"></i>
        <span data-en="Hospitals" data-sw="Hospitali">Hospitals</span>
      </a>
      <a href="/patients/search.php?geo=1&type=doctor"
         class="pz-nav-link <?= strpos($requestUri,'search')!==false&&strpos($requestUri,'hospital')===false?'active':'' ?>"
         data-en="Doctors Near You" data-sw="Madaktari Karibu Nawe">
        <i class="fa-solid fa-stethoscope" style="font-size:11px"></i>
        <span data-en="Doctors" data-sw="Madaktari">Doctors</span>
      </a>

      <!-- For Facilities dropdown -->
      <div class="pz-dropdown">
        <button class="pz-nav-link pz-dropdown-btn" onclick="toggleDropdown(event)" aria-haspopup="true">
          <i class="fa-solid fa-building-user" style="font-size:11px"></i>
          <span data-en="For Facilities" data-sw="Kwa Vituo">For Facilities</span>
          <i class="fa-solid fa-chevron-down" style="font-size:9px;margin-left:2px;transition:transform .2s" id="dropChev"></i>
        </button>
        <div class="pz-dropdown-menu" id="facilityDropdown">
          <div class="pz-dropdown-section" data-en="Hospitals &amp; Clinics" data-sw="Hospitali na Kliniki">Hospitals &amp; Clinics</div>
          <a href="/hospital/onboarding/join.php" class="pz-dropdown-item">
            <div class="pz-dropdown-icon" style="background:rgba(25,120,229,.1)"><i class="fa-solid fa-hospital" style="color:#1978e5"></i></div>
            <div>
              <div class="pz-dropdown-label" data-en="Register Your Hospital" data-sw="Sajili Hospitali Yako">Register Your Hospital</div>
              <div class="pz-dropdown-sub" data-en="Join Planeazzy as a verified facility" data-sw="Jiunge kama kituo kilichoidhinishwa">Join Planeazzy as a verified facility</div>
            </div>
          </a>
          <a href="/hospital/onboarding/login.php" class="pz-dropdown-item">
            <div class="pz-dropdown-icon" style="background:rgba(25,120,229,.1)"><i class="fa-solid fa-right-to-bracket" style="color:#1978e5"></i></div>
            <div>
              <div class="pz-dropdown-label" data-en="Hospital Sign In" data-sw="Ingia kama Hospitali">Hospital Sign In</div>
              <div class="pz-dropdown-sub" data-en="Access your hospital dashboard" data-sw="Fikia dashibodi ya hospitali yako">Access your hospital dashboard</div>
            </div>
          </a>
          <div class="pz-dropdown-divider"></div>
          <div class="pz-dropdown-section" data-en="Doctors &amp; Specialists" data-sw="Madaktari na Wataalamu">Doctors &amp; Specialists</div>
          <a href="/doctors/onboarding/register.php" class="pz-dropdown-item">
            <div class="pz-dropdown-icon" style="background:rgba(13,148,136,.1)"><i class="fa-solid fa-user-doctor" style="color:#0d9488"></i></div>
            <div>
              <div class="pz-dropdown-label" data-en="Doctor Registration" data-sw="Ingia kama Daktari">Register as a Doctor</div>
              <div class="pz-dropdown-sub" data-en="Sign up to access as a doctor" data-sw="Ingia kama daktari">Sign up as a doctor</div>
            </div>
          </a>
          <a href="/doctors/onboarding/login.php" class="pz-dropdown-item">
            <div class="pz-dropdown-icon" style="background:rgba(25,120,229,.1)"><i class="fa-solid fa-right-to-bracket" style="color:#1978e5"></i></div>
            <div>
              <div class="pz-dropdown-label" data-en="Doctor Login" data-sw="Ingia kama Hospitali">Sign in as a Doctor</div>
              <div class="pz-dropdown-sub" data-en="Access your hospital dashboard" data-sw="Fikia dashibodi ya hospitali yako">Access your hospital dashboard</div>
            </div>
          </a>
          <div class="pz-dropdown-divider"></div>
        </div>
      </div>
    </nav>

    <!-- Actions -->
    <div class="pz-hdr-actions">
      <!--<button class="pz-hdr-lang" id="langToggle" title="Switch Language">
        <i class="fa-solid fa-language"></i>
        <span id="langLabel">SW</span>
      </button>-->

      <?php if ($isPatient): ?>
      <a href="/patients/dashboard.php?tab=notifications" style="position:relative;color:#64748b;font-size:15px;padding:5px;text-decoration:none">
        <i class="fa-solid fa-bell"></i>
        <?php if ($unreadCount > 0): ?>
        <span style="position:absolute;top:3px;right:3px;width:7px;height:7px;border-radius:50%;background:#dc2626;border:2px solid #fff"></span>
        <?php endif; ?>
      </a>
      <a href="/patients/dashboard.php?tab=settings" class="pz-user-avatar" title="Profile"><?= $initials ?></a>
      <a href="/api/auth/logout.php" class="pz-login-link" data-en="Sign Out" data-sw="Toka">Sign Out</a>
      <?php elseif ($isProvider): ?>
      
      <a href="/api/hospital/logout.php" class="pz-login-link" data-en="Sign Out" data-sw="Toka">Sign Out</a>
      <?php else: ?>
      <a href="/patients/login.php" class="pz-login-link" data-en="Log in" data-sw="Ingia">Log in</a>
      <button class="pz-hdr-cta" onclick="location.href='/patients/register.php'"
              data-en="Get Started" data-sw="Anza Sasa">Get Started</button>
      <?php endif; ?>

      <button class="pz-hamburger" id="pzHamburger" aria-label="Menu" aria-expanded="false">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
  </div>
</header>

<!-- Patient mobile nav drawer -->
<nav class="pz-nav-drawer pz-nav-drawer--patient" id="pzNavDrawer">
  <a href="/patients/search.php?geo=1&type=hospital" class="pz-nav-link" data-en="Hospitals Near You" data-sw="Hospitali Karibu Nawe">Hospitals Near You</a>
  <a href="/patients/search.php?geo=1&type=doctor" class="pz-nav-link" data-en="Doctors Near You" data-sw="Madaktari Karibu Nawe">Doctors Near You</a>
  <div style="padding:10px 10px 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8" data-en="For Facilities" data-sw="Kwa Vituo">For Facilities</div>
  <a href="/hospital/onboarding/join.php" class="pz-nav-link" data-en="Register Hospital / Clinic" data-sw="Sajili Hospitali / Kliniki">Register Hospital / Clinic</a>
  <a href="/hospital/onboarding/login.php" class="pz-nav-link" data-en="Hospital Sign In" data-sw="Ingia kama Hospitali">Hospital Sign In</a>
  
  
  <?php if ($isPatient): ?>
  <div style="height:1px;background:#f1f5f9;margin:6px 0"></div>
  <a href="/patients/dashboard.php" class="pz-nav-link" data-en="My Dashboard" data-sw="Dashibodi Yangu">My Dashboard</a>
  <a href="/api/auth/logout.php" class="pz-nav-link" style="color:#dc2626" data-en="Sign Out" data-sw="Toka">Sign Out</a>
  <?php else: ?>
  <div style="height:1px;background:#f1f5f9;margin:6px 0"></div>
  <a href="/patients/login.php" class="pz-nav-link" data-en="Patient Login" data-sw="Ingia kama Mgonjwa">Patient Login</a>
  <a href="/patients/register.php" class="pz-nav-link" data-en="Create Patient Account" data-sw="Unda Akaunti ya Mgonjwa" style="color:#1978e5;font-weight:700">Create Patient Account</a>
  <?php endif; ?>
</nav>

<?php endif; // end header variants ?>

<!-- Global JS for hamburger and dropdown -->
<script>
(function(){
  const ham  = document.getElementById('pzHamburger');
  const draw = document.getElementById('pzNavDrawer');
  const drop = document.getElementById('facilityDropdown');
  const chev = document.getElementById('dropChev');

  // Hamburger toggle
  if (ham && draw) {
    ham.addEventListener('click', () => {
      const open = draw.classList.toggle('open');
      ham.setAttribute('aria-expanded', open);
      draw.setAttribute('aria-hidden', !open);
      ham.querySelector('i').className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
    });
    // Close drawer on outside tap
    document.addEventListener('click', e => {
      if (!ham.contains(e.target) && !draw.contains(e.target)) {
        draw.classList.remove('open');
        ham.setAttribute('aria-expanded', false);
        ham.querySelector('i').className = 'fa-solid fa-bars';
      }
    });
  }

  // Facility dropdown
  window.toggleDropdown = function(e) {
    e.stopPropagation();
    if (!drop) return;
    const open = drop.classList.toggle('open');
    if (chev) chev.style.transform = open ? 'rotate(180deg)' : '';
  };
  document.addEventListener('click', () => {
    drop?.classList.remove('open');
    if (chev) chev.style.transform = '';
  });
  drop?.addEventListener('click', e => e.stopPropagation());

  // Show KEPDA badge on desktop
  const badge = document.getElementById('kpdaBadge');
  if (badge && window.innerWidth > 1024) badge.style.display = 'flex';
})();
</script>

<?php if (!$noSidebar): ?>
<!-- 
     DASHBOARD SIDEBAR (patient & provider portals)
 -->
<div style="display:flex;flex:1;min-height:calc(100vh - 52px)">
<aside class="sidebar" id="sidebar">
  <div class="s-logo" style="border-bottom:1px solid var(--slate-100)">
    <img src="/assets/images/logo.svg" alt="Planeazzy" style="height:32px;width:auto;object-fit:contain;display:block">
    <div class="s-logo-row" style="flex:1;margin-left:4px">
      <button class="s-toggle-btn" onclick="Sidebar.toggle()" title="Collapse sidebar">
        <i class="fa-solid fa-chevron-left" id="sToggleIcon"></i>
      </button>
    </div>
  </div>
  <?php if ($isPatient || $isProvider): ?>
  <div style="padding:8px 12px 4px">
    <div style="display:flex;align-items:center;gap:9px;padding:9px 10px;background:var(--slate-50);border-radius:9px;overflow:hidden">
      <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--teal));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0"><?= $initials ?></div>
      <div class="s-logo-text" style="min-width:0">
        <div style="font-size:12.5px;font-weight:700;color:var(--slate-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= $userName ?></div>
        <div style="font-size:10.5px;color:var(--slate-400)"><?= ucfirst($portalType) ?> Portal</div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <nav class="s-nav">
    <?php if ($isPatient): ?>
    <a href="/patients/dashboard.php?tab=overview"      class="s-nav-item <?= $activeTab==='overview'?'active':''?>"><i class="fa-solid fa-gauge s-item-icon"></i><span class="s-nav-label" data-en="Dashboard" data-sw="Dashibodi">Dashboard</span></a>
    <a href="/patients/dashboard.php?tab=appointments"  class="s-nav-item <?= $activeTab==='appointments'?'active':''?>"><i class="fa-solid fa-calendar-check s-item-icon"></i><span class="s-nav-label" data-en="Appointments" data-sw="Miadi">Appointments</span><?php if($unreadCount>0):?><span class="s-badge"><?=$unreadCount?></span><?php endif;?></a>
    <a href="/patients/dashboard.php?tab=nearby"        class="s-nav-item <?= $activeTab==='nearby'?'active':''?>"><i class="fa-solid fa-location-dot s-item-icon"></i><span class="s-nav-label" data-en="Find Care" data-sw="Tafuta Huduma">Find Care</span></a>
    <a href="/patients/dashboard.php?tab=insurance"     class="s-nav-item <?= $activeTab==='insurance'?'active':''?>"><i class="fa-solid fa-shield s-item-icon"></i><span class="s-nav-label" data-en="Insurance" data-sw="Bima">Insurance</span></a>
    <a href="/patients/dashboard.php?tab=notifications" class="s-nav-item <?= $activeTab==='notifications'?'active':''?>"><i class="fa-solid fa-bell s-item-icon"></i><span class="s-nav-label" data-en="Notifications" data-sw="Arifa">Notifications</span><?php if($unreadCount>0):?><span class="s-badge"><?=$unreadCount?></span><?php endif;?></a>
    <a href="/patients/dashboard.php?tab=emergency"     class="s-nav-item <?= $activeTab==='emergency'?'active':''?>"><i class="fa-solid fa-truck-medical s-item-icon"></i><span class="s-nav-label" data-en="Emergency" data-sw="Dharura">Emergency</span></a>
    <a href="/patients/telehealth.php"                  class="s-nav-item <?= $activeTab==='telehealth'?'active':''?>"><i class="fa-solid fa-video s-item-icon"></i><span class="s-nav-label" data-en="Telehealth" data-sw="Telemedicine">Telehealth</span></a>
    <a href="/patients/dashboard.php?tab=settings"      class="s-nav-item <?= $activeTab==='settings'?'active':''?>"><i class="fa-solid fa-gear s-item-icon"></i><span class="s-nav-label" data-en="Settings" data-sw="Mipangilio">Settings</span></a>
    <a href="/api/auth/logout.php"                      class="s-nav-item"><i class="fa-solid fa-right-from-bracket s-item-icon"></i><span class="s-nav-label" data-en="Sign Out" data-sw="Toka">Sign Out</span></a>
    <?php elseif ($isProvider): ?>
    
    
    
    
    <a href="/api/hospital/logout.php"                  class="s-nav-item"><i class="fa-solid fa-right-from-bracket s-item-icon"></i><span class="s-nav-label" data-en="Sign Out" data-sw="Toka">Sign Out</span></a>
    <?php endif; ?>
  </nav>
  <?php if ($isPatient): ?>
  <div class="s-ins-box">
    <div class="s-ins-inner">
      <div class="s-ins-title s-ins-text" data-en="Insurance Status" data-sw="Hali ya Bima">Insurance Status</div>
      <div class="s-ins-status"><i class="fa-solid fa-circle-check"></i><span class="s-ins-text" data-en="Active Coverage" data-sw="Inafanya Kazi">Active Coverage</span></div>
      <div class="s-ins-provider s-ins-text" data-en="Provider: NHIF" data-sw="Mtoa: NHIF">Provider: NHIF</div>
    </div>
  </div>
  <?php endif; ?>
</aside>

<div id="mobOv" class="mob-ov" onclick="Sidebar.closeMob()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:299;backdrop-filter:blur(2px)"></div>
<div class="main-wrap" id="mainWrap" style="flex:1;display:flex;flex-direction:column;margin-left:var(--sidebar-w);transition:margin-left .15s ease;min-height:calc(100vh - 52px)">

<!-- Dashboard topbar -->
<div style="height:56px;border-bottom:1px solid var(--slate-200);background:rgba(255,255,255,.9);backdrop-filter:blur(12px);position:sticky;top:52px;z-index:40;padding:0 14px 0 20px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
  <div style="flex:1;max-width:380px;position:relative">
    <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--slate-400);font-size:13px"></i>
    <input type="text" placeholder="Search…" id="dashSearch"
      data-en-placeholder="Search doctors, records, or clinics…"
      data-sw-placeholder="Tafuta madaktari, rekodi, au kliniki…"
      style="width:100%;padding:7px 14px 7px 34px;background:var(--slate-100);border:none;border-radius:7px;font-family:'Inter',sans-serif;font-size:12.5px;color:var(--slate-900);outline:none"
      onkeydown="if(event.key==='Enter')location.href='/patients/search.php?q='+encodeURIComponent(this.value)">
  </div>
  <div style="display:flex;align-items:center;gap:8px;padding-left:12px">
    <a href="<?= $isPatient ? '/patients/dashboard.php?tab=notifications' : '#' ?>"
       style="position:relative;padding:6px;border-radius:50%;color:var(--slate-500);text-decoration:none">
      <i class="fa-solid fa-bell" style="font-size:15px"></i>
      <?php if ($unreadCount > 0): ?>
      <span style="position:absolute;top:6px;right:6px;width:7px;height:7px;border-radius:50%;background:var(--red);border:2px solid #fff"></span>
      <?php endif; ?>
    </a>
    <div style="width:1px;height:24px;background:var(--slate-200)"></div>
    <div style="display:flex;align-items:center;gap:8px">
      <div style="text-align:right">
        <div style="font-size:12.5px;font-weight:600;color:var(--slate-900)"><?= $userName ?></div>
        <div style="font-size:10.5px;color:var(--slate-400)"><?= $isPatient ? 'Patient ID: #'.str_pad($_SESSION['patient_id']??0,5,'0',STR_PAD_LEFT) : ucfirst($provType) ?></div>
      </div>
      <a href="<?= $isPatient ? '/patients/dashboard.php?tab=settings' : '/hospital/dashboard.php?tab=settings' ?>"
         style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--teal));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;text-decoration:none;border:2px solid rgba(25,120,229,.2)">
        <?= $initials ?>
      </a>
    </div>
  </div>
</div>

<?php endif; // !$noSidebar ?>
