<?php
/**
 * Planeazzy — Hospital Doctor Profile Page
 * Full page: sidebar nav, appointments (hospital + guest), messaging
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();

if (empty($_SESSION['hospital_id']) || empty($_SESSION['hospital_auth'])) {
    header('Location: /hospital/onboarding/login.php'); exit;
}
$hid  = (int)$_SESSION['hospital_id'];
$db   = Database::getInstance();
$hosp = $db->fetchOne('SELECT * FROM hospital_providers WHERE id=:id', [':id'=>$hid]);
if (!$hosp || $hosp['status'] !== 'approved' || !$hosp['is_active']) {
    header('Location: /hospital/onboarding/pending.php'); exit;
}

$did = (int)($_GET['id'] ?? 0);
if (!$did) { header('Location: /hospital/onboarding/dashboard.php?tab=doctors'); exit; }

$doc = $db->fetchOne(
    'SELECT d.*, dep.name dept_name, dep.icon dept_icon
     FROM hospital_doctors d
     LEFT JOIN hospital_departments dep ON dep.id=d.department_id
     WHERE d.id=:id AND d.hospital_id=:h AND d.is_active=1',
    [':id'=>$did, ':h'=>$hid]
);
if (!$doc) { header('Location: /hospital/onboarding/dashboard.php?tab=doctors'); exit; }

// All hospital appointments for this doctor
$appts = $db->fetchAll(
    'SELECT *, "hospital" AS appt_source FROM hospital_appointments
     WHERE hospital_id=:h AND doctor_id=:d
     ORDER BY appointment_at DESC LIMIT 120',
    [':h'=>$hid, ':d'=>$did]
);

// Also pull guest_bookings made for this doctor (offset 20000+did)
try {
    $docName = $doc['name'];
    $guestRows = $db->fetchAll(
        "SELECT g.id + 800000 AS id, g.guest_name AS patient_name,
                g.guest_phone AS patient_phone, g.guest_email AS patient_email,
                g.appointment_at, g.location_type AS visit_type,
                g.service_type AS department, g.reason AS notes,
                g.created_at, 'guest' AS appt_source,
                CASE g.status WHEN 'confirmed' THEN 'confirmed' WHEN 'cancelled' THEN 'cancelled' ELSE 'pending' END AS status
         FROM guest_bookings g
         WHERE (g.provider_id = :dpid OR g.provider_name LIKE :dname)
           AND g.status != 'cancelled'
         ORDER BY g.created_at DESC LIMIT 40",
        [':dpid' => $did + 20000, ':dname' => '%' . trim($docName) . '%']
    );
    // Merge, deduplicate by appointment_at
    $existingTimes = array_column($appts, 'appointment_at');
    foreach ($guestRows as $gr) {
        if (!in_array($gr['appointment_at'], $existingTimes)) {
            $appts[] = $gr;
        }
    }
    usort($appts, fn($a,$b) => strtotime($b['appointment_at']??'0') - strtotime($a['appointment_at']??'0'));
} catch(Exception $e) {}

// Smart display status
foreach ($appts as &$a) {
    $at = strtotime($a['appointment_at']??'');
    $now = time(); $diff = $at ? ($now - $at)/3600 : 0;
    $base = $a['status'] ?? 'pending';
    if (in_array($base,['pending','confirmed']) && $at && $now > $at) {
        if ($diff <= 3)     $a['display_status'] = 'pending_checkin';
        elseif ($diff <= 6) $a['display_status'] = 'awaiting_confirmation';
        else                $a['display_status'] = 'unconfirmed';
    } else { $a['display_status'] = $base; }
}
unset($a);

$totalAppts     = count($appts);
$pendingAppts   = count(array_filter($appts, fn($a)=>$a['status']==='pending'));
$confirmedAppts = count(array_filter($appts, fn($a)=>$a['status']==='confirmed'));
$completedAppts = count(array_filter($appts, fn($a)=>$a['status']==='completed'));
$todayAppts     = count(array_filter($appts, fn($a)=>date('Y-m-d',strtotime($a['appointment_at']??''))==date('Y-m-d')));
$avail     = !empty($doc['availability']) ? (json_decode($doc['availability'],true)??[]) : [];
$initials  = strtoupper(substr($doc['name'],0,1).(strpos($doc['name'],' ')!==false?substr($doc['name'],strrpos($doc['name'],' ')+1,1):''));
$dotColor  = match($doc['status']){'on-duty'=>'#22c55e','on-break'=>'#f59e0b','suspended'=>'#ef4444',default=>'#94a3b8'};
$csrf      = Security::csrfToken();
$currentPage = 'doctors';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Dr. <?=htmlspecialchars($doc['name'])?> — <?=htmlspecialchars($hosp['facility_name']??'Hospital')?></title>
  <link rel="icon" type="image/png" href="/assets/images/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/clinical.css">
<style>
:root{--blue:#005ab4;--teal:#006a6a;--green:#16a34a;--red:#dc2626;--amber:#d97706;--s50:#f8fafc;--s100:#f1f5f9;--s200:#e2e8f0;--s300:#cbd5e1;--s400:#94a3b8;--s500:#64748b;--s600:#475569;--s700:#334155;--s900:#0f172a;--r:8px;--sb-w:220px}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1a1c1e;min-height:100vh}
a{text-decoration:none;color:inherit}
/* layout with sidebar */
.db-wrap{display:flex;min-height:100vh}
.db-main{margin-left:var(--sb-w);flex:1;display:flex;flex-direction:column;min-width:0;overflow:hidden}
.db-topbar{position:sticky;top:0;z-index:100;background:rgba(255,255,255,.96);backdrop-filter:blur(12px);border-bottom:1px solid rgba(193,198,213,.2);padding:10px 22px;display:flex;align-items:center;gap:12px;width:100%}
.db-content{padding:16px 20px;flex:1;min-width:0;overflow-x:hidden;overflow-y:auto;width:100%}
.db-hamb{display:none;align-items:center;justify-content:center;width:36px;height:36px;border:none;background:none;cursor:pointer;border-radius:8px;color:#42474e}
/* Sidebar */
.sidebar{position:fixed;left:0;top:0;bottom:0;width:var(--sb-w);background:#fff;border-right:1px solid rgba(193,198,213,.2);display:flex;flex-direction:column;z-index:200;overflow:hidden;transition:transform .25s}
.sb-brand{padding:14px 16px 10px;border-bottom:1px solid rgba(193,198,213,.15)}
.sb-brand-name{font-size:.875rem;font-weight:900;letter-spacing:-.03em;color:var(--blue)}
.sb-brand-sub{font-size:.55rem;text-transform:uppercase;letter-spacing:.1em;color:var(--s400);margin-bottom:8px}
.sb-facility{display:flex;align-items:center;gap:7px}
.sb-fac-icon{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--blue),#0873df);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-section{font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:var(--s400);padding:10px 14px 3px;opacity:.7}
.nav-item{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:var(--r);margin:1px 7px;font-size:.78rem;font-weight:500;color:var(--s500);cursor:pointer;transition:all .15s;text-decoration:none;border:none;background:none;width:calc(100% - 14px);text-align:left}
.nav-item:hover{background:var(--s100);color:var(--s900)}
.nav-item.active{background:rgba(0,90,180,.08);color:var(--blue);font-weight:700}
.nav-badge{margin-left:auto;background:var(--blue);color:#fff;font-size:.5rem;font-weight:800;padding:1px 5px;border-radius:9999px;min-width:14px;text-align:center}
.sb-footer{padding:6px;border-top:1px solid rgba(193,198,213,.15)}
/* Profile hero */
.profile-strip{background:#fff;border-radius:16px;border:1px solid var(--s200);box-shadow:0 1px 4px rgba(0,0,0,.05);padding:24px;display:flex;align-items:flex-start;gap:20px;margin-bottom:16px;flex-wrap:wrap}
.profile-avatar-wrap{position:relative;flex-shrink:0}
.profile-avatar{width:92px;height:92px;border-radius:50%;object-fit:cover;border:3px solid rgba(0,90,180,.15);background:linear-gradient(135deg,#0873df,#005ab4);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff}
.camera-label{position:absolute;bottom:2px;right:2px;width:26px;height:26px;border-radius:50%;background:#fff;border:2px solid var(--s100);display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,.12)}
.camera-label input{display:none}
.profile-meta{flex:1;min-width:220px}
.profile-name{font-size:1.375rem;font-weight:900;letter-spacing:-.04em;margin-bottom:3px}
.profile-spec{font-size:.875rem;color:var(--s500);margin-bottom:8px}
.profile-tags{display:flex;flex-wrap:wrap;gap:6px}
.ptag{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:9999px;font-size:.6875rem;font-weight:700}
.ptag-blue{background:rgba(0,90,180,.08);color:var(--blue)}
.ptag-teal{background:rgba(0,106,106,.08);color:var(--teal)}
.ptag-green{background:rgba(22,163,74,.08);color:var(--green)}
.ptag-slate{background:var(--s100);color:var(--s500)}
/* Stat row */
.stat-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:10px;margin-bottom:16px}
.stat-box{background:#fff;border-radius:14px;border:1px solid var(--s200);box-shadow:0 1px 3px rgba(0,0,0,.05);padding:14px 16px}
.stat-num{font-size:1.625rem;font-weight:900;letter-spacing:-.04em}
.stat-lbl{font-size:.5625rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--s400);margin-top:2px}
/* Grid layout */
.content-grid{display:grid;grid-template-columns:220px 1fr;gap:12px;align-items:start;width:100%;min-width:0}
/* Info sidebar */
.info-card{background:#fff;border-radius:14px;border:1px solid var(--s200);box-shadow:0 1px 3px rgba(0,0,0,.05);overflow:hidden;position:sticky;top:74px}
.info-card-head{background:linear-gradient(135deg,var(--blue),#0873df);padding:16px}
.info-section{padding:14px 16px;border-bottom:1px solid var(--s100)}
.info-section:last-child{border-bottom:none}
.info-sec-lbl{font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:var(--s400);margin-bottom:8px}
.info-row{display:flex;align-items:flex-start;gap:8px;padding:5px 0;font-size:.8rem}
.info-row i{color:var(--s400);width:15px;font-size:12px;margin-top:2px;flex-shrink:0}
.info-val{color:var(--s700);font-weight:500;flex:1;line-height:1.45}
/* Avail grid */
.avail-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;margin-top:6px}
.avday{text-align:center;padding:5px 2px;border-radius:6px;font-size:.5625rem;font-weight:700}
.avday.on{background:rgba(0,90,180,.1);color:var(--blue)}
.avday.off{background:var(--s100);color:var(--s400)}
/* Main right */
.main-card{background:#fff;border-radius:14px;border:1px solid var(--s200);box-shadow:0 1px 3px rgba(0,0,0,.05);overflow:hidden;margin-bottom:14px}
.card-head{padding:14px 18px;border-bottom:1px solid var(--s100);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.card-title{font-size:.9375rem;font-weight:700;display:flex;align-items:center;gap:7px}
.card-title i{color:var(--blue);font-size:15px}
/* Filter chips */
.chip{padding:4px 12px;border-radius:9999px;border:1.5px solid var(--s200);background:transparent;font-family:inherit;font-size:.6875rem;font-weight:700;color:var(--s500);cursor:pointer;transition:all .12s}
.chip.active,.chip:hover{background:var(--blue);color:#fff;border-color:var(--blue)}
/* Table */
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.8125rem}
thead th{background:var(--s50);padding:9px 14px;text-align:left;font-size:.5625rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--s400);white-space:nowrap;border-bottom:1px solid var(--s100)}
tbody td{padding:11px 14px;border-bottom:1px solid var(--s100);vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover td{background:var(--s50)}
.tbl-av{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#1978e5,#1251a3);display:flex;align-items:center;justify-content:center;font-size:.5625rem;font-weight:800;color:#fff;flex-shrink:0}
/* Status pills */
.spill{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:9999px;font-size:.5625rem;font-weight:700}
.sp-pending{background:#fef9c3;color:#854d0e}
.sp-confirmed{background:#dcfce7;color:#166534}
.sp-completed{background:#dbeafe;color:#1e40af}
.sp-cancelled{background:#fee2e2;color:#991b1b}
.sp-checkin{background:#fef3c7;color:#92400e}
.sp-awaiting{background:#fde68a;color:#78350f}
.sp-unconfirmed{background:#fee2e2;color:#991b1b}
/* Action buttons in table */
.act-btn{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:6px;border:none;font-family:inherit;font-size:.6875rem;font-weight:700;cursor:pointer;transition:all .12s}
.act-confirm{background:rgba(0,90,180,.08);color:var(--blue)}
.act-cancel{background:rgba(220,38,38,.08);color:var(--red)}
.act-complete{background:rgba(22,163,74,.08);color:var(--green)}
.act-msg{background:rgba(0,106,106,.08);color:var(--teal)}
/* Messaging panel */
.msg-panel{position:fixed;right:0;top:0;bottom:0;width:360px;background:#fff;border-left:1px solid var(--s200);box-shadow:-4px 0 24px rgba(0,0,0,.08);z-index:400;display:none;flex-direction:column;transition:transform .25s}
.msg-panel.open{display:flex}
.msg-head{padding:14px 16px;border-bottom:1px solid var(--s100);display:flex;align-items:center;gap:10px;flex-shrink:0}
.msg-body{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:8px;background:var(--s50)}
.msg-bubble{max-width:80%;padding:8px 12px;border-radius:12px;font-size:.8125rem;line-height:1.5}
.msg-bubble.mine{background:var(--blue);color:#fff;align-self:flex-end;border-radius:12px 12px 3px 12px}
.msg-bubble.theirs{background:#fff;color:var(--s900);align-self:flex-start;border:1px solid var(--s200);border-radius:12px 12px 12px 3px}
.msg-sender{font-size:.5625rem;font-weight:700;margin-bottom:3px;opacity:.75}
.msg-time{font-size:.5rem;margin-top:3px;opacity:.6;text-align:right}
.msg-footer{padding:12px 14px;border-top:1px solid var(--s100);display:flex;gap:8px;flex-shrink:0}
.msg-input{flex:1;padding:9px 13px;background:var(--s50);border:1.5px solid var(--s200);border-radius:10px;font-family:inherit;font-size:.875rem;outline:none;resize:none;max-height:80px;transition:border .15s}
.msg-input:focus{border-color:var(--blue);background:#fff}
.msg-send{width:38px;height:38px;border-radius:10px;background:var(--blue);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .15s}
.msg-send:hover{opacity:.88}
.msg-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:399;display:none}
.msg-overlay.open{display:block}
/* Modal */
.modal-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px}
.modal-ov.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:22px;width:100%;max-width:460px;max-height:88vh;overflow-y:auto;box-shadow:0 24px 48px rgba(0,0,0,.18)}
.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.form-label{font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--s700);margin-bottom:5px;display:block}
.form-inp{width:100%;padding:9px 12px;background:var(--s50);border:2px solid transparent;border-radius:9px;font-family:inherit;font-size:.875rem;color:#1a1c1e;outline:none;transition:all .2s}
.form-inp:focus{background:#fff;border-color:var(--blue)}
/* Toast */
.toast{position:fixed;bottom:22px;right:22px;z-index:9999;background:#1e293b;color:#fff;padding:11px 17px;border-radius:11px;font-size:.875rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.2);transform:translateY(70px);opacity:0;transition:all .3s;display:flex;align-items:center;gap:8px}
.toast.show{transform:translateY(0);opacity:1}
.toast.ok{background:#065f46;border-left:4px solid #34d399}
.toast.err{background:#7f1d1d;border-left:4px solid #f87171}
/* Mob */
.cp-mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:199}
.cp-mob-overlay.open{display:block}
@media(max-width:1280px){.content-grid{grid-template-columns:1fr}}
@media(max-width:1100px){.content-grid{grid-template-columns:1fr}}
@media(max-width:1024px){.db-main{margin-left:0}.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.db-hamb{display:flex}}
@media(max-width:768px){.stat-row{grid-template-columns:repeat(2,1fr)}.profile-strip{flex-direction:column;align-items:center;text-align:center}}
@media(max-width:768px){.stat-row{grid-template-columns:repeat(2,1fr)}.profile-strip{flex-direction:column;gap:14px}.msg-panel{width:100%}}
</style>
</head>
<body>
<div class="db-wrap">
<?php
// Inline sidebar (same data as _sidebar.php: $hosp, $hid, $db available)
$_logoPath  = $hosp['logo_path'] ?? '';
$_facName   = $hosp['facility_name'] ?? ($hosp['admin_name'] ?? 'Hospital');
$_initials  = strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', trim($hosp['admin_name']??'H')), 0, 2))));
try {
    $_pending = (int)($db->fetchOne('SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND status="pending"', [':h'=>$hid])['c'] ?? 0);
    $_unread  = (int)($db->fetchOne('SELECT COUNT(*) c FROM hospital_notifications WHERE hospital_id=:h AND is_read=0', [':h'=>$hid])['c'] ?? 0);
} catch(Throwable $_e) { $_pending = $_unread = 0; }
?>
<aside class="sidebar" id="cpSidebar">
  <div class="sb-brand">
    <?php if($_logoPath):?><img src="<?=htmlspecialchars($_logoPath)?>" alt="" style="height:28px;object-fit:contain;margin-bottom:5px;border-radius:5px;display:block">
    <?php else:?><div class="sb-brand-name">Planeazzy</div><?php endif;?>
    <div class="sb-brand-sub">Provider Dashboard</div>
    <div class="sb-facility">
      <div class="sb-fac-icon"><span class="material-symbols-outlined" style="font-size:13px;color:#fff">local_hospital</span></div>
      <div style="min-width:0">
        <div style="font-size:.75rem;font-weight:700;color:var(--blue);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:148px"><?=htmlspecialchars($_facName)?></div>
        <div style="font-size:.5625rem;color:var(--s400)">Provider Admin</div>
      </div>
    </div>
  </div>
  <div style="flex:1;overflow-y:auto;padding:6px 0">
    <div class="sb-section">MAIN</div>
    <?php foreach([
      ['overview',     'dashboard',        'Overview',      0],
      ['appointments', 'calendar_today',   'Appointments',  $_pending],
      ['doctors',      'medical_services', 'Doctors',       0],
      ['services',     'business_center',  'Services',      0],
    ] as [$k,$ic,$lb,$bd]): $isA = ($k==='doctors'); ?>
    <a href="/hospital/onboarding/dashboard.php?tab=<?=$k?>" class="nav-item <?=$isA?'active':''?>">
      <span class="material-symbols-outlined" style="font-size:17px;flex-shrink:0"><?=$ic?></span>
      <span><?=$lb?></span>
      <?php if($bd>0):?><span class="nav-badge"><?=$bd?></span><?php endif;?>
    </a>
    <?php endforeach;?>
    <div class="sb-section" style="margin-top:4px">REPORTS</div>
    <?php foreach([
      ['insurance',      'verified_user',  'Insurance',       0],
      ['analytics',      'analytics',      'Analytics',       0],
      ['notifications',  'notifications',  'Notifications',   $_unread],
      ['settings',       'settings',       'Settings',        0],
    ] as [$k,$ic,$lb,$bd]): ?>
    <a href="/hospital/onboarding/dashboard.php?tab=<?=$k?>" class="nav-item">
      <span class="material-symbols-outlined" style="font-size:17px;flex-shrink:0"><?=$ic?></span>
      <span><?=$lb?></span>
      <?php if($bd>0):?><span class="nav-badge"><?=$bd?></span><?php endif;?>
    </a>
    <?php endforeach;?>
  </div>
  <div class="sb-footer">
    <a href="/hospital/onboarding/logout.php" class="nav-item" style="color:#dc2626">
      <span class="material-symbols-outlined" style="font-size:17px;flex-shrink:0">logout</span>
      <span>Logout</span>
    </a>
  </div>
</aside>

<div class="db-main" id="dbMain">
  <!-- Topbar -->
  <header class="db-topbar">
    <button class="db-hamb" id="mobToggle" onclick="toggleSidebar()"><span class="material-symbols-outlined" style="font-size:22px">menu</span></button>
    <div style="display:flex;align-items:center;gap:8px;flex:1">
      <a href="/hospital/onboarding/dashboard.php?tab=doctors" style="display:inline-flex;align-items:center;gap:5px;font-size:.8125rem;font-weight:600;color:var(--s500);border:1.5px solid var(--s200);padding:6px 12px;border-radius:8px">
        <span class="material-symbols-outlined" style="font-size:16px">arrow_back</span> Doctors
      </a>
      <span style="color:var(--s400)">/</span>
      <span style="font-size:.875rem;font-weight:600;color:var(--s700)">Dr. <?=htmlspecialchars($doc['name'])?></span>
    </div>
    <a href="/hospital/onboarding/edit-doctor.php?id=<?=$did?>" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:var(--blue);color:#fff;border-radius:9px;font-size:.8125rem;font-weight:700">
      <span class="material-symbols-outlined" style="font-size:16px">edit</span> Edit Profile
    </a>
  </header>

  <div class="db-content">

    <!-- Profile hero strip -->
    <div class="profile-strip">
      <div class="profile-avatar-wrap">
        <?php if(!empty($doc['avatar_path']??'')):?>
        <img src="<?=htmlspecialchars($doc['avatar_path'])?>" class="profile-avatar" id="pavatar" alt="">
        <?php else:?>
        <div class="profile-avatar" id="pavatar"><?=htmlspecialchars($initials)?></div>
        <?php endif;?>
        <label class="camera-label" title="Change photo">
          <input type="file" accept="image/*" onchange="uploadAvatar(this)">
          <span class="material-symbols-outlined" style="font-size:12px;color:var(--blue)">photo_camera</span>
        </label>
      </div>
      <div class="profile-meta">
        <div class="profile-name">Dr. <?=htmlspecialchars($doc['name'])?></div>
        <div class="profile-spec"><?=htmlspecialchars($doc['specialty']??'General Practitioner')?><?=$doc['dept_name']?' · <span style="color:var(--teal)">'.htmlspecialchars($doc['dept_name']).'</span>':''?></div>
        <div class="profile-tags">
          <span class="ptag" style="background:<?=$dotColor?>20;color:<?=$dotColor?>">
            <i class="fa-solid fa-circle" style="font-size:7px"></i>
            <?=ucwords(str_replace('-',' ',$doc['status']))?>
          </span>
          <?php if($doc['kmpdc_licence']??''):?><span class="ptag ptag-blue"><i class="fa-solid fa-id-card" style="font-size:10px"></i><?=htmlspecialchars($doc['kmpdc_licence'])?></span><?php endif;?>
          <?php if($doc['years_exp']??0):?><span class="ptag ptag-slate"><?=$doc['years_exp']?> yr<?=$doc['years_exp']>1?'s':''?> exp</span><?php endif;?>
          <?php if($doc['accepts_walkin']??1):?><span class="ptag ptag-green"><i class="fa-solid fa-person-walking" style="font-size:10px"></i>Walk-ins</span><?php endif;?>
          <?php if($doc['accepts_tele']??0):?><span class="ptag ptag-teal"><i class="fa-solid fa-video" style="font-size:10px"></i>Tele-consult</span><?php endif;?>
        </div>
      </div>
      <?php if($doc['consult_fee']??0 > 0):?>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-size:.5625rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--s400);margin-bottom:3px">Consult Fee</div>
        <div style="font-size:1.375rem;font-weight:900;color:var(--blue);letter-spacing:-.04em">KES <?=number_format($doc['consult_fee'],0)?></div>
      </div>
      <?php endif;?>
    </div>

    <!-- Stat row -->
    <div class="stat-row">
      <?php foreach([
        [$totalAppts,    'Total',     'var(--blue)'],
        [$pendingAppts,  'Pending',   '#d97706'],
        [$confirmedAppts,'Confirmed', 'var(--teal)'],
        [$completedAppts,'Completed', 'var(--green)'],
        [$todayAppts,    'Today',     '#7c3aed'],
      ] as [$n,$l,$c]):?>
      <div class="stat-box"><div class="stat-num" style="color:<?=$c?>"><?=$n?></div><div class="stat-lbl"><?=$l?></div></div>
      <?php endforeach;?>
    </div>

    <div class="content-grid">
      <!-- Info sidebar -->
      <div class="info-card">
        <div class="info-card-head">
          <div style="font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:rgba(255,255,255,.7);margin-bottom:4px"><?=htmlspecialchars($hosp['facility_name']??'')?></div>
          <div style="font-size:.875rem;font-weight:700;color:#fff">Doctor Information</div>
        </div>
        <?php if($doc['email']??''):?>
        <div class="info-section">
          <div class="info-sec-lbl">Contact</div>
          <div class="info-row"><i class="fa-solid fa-envelope"></i><div class="info-val"><?=htmlspecialchars($doc['email'])?></div></div>
          <?php if($doc['phone']??''):?><div class="info-row"><i class="fa-solid fa-phone"></i><div class="info-val"><?=htmlspecialchars($doc['phone'])?></div></div><?php endif;?>
        </div>
        <?php endif;?>
        <?php if($doc['education']??''):?>
        <div class="info-section">
          <div class="info-sec-lbl">Education</div>
          <div style="font-size:.8125rem;color:var(--s700)"><?=htmlspecialchars($doc['education'])?></div>
        </div>
        <?php endif;?>
        <?php if($doc['languages']??''):?>
        <div class="info-section">
          <div class="info-sec-lbl">Languages</div>
          <div style="display:flex;flex-wrap:wrap;gap:3px">
            <?php foreach(array_map('trim',explode(',',$doc['languages'])) as $lg):if($lg):?>
            <span style="background:rgba(0,90,180,.07);color:var(--blue);padding:2px 7px;border-radius:6px;font-size:.625rem;font-weight:700"><?=htmlspecialchars($lg)?></span>
            <?php endif;endforeach;?>
          </div>
        </div>
        <?php endif;?>
        <?php if(!empty($avail)):?>
        <div class="info-section">
          <div class="info-sec-lbl">Weekly Schedule</div>
          <div class="avail-grid">
            <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dy):$on=!empty($avail[$dy]);?>
            <div class="avday <?=$on?'on':'off'?>">
              <?=$dy?>
              <?php if($on):?><div style="font-size:.4rem;margin-top:2px"><?=htmlspecialchars($avail[$dy])?></div><?php endif;?>
            </div>
            <?php endforeach;?>
          </div>
        </div>
        <?php endif;?>
        <?php if($doc['bio']??''):?>
        <div class="info-section">
          <div class="info-sec-lbl">Bio</div>
          <div style="font-size:.8rem;color:var(--s600);line-height:1.65"><?=nl2br(htmlspecialchars($doc['bio']))?></div>
        </div>
        <?php endif;?>
        <!-- Chart breakdown -->
        <div class="info-section">
          <div class="info-sec-lbl">Appointment Breakdown</div>
          <?php foreach(['Pending'=>[$pendingAppts,'#d97706'],'Confirmed'=>[$confirmedAppts,'var(--teal)'],'Completed'=>[$completedAppts,'var(--green)'],'Cancelled'=>[count(array_filter($appts,fn($a)=>$a['status']==='cancelled')),'var(--red)']] as $bl=>[$bc,$bcol]):
            $pct=round($bc/max(1,$totalAppts)*100);?>
          <div style="margin-bottom:8px">
            <div style="display:flex;justify-content:space-between;margin-bottom:3px"><span style="font-size:.75rem"><?=$bl?></span><span style="font-size:.6875rem;color:var(--s400)"><?=$bc?></span></div>
            <div style="height:4px;background:var(--s100);border-radius:9999px"><div style="height:100%;width:<?=$pct?>%;background:<?=$bcol?>;border-radius:9999px"></div></div>
          </div>
          <?php endforeach;?>
        </div>
      </div>

      <!-- Appointments table -->
      <div>
        <div class="main-card">
          <div class="card-head">
            <div class="card-title"><i class="fa-solid fa-calendar-check"></i>Patient Appointments<span style="background:var(--blue);color:#fff;font-size:.5rem;padding:1px 7px;border-radius:9999px;font-weight:800;margin-left:4px"><?=$totalAppts?></span></div>
            <div style="display:flex;gap:5px;flex-wrap:wrap">
              <?php foreach([['all','All'],['pending','Pending'],['confirmed','Confirmed'],['completed','Completed']] as [$v,$lb]):?>
              <button class="chip <?=$v==='all'?'active':''?>" onclick="filterAppts('<?=$v?>',this)"><?=$lb?></button>
              <?php endforeach;?>
            </div>
          </div>
          <?php if(empty($appts)):?>
          <div style="text-align:center;padding:48px 24px;color:var(--s400)">
            <i class="fa-regular fa-calendar-xmark" style="font-size:36px;display:block;margin-bottom:10px"></i>
            <h3 style="font-size:.9375rem;font-weight:700;margin-bottom:5px">No appointments yet</h3>
            <p style="font-size:.8125rem">Bookings assigned to Dr. <?=htmlspecialchars($doc['name'])?> will appear here.</p>
          </div>
          <?php else:?>
          <div class="tbl-wrap">
            <table id="apptTable">
              <thead><tr>
                <th>Patient</th><th>Date &amp; Time</th><th>Type</th><th>Notes</th><th>Status</th><th>Actions</th>
              </tr></thead>
              <tbody>
              <?php
              $pillMap=['pending'=>['sp-pending','fa-clock','Pending'],'confirmed'=>['sp-confirmed','fa-circle-check','Confirmed'],'completed'=>['sp-completed','fa-check-double','Completed'],'cancelled'=>['sp-cancelled','fa-circle-xmark','Cancelled'],'pending_checkin'=>['sp-checkin','fa-hourglass-half','Pending Check-in'],'awaiting_confirmation'=>['sp-awaiting','fa-hourglass','Awaiting Confirm.'],'unconfirmed'=>['sp-unconfirmed','fa-triangle-exclamation','Unconfirmed']];
              foreach($appts as $a):
                  $ds=$a['display_status']??$a['status'];
                  [$pc,$pi,$pl]=$pillMap[$ds]??['sp-pending','fa-circle-dot',ucfirst($ds)];
                  $init=strtoupper(substr(preg_replace('/[^A-Za-z ]/','',$a['patient_name']??'?'),0,1).(strpos($a['patient_name']??'?',' ')!==false?substr($a['patient_name'],strrpos($a['patient_name'],' ')+1,1):''));
                  $isGuest=($a['appt_source']??'')==='guest';
                  $realApptId=($a['id']??0)>800000?0:($a['id']??0);
              ?>
              <tr data-status="<?=htmlspecialchars($a['status'])?>">
                <td><div style="display:flex;align-items:center;gap:8px">
                  <div class="tbl-av"><?=htmlspecialchars($init)?></div>
                  <div>
                    <div style="font-weight:600"><?=htmlspecialchars($a['patient_name']??'Guest')?></div>
                    <div style="font-size:.6875rem;color:var(--s400)"><?=htmlspecialchars($a['patient_phone']??'')?></div>
                    <?php if($a['patient_email']??''):?><div style="font-size:.6875rem;color:var(--s400)"><?=htmlspecialchars($a['patient_email'])?></div><?php endif;?>
                    <?php if($isGuest):?><span style="font-size:.5rem;font-weight:800;background:#fef3c7;color:#92400e;padding:1px 5px;border-radius:4px">GUEST</span><?php endif;?>
                  </div>
                </div></td>
                <td style="white-space:nowrap">
                  <div style="font-weight:700;color:var(--blue);font-size:.8125rem"><?=date('M d, Y',strtotime($a['appointment_at']??''))?></div>
                  <div style="font-size:.75rem;color:var(--s500)"><?=date('g:i A',strtotime($a['appointment_at']??''))?></div>
                </td>
                <td style="font-size:.8125rem"><?=ucwords(str_replace('-',' ',$a['visit_type']??'in-person'))?></td>
                <td style="font-size:.75rem;color:var(--s500);max-width:150px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($a['notes']??'—')?></div></td>
                <td><span class="spill <?=$pc?>"><i class="fa-solid <?=$pi?>"></i><?=$pl?></span></td>
                <td>
                  <div style="display:flex;gap:4px;flex-wrap:wrap">
                    <?php if(!$isGuest && $realApptId):?>
                    <?php if(in_array($a['status'],['pending'])):?>
                    <button class="act-btn act-confirm" onclick="actAppt(<?=$realApptId?>,'confirmed')"><i class="fa-solid fa-check"></i>Confirm</button>
                    <button class="act-btn act-cancel" onclick="openCancel(<?=$realApptId?>)"><i class="fa-solid fa-xmark"></i>Cancel</button>
                    <?php elseif($a['status']==='confirmed'):?>
                    <button class="act-btn act-complete" onclick="actAppt(<?=$realApptId?>,'completed')"><i class="fa-solid fa-check-double"></i>Complete</button>
                    <button class="act-btn act-cancel" onclick="openCancel(<?=$realApptId?>)"><i class="fa-solid fa-xmark"></i>Cancel</button>
                    <button class="act-btn" style="background:rgba(217,119,6,.08);color:#d97706" onclick="openReschedule(<?=$realApptId?>)"><i class="fa-solid fa-calendar-day"></i>Reschedule</button>
                    <?php endif;?>
                    <?php if(!$isGuest && $realApptId):?><a href="/messages.php?appt_id=<?=$realApptId?>&appt_type=hospital&back=<?=urlencode('/hospital/onboarding/doctor-profile.php?id='.$did)?>" class="act-btn act-msg"><i class="fa-solid fa-comment"></i>Chat</a><?php endif;?>
                    <?php elseif($isGuest):?>
                    <span style="font-size:.6875rem;color:var(--s400);font-style:italic">Guest booking — contact directly</span>
                    <?php endif;?>
                  </div>
                </td>
              </tr>
              <?php endforeach;?>
              </tbody>
            </table>
          </div>
          <?php endif;?>
        </div>
      </div>
    </div>
  </div>

  <footer style="padding:14px 24px;border-top:1px solid rgba(193,198,213,.15);display:flex;justify-content:space-between;align-items:center;font-size:.6875rem;color:#73777f;background:#fff;flex-wrap:wrap;gap:6px">
    <span>© <?=date('Y')?> Planeazzy · KEPDA Compliant</span>
    <div style="display:flex;gap:14px"><a href="/privacy.php" style="color:#73777f">Privacy</a><a href="/terms.php" style="color:#73777f">Terms</a></div>
  </footer>
</div><!-- /db-main -->
</div><!-- /db-wrap -->

<!-- Messaging Panel -->
<div class="msg-overlay" id="msgOverlay" onclick="closeMsg()"></div>
<div class="msg-panel" id="msgPanel">
  <div class="msg-head">
    <button onclick="closeMsg()" style="background:none;border:none;cursor:pointer;padding:4px"><span class="material-symbols-outlined" style="font-size:20px;color:var(--s500)">arrow_back</span></button>
    <div style="flex:1">
      <div style="font-size:.875rem;font-weight:700" id="msgPatName">Patient</div>
      <div style="font-size:.625rem;color:var(--s400)">Appointment Chat</div>
    </div>
    <div id="msgSpinner" style="display:none"><i class="fa-solid fa-circle-notch fa-spin" style="color:var(--blue)"></i></div>
  </div>
  <div class="msg-body" id="msgBody"><div style="text-align:center;color:var(--s400);font-size:.8125rem;margin:auto">Loading messages…</div></div>
  <div class="msg-footer">
    <textarea class="msg-input" id="msgInput" placeholder="Type a message…" rows="1" onkeydown="msgKeyDown(event)"></textarea>
    <button class="msg-send" onclick="sendMsg()"><span class="material-symbols-outlined" style="font-size:18px">send</span></button>
  </div>
</div>

<!-- Cancel Modal -->
<div class="modal-ov" id="cancelMo" onclick="if(event.target===this)closeMo('cancelMo')">
  <div class="modal">
    <div class="modal-head"><h2 style="font-size:1rem;font-weight:700">Cancel Appointment</h2><button onclick="closeMo('cancelMo')" style="background:none;border:none;cursor:pointer"><span class="material-symbols-outlined">close</span></button></div>
    <input type="hidden" id="cancelApptId">
    <div style="margin-bottom:14px"><label class="form-label">Reason for Cancellation</label><textarea class="form-inp" id="cancelReason" rows="3" placeholder="Optional — patient will be notified"></textarea></div>
    <button class="act-btn act-cancel" style="width:100%;justify-content:center;padding:10px" onclick="submitCancel()"><i class="fa-solid fa-xmark"></i>Confirm Cancellation</button>
  </div>
</div>

<!-- Reschedule Modal -->
<div class="modal-ov" id="reschedMo" onclick="if(event.target===this)closeMo('reschedMo')">
  <div class="modal">
    <div class="modal-head"><h2 style="font-size:1rem;font-weight:700">Reschedule Appointment</h2><button onclick="closeMo('reschedMo')" style="background:none;border:none;cursor:pointer"><span class="material-symbols-outlined">close</span></button></div>
    <input type="hidden" id="reschedApptId">
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:.8125rem;color:#92400e"><i class="fa-solid fa-triangle-exclamation" style="margin-right:6px"></i>Patient will be notified by email and SMS.</div>
    <div style="margin-bottom:12px"><label class="form-label">New Date &amp; Time</label><input class="form-inp" type="datetime-local" id="reschedDt" min="<?=date('Y-m-d\TH:i')?>"></div>
    <div style="margin-bottom:14px"><label class="form-label">Reason (optional)</label><textarea class="form-inp" id="reschedReason" rows="2" placeholder="e.g. Doctor schedule conflict…"></textarea></div>
    <button class="act-btn" style="background:var(--blue);color:#fff;width:100%;justify-content:center;padding:10px" onclick="submitReschedule()"><i class="fa-solid fa-calendar-day"></i>Confirm Reschedule</button>
  </div>
</div>

<div class="cp-mob-overlay" id="mobOverlay" id="mobOverlay" onclick="closeSidebar()"></div>
<div class="toast" id="toast"></div>

<script>
const CSRF   = '<?=htmlspecialchars($csrf)?>';
const HAPI   = '/hospital/onboarding/api.php';
const MSGAPI = '/api/appointment-messages.php';
let _msgApptId = 0, _msgPoll = null;

function toast(msg,type='ok'){
  const el=document.getElementById('toast');
  el.textContent=msg;el.className='toast '+type+' show';
  setTimeout(()=>el.classList.remove('show'),3500);
}
async function hapi(action,data={}){
  try{
    const r=await fetch(HAPI,{
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body:JSON.stringify({action,csrf_token:CSRF,...data})
    });
    if(!r.ok){toast('Server error ('+r.status+')','err');return{ok:false};}
    return await r.json();
  }catch(e){toast('Network error — check connection','err');return{ok:false};}
}

/* Sidebar */
function toggleSidebar(){
  const sb=document.getElementById('cpSidebar'),ov=document.getElementById('mobOverlay');
  const o=sb.classList.toggle('open');ov.classList.toggle('open',o);document.body.style.overflow=o?'hidden':'';
}
function closeSidebar(){
  document.getElementById('cpSidebar')?.classList.remove('open');
  document.getElementById('mobOverlay')?.classList.remove('open');
  document.body.style.overflow='';
}

/* Filter */
function filterAppts(status,btn){
  document.querySelectorAll('.chip').forEach(b=>b.classList.remove('active'));btn.classList.add('active');
  document.querySelectorAll('#apptTable tbody tr').forEach(r=>{
    r.style.display=(status==='all'||r.dataset.status===status)?'':'none';
  });
}

/* Actions */
async function actAppt(id,status){
  const r=await hapi('update_appointment',{appointment_id:id,status});
  if(r.ok){toast('Appointment '+status,'ok');setTimeout(()=>location.reload(),800);}
  else toast(r.msg||'Error','err');
}
function openCancel(id){document.getElementById('cancelApptId').value=id;document.getElementById('cancelReason').value='';openMo('cancelMo');}
async function submitCancel(){
  const id=document.getElementById('cancelApptId').value;
  const rsn=document.getElementById('cancelReason').value;
  const r=await hapi('update_appointment',{appointment_id:parseInt(id),status:'cancelled',cancel_reason:rsn});
  if(r.ok){toast('Appointment cancelled','ok');closeMo('cancelMo');setTimeout(()=>location.reload(),800);}
  else toast(r.msg||'Error','err');
}
function openReschedule(id){document.getElementById('reschedApptId').value=id;document.getElementById('reschedDt').value='';document.getElementById('reschedReason').value='';openMo('reschedMo');}
async function submitReschedule(){
  const id=document.getElementById('reschedApptId').value;
  const dt=document.getElementById('reschedDt').value;
  const rsn=document.getElementById('reschedReason').value;
  if(!dt){toast('Select a new date','err');return;}
  const r=await hapi('reschedule_appointment',{appointment_id:parseInt(id),new_datetime:dt,reason:rsn});
  if(r.ok){toast('Rescheduled. Patient notified.','ok');closeMo('reschedMo');setTimeout(()=>location.reload(),800);}
  else toast(r.msg||'Error','err');
}
function openMo(id){document.getElementById(id)?.classList.add('open');document.body.style.overflow='hidden';}
function closeMo(id){document.getElementById(id)?.classList.remove('open');document.body.style.overflow='';}

/* Avatar */
async function uploadAvatar(input){
  if(!input.files[0])return;
  const fd=new FormData();fd.append('avatar',input.files[0]);fd.append('doctor_id',<?=$did?>);
  toast('Uploading…');
  try{
    const r=await fetch('/api/hospital/upload-doctor-avatar.php',{method:'POST',body:fd});
    const j=await r.json();
    if(j.ok){
      toast('Photo updated','ok');
      const el=document.getElementById('pavatar');
      if(el){const img=document.createElement('img');img.src=j.avatar_path;img.className='profile-avatar';img.id='pavatar';el.replaceWith(img);}
    }else toast(j.msg||'Upload failed','err');
  }catch(e){toast('Upload error','err');}
}

/* Messaging */
function openMsg(apptId,isGuest,patName){
  if(isGuest){toast('Guest bookings — contact patient directly by phone or email.','err');return;}
  if(!apptId){toast('Cannot message unresolved appointment.','err');return;}
  _msgApptId=apptId;
  document.getElementById('msgPatName').textContent=patName;
  document.getElementById('msgPanel').classList.add('open');
  document.getElementById('msgOverlay').classList.add('open');
  document.body.style.overflow='hidden';
  loadMessages();
  _msgPoll=setInterval(loadMessages,8000);
}
function closeMsg(){
  document.getElementById('msgPanel').classList.remove('open');
  document.getElementById('msgOverlay').classList.remove('open');
  document.body.style.overflow='';
  clearInterval(_msgPoll);_msgPoll=null;
}
async function loadMessages(){
  if(!_msgApptId)return;
  try{
    const r=await fetch(MSGAPI+'?appt_id='+_msgApptId+'&appt_type=hospital');
    const j=await r.json();
    if(j.ok) renderMessages(j.messages);
  }catch(e){}
}
function renderMessages(msgs){
  const body=document.getElementById('msgBody');
  if(!msgs||!msgs.length){body.innerHTML='<div style="text-align:center;color:var(--s400);font-size:.8125rem;margin:auto;padding:20px">No messages yet.<br>Start the conversation below.</div>';return;}
  const atBottom=body.scrollHeight-body.scrollTop-body.clientHeight<60;
  body.innerHTML=msgs.map(m=>{
    const mine=m.sender_type==='hospital';
    const time=new Date(m.created_at).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
    return `<div style="display:flex;flex-direction:column;align-items:${mine?'flex-end':'flex-start'}">
      <div class="msg-bubble ${mine?'mine':'theirs'}">
        <div class="msg-sender">${mine?'You':escHtml(m.sender_name)}</div>
        ${escHtml(m.message)}
        <div class="msg-time">${time}</div>
      </div>
    </div>`;
  }).join('');
  if(atBottom||msgs.length<=3) body.scrollTop=body.scrollHeight;
}
function escHtml(t){return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
async function sendMsg(){
  const inp=document.getElementById('msgInput');
  const msg=inp.value.trim();
  if(!msg||!_msgApptId)return;
  inp.value='';inp.style.height='auto';
  try{
    const r=await fetch(MSGAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({appt_id:_msgApptId,appt_type:'hospital',message:msg})});
    const j=await r.json();
    if(j.ok) loadMessages();
    else toast(j.msg||'Send failed','err');
  }catch(e){toast('Send error','err');}
}
function msgKeyDown(e){
  if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMsg();}
  // auto-resize
  e.target.style.height='auto';e.target.style.height=Math.min(e.target.scrollHeight,80)+'px';
}
</script>
</body>
</html>
