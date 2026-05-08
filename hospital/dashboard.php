<?php
/**
 * Clinical Precision — Hospital Dashboard v3
 * Fixes: billing removed, hover fix, profile pics, rich doctor management
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();

if (empty($_SESSION['hospital_id']))   { header('Location: /hospital/onboarding/login.php');   exit; }
if (empty($_SESSION['hospital_auth'])) { header('Location: /hospital/onboarding/pending.php'); exit; }

$hid  = (int)$_SESSION['hospital_id'];
$db   = Database::getInstance();
$hosp = $db->fetchOne('SELECT * FROM hospital_providers WHERE id=:id', [':id' => $hid]);

if (!$hosp || $hosp['status'] !== 'approved' || !$hosp['is_active']) {
    $_SESSION['hospital_auth'] = false; header('Location: /hospital/onboarding/pending.php'); exit;
}

$tab  = in_array($_GET['tab'] ?? '', ['overview','appointments','doctors','services','insurance','analytics','notifications','settings'])
      ? ($_GET['tab'] ?? 'overview') : 'overview';
$csrf = Security::csrfToken();

/*  DB queries  */
$depts   = $db->fetchAll('SELECT * FROM hospital_departments WHERE hospital_id=:h ORDER BY sort_order,name', [':h'=>$hid]);
$doctors = $db->fetchAll(
    'SELECT d.*,dep.name dept_name FROM hospital_doctors d
     LEFT JOIN hospital_departments dep ON dep.id=d.department_id
     WHERE d.hospital_id=:h AND d.is_active=1 ORDER BY d.name', [':h'=>$hid]
);
$notifs  = $db->fetchAll('SELECT * FROM hospital_notifications WHERE hospital_id=:h ORDER BY created_at DESC LIMIT 60', [':h'=>$hid]);
$unread  = count(array_filter($notifs, fn($n)=>!$n['is_read']));
$insurers= $db->fetchAll('SELECT * FROM hospital_insurance WHERE hospital_id=:h', [':h'=>$hid]);
$insMap  = []; foreach ($insurers as $ins) $insMap[$ins['provider_key']] = $ins;

/*  Stats  */
$todayCount   = $db->fetchOne('SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND DATE(appointment_at)=CURDATE()', [':h'=>$hid])['c'] ?? 0;
$pendingCount = $db->fetchOne('SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND status="pending"', [':h'=>$hid])['c'] ?? 0;
$patientCount = $db->fetchOne('SELECT COUNT(DISTINCT LOWER(TRIM(patient_email))) c FROM hospital_appointments WHERE hospital_id=:h AND patient_email IS NOT NULL AND patient_email != ""', [':h'=>$hid])['c'] ?? 0;
$docsOn       = $db->fetchOne('SELECT COUNT(*) c FROM hospital_doctors WHERE hospital_id=:h AND status="on-duty" AND is_active=1', [':h'=>$hid])['c'] ?? 0;
$apptRows     = $db->fetchAll('SELECT * FROM hospital_appointments WHERE hospital_id=:h ORDER BY appointment_at DESC LIMIT 100', [':h'=>$hid]);

$services     = json_decode($hosp['services'] ?? '[]', true) ?? [];
$facilityName = $hosp['facility_name'] ?? ($hosp['admin_name'] ?? 'Hospital');
$adminName    = $hosp['admin_name'] ?? 'Admin';
$logoPath     = $hosp['logo_path'] ?? '';
$initials     = strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', trim($adminName)), 0, 2))));
$hour         = (int)date('G');
$greetEn      = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

/*  Insurance definitions  */
$insurerDefs = [
  ['nhif','NHIF','National Hospital Insurance Fund','account_balance','#16a34a'],
  ['jubilee','Jubilee','Jubilee Health Insurance','health_and_safety','#005ab4'],
  ['aon','Aon Minet','Aon Minet Healthcare','business_center','#d97706'],
  ['axa','AXA Mansard','AXA Mansard Insurance','policy','#7c3aed'],
  ['aar','AAR','AAR Healthcare','medical_services','#475569'],
  ['cic','CIC','CIC Insurance Group','groups','#0d9488'],
];

/*  Service definitions  */
$serviceDefs = [
  ['general_practice','General Practice','stethoscope'],
  ['pediatrics','Pediatrics','child_care'],
  ['radiology','Radiology','radiology'],
  ['cardiology','Cardiology','cardiology'],
  ['maternity','Maternity','pregnant_woman'],
  ['laboratory','Laboratory','biotech'],
  ['surgery','Surgery','surgical'],
  ['oncology','Oncology','oncology'],
  ['emergency','Emergency & Critical Care','emergency'],
  ['pharmacy','Pharmacy','local_pharmacy'],
  ['physiotherapy','Physiotherapy','accessibility_new'],
  ['nutrition','Nutrition & Dietetics','nutrition'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title><?= htmlspecialchars($facilityName) ?> — Planeazzy Provider</title>
  <link rel="icon" type="image/png" href="/assets/images/favicon.png">
  <link rel="apple-touch-icon" href="/assets/images/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/clinical.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<style>
:root{--cp-primary:#005ab4;--cp-secondary:#006a6a;--cp-error:#ba1a1a;--cp-on-surface:#1a1c1e;--cp-on-surface-var:#42474e;--cp-outline:#73777f;--cp-outline-var:#c1c6d5;--cp-surface-container-lowest:#fff;--cp-surface-container-low:#f3f4f6;--cp-surface-container-high:#e5e7eb;--cp-surface-container-highest:#d1d5db;--cp-r:10px;--cp-r-xl:16px;--cp-shadow-sm:0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.04);--cp-sb-w:220px}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:var(--cp-on-surface);line-height:1.5;min-height:100vh}
a{color:inherit;text-decoration:none}
/*  No underlines on hover globally  */
a:hover{text-decoration:none}
/*  Layout  */
.db-wrap{display:flex;min-height:100vh}
.db-main{margin-left:var(--cp-sb-w);flex:1;display:flex;flex-direction:column;min-width:0}
.db-content{padding:24px 28px;flex:1}
/*  Sidebar  */
.cp-sidebar{position:fixed;left:0;top:0;bottom:0;width:var(--cp-sb-w);background:#fff;border-right:1px solid rgba(193,198,213,.2);display:flex;flex-direction:column;z-index:200;overflow:hidden;transition:transform .25s}
.cp-sidebar-brand{padding:16px 16px 12px;border-bottom:1px solid rgba(193,198,213,.15)}
.cp-sidebar-brand-name{font-size:.875rem;font-weight:900;letter-spacing:-.03em;color:var(--cp-primary)}
.cp-sidebar-brand-sub{font-size:.5625rem;text-transform:uppercase;letter-spacing:.1em;color:var(--cp-outline);margin-bottom:10px}
.cp-sidebar-facility{display:flex;align-items:center;gap:8px}
.cp-facility-icon{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--cp-primary),#0873df);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cp-sidebar-section-label{font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:var(--cp-outline);padding:12px 14px 4px;opacity:.7}
.cp-nav-item{display:flex;align-items:center;gap:9px;padding:9px 14px;border-radius:var(--cp-r);margin:1px 8px;font-size:.8rem;font-weight:500;color:var(--cp-on-surface-var);cursor:pointer;transition:all .15s;border:none;background:none;width:calc(100% - 16px);text-align:left;text-decoration:none}
.cp-nav-item:hover{background:var(--cp-surface-container-low);color:var(--cp-on-surface);text-decoration:none}
.cp-nav-item.active{background:rgba(0,90,180,.08);color:var(--cp-primary);font-weight:700}
.cp-nav-item.active .material-symbols-outlined{color:var(--cp-primary)}
.cp-nav-badge{margin-left:auto;background:var(--cp-primary);color:#fff;font-size:.5rem;font-weight:800;padding:1px 5px;border-radius:9999px;min-width:16px;text-align:center}
.cp-sidebar-footer{padding:8px;border-top:1px solid rgba(193,198,213,.15)}
/*  Topbar  */
.cp-dash-topbar{position:sticky;top:0;z-index:100;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);border-bottom:1px solid rgba(193,198,213,.2);padding:10px 24px;display:flex;align-items:center;gap:12px}
.cp-topbar-search{display:flex;align-items:center;gap:8px;background:var(--cp-surface-container-low);border-radius:9999px;padding:6px 14px;flex:1;max-width:380px}
.cp-topbar-search input{border:none;background:none;outline:none;font-size:.875rem;font-family:inherit;color:var(--cp-on-surface);width:100%}
.cp-icon-btn{position:relative;width:36px;height:36px;border:none;background:none;cursor:pointer;border-radius:var(--cp-r);display:flex;align-items:center;justify-content:center;color:var(--cp-on-surface-var);transition:background .15s;text-decoration:none}
.cp-icon-btn:hover{background:var(--cp-surface-container-low);text-decoration:none}
.notif-dot{position:absolute;top:5px;right:5px;width:8px;height:8px;border-radius:50%;background:#ef4444;border:1.5px solid #fff}
.db-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#005ab4,#0873df);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff;flex-shrink:0;cursor:pointer;overflow:hidden}
.db-avatar img{width:100%;height:100%;object-fit:cover}
.db-hamb{display:none;align-items:center;justify-content:center;width:36px;height:36px;border:none;background:none;cursor:pointer;border-radius:8px;color:var(--cp-on-surface-var)}
/*  Stats grid  */
.cp-stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px}
.cp-stat-card{background:#fff;border-radius:var(--cp-r-xl);padding:16px 18px;border:1px solid rgba(193,198,213,.15);box-shadow:var(--cp-shadow-sm);display:flex;flex-direction:column;gap:8px}
.cp-stat-icon-wrap{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:2px}
.cp-stat-label{font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--cp-outline)}
.cp-stat-value{font-size:1.625rem;font-weight:900;letter-spacing:-.04em;color:var(--cp-on-surface)}
/*  Panels / cards  */
.db-panel{background:#fff;border-radius:var(--cp-r-xl);padding:20px;border:1px solid rgba(193,198,213,.15);box-shadow:var(--cp-shadow-sm)}
.db-bento-main{display:grid;grid-template-columns:4fr 8fr;gap:16px;margin-bottom:16px}
.db-bento-bottom{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
/*  Table  */
.cp-table-wrap{overflow-x:auto}
.cp-table{width:100%;border-collapse:collapse;font-size:.8125rem}
.cp-table th{background:var(--cp-surface-container-low);padding:10px 14px;text-align:left;font-size:.625rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--cp-outline);white-space:nowrap}
.cp-table td{padding:11px 14px;border-bottom:1px solid rgba(193,198,213,.12);vertical-align:middle}
.cp-table tbody tr:hover{background:var(--cp-surface-container-low)}
.cp-table tbody tr:last-child td{border-bottom:none}
/*  Booking row  */
.db-booking-row{display:flex;gap:10px;padding:10px;border-radius:var(--cp-r);cursor:pointer;transition:background .15s}
.db-booking-row:hover{background:var(--cp-surface-container-low)}
.cp-booking-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#1978e5,#1251a3);display:flex;align-items:center;justify-content:center;font-size:.625rem;font-weight:800;color:#fff;flex-shrink:0}
/*  Status pills  */
.cp-status-pill{display:inline-block;padding:2px 9px;border-radius:9999px;font-size:.5625rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.cp-status-pending{background:#fef9c3;color:#854d0e}
.cp-status-confirmed{background:#dcfce7;color:#166534}
.cp-status-completed,.cp-status-done{background:#dbeafe;color:#1e40af}
.cp-status-cancelled{background:#fee2e2;color:#991b1b}
/*  Badges  */
.cp-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:9999px;font-size:.5625rem;font-weight:700}
.cp-badge-primary{background:rgba(0,90,180,.1);color:var(--cp-primary)}
.cp-badge-secondary{background:rgba(0,106,106,.1);color:var(--cp-secondary)}
.cp-badge-success{background:#dcfce7;color:#166534}
.cp-badge-warning{background:#fef9c3;color:#92400e}
/*  Doctor cards  */
.cp-doc-card{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--cp-surface-container-low);border-radius:var(--cp-r);border:1px solid rgba(193,198,213,.15)}
.cp-doc-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#0873df,#005ab4);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden}
.cp-doc-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.cp-status-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.dot-on{background:#22c55e}.dot-break{background:#f59e0b}.dot-off{background:#94a3b8}.dot-susp{background:#ef4444}
/*  Dept cards  */
.cp-dept-card{background:var(--cp-surface-container-low);border-radius:var(--cp-r);padding:12px;border:1px solid rgba(193,198,213,.15)}
.cp-dept-icon{width:36px;height:36px;border-radius:8px;background:rgba(0,106,106,.1);display:flex;align-items:center;justify-content:center;color:var(--cp-secondary);flex-shrink:0}
/*  Insurance card  */
.cp-ins-card{background:#fff;border-radius:var(--cp-r-xl);padding:16px;border:1px solid rgba(193,198,213,.15);box-shadow:var(--cp-shadow-sm);display:flex;gap:12px}
.cp-ins-icon-wrap{width:44px;height:44px;border-radius:10px;background:var(--cp-surface-container-low);display:flex;align-items:center;justify-content:center;flex-shrink:0}
/*  Chart  */
.cp-chart-wrap{display:flex;gap:4px;align-items:flex-end;height:120px;position:relative;padding-bottom:24px}
.cp-chart-grid{position:absolute;inset:0;bottom:24px;display:flex;flex-direction:column;justify-content:space-between;pointer-events:none}
.cp-chart-grid-line{border-top:1px dashed rgba(193,198,213,.3);width:100%}
.cp-bar-col{display:flex;flex-direction:column;align-items:center;gap:4px;flex:1;height:100%;justify-content:flex-end}
.cp-bar{width:100%;border-radius:4px 4px 0 0;background:rgba(0,90,180,.25);transition:height .4s ease;cursor:pointer;position:relative;z-index:1}
.cp-bar:hover,.cp-bar.active{background:var(--cp-primary)}
.cp-bar-label{font-size:.5rem;color:var(--cp-outline);font-weight:600;white-space:nowrap;position:absolute;bottom:4px}
/*  Track / fill  */
.cp-bed-track{height:5px;background:var(--cp-surface-container-high);border-radius:9999px;overflow:hidden}
.cp-bed-fill{height:100%;background:var(--cp-primary);border-radius:9999px}
/*  Form  */
.cp-form-label{font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--cp-on-surface-var);margin-bottom:5px;display:block}
.cp-form-input{width:100%;padding:10px 13px;background:var(--cp-surface-container-low);border:2px solid transparent;border-radius:var(--cp-r);font-family:inherit;font-size:.875rem;color:var(--cp-on-surface);outline:none;transition:all .2s}
.cp-form-input:focus{background:#fff;border-color:var(--cp-primary);box-shadow:0 0 0 4px rgba(0,90,180,.08)}
.cp-form-group{margin-bottom:14px}
/*  Buttons  */
.cp-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--cp-r);border:none;font-family:inherit;font-size:.8125rem;font-weight:700;cursor:pointer;transition:all .15s}
.cp-btn:hover{opacity:.88}
.cp-btn-primary{background:var(--cp-primary);color:#fff}
.cp-btn-ghost{background:var(--cp-surface-container-low);color:var(--cp-on-surface);border:1.5px solid var(--cp-outline-var)}
.cp-btn-sm{padding:6px 12px;font-size:.75rem}
.cp-btn-full{width:100%;justify-content:center}
/*  Tabs  */
.cp-tab-row{display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap}
.cp-tab-btn{padding:6px 14px;border-radius:9999px;border:1.5px solid var(--cp-outline-var);background:transparent;font-family:inherit;font-size:.75rem;font-weight:600;color:var(--cp-on-surface-var);cursor:pointer;transition:all .15s}
.cp-tab-btn:hover{background:var(--cp-surface-container-low);border-color:var(--cp-primary)}
.cp-tab-btn.active{background:var(--cp-primary);color:#fff;border-color:var(--cp-primary)}
/*  Modal  */
.cp-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px}
.cp-modal-overlay.open{display:flex}
.cp-modal{background:#fff;border-radius:18px;padding:24px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 48px rgba(0,0,0,.18)}
.cp-modal-lg{max-width:700px}
.cp-modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
/*  Upload zone  */
.upload-zone{border:2px dashed var(--cp-outline-var);border-radius:var(--cp-r-xl);padding:24px;text-align:center;cursor:pointer;transition:all .2s;position:relative;overflow:hidden}
.upload-zone:hover,.upload-zone.drag{border-color:var(--cp-primary);background:rgba(0,90,180,.03)}
.upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
/*  Avatar upload  */
.avatar-upload-wrap{position:relative;width:80px;height:80px;margin:0 auto 12px}
.avatar-upload-wrap img,.avatar-upload-wrap .avatar-placeholder{width:80px;height:80px;border-radius:50%;object-fit:cover}
.avatar-placeholder{background:linear-gradient(135deg,#0873df,#005ab4);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;color:#fff}
.avatar-upload-btn{position:absolute;bottom:0;right:0;width:26px;height:26px;border-radius:50%;background:var(--cp-primary);border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff}
/*  Services grid  */
.svc-grid-dash{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}
/*  Toast  */
.toast{position:fixed;bottom:24px;right:24px;z-index:9999;background:#1e293b;color:#fff;padding:12px 18px;border-radius:12px;font-size:.875rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.2);transform:translateY(80px);opacity:0;transition:all .3s;max-width:320px;display:flex;align-items:center;gap:8px}
.toast.show{transform:translateY(0);opacity:1}
.toast.ok{background:#065f46;border-left:4px solid #34d399}
.toast.err{background:#7f1d1d;border-left:4px solid #f87171}
/*  Ins grid  */
.ins-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
/*  Mobile  */
.cp-mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:199}
.cp-mob-overlay.open{display:block}
.cp-mob-toggle{display:none;position:fixed;bottom:24px;right:24px;z-index:300;width:50px;height:50px;border-radius:50%;background:var(--cp-primary);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 16px rgba(0,90,180,.35);align-items:center;justify-content:center}
/*  Footer  */
.cp-footer{padding:16px 28px;border-top:1px solid rgba(193,198,213,.15);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;font-size:.6875rem;color:var(--cp-outline);background:#fff}
.cp-footer-links{display:flex;gap:14px}
.cp-footer-links a{color:var(--cp-outline);font-size:.6875rem;text-decoration:none}
.cp-footer-links a:hover{color:var(--cp-primary);text-decoration:none}
/*  Notification tab  */
.notif-item{display:flex;gap:12px;padding:12px 14px;border-radius:var(--cp-r);border:1.5px solid rgba(193,198,213,.2);background:#fff;transition:all .2s;margin-bottom:8px}
.notif-item.unread{border-color:rgba(0,90,180,.18);background:#f0f7ff}
.notif-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
/*  Page header row  */
.phd{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px}
.ph-title{font-size:1.125rem;font-weight:800;letter-spacing:-.03em}
.ph-sub{font-size:.8125rem;color:var(--cp-on-surface-var);margin-top:2px}
/*  Responsive  */
@media(max-width:1024px){.db-main{margin-left:0}.cp-sidebar{transform:translateX(-100%)}.cp-sidebar.open{transform:translateX(0)}.db-hamb{display:flex}.db-bento-main{grid-template-columns:1fr}.cp-mob-toggle{display:flex}}
@media(max-width:768px){.db-bento-bottom{grid-template-columns:1fr}.cp-stat-grid{grid-template-columns:1fr 1fr}.ins-grid{grid-template-columns:1fr}.db-content{padding:14px 16px}}
@media(max-width:480px){.cp-stat-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="db-wrap">

<!--  SIDEBAR  -->
<aside class="cp-sidebar" id="cpSidebar">
  <div class="cp-sidebar-brand">
    <?php if($logoPath):?>
    <img src="<?=htmlspecialchars($logoPath)?>" alt="<?=htmlspecialchars($facilityName)?>" style="height:32px;object-fit:contain;margin-bottom:6px;border-radius:6px">
    <?php else:?>
    <div class="cp-sidebar-brand-name">Planeazzy</div>
    <?php endif;?>
    <div class="cp-sidebar-brand-sub">Provider Dashboard</div>
    <div class="cp-sidebar-facility">
      <div class="cp-facility-icon"><i class="fa-solid fa-hospital" style="font-size:13px;color:#fff"></i></div>
      <div>
        <div style="font-size:.8rem;font-weight:700;color:var(--cp-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px"><?=htmlspecialchars($facilityName)?></div>
        <div style="font-size:.6rem;color:var(--cp-outline)">Provider Admin</div>
      </div>
    </div>
  </div>
  <div style="padding:8px 0;flex:1;overflow-y:auto">
    <div class="cp-sidebar-section-label">MAIN</div>
    <?php foreach([
      ['overview','fa-gauge','Overview',0],
      ['appointments','fa-calendar-check','Appointments',$pendingCount],
      ['doctors','fa-user-doctor','Doctors',0],
      ['services','fa-briefcase-medical','Services',0],
    ] as [$k,$ic,$lb,$bd]):$a=$tab===$k;?>
    <a href="?tab=<?=$k?>" class="cp-nav-item <?=$a?'active':''?>">
      <i class="fa-solid <?=$ic?>" style="font-size:14px;width:16px;text-align:center"></i>
      <span><?=$lb?></span>
      <?php if($bd>0):?><span class="cp-nav-badge"><?=$bd?></span><?php endif;?>
    </a>
    <?php endforeach;?>
    <div class="cp-sidebar-section-label" style="margin-top:4px">REPORTS</div>
    <?php foreach([
      ['insurance','fa-shield-halved','Insurance',0],
      ['analytics','fa-chart-line','Analytics',0],
      ['notifications','fa-bell','Notifications',$unread],
    ] as [$k,$ic,$lb,$bd]):$a=$tab===$k;?>
    <a href="?tab=<?=$k?>" class="cp-nav-item <?=$a?'active':''?>">
      <i class="fa-solid <?=$ic?>" style="font-size:14px;width:16px;text-align:center"></i>
      <span><?=$lb?></span>
      <?php if($bd>0):?><span class="cp-nav-badge"><?=$bd?></span><?php endif;?>
    </a>
    <?php endforeach;?>
    <div class="cp-sidebar-section-label" style="margin-top:4px">SYSTEM</div>
    <a href="?tab=settings" class="cp-nav-item <?=$tab==='settings'?'active':''?>">
      <i class="fa-solid fa-gear" style="font-size:14px;width:16px;text-align:center"></i>
      <span>Settings</span>
    </a>
  </div>
  <div class="cp-sidebar-footer">
    <a href="mailto:support@planeazzy.co.ke" class="cp-nav-item">
      <i class="fa-solid fa-headset" style="font-size:14px;width:16px;text-align:center"></i>
      <span>Support</span>
    </a>
    <a href="/hospital/onboarding/logout.php" class="cp-nav-item" style="color:var(--cp-error)">
      <i class="fa-solid fa-right-from-bracket" style="font-size:14px;width:16px;text-align:center"></i>
      <span>Logout</span>
    </a>
  </div>
</aside>

<!--  MAIN  -->
<div class="db-main" id="dbMain">

  <!-- TOPBAR -->
  <header class="cp-dash-topbar">
    <div style="display:flex;align-items:center;gap:10px;flex:1">
      <button class="db-hamb" id="mobToggle"><i class="fa-solid fa-circle-info"></i></button>
      <div class="cp-topbar-search">
        <i class="fa-solid fa-circle-info"></i>
        <input type="text" placeholder="Search patients, doctors…">
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:6px">
      <button class="cp-icon-btn" onclick="openModal('notifModal')">
        <i class="fa-solid fa-circle-info"></i>
        <?php if($unread>0):?><div class="notif-dot"></div><?php endif;?>
      </button>
      <a href="?tab=settings" class="cp-icon-btn"><i class="fa-solid fa-circle-info"></i></a>
      <div style="width:1px;height:22px;background:rgba(193,198,213,.3);margin:0 2px"></div>
      <a href="?tab=settings" class="db-avatar" title="Profile">
        <?php if($logoPath):?>
        <img src="<?=htmlspecialchars($logoPath)?>" alt="logo">
        <?php else:?><?=htmlspecialchars($initials)?><?php endif;?>
      </a>
    </div>
  </header>

  <!-- CONTENT -->
  <div class="db-content">

  <!-- Page heading -->
  <div style="margin-bottom:20px">
    <h2 style="font-size:1.375rem;font-weight:800;letter-spacing:-.03em;color:var(--cp-on-surface);margin-bottom:5px">
      <?=htmlspecialchars("$greetEn, $facilityName")?>
    </h2>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <span style="display:inline-flex;align-items:center;gap:4px;background:rgba(0,106,106,.08);padding:3px 10px;border-radius:9999px;font-size:.6rem;font-weight:700;color:var(--cp-secondary);text-transform:uppercase;letter-spacing:.06em">
        <i class="fa-solid fa-circle-info"></i>KEPDA Verified
      </span>
      <span style="font-size:.75rem;color:var(--cp-on-surface-var);font-style:italic"><?=htmlspecialchars(ucfirst($hosp['facility_type']??'hospital'))?> · <?=htmlspecialchars($hosp['county']??'')?></span>
    </div>
  </div>

<?php /*  OVERVIEW  */ if($tab==='overview'): ?>

  <div class="cp-stat-grid">
    <?php foreach([
      ["Today's","Appointments",$todayCount,'calendar_today','var(--cp-primary)','rgba(0,90,180,.08)'],
      ['Pending','Bookings',$pendingCount,'pending_actions','#d97706','rgba(217,119,6,.08)'],
      ['Total','Patients',number_format($patientCount),'people','var(--cp-secondary)','rgba(0,106,106,.08)'],
      ['Doctors','On Duty',$docsOn.' / '.count($doctors),'medical_services','#7c3aed','rgba(124,58,237,.08)'],
    ] as [$l1,$l2,$val,$ic,$col,$bg]):?>
    <div class="cp-stat-card">
      <div class="cp-stat-icon-wrap" style="background:<?=$bg?>"><span class="material-symbols-outlined" style="font-size:18px;color:<?=$col?>"><?=$ic?></span></div>
      <div><div class="cp-stat-label"><?=$l1?></div><div style="font-size:.5rem;font-weight:600;color:var(--cp-outline);text-transform:uppercase;letter-spacing:.06em"><?=$l2?></div></div>
      <div class="cp-stat-value"><?=$val?></div>
    </div>
    <?php endforeach;?>
  </div>

  <div class="db-bento-main">
    <div style="display:flex;flex-direction:column;gap:14px">
      <!-- Cert panel -->
      <div class="db-panel">
        <div style="font-size:.5625rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--cp-outline);margin-bottom:12px">Certification Status</div>
        <?php foreach([['security','KDPA','Data Protection Active'],['gavel','KMPDC','Licensed Facility'],['health_and_safety','KEPDA','Data Authority Active']] as [$ic,$en,$sub]):?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--cp-surface-container-low);border-radius:var(--cp-r);margin-bottom:7px">
          <div style="display:flex;align-items:center;gap:9px">
            <div style="width:30px;height:30px;border-radius:50%;background:rgba(0,90,180,.08);display:flex;align-items:center;justify-content:center"><span class="material-symbols-outlined" style="font-size:14px;color:var(--cp-primary)"><?=$ic?></span></div>
            <div><div style="font-size:.8125rem;font-weight:700"><?=$en?></div><div style="font-size:.6875rem;color:var(--cp-on-surface-var)"><?=$sub?></div></div>
          </div>
          <i class="fa-solid fa-circle-info"></i>
        </div>
        <?php endforeach;?>
      </div>
      <!-- Pending bookings -->
      <div class="db-panel" style="flex:1">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <div style="font-size:.875rem;font-weight:700">Pending Bookings</div>
          <?php if($pendingCount>0):?><span style="background:var(--cp-primary);color:#fff;font-size:.5rem;padding:2px 7px;border-radius:9999px;font-weight:800"><?=$pendingCount?> NEW</span><?php endif;?>
        </div>
        <?php $pending=array_slice(array_filter($apptRows,fn($a)=>$a['status']==='pending'),0,4);
        if(empty($pending)):?>
        <div style="text-align:center;padding:24px;color:var(--cp-outline)">
          <i class="fa-solid fa-circle-info"></i>
          <span style="font-size:.8125rem">No pending bookings</span>
        </div>
        <?php else: foreach($pending as $a):
          $init=strtoupper(substr(preg_replace('/[^A-Za-z ]/','',$a['patient_name']),0,1).(strpos($a['patient_name'],' ')!==false?substr($a['patient_name'],strrpos($a['patient_name'],' ')+1,1):''));
        ?>
        <div class="db-booking-row" onclick="location.href='?tab=appointments'">
          <div class="cp-booking-avatar"><?=htmlspecialchars($init)?></div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;justify-content:space-between;margin-bottom:2px">
              <span style="font-size:.8125rem;font-weight:700"><?=htmlspecialchars($a['patient_name'])?></span>
              <span style="font-size:.6875rem;color:var(--cp-outline)"><?=date('M d, g:i A',strtotime($a['appointment_at']))?></span>
            </div>
            <div style="font-size:.75rem;color:var(--cp-on-surface-var)"><?=htmlspecialchars($a['visit_type']??'in-person')?> · <?=htmlspecialchars($a['department']??'General')?></div>
          </div>
        </div>
        <?php endforeach;endif;?>
        <a href="?tab=appointments" style="display:block;text-align:center;font-size:.75rem;font-weight:700;color:var(--cp-primary);padding:10px;margin-top:6px;border-top:1px solid rgba(193,198,213,.12)">View All Appointments →</a>
      </div>
    </div>

    <!-- Chart -->
    <div class="db-panel">
      <div style="margin-bottom:18px">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:2px">Appointment Volume</h3>
        <p style="font-size:.75rem;color:var(--cp-on-surface-var)">Monthly activity — last 12 months</p>
      </div>
      <?php
      $chartData=[];
      for($m=11;$m>=0;$m--){
        $lbl=date('M',strtotime("-$m months"));
        $cnt=$db->fetchOne('SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND DATE_FORMAT(appointment_at,\'%Y-%m\')=DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL :m MONTH),\'%Y-%m\')',[':h'=>$hid,':m'=>$m])['c']??0;
        $chartData[]=['label'=>$lbl,'val'=>(int)$cnt];
      }
      $chartMax=max(1,max(array_column($chartData,'val')));
      ?>
      <div class="cp-chart-wrap">
        <div class="cp-chart-grid"><?php for($i=0;$i<5;$i++) echo '<div class="cp-chart-grid-line"></div>';?></div>
        <?php foreach($chartData as $i=>$cd):$pct=round($cd['val']/$chartMax*100);$active=$i===11;?>
        <div class="cp-bar-col">
          <div class="cp-bar <?=$active?'active':''?>" style="height:<?=max($pct,2)?>%" title="<?=$cd['val']?> appointments"></div>
          <div class="cp-bar-label"><?=$cd['label']?></div>
        </div>
        <?php endforeach;?>
      </div>
      <div style="display:flex;gap:24px;margin-top:16px;padding-top:14px;border-top:1px solid rgba(193,198,213,.12);flex-wrap:wrap">
        <?php foreach([['Patients','var(--cp-on-surface)',number_format($patientCount)],['Total Appts','var(--cp-secondary)',count($apptRows)],['Doctors','var(--cp-primary)',count($doctors)]] as [$lbl,$col,$val]):?>
        <div>
          <div style="font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:var(--cp-outline);margin-bottom:3px"><?=$lbl?></div>
          <div style="font-size:1.25rem;font-weight:800;color:<?=$col?>;letter-spacing:-.04em"><?=$val?></div>
        </div>
        <?php endforeach;?>
      </div>
    </div>
  </div>

  <!-- Doctors on duty strip -->
  <div class="db-panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div style="font-size:.875rem;font-weight:700">Doctors on Duty</div>
      <a href="?tab=doctors" style="font-size:.75rem;font-weight:700;color:var(--cp-primary)">Manage All →</a>
    </div>
    <?php if(empty($doctors)):?>
    <div style="text-align:center;padding:24px;color:var(--cp-outline)"><i class="fa-solid fa-circle-info"></i><span style="font-size:.8125rem">No doctors added yet. <a href="?tab=doctors" style="color:var(--cp-primary)">Add doctors →</a></span></div>
    <?php else:?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px">
    <?php foreach(array_slice($doctors,0,6) as $doc):
      $init=strtoupper(substr($doc['name'],0,1).(strpos($doc['name'],' ')!==false?substr($doc['name'],strrpos($doc['name'],' ')+1,1):''));
      $dotCls=$doc['status']==='on-duty'?'dot-on':($doc['status']==='on-break'?'dot-break':($doc['status']==='suspended'?'dot-susp':'dot-off'));
    ?>
    <div class="cp-doc-card">
      <div class="cp-doc-avatar">
        <?php if(!empty($doc['avatar_path']??'')):?><img src="<?=htmlspecialchars($doc['avatar_path']??'')?>" alt=""><?php else:?><?=htmlspecialchars($init)?><?php endif;?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:.8125rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Dr. <?=htmlspecialchars($doc['name'])?></div>
        <div style="font-size:.6875rem;color:var(--cp-on-surface-var)"><?=htmlspecialchars($doc['specialty']??$doc['dept_name']??'General')?></div>
      </div>
      <div style="display:flex;align-items:center;gap:4px;flex-shrink:0">
        <div class="cp-status-dot <?=$dotCls?>"></div>
        <span style="font-size:.625rem;color:var(--cp-on-surface-var)"><?=ucwords(str_replace('-',' ',$doc['status']))?></span>
      </div>
    </div>
    <?php endforeach;?>
    </div>
    <?php endif;?>
  </div>

<?php /*  APPOINTMENTS  */ elseif($tab==='appointments'): ?>
  <div class="phd">
    <div><div class="ph-title">All Appointments</div><div class="ph-sub">Manage patient bookings for <?=htmlspecialchars($facilityName)?></div></div>
    <button class="cp-btn cp-btn-primary cp-btn-sm" onclick="openModal('addApptModal')"><i class="fa-solid fa-circle-info"></i> Add Appointment</button>
  </div>
  <div class="cp-tab-row">
    <?php foreach([['all','All'],['pending','Pending'],['confirmed','Confirmed'],['completed','Completed'],['cancelled','Cancelled']] as [$v,$lb]):?>
    <button class="cp-tab-btn <?=$v==='all'?'active':''?>" onclick="filterAppts('<?=$v?>',this)"><?=$lb?></button>
    <?php endforeach;?>
  </div>
  <div class="db-panel" style="padding:0;overflow:hidden">
    <div class="cp-table-wrap">
      <table class="cp-table" id="apptTable">
        <thead><tr><th>Patient</th><th>Date &amp; Time</th><th>Department</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if(empty($apptRows)):?>
        <tr><td colspan="6" style="text-align:center;padding:48px;color:var(--cp-outline)"><i class="fa-solid fa-circle-info"></i>No appointments yet</td></tr>
        <?php else: foreach($apptRows as $a):
          $init=strtoupper(substr(preg_replace('/[^A-Za-z ]/','',$a['patient_name']),0,1).(strpos($a['patient_name'],' ')!==false?substr($a['patient_name'],strrpos($a['patient_name'],' ')+1,1):''));
        ?>
        <tr data-status="<?=htmlspecialchars($a['status'])?>">
          <td><div style="display:flex;align-items:center;gap:9px">
            <div class="cp-booking-avatar" style="width:30px;height:30px;font-size:.6rem;flex-shrink:0"><?=htmlspecialchars($init)?></div>
            <div><div style="font-weight:600;font-size:.8125rem"><?=htmlspecialchars($a['patient_name'])?></div>
            <div style="font-size:.6875rem;color:var(--cp-on-surface-var)"><?=htmlspecialchars($a['patient_phone']??'')?></div></div>
          </div></td>
          <td style="font-weight:600;font-size:.8125rem;color:var(--cp-primary);white-space:nowrap"><?=date('M d, Y g:i A',strtotime($a['appointment_at']))?></td>
          <td style="font-size:.8125rem"><?=htmlspecialchars($a['department']??'General')?></td>
          <td><span class="cp-badge cp-badge-primary" style="font-size:.5625rem"><?=htmlspecialchars(ucwords(str_replace('-',' ',$a['visit_type']??'in-person')))?></span></td>
          <td><span class="cp-status-pill cp-status-<?=htmlspecialchars($a['status'])?>"><?=ucfirst($a['status'])?></span></td>
          <td><div style="display:flex;gap:5px">
            <?php if($a['status']==='pending'):?>
            <button class="cp-btn cp-btn-sm" style="background:rgba(0,90,180,.08);color:var(--cp-primary);padding:4px 10px;font-size:.6875rem" onclick="confirmAppt(<?=$a['id']?>)">Confirm</button>
            <button class="cp-btn cp-btn-sm" style="background:rgba(186,26,26,.08);color:var(--cp-error);padding:4px 10px;font-size:.6875rem" onclick="cancelAppt(<?=$a['id']?>)">Cancel</button>
            <?php elseif($a['status']==='confirmed'):?>
            <button class="cp-btn cp-btn-sm" style="background:rgba(0,106,106,.08);color:var(--cp-secondary);padding:4px 10px;font-size:.6875rem" onclick="completeAppt(<?=$a['id']?>)">Complete</button>
            <?php endif;?>
          </div></td>
        </tr>
        <?php endforeach;endif;?>
        </tbody>
      </table>
    </div>
  </div>

<?php /*  DOCTORS  */ elseif($tab==='doctors'): ?>
  <div class="phd">
    <div><div class="ph-title">Medical Staff</div><div class="ph-sub">Manage doctors, profiles, availability and status</div></div>
    <button class="cp-btn cp-btn-primary cp-btn-sm" onclick="openModal('addDoctorModal')"><i class="fa-solid fa-circle-info"></i> Add Doctor</button>
  </div>
  <?php if(empty($doctors)):?>
  <div class="db-panel" style="text-align:center;padding:56px 24px">
    <i class="fa-solid fa-circle-info"></i>
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:8px">No doctors added yet</h3>
    <p style="font-size:.875rem;color:var(--cp-on-surface-var);margin-bottom:16px">Add your medical staff to manage schedules and appointments.</p>
    <button class="cp-btn cp-btn-primary" onclick="openModal('addDoctorModal')">Add First Doctor</button>
  </div>
  <?php else:?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
  <?php foreach($doctors as $doc):
    $init=strtoupper(substr($doc['name'],0,1).(strpos($doc['name'],' ')!==false?substr($doc['name'],strrpos($doc['name'],' ')+1,1):''));
    $dotCls=$doc['status']==='on-duty'?'dot-on':($doc['status']==='on-break'?'dot-break':($doc['status']==='suspended'?'dot-susp':'dot-off'));
    $avail=!empty($doc['availability']??'')?json_decode($doc['availability']??'[]',true):[];
    $availDays=is_array($avail)?implode(', ',array_keys(array_filter($avail))):'';
  ?>
  <div class="db-panel" id="doc-card-<?=$doc['id']?>">
    <!-- Avatar + identity -->
    <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px">
      <div style="position:relative">
        <div class="cp-doc-avatar" style="width:52px;height:52px;font-size:.9rem" id="docav-<?=$doc['id']?>">
          <?php if(!empty($doc['avatar_path']??'')):?><img src="<?=htmlspecialchars($doc['avatar_path']??'')?>" alt=""><?php else:?><?=htmlspecialchars($init)?><?php endif;?>
        </div>
        <label style="position:absolute;bottom:-2px;right:-2px;width:20px;height:20px;border-radius:50%;background:var(--cp-primary);border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer" title="Change photo">
          <input type="file" accept="image/*" style="display:none" onchange="uploadDocAvatar(<?=$doc['id']?>,this)">
          <i class="fa-solid fa-circle-info"></i>
        </label>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:.9375rem;font-weight:700">Dr. <?=htmlspecialchars($doc['name'])?></div>
        <div style="font-size:.75rem;color:var(--cp-on-surface-var)"><?=htmlspecialchars($doc['specialty']??'General')?></div>
        <?php if($doc['kmpdc_licence']):?><div style="font-size:.6875rem;color:var(--cp-outline);margin-top:1px"><?=htmlspecialchars($doc['kmpdc_licence'])?></div><?php endif;?>
        <?php if($doc['dept_name']):?><div style="font-size:.6875rem;background:rgba(0,106,106,.08);color:var(--cp-secondary);display:inline-block;padding:1px 7px;border-radius:9999px;font-weight:600;margin-top:3px"><?=htmlspecialchars($doc['dept_name'])?></div><?php endif;?>
      </div>
      <div style="display:flex;align-items:center;gap:4px;flex-shrink:0">
        <div class="cp-status-dot <?=$dotCls?>"></div>
        <span style="font-size:.625rem;color:var(--cp-on-surface-var)"><?=ucwords(str_replace('-',' ',$doc['status']))?></span>
      </div>
    </div>

    <!-- Quick details -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px">
      <?php if($doc['years_exp']):?>
      <div style="font-size:.6875rem;background:var(--cp-surface-container-low);border-radius:6px;padding:5px 8px">
        <div style="color:var(--cp-outline);font-size:.5625rem;font-weight:700;text-transform:uppercase">Experience</div>
        <div style="font-weight:700"><?=$doc['years_exp']?> yr<?=($doc['years_exp']??0)>1?'s':''?></div>
      </div>
      <?php endif;?>
      <?php if(($doc['consult_fee']??0)>0):?>
      <div style="font-size:.6875rem;background:var(--cp-surface-container-low);border-radius:6px;padding:5px 8px">
        <div style="color:var(--cp-outline);font-size:.5625rem;font-weight:700;text-transform:uppercase">Consult Fee</div>
        <div style="font-weight:700">KES <?=number_format($doc['consult_fee']??0,0)?></div>
      </div>
      <?php endif;?>
      <?php if($doc['languages']):?>
      <div style="font-size:.6875rem;background:var(--cp-surface-container-low);border-radius:6px;padding:5px 8px">
        <div style="color:var(--cp-outline);font-size:.5625rem;font-weight:700;text-transform:uppercase">Languages</div>
        <div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($doc['languages']??'')?></div>
      </div>
      <?php endif;?>
      <?php if($doc['accepts_tele']):?>
      <div style="font-size:.6875rem;background:rgba(0,90,180,.06);border-radius:6px;padding:5px 8px">
        <div style="color:var(--cp-outline);font-size:.5625rem;font-weight:700;text-transform:uppercase">Tele-consult</div>
        <div style="font-weight:700;color:var(--cp-primary)">Available</div>
      </div>
      <?php endif;?>
    </div>

    <?php if($availDays):?>
    <div style="font-size:.6875rem;color:var(--cp-on-surface-var);margin-bottom:10px;padding:6px 10px;background:var(--cp-surface-container-low);border-radius:6px">
      <i class="fa-solid fa-circle-info"></i>
      Available: <?=htmlspecialchars(ucwords($availDays))?>
    </div>
    <?php endif;?>

    <!-- Status + actions -->
    <div style="display:flex;gap:7px">
      <select class="cp-form-input" style="flex:1;padding:7px 10px;font-size:.75rem;cursor:pointer"
              onchange="updateDoctorStatus(<?=$doc['id']?>,this.value)">
        <?php foreach(['on-duty'=>'On Duty','off-duty'=>'Off Duty','on-break'=>'On Break','suspended'=>'Suspended'] as $sv=>$sl):?>
        <option value="<?=$sv?>" <?=$doc['status']===$sv?'selected':''?>><?=$sl?></option>
        <?php endforeach;?>
      </select>
      <button class="cp-btn cp-btn-sm" style="background:rgba(0,90,180,.07);color:var(--cp-primary);padding:7px 12px" onclick="editDoctor(<?=$doc['id']?>)" title="Edit profile"><i class="fa-solid fa-circle-info"></i></button>
      <button class="cp-btn cp-btn-sm" style="background:rgba(186,26,26,.07);color:var(--cp-error);padding:7px 12px" onclick="deleteDoctor(<?=$doc['id']?>,this)" title="Remove"><i class="fa-solid fa-circle-info"></i></button>
    </div>
  </div>
  <?php endforeach;?>
  </div>
  <?php endif;?>

  <!-- Departments sub-section -->
  <div style="margin-top:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h3 style="font-size:1rem;font-weight:700">Departments</h3>
      <button class="cp-btn cp-btn-primary cp-btn-sm" onclick="openModal('addDeptModal')"><i class="fa-solid fa-circle-info"></i> Add</button>
    </div>
    <?php if(empty($depts)):?>
    <div class="db-panel" style="text-align:center;padding:24px;color:var(--cp-outline);font-size:.8125rem">No departments yet.</div>
    <?php else:?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:10px">
    <?php foreach($depts as $dept):?>
    <div class="cp-dept-card" style="display:flex;flex-direction:column;gap:10px">
      <div style="display:flex;align-items:center;gap:10px">
        <div class="cp-dept-icon"><span class="material-symbols-outlined" style="font-size:18px"><?=htmlspecialchars($dept['icon']??'stethoscope')?></span></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:.875rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($dept['name'])?></div>
          <div style="font-size:.6875rem;color:var(--cp-on-surface-var)"><?=(int)($dept['capacity']??0)?> capacity</div>
        </div>
      </div>
      <button class="cp-btn cp-btn-sm" style="background:rgba(186,26,26,.08);color:var(--cp-error);font-size:.6875rem;padding:5px 10px" onclick="deleteDept(<?=$dept['id']?>,this)">Remove</button>
    </div>
    <?php endforeach;?>
    </div>
    <?php endif;?>
  </div>

<?php /*  SERVICES  */ elseif($tab==='services'): ?>
  <div class="phd"><div><div class="ph-title">Service Management</div><div class="ph-sub">Toggle the services your facility offers</div></div></div>
  <div class="svc-grid-dash">
  <?php foreach($serviceDefs as [$key,$label,$icon]):$active=in_array($key,$services);?>
  <div class="db-panel" id="svc-<?=$key?>" style="opacity:<?=$active?1:.6?>;transition:opacity .2s">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
      <div style="width:38px;height:38px;border-radius:9px;background:<?=$active?'rgba(0,106,106,.1)':'var(--cp-surface-container-high)'?>;display:flex;align-items:center;justify-content:center;color:<?=$active?'var(--cp-secondary)':'var(--cp-outline)'?>">
        <span class="material-symbols-outlined" style="font-size:18px"><?=$icon?></span>
      </div>
      <label style="position:relative;display:inline-flex;align-items:center;cursor:pointer">
        <input type="checkbox" <?=$active?'checked':''?> onchange="toggleService('<?=$key?>',this.checked,this)" style="opacity:0;position:absolute;width:0;height:0">
        <div id="tog-<?=$key?>" style="width:40px;height:22px;border-radius:9999px;background:<?=$active?'var(--cp-secondary)':'var(--cp-outline-var)'?>;position:relative;transition:background .2s">
          <div id="togknob-<?=$key?>" style="width:18px;height:18px;border-radius:50%;background:#fff;position:absolute;top:2px;left:<?=$active?'20px':'2px'?>;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2)"></div>
        </div>
      </label>
    </div>
    <div style="font-size:.875rem;font-weight:700;margin-bottom:2px"><?=htmlspecialchars($label)?></div>
    <div style="font-size:.6875rem;color:var(--cp-on-surface-var)" id="svc-status-<?=$key?>"><?=$active?'Active':'Inactive'?></div>
  </div>
  <?php endforeach;?>
  </div>

<?php /*  INSURANCE  */ elseif($tab==='insurance'): ?>
  <div class="phd"><div><div class="ph-title">Insurance Partners</div><div class="ph-sub">Connect the insurance schemes your facility accepts</div></div></div>
  <div class="ins-grid">
  <?php foreach($insurerDefs as [$key,$name,$full,$icon,$color]):
    $rec=$insMap[$key]??null;$status=$rec['status']??'disconnected';$isConn=$status==='connected';
  ?>
  <div class="cp-ins-card" id="ins-<?=$key?>">
    <div class="cp-ins-icon-wrap"><span class="material-symbols-outlined" style="color:<?=$color?>"><?=$icon?></span></div>
    <div style="flex:1">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px">
        <div><div style="font-size:.9375rem;font-weight:700"><?=htmlspecialchars($name)?></div><div style="font-size:.75rem;color:var(--cp-on-surface-var)"><?=htmlspecialchars($full)?></div></div>
        <span class="cp-badge <?=$isConn?'cp-badge-success':'cp-badge-warning'?>" id="ins-status-<?=$key?>"><?=ucfirst($status)?></span>
      </div>
      <?php if($rec&&$rec['policy_ref']):?><div style="font-size:.6875rem;color:var(--cp-on-surface-var);margin-bottom:4px">Ref: <?=htmlspecialchars($rec['policy_ref'])?></div><?php endif;?>
      <div style="display:flex;gap:6px;margin-top:8px">
        <?php if(!$isConn):?>
        <button class="cp-btn cp-btn-sm" style="background:rgba(0,90,180,.08);color:var(--cp-primary);font-size:.6875rem" onclick="connectIns('<?=$key?>','<?=htmlspecialchars($name)?>','<?=htmlspecialchars($full)?>')"><i class="fa-solid fa-circle-info"></i> Connect</button>
        <?php else:?>
        <button class="cp-btn cp-btn-sm" style="background:rgba(22,163,74,.08);color:#16a34a;font-size:.6875rem" disabled><i class="fa-solid fa-circle-info"></i> Connected</button>
        <button class="cp-btn cp-btn-sm" style="background:rgba(186,26,26,.08);color:var(--cp-error);font-size:.6875rem" onclick="disconnectIns('<?=$key?>')">Disconnect</button>
        <?php endif;?>
      </div>
    </div>
  </div>
  <?php endforeach;?>
  </div>

<?php /*  ANALYTICS  */ elseif($tab==='analytics'): ?>
  <div class="phd"><div class="ph-title">Analytics</div></div>
  <div class="cp-stat-grid" style="margin-bottom:16px">
    <?php foreach([['Total Patients',$patientCount,'people'],['Total Appointments',count($apptRows),'calendar_today'],['Active Doctors',count($doctors),'medical_services'],['Departments',count($depts),'category']] as [$en,$val,$ic]):?>
    <div class="cp-stat-card"><div class="cp-stat-label"><?=$en?></div><div class="cp-stat-value" style="font-size:1.5rem"><?=$val?></div></div>
    <?php endforeach;?>
  </div>
  <!-- 12-month chart -->
  <div class="db-panel" style="margin-bottom:16px">
    <h3 style="font-size:.9375rem;font-weight:700;margin-bottom:16px">Appointment Volume — Last 12 Months</h3>
    <?php $anData=[];
    for($m=11;$m>=0;$m--){
      $lbl=date('M',strtotime("-$m months"));
      $cnt=$db->fetchOne('SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND DATE_FORMAT(appointment_at,\'%Y-%m\')=DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL :m MONTH),\'%Y-%m\')',[':h'=>$hid,':m'=>$m])['c']??0;
      $anData[]=['label'=>$lbl,'val'=>(int)$cnt];
    }$anMax=max(1,max(array_column($anData,'val')));?>
    <div class="cp-chart-wrap" style="height:160px">
      <div class="cp-chart-grid"><?php for($i=0;$i<4;$i++) echo '<div class="cp-chart-grid-line"></div>';?></div>
      <?php foreach($anData as $cd):$pct=round($cd['val']/$anMax*100);?>
      <div class="cp-bar-col"><div class="cp-bar active" style="height:<?=max($pct,2)?>%;background:rgba(0,90,180,.35)" title="<?=$cd['val']?>"></div><div class="cp-bar-label"><?=$cd['label']?></div></div>
      <?php endforeach;?>
    </div>
  </div>
  <!-- Breakdowns -->
  <div class="db-bento-bottom">
    <?php
    $statusBreak=$db->fetchAll('SELECT status,COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h GROUP BY status',[':h'=>$hid]);
    $stTotal=max(1,array_sum(array_column($statusBreak,'c')));
    ?>
    <div class="db-panel">
      <div style="font-size:.875rem;font-weight:700;margin-bottom:14px">Appointment Status</div>
      <?php if(empty($statusBreak)):?><div style="font-size:.8125rem;color:var(--cp-outline);text-align:center;padding:16px">No data yet</div>
      <?php else: foreach($statusBreak as $r):$pct=round($r['c']/$stTotal*100);?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span style="font-size:.75rem;font-weight:500;text-transform:capitalize"><?=$r['status']?></span><span style="font-size:.6875rem;color:var(--cp-on-surface-var)"><?=$r['c']?> (<?=$pct?>%)</span></div>
        <div class="cp-bed-track"><div class="cp-bed-fill" style="width:<?=$pct?>%"></div></div>
      </div>
      <?php endforeach;endif;?>
    </div>
    <div class="db-panel">
      <div style="font-size:.875rem;font-weight:700;margin-bottom:14px">Visit Types</div>
      <?php $vtBreak=$db->fetchAll('SELECT visit_type,COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h GROUP BY visit_type',[':h'=>$hid]);
      $vtTotal=max(1,array_sum(array_column($vtBreak,'c')));
      if(empty($vtBreak)):?><div style="font-size:.8125rem;color:var(--cp-outline);text-align:center;padding:16px">No data yet</div>
      <?php else: foreach($vtBreak as $r):$pct=round($r['c']/$vtTotal*100);?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span style="font-size:.75rem;font-weight:500;text-transform:capitalize"><?=htmlspecialchars(str_replace('-',' ',$r['visit_type']??''))?></span><span style="font-size:.6875rem;color:var(--cp-on-surface-var)"><?=$r['c']?> (<?=$pct?>%)</span></div>
        <div class="cp-bed-track"><div class="cp-bed-fill" style="width:<?=$pct?>%;background:var(--cp-secondary)"></div></div>
      </div>
      <?php endforeach;endif;?>
    </div>
    <div class="db-panel">
      <div style="font-size:.875rem;font-weight:700;margin-bottom:14px">Doctors by Department</div>
      <?php foreach($depts as $dept):
        $dcnt=count(array_filter($doctors,fn($d)=>$d['department_id']==$dept['id']));
        if(!$dcnt) continue;
      ?>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(193,198,213,.12)">
        <span style="font-size:.8125rem"><?=htmlspecialchars($dept['name'])?></span>
        <span style="font-weight:700;color:var(--cp-primary);font-size:.875rem"><?=$dcnt?></span>
      </div>
      <?php endforeach;?>
      <?php if(empty($depts)):?><div style="font-size:.8125rem;color:var(--cp-outline);text-align:center;padding:16px">Add departments first</div><?php endif;?>
    </div>
  </div>

<?php /*  NOTIFICATIONS  */ elseif($tab==='notifications'): ?>
  <div class="phd">
    <div><div class="ph-title">Notifications</div><div class="ph-sub">Patient booking alerts and system messages</div></div>
    <?php if($unread>0):?>
    <button class="cp-btn cp-btn-ghost cp-btn-sm" onclick="markAllRead()"><i class="fa-solid fa-circle-info"></i> Mark All Read</button>
    <?php endif;?>
  </div>
  <?php if(empty($notifs)):?>
  <div class="db-panel" style="text-align:center;padding:56px 24px;color:var(--cp-outline)">
    <i class="fa-solid fa-circle-info"></i>
    <div style="font-size:.875rem;font-weight:600">No notifications yet</div>
    <div style="font-size:.8125rem;margin-top:4px">Booking alerts and system messages will appear here</div>
  </div>
  <?php else:
  $typeMap=['booking'=>['notifications','var(--cp-primary)','rgba(0,90,180,.08)'],'insurance'=>['verified_user','var(--cp-secondary)','rgba(0,106,106,.08)'],'system'=>['info','#7c3aed','rgba(124,58,237,.08)'],'alert'=>['warning','#d97706','rgba(217,119,6,.08)'],'review'=>['rate_review','#64748b','rgba(100,116,139,.08)']];
  foreach($notifs as $n):
    $nr=!$n['is_read'];[$tic,$tcol,$tbg]=$typeMap[$n['type']??'system']??$typeMap['system'];
    $ago=time()-strtotime($n['created_at']);
    $agoStr=$ago<60?'Just now':($ago<3600?round($ago/60).'m ago':($ago<86400?round($ago/3600).'h ago':date('M j',strtotime($n['created_at']))));
  ?>
  <div class="notif-item <?=$nr?'unread':''?>" id="hn-<?=$n['id']?>" onclick="markHospNotif(<?=$n['id']?>,this)" style="cursor:pointer">
    <div class="notif-icon" style="background:<?=$tbg?>"><span class="material-symbols-outlined" style="font-size:17px;color:<?=$tcol?>"><?=$tic?></span></div>
    <div style="flex:1;min-width:0">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
        <div style="font-size:.8125rem;font-weight:<?=$nr?'700':'600'?>"><?=htmlspecialchars($n['title'])?></div>
        <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
          <span style="font-size:.6875rem;color:var(--cp-outline)"><?=$agoStr?></span>
          <?php if($nr):?><div style="width:7px;height:7px;border-radius:50%;background:var(--cp-primary);flex-shrink:0"></div><?php endif;?>
        </div>
      </div>
      <div style="font-size:.75rem;color:var(--cp-on-surface-var);margin-top:2px;line-height:1.5"><?=htmlspecialchars($n['message'])?></div>
    </div>
  </div>
  <?php endforeach;?>
  <?php endif;?>

<?php /*  SETTINGS  */ elseif($tab==='settings'): ?>
  <div class="phd"><div class="ph-title">Account Settings</div></div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start">
    <!-- Facility info + logo -->
    <div class="db-panel">
      <h3 style="font-size:.9375rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:7px">
        <i class="fa-solid fa-circle-info"></i> Facility Information
      </h3>
      <!-- Logo upload -->
      <div class="cp-form-group">
        <label class="cp-form-label">Facility Logo / Profile Photo</label>
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px">
          <div style="width:64px;height:64px;border-radius:12px;overflow:hidden;background:linear-gradient(135deg,#005ab4,#0873df);display:flex;align-items:center;justify-content:center;flex-shrink:0" id="logoPreview">
            <?php if($logoPath):?><img id="logoImg" src="<?=htmlspecialchars($logoPath)?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else:?><i class="fa-solid fa-circle-info"></i><?php endif;?>
          </div>
          <div>
            <label class="cp-btn cp-btn-ghost cp-btn-sm" style="cursor:pointer">
              <i class="fa-solid fa-circle-info"></i> Upload Logo
              <input type="file" accept="image/*" style="display:none" onchange="uploadLogo(this)" id="logoFileInput">
            </label>
            <div style="font-size:.6875rem;color:var(--cp-outline);margin-top:5px">JPG, PNG or WebP · Max 5MB</div>
          </div>
        </div>
      </div>
      <div class="cp-form-group"><label class="cp-form-label">Facility Name</label><input class="cp-form-input" id="set_fname" type="text" value="<?=htmlspecialchars($hosp['facility_name']??'')?>"></div>
      <div class="cp-form-group"><label class="cp-form-label">Phone</label><input class="cp-form-input" id="set_phone" type="tel" value="<?=htmlspecialchars($hosp['phone']??'')?>"></div>
      <div class="cp-form-group"><label class="cp-form-label">County</label><input class="cp-form-input" id="set_county" type="text" value="<?=htmlspecialchars($hosp['county']??'')?>"></div>
      <div class="cp-form-group"><label class="cp-form-label">Website</label><input class="cp-form-input" id="set_web" type="url" value="<?=htmlspecialchars($hosp['website']??'')?>" placeholder="https://"></div>
      <div class="cp-form-group"><label class="cp-form-label">Address</label><textarea class="cp-form-input" id="set_addr" rows="2"><?=htmlspecialchars($hosp['address']??'')?></textarea></div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
        <input type="checkbox" id="set_emerg" style="width:17px;height:17px;accent-color:var(--cp-primary)" <?=$hosp['emergency_24h']?'checked':''?>>
        <label for="set_emerg" style="font-size:.875rem;font-weight:600;cursor:pointer">24/7 Emergency Services</label>
      </div>
      <button class="cp-btn cp-btn-primary cp-btn-full" onclick="saveSettings()"><i class="fa-solid fa-circle-info"></i> Save Changes</button>
    </div>
    <!-- Security -->
    <div class="db-panel">
      <h3 style="font-size:.9375rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:7px">
        <i class="fa-solid fa-circle-info"></i> Security
      </h3>
      <div class="cp-form-group"><label class="cp-form-label">Admin Email</label><input class="cp-form-input" type="email" value="<?=htmlspecialchars($hosp['admin_email']??'')?>" readonly style="opacity:.6;cursor:not-allowed"></div>
      <div class="cp-form-group"><label class="cp-form-label">Current Password</label><input class="cp-form-input" id="pw_current" type="password" placeholder="Enter current password"></div>
      <div class="cp-form-group"><label class="cp-form-label">New Password</label><input class="cp-form-input" id="pw_new" type="password" placeholder="Min 8 characters"></div>
      <div class="cp-form-group"><label class="cp-form-label">Confirm Password</label><input class="cp-form-input" id="pw_confirm" type="password" placeholder="Repeat new password"></div>
      <button class="cp-btn cp-btn-ghost cp-btn-full" onclick="changePassword()">Update Password</button>
      <div style="margin-top:16px;padding:12px;background:rgba(0,106,106,.06);border-radius:var(--cp-r);border:1px solid rgba(0,106,106,.12)">
        <div style="display:flex;align-items:center;gap:8px">
          <i class="fa-solid fa-circle-info"></i>
          <div><div style="font-size:.8125rem;font-weight:600">KEPDA Security Active</div><div style="font-size:.6875rem;color:var(--cp-on-surface-var)">Data encrypted at rest — AES-256</div></div>
        </div>
      </div>
    </div>
  </div>
<?php endif;?>

  </div><!-- /db-content -->
  <footer class="cp-footer">
    <div class="cp-footer-links"><a href="/privacy.php">Privacy</a><a href="/terms.php">Terms</a><a href="/security.php">Security</a></div>
    <span>© <?=date('Y')?> Planeazzy. KEPDA Compliant.</span>
  </footer>
</div><!-- /db-main -->

<!--  MODALS  -->
<!-- Notifications modal -->
<div class="cp-modal-overlay" id="notifModal" onclick="if(event.target===this)closeModal('notifModal')">
  <div class="cp-modal">
    <div class="cp-modal-header">
      <h2 style="font-size:1rem;font-weight:700">Notifications</h2>
      <div style="display:flex;gap:6px">
        <?php if($unread>0):?><button class="cp-btn cp-btn-sm cp-btn-ghost" onclick="markAllRead()" style="font-size:.6875rem">Mark all read</button><?php endif;?>
        <button onclick="closeModal('notifModal')" style="background:none;border:none;cursor:pointer"><i class="fa-solid fa-circle-info"></i></button>
      </div>
    </div>
    <div style="max-height:420px;overflow-y:auto">
    <?php if(empty($notifs)):?><div style="text-align:center;padding:32px;color:var(--cp-outline)"><i class="fa-solid fa-circle-info"></i>No notifications</div>
    <?php else: foreach(array_slice($notifs,0,20) as $n):?>
    <div class="db-booking-row" id="mn-<?=$n['id']?>" onclick="markNotifRead(<?=$n['id']?>)" style="opacity:<?=$n['is_read']?.6:1?>">
      <div style="width:32px;height:32px;border-radius:50%;background:rgba(0,90,180,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa-solid fa-circle-info"></i></div>
      <div><div style="font-weight:600;font-size:.8125rem;margin-bottom:1px"><?=htmlspecialchars($n['title'])?></div><div style="font-size:.75rem;color:var(--cp-on-surface-var)"><?=htmlspecialchars($n['message'])?></div><div style="font-size:.625rem;color:var(--cp-outline);margin-top:2px"><?=date('M d, g:i A',strtotime($n['created_at']))?></div></div>
    </div>
    <?php endforeach;endif;?>
    </div>
  </div>
</div>

<!-- Add Doctor -->
<div class="cp-modal-overlay" id="addDoctorModal" onclick="if(event.target===this)closeModal('addDoctorModal')">
  <div class="cp-modal"><div class="cp-modal-header"><h2 style="font-size:1rem;font-weight:700">Add Doctor</h2><button onclick="closeModal('addDoctorModal')" style="background:none;border:none;cursor:pointer"><i class="fa-solid fa-circle-info"></i></button></div>
    <div class="cp-form-group"><label class="cp-form-label">Full Name *</label><input class="cp-form-input" id="doc_name" type="text" placeholder="Dr. Full Name"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="cp-form-group"><label class="cp-form-label">Email</label><input class="cp-form-input" id="doc_email" type="email" placeholder="doctor@email.com"></div>
      <div class="cp-form-group"><label class="cp-form-label">Phone</label><input class="cp-form-input" id="doc_phone" type="tel" placeholder="+254700000000"></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="cp-form-group"><label class="cp-form-label">Specialty *</label><input class="cp-form-input" id="doc_spec" type="text" placeholder="e.g., Cardiologist"></div>
      <div class="cp-form-group"><label class="cp-form-label">KMPDC Licence</label><input class="cp-form-input" id="doc_lic" type="text" placeholder="KMPDC/0000/0000"></div>
    </div>
    <div class="cp-form-group"><label class="cp-form-label">Department</label><select class="cp-form-input" id="doc_dept" style="cursor:pointer"><option value="">— None —</option><?php foreach($depts as $d):?><option value="<?=$d['id']?>"><?=htmlspecialchars($d['name'])?></option><?php endforeach;?></select></div>
    <button class="cp-btn cp-btn-primary cp-btn-full" onclick="addDoctor()"><i class="fa-solid fa-circle-info"></i> Add Doctor</button>
  </div>
</div>

<!-- Edit Doctor (rich profile) -->
<div class="cp-modal-overlay" id="editDoctorModal" onclick="if(event.target===this)closeModal('editDoctorModal')">
  <div class="cp-modal cp-modal-lg">
    <div class="cp-modal-header"><h2 style="font-size:1rem;font-weight:700">Edit Doctor Profile</h2><button onclick="closeModal('editDoctorModal')" style="background:none;border:none;cursor:pointer"><i class="fa-solid fa-circle-info"></i></button></div>
    <input type="hidden" id="edit_doc_id">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="cp-form-group"><label class="cp-form-label">Full Name *</label><input class="cp-form-input" id="edit_name" type="text"></div>
      <div class="cp-form-group"><label class="cp-form-label">Specialty</label><input class="cp-form-input" id="edit_spec" type="text"></div>
      <div class="cp-form-group"><label class="cp-form-label">Email</label><input class="cp-form-input" id="edit_email" type="email"></div>
      <div class="cp-form-group"><label class="cp-form-label">Phone</label><input class="cp-form-input" id="edit_phone" type="tel"></div>
      <div class="cp-form-group"><label class="cp-form-label">KMPDC Licence</label><input class="cp-form-input" id="edit_lic" type="text"></div>
      <div class="cp-form-group"><label class="cp-form-label">Gender</label><select class="cp-form-input" id="edit_gender"><option value="">— Select —</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
      <div class="cp-form-group"><label class="cp-form-label">Years Experience</label><input class="cp-form-input" id="edit_exp" type="number" min="0" max="60"></div>
      <div class="cp-form-group"><label class="cp-form-label">Consult Fee (KES)</label><input class="cp-form-input" id="edit_fee" type="number" min="0" step="100"></div>
      <div class="cp-form-group"><label class="cp-form-label">Languages</label><input class="cp-form-input" id="edit_langs" type="text" placeholder="English, Swahili"></div>
      <div class="cp-form-group"><label class="cp-form-label">Education</label><input class="cp-form-input" id="edit_edu" type="text" placeholder="MBChB, University of Nairobi"></div>
      <div class="cp-form-group"><label class="cp-form-label">Status</label><select class="cp-form-input" id="edit_status"><option value="on-duty">On Duty</option><option value="off-duty">Off Duty</option><option value="on-break">On Break</option><option value="suspended">Suspended</option></select></div>
      <div class="cp-form-group" style="display:flex;flex-direction:column;gap:8px;justify-content:flex-end;padding-bottom:14px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" id="edit_tele" style="accent-color:var(--cp-primary)"><span style="font-size:.875rem">Accepts Tele-consult</span></label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" id="edit_walkin" style="accent-color:var(--cp-primary)" checked><span style="font-size:.875rem">Accepts Walk-in</span></label>
      </div>
    </div>
    <div class="cp-form-group"><label class="cp-form-label">Bio / About</label><textarea class="cp-form-input" id="edit_bio" rows="3" placeholder="Brief professional bio..."></textarea></div>
    <!-- Availability -->
    <div class="cp-form-group">
      <label class="cp-form-label">Available Days &amp; Times</label>
      <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px" id="availGrid">
        <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day):?>
        <div style="text-align:center">
          <label style="cursor:pointer">
            <input type="checkbox" class="avail-day" data-day="<?=$day?>" style="display:block;margin:0 auto 3px;accent-color:var(--cp-primary)">
            <span style="font-size:.625rem;font-weight:700;color:var(--cp-on-surface-var)"><?=$day?></span>
          </label>
          <input type="text" class="avail-time" data-day="<?=$day?>" placeholder="9-17" style="width:100%;font-size:.625rem;padding:3px;border:1px solid var(--cp-outline-var);border-radius:4px;text-align:center;background:var(--cp-surface-container-low);color:var(--cp-on-surface)">
        </div>
        <?php endforeach;?>
      </div>
      <div style="font-size:.6875rem;color:var(--cp-outline);margin-top:5px">Enter time range e.g. "8-16" or "08:00-16:00"</div>
    </div>
    <button class="cp-btn cp-btn-primary cp-btn-full" onclick="saveDocProfile()"><i class="fa-solid fa-circle-info"></i> Save Doctor Profile</button>
  </div>
</div>

<!-- Add Appointment -->
<div class="cp-modal-overlay" id="addApptModal" onclick="if(event.target===this)closeModal('addApptModal')">
  <div class="cp-modal"><div class="cp-modal-header"><h2 style="font-size:1rem;font-weight:700">Add Appointment</h2><button onclick="closeModal('addApptModal')" style="background:none;border:none;cursor:pointer"><i class="fa-solid fa-circle-info"></i></button></div>
    <div class="cp-form-group"><label class="cp-form-label">Patient Name *</label><input class="cp-form-input" id="appt_name" type="text" placeholder="Full name"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="cp-form-group"><label class="cp-form-label">Phone</label><input class="cp-form-input" id="appt_phone" type="tel" placeholder="+254..."></div>
      <div class="cp-form-group"><label class="cp-form-label">Email</label><input class="cp-form-input" id="appt_email" type="email" placeholder="patient@email.com"></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="cp-form-group"><label class="cp-form-label">Date &amp; Time *</label><input class="cp-form-input" id="appt_datetime" type="datetime-local"></div>
      <div class="cp-form-group"><label class="cp-form-label">Visit Type</label><select class="cp-form-input" id="appt_type" style="cursor:pointer"><option value="in-person">In-Person</option><option value="tele-consult">Tele-consult</option></select></div>
    </div>
    <div class="cp-form-group"><label class="cp-form-label">Department</label><select class="cp-form-input" id="appt_dept" style="cursor:pointer"><option value="">— Select —</option><?php foreach($depts as $d):?><option value="<?=htmlspecialchars($d['name'])?>"><?=htmlspecialchars($d['name'])?></option><?php endforeach;?></select></div>
    <button class="cp-btn cp-btn-primary cp-btn-full" onclick="addAppt()">Add Appointment</button>
  </div>
</div>

<!-- Add Department -->
<div class="cp-modal-overlay" id="addDeptModal" onclick="if(event.target===this)closeModal('addDeptModal')">
  <div class="cp-modal"><div class="cp-modal-header"><h2 style="font-size:1rem;font-weight:700">Add Department</h2><button onclick="closeModal('addDeptModal')" style="background:none;border:none;cursor:pointer"><i class="fa-solid fa-circle-info"></i></button></div>
    <div class="cp-form-group"><label class="cp-form-label">Department Name *</label><input class="cp-form-input" id="dept_name" type="text" placeholder="e.g., Cardiology"></div>
    <div class="cp-form-group"><label class="cp-form-label">Icon (Material Symbol)</label><input class="cp-form-input" id="dept_icon" type="text" value="stethoscope"></div>
    <div class="cp-form-group"><label class="cp-form-label">Capacity</label><input class="cp-form-input" id="dept_cap" type="number" min="0" placeholder="0"></div>
    <button class="cp-btn cp-btn-primary cp-btn-full" onclick="addDept()">Add Department</button>
  </div>
</div>

<!-- Connect Insurance -->
<div class="cp-modal-overlay" id="connInsModal" onclick="if(event.target===this)closeModal('connInsModal')">
  <div class="cp-modal"><div class="cp-modal-header"><h2 style="font-size:1rem;font-weight:700" id="connInsTitle">Connect Insurance</h2><button onclick="closeModal('connInsModal')" style="background:none;border:none;cursor:pointer"><i class="fa-solid fa-circle-info"></i></button></div>
    <input type="hidden" id="conn_ins_key"><input type="hidden" id="conn_ins_name">
    <div class="cp-form-group"><label class="cp-form-label">Policy / Contract Reference (optional)</label><input class="cp-form-input" id="conn_ins_ref" type="text" placeholder="e.g., NHIF-2025-12345"></div>
    <p style="font-size:.8125rem;color:var(--cp-on-surface-var);margin-bottom:16px">By connecting, you confirm your facility has a valid agreement with this insurer.</p>
    <button class="cp-btn cp-btn-primary cp-btn-full" onclick="submitConnectIns()"><i class="fa-solid fa-circle-info"></i> Connect Now</button>
  </div>
</div>

<div class="cp-mob-overlay" id="mobOverlay" onclick="closeSidebar()"></div>
<button class="cp-mob-toggle" id="mobFABToggle" onclick="toggleSidebar()"><i class="fa-solid fa-circle-info"></i></button>
<div class="toast" id="toast"></div>

<script>
const CSRF = '<?=htmlspecialchars($csrf)?>';
const API  = '/hospital/onboarding/api.php';

/*  Toast  */
function toast(msg,type='ok'){
  const el=document.getElementById('toast');
  el.textContent=msg; el.className='toast '+type+' show';
  setTimeout(()=>el.classList.remove('show'),3500);
}

/*  API  */
async function api(action,data={}){
  try{
    const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,csrf_token:CSRF,...data})});
    return await r.json();
  }catch(e){toast('Network error','err');return{ok:false};}
}

/*  Modals  */
function openModal(id){document.getElementById(id)?.classList.add('open');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id)?.classList.remove('open');document.body.style.overflow='';}

/*  Sidebar  */
function toggleSidebar(){
  const sb=document.getElementById('cpSidebar'),ov=document.getElementById('mobOverlay');
  const open=sb.classList.toggle('open');ov.classList.toggle('open',open);document.body.style.overflow=open?'hidden':'';
}
function closeSidebar(){
  document.getElementById('cpSidebar')?.classList.remove('open');
  document.getElementById('mobOverlay')?.classList.remove('open');
  document.body.style.overflow='';
}
document.getElementById('mobToggle')?.addEventListener('click',toggleSidebar);

/*  Appointments  */
function filterAppts(status,btn){
  document.querySelectorAll('.cp-tab-btn').forEach(b=>b.classList.remove('active'));btn.classList.add('active');
  document.querySelectorAll('#apptTable tbody tr').forEach(r=>{r.style.display=(status==='all'||r.dataset.status===status)?'':'none';});
}
async function updateApptStatus(id,status){
  const r=await api('update_appointment',{appointment_id:id,status});
  if(r.ok){toast('Appointment '+status,'ok');setTimeout(()=>location.reload(),900);}
  else toast(r.msg||'Error','err');
}
function confirmAppt(id){if(confirm('Confirm this appointment? Patient will be notified by email and SMS.'))updateApptStatus(id,'confirmed');}
function cancelAppt(id){if(confirm('Cancel this appointment? Patient will be notified.'))updateApptStatus(id,'cancelled');}
function completeAppt(id){updateApptStatus(id,'completed');}

async function addAppt(){
  const name=document.getElementById('appt_name').value.trim();
  const dt=document.getElementById('appt_datetime').value;
  if(!name||!dt){toast('Patient name and date are required','err');return;}
  const r=await api('add_appointment',{patient_name:name,patient_phone:document.getElementById('appt_phone').value,patient_email:document.getElementById('appt_email').value,appointment_at:dt,visit_type:document.getElementById('appt_type').value,department:document.getElementById('appt_dept').value});
  if(r.ok){toast('Appointment added','ok');closeModal('addApptModal');setTimeout(()=>location.reload(),800);}
  else toast(r.msg||'Error','err');
}

/*  Doctors  */
async function addDoctor(){
  const name=document.getElementById('doc_name').value.trim();
  if(!name){toast('Doctor name is required','err');return;}
  const r=await api('add_doctor',{name,email:document.getElementById('doc_email').value,phone:document.getElementById('doc_phone').value,specialty:document.getElementById('doc_spec').value,licence:document.getElementById('doc_lic').value,department_id:document.getElementById('doc_dept').value});
  if(r.ok){toast(r.msg||'Doctor added','ok');closeModal('addDoctorModal');setTimeout(()=>location.reload(),800);}
  else toast(r.msg||'Error adding doctor','err');
}
async function updateDoctorStatus(id,status){
  const r=await api('update_doctor_status',{doctor_id:id,status});
  if(r.ok)toast('Status updated','ok');else toast('Error','err');
}
async function deleteDoctor(id,btn){
  if(!confirm('Remove this doctor?'))return;
  btn.disabled=true;
  const r=await api('delete_doctor',{doctor_id:id});
  if(r.ok){toast('Doctor removed','ok');document.getElementById('doc-card-'+id)?.remove();}
  else{toast(r.msg||'Error','err');btn.disabled=false;}
}
async function editDoctor(id){
  const r=await api('get_doctor',{doctor_id:id});
  if(!r.ok){toast('Could not load doctor','err');return;}
  const d=r.doctor;
  document.getElementById('edit_doc_id').value=id;
  document.getElementById('edit_name').value=d.name||'';
  document.getElementById('edit_spec').value=d.specialty||'';
  document.getElementById('edit_email').value=d.email||'';
  document.getElementById('edit_phone').value=d.phone||'';
  document.getElementById('edit_lic').value=d.kmpdc_licence||'';
  document.getElementById('edit_gender').value=d.gender||'';
  document.getElementById('edit_exp').value=d.years_exp||0;
  document.getElementById('edit_fee').value=d.consult_fee||0;
  document.getElementById('edit_langs').value=d.languages||'English';
  document.getElementById('edit_edu').value=d.education||'';
  document.getElementById('edit_bio').value=d.bio||'';
  document.getElementById('edit_status').value=d.status||'off-duty';
  document.getElementById('edit_tele').checked=!!parseInt(d.accepts_tele||0);
  document.getElementById('edit_walkin').checked=!!parseInt(d.accepts_walkin||1);
  // Populate availability
  const avail=d.availability?JSON.parse(d.availability):{};
  document.querySelectorAll('.avail-day').forEach(cb=>{
    const day=cb.dataset.day;cb.checked=!!(avail[day]);
    const ti=document.querySelector('.avail-time[data-day="'+day+'"]');
    if(ti) ti.value=avail[day]||'';
  });
  openModal('editDoctorModal');
}
async function saveDocProfile(){
  const id=document.getElementById('edit_doc_id').value;
  if(!id){toast('No doctor selected','err');return;}
  // Build availability object
  const avail={};
  document.querySelectorAll('.avail-day:checked').forEach(cb=>{
    const day=cb.dataset.day;
    const ti=document.querySelector('.avail-time[data-day="'+day+'"]');
    avail[day]=ti?ti.value:'';
  });
  const r=await api('update_doctor_full',{
    doctor_id:parseInt(id),
    name:document.getElementById('edit_name').value.trim(),
    specialty:document.getElementById('edit_spec').value,
    email:document.getElementById('edit_email').value,
    phone:document.getElementById('edit_phone').value,
    kmpdc_licence:document.getElementById('edit_lic').value,
    gender:document.getElementById('edit_gender').value,
    years_exp:parseInt(document.getElementById('edit_exp').value)||0,
    consult_fee:parseFloat(document.getElementById('edit_fee').value)||0,
    languages:document.getElementById('edit_langs').value,
    education:document.getElementById('edit_edu').value,
    bio:document.getElementById('edit_bio').value,
    status:document.getElementById('edit_status').value,
    accepts_tele:document.getElementById('edit_tele').checked?1:0,
    accepts_walkin:document.getElementById('edit_walkin').checked?1:0,
    availability:avail
  });
  if(r.ok){toast('Doctor profile saved','ok');closeModal('editDoctorModal');setTimeout(()=>location.reload(),800);}
  else toast(r.msg||'Error saving profile','err');
}

/*  Avatar uploads  */
async function uploadDocAvatar(docId,input){
  if(!input.files[0])return;
  const fd=new FormData();
  fd.append('avatar',input.files[0]);fd.append('doctor_id',docId);
  toast('Uploading photo…','ok');
  try{
    const r=await fetch('/api/hospital/upload-doctor-avatar.php',{method:'POST',body:fd});
    const j=await r.json();
    if(j.ok){
      toast('Photo updated','ok');
      const el=document.getElementById('docav-'+docId);
      if(el){
        el.innerHTML='<img src="'+j.avatar_path+'" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">';
      }
    }else toast(j.msg||'Upload failed','err');
  }catch(e){toast('Upload error','err');}
}

async function uploadLogo(input){
  if(!input.files[0])return;
  const fd=new FormData();fd.append('logo',input.files[0]);
  toast('Uploading logo…','ok');
  try{
    const r=await fetch('/api/hospital/upload-logo.php',{method:'POST',body:fd});
    const j=await r.json();
    if(j.ok){
      toast('Logo updated','ok');
      const preview=document.getElementById('logoPreview');
      if(preview){preview.innerHTML='<img id="logoImg" src="'+j.logo_path+'" alt="" style="width:100%;height:100%;object-fit:cover">';}
    }else toast(j.msg||'Upload failed','err');
  }catch(e){toast('Upload error','err');}
}

/*  Services  */
async function toggleService(key,enabled,cb){
  const card=document.getElementById('svc-'+key);
  const status=document.getElementById('svc-status-'+key);
  const tog=document.getElementById('tog-'+key);
  const knob=document.getElementById('togknob-'+key);
  const r=await api('toggle_service',{service_key:key,enabled});
  if(r.ok){
    if(card) card.style.opacity=enabled?'1':'0.6';
    if(status) status.textContent=enabled?'Active':'Inactive';
    if(tog) tog.style.background=enabled?'var(--cp-secondary)':'var(--cp-outline-var)';
    if(knob) knob.style.left=enabled?'20px':'2px';
    toast(enabled?'Service activated':'Service deactivated','ok');
  }else{cb.checked=!enabled;toast(r.msg||'Error','err');}
}

/*  Departments  */
async function addDept(){
  const name=document.getElementById('dept_name').value.trim();
  const icon=document.getElementById('dept_icon').value.trim()||'stethoscope';
  const cap=document.getElementById('dept_cap').value||0;
  if(!name){toast('Department name required','err');return;}
  const r=await api('add_department',{name,icon,capacity:parseInt(cap)});
  if(r.ok){toast('Department added','ok');closeModal('addDeptModal');setTimeout(()=>location.reload(),800);}
  else toast(r.msg||'Error','err');
}
async function deleteDept(id,btn){
  if(!confirm('Remove this department?'))return;btn.disabled=true;
  const r=await api('delete_department',{department_id:id});
  if(r.ok){toast('Removed','ok');btn.closest('.cp-dept-card')?.remove();}
  else{toast(r.msg||'Error','err');btn.disabled=false;}
}

/*  Insurance  */
function connectIns(key,name,full){
  document.getElementById('conn_ins_key').value=key;document.getElementById('conn_ins_name').value=name;
  document.getElementById('connInsTitle').textContent='Connect '+name;document.getElementById('conn_ins_ref').value='';
  openModal('connInsModal');
}
async function submitConnectIns(){
  const key=document.getElementById('conn_ins_key').value;
  const name=document.getElementById('conn_ins_name').value;
  const ref=document.getElementById('conn_ins_ref').value;
  const r=await api('connect_insurance',{provider_key:key,provider_name:name,policy_ref:ref});
  if(r.ok){closeModal('connInsModal');document.getElementById('ins-status-'+key)?.setAttribute('class','cp-badge cp-badge-success');document.getElementById('ins-status-'+key).textContent='Connected';toast(r.msg||'Connected','ok');setTimeout(()=>location.reload(),1200);}
  else toast(r.msg||'Error','err');
}
async function disconnectIns(key){
  if(!confirm('Disconnect this insurer?'))return;
  const r=await api('disconnect_insurance',{provider_key:key});
  if(r.ok){toast('Disconnected','ok');setTimeout(()=>location.reload(),800);}else toast(r.msg||'Error','err');
}

/*  Settings  */
async function saveSettings(){
  const r=await api('save_settings',{facility_name:document.getElementById('set_fname').value.trim(),phone:document.getElementById('set_phone').value,county:document.getElementById('set_county').value,address:document.getElementById('set_addr').value,website:document.getElementById('set_web').value,emergency_24h:document.getElementById('set_emerg').checked});
  toast(r.ok?(r.msg||'Settings saved'):(r.msg||'Error'),r.ok?'ok':'err');
}
async function changePassword(){
  const nw=document.getElementById('pw_new').value;
  const cnf=document.getElementById('pw_confirm').value;
  if(nw!==cnf){toast('Passwords do not match','err');return;}
  const r=await api('change_password',{current_password:document.getElementById('pw_current').value,new_password:nw});
  if(r.ok){toast(r.msg||'Password updated','ok');['pw_current','pw_new','pw_confirm'].forEach(id=>document.getElementById(id).value='');}
  else toast(r.msg||'Error','err');
}

/*  Notifications  */
async function markNotifRead(id){
  const el=document.getElementById('mn-'+id);if(el)el.style.opacity='0.5';
  await api('mark_notif_read',{notif_id:id});
}
async function markHospNotif(id,el){
  el.classList.remove('unread');
  await api('mark_notif_read',{notif_id:id});
  const dot=el.querySelector('.notif-dot');if(dot)dot.remove();
}
async function markAllRead(){
  await api('mark_all_read');
  document.querySelectorAll('.notif-item').forEach(el=>el.classList.remove('unread'));
  document.querySelectorAll('.notif-dot').forEach(d=>d.remove());
  toast('All marked as read','ok');
}
</script>
</body>
</html>
