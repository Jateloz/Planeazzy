<?php
/**
 * Planeazzy — Hospital Add Appointment (Full Page with Sidebar)
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

// Safe column additions
try { $db->query('ALTER TABLE hospital_appointments ADD COLUMN IF NOT EXISTS priority ENUM("normal","urgent","emergency") NOT NULL DEFAULT "normal"'); } catch(Throwable $e) {}
try { $db->query('ALTER TABLE hospital_appointments ADD COLUMN IF NOT EXISTS reason VARCHAR(300) DEFAULT NULL'); } catch(Throwable $e) {}

$csrf    = Security::csrfToken();
$depts   = $db->fetchAll('SELECT id, name FROM hospital_departments WHERE hospital_id=:h ORDER BY name', [':h'=>$hid]);
$doctors = $db->fetchAll('SELECT id, name, specialty FROM hospital_doctors WHERE hospital_id=:h AND is_active=1 ORDER BY name', [':h'=>$hid]);
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $patName   = trim($_POST['patient_name'] ?? '');
        $patPhone  = trim($_POST['patient_phone'] ?? '');
        $patEmail  = trim($_POST['patient_email'] ?? '');
        $apptAt    = trim($_POST['appointment_at'] ?? '');
        $visitType = in_array($_POST['visit_type']??'', ['in-person','tele-consult']) ? $_POST['visit_type'] : 'in-person';
        $dept      = trim($_POST['department'] ?? '');
        $docId     = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
        $reason    = trim($_POST['reason'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');
        $insurance = trim($_POST['insurance'] ?? '');
        $priority  = in_array($_POST['priority']??'', ['normal','urgent','emergency']) ? $_POST['priority'] : 'normal';

        if (!$patName)    { $error = 'Patient name is required.'; }
        elseif (!$apptAt) { $error = 'Appointment date and time is required.'; }
        else {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $apptAt);
            if (!$dt) $dt = DateTime::createFromFormat('Y-m-d H:i', $apptAt);
            if (!$dt) { $error = 'Invalid date/time format.'; }
            else {
                try {
                    $notesText = trim(implode("\n", array_filter([$notes, $insurance ? "Insurance: $insurance" : '', $priority !== 'normal' ? "Priority: $priority" : ''])));
                    $newId = $db->insert(
                        'INSERT INTO hospital_appointments
                         (hospital_id, doctor_id, patient_name, patient_phone, patient_email,
                          appointment_at, visit_type, department, reason, notes, priority, status)
                         VALUES (:h,:doc,:pn,:pp,:pe,:at,:vt,:dept,:rsn,:notes,:pr,"pending")',
                        [':h'=>$hid,':doc'=>$docId,':pn'=>$patName,':pp'=>$patPhone,
                         ':pe'=>$patEmail,':at'=>$dt->format('Y-m-d H:i:s'),
                         ':vt'=>$visitType,':dept'=>$dept,':rsn'=>$reason,
                         ':notes'=>$notesText,':pr'=>$priority]
                    );

                    // Doctor notification
                    if ($docId) {
                        $docRow = $db->fetchOne('SELECT name FROM hospital_doctors WHERE id=:id', [':id'=>$docId]);
                        $docName = $docRow['name'] ?? '';
                        try {
                            $db->insert(
                                'INSERT INTO hospital_notifications (hospital_id, type, title, message, created_at)
                                 VALUES (:h,"booking","New Appointment Assigned",:msg,NOW())',
                                [':h'=>$hid, ':msg'=>"Dr. $docName has been assigned to $patName on ".$dt->format('M j, Y g:i A')]
                            );
                        } catch(Throwable $e) {}
                    }

                    // Patient notifications via email + SMS
                    if ($patEmail || $patPhone) {
                        require_once dirname(__DIR__, 2) . '/services/Mailer.php';
                        require_once dirname(__DIR__, 2) . '/services/SmsService.php';
                        $facName  = $hosp['facility_name'] ?? 'Hospital';
                        $dtStr    = $dt->format('D, M j, Y \a\t g:i A');
                        $docName  = '';
                        if ($docId) {
                            $docRow = $db->fetchOne('SELECT name, specialty FROM hospital_doctors WHERE id=:id', [':id'=>$docId]);
                            $docName = $docRow ? 'Dr. ' . $docRow['name'] . ($docRow['specialty'] ? ' (' . $docRow['specialty'] . ')' : '') : '';
                        }
                        if ($patEmail) {
                            try {
                                Mailer::sendHospitalConfirmation($patEmail, $patName, $facName, $dtStr, $dept, $newId, $docName);
                            } catch(Throwable $e) {
                                error_log('Add appt email error: ' . $e->getMessage());
                            }
                        }
                        if ($patPhone) {
                            SmsService::sendHospitalAppointmentSms($patPhone, $patName, $facName, $dtStr, 'confirmed', $newId, $dept);
                        }
                    }

                    header('Location: /hospital/onboarding/dashboard.php?tab=appointments&added=1');
                    exit;
                } catch (Throwable $e) {
                    $error = 'Could not save appointment: ' . (APP_ENV === 'production' ? $e->getMessage() : 'Please try again.');
                }
            }
        }
    }
}

$minDate     = date('Y-m-d\TH:i', strtotime('+15 minutes'));
$facilityName = $hosp['facility_name'] ?? 'Hospital';
$logoPath     = $hosp['logo_path'] ?? '';
$adminName    = $hosp['admin_name'] ?? 'Admin';
$initials     = strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', trim($adminName)), 0, 2))));

// Sidebar stats
try {
    $pendingCount = (int)($db->fetchOne('SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND status="pending"', [':h'=>$hid])['c'] ?? 0);
    $unread = (int)($db->fetchOne('SELECT COUNT(*) c FROM hospital_notifications WHERE hospital_id=:h AND is_read=0', [':h'=>$hid])['c'] ?? 0);
} catch(Throwable $e) { $pendingCount = $unread = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Add Appointment — <?=htmlspecialchars($facilityName)?></title>
  <link rel="icon" type="image/png" href="/assets/images/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/clinical.css">
<style>
:root{--blue:#005ab4;--teal:#006a6a;--s50:#f8fafc;--s100:#f1f5f9;--s200:#e2e8f0;--s400:#94a3b8;--s500:#64748b;--s700:#334155;--s900:#0f172a;--sb-w:220px;--r:10px}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:var(--s900)}
a{text-decoration:none;color:inherit}

/* Sidebar */
.cp-sidebar{position:fixed;left:0;top:0;bottom:0;width:var(--sb-w);background:#fff;border-right:1px solid rgba(193,198,213,.2);display:flex;flex-direction:column;z-index:200;overflow:hidden;transition:transform .25s}
.cp-sidebar-brand{padding:14px 14px 10px;border-bottom:1px solid rgba(193,198,213,.12)}
.cp-sidebar-brand-name{font-size:.875rem;font-weight:900;color:var(--blue);letter-spacing:-.03em}
.cp-sidebar-brand-sub{font-size:.5rem;text-transform:uppercase;letter-spacing:.12em;color:#73777f;margin-bottom:8px}
.cp-sb-facility{display:flex;align-items:center;gap:8px}
.cp-sb-fac-ic{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--blue),#0873df);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cp-sb-section{font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:#73777f;padding:10px 14px 3px;opacity:.7}
.cp-nav-item{display:flex;align-items:center;gap:9px;padding:8px 12px;border-radius:9px;margin:1px 7px;font-size:.8rem;font-weight:500;color:#42474e;text-decoration:none;transition:all .12s}
.cp-nav-item:hover{background:var(--s100);color:var(--s900)}
.cp-nav-item.active{background:rgba(0,90,180,.09);color:var(--blue);font-weight:700}
.cp-nav-item .ms{font-size:17px;flex-shrink:0}
.cp-nav-badge{margin-left:auto;background:var(--blue);color:#fff;font-size:.5rem;font-weight:800;padding:1px 5px;border-radius:9999px;min-width:15px;text-align:center}
.cp-sb-footer{padding:6px 7px;border-top:1px solid rgba(193,198,213,.12)}

/* Layout */
.db-wrap{display:flex;min-height:100vh}
.db-main{margin-left:var(--sb-w);flex:1;min-width:0;display:flex;flex-direction:column}
.db-topbar{position:sticky;top:0;z-index:100;background:rgba(255,255,255,.96);backdrop-filter:blur(12px);border-bottom:1px solid var(--s200);padding:10px 22px;display:flex;align-items:center;gap:10px}
.db-hamb{display:none;align-items:center;justify-content:center;width:34px;height:34px;border:1.5px solid var(--s200);background:#fff;cursor:pointer;border-radius:8px;color:var(--s500)}
.db-content{padding:18px 20px;flex:1;min-width:0}
.cp-mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:199}
.cp-mob-overlay.open{display:block}

/* Page */
.page-inner{width:100%;max-width:100%}
.top-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:10px}
.page-title{font-size:1.25rem;font-weight:800;letter-spacing:-.04em}
.page-sub{font-size:.8125rem;color:var(--s500);margin-top:2px}
.back-link{display:inline-flex;align-items:center;gap:5px;font-size:.8125rem;font-weight:600;color:var(--s500);border:1.5px solid var(--s200);padding:7px 13px;border-radius:9px;transition:all .15s}
.back-link:hover{border-color:var(--blue);color:var(--blue)}

/* Form grid */
.form-grid{display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start;width:100%}

/* Cards */
.card{background:#fff;border-radius:14px;border:1px solid var(--s200);box-shadow:0 1px 4px rgba(0,0,0,.05);overflow:hidden;margin-bottom:16px}
.card:last-child{margin-bottom:0}
.card-head{padding:16px 20px;border-bottom:1px solid var(--s100);display:flex;align-items:center;gap:10px}
.card-icon{width:34px;height:34px;border-radius:9px;background:rgba(0,90,180,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.card-icon i{font-size:15px;color:var(--blue)}
.card-title{font-size:.9375rem;font-weight:700}
.card-sub{font-size:.6875rem;color:var(--s500);margin-top:1px}
.card-body{padding:16px 18px}

/* Fields */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.field{margin-bottom:14px}
.field:last-child{margin-bottom:0}
.label{font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--s700);margin-bottom:5px;display:block}
.req{color:#ef4444;margin-left:2px}
.inp{width:100%;padding:9px 12px;background:var(--s50);border:1.5px solid var(--s200);border-radius:9px;font-family:inherit;font-size:.875rem;color:var(--s900);outline:none;transition:all .2s}
.inp:focus{background:#fff;border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,90,180,.08)}
textarea.inp{resize:vertical;min-height:72px}

/* Visit type */
.vtype-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.vtype-opt{position:relative}
.vtype-opt input{position:absolute;opacity:0;width:0;height:0}
.vtype-opt label{display:flex;align-items:center;gap:9px;padding:11px 13px;border:2px solid var(--s200);border-radius:11px;cursor:pointer;transition:all .2s}
.vtype-opt label:hover,.vtype-opt input:checked+label{border-color:var(--blue)}
.vtype-opt input:checked+label{background:#eff6ff}
.vtype-opt .vt-icon{width:32px;height:32px;border-radius:8px;background:var(--s100);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;color:var(--s500);transition:all .2s}
.vtype-opt input:checked+label .vt-icon{background:rgba(0,90,180,.1);color:var(--blue)}

/* Priority */
.prio-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.prio-opt{position:relative}
.prio-opt input{position:absolute;opacity:0;width:0;height:0}
.prio-opt label{display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 6px;border:2px solid var(--s200);border-radius:10px;cursor:pointer;font-size:.6875rem;font-weight:700;color:var(--s500);transition:all .2s;text-align:center}
.prio-opt label i{font-size:16px}
.prio-opt input[value=normal]:checked+label{border-color:#22c55e;background:#f0fdf4;color:#15803d}
.prio-opt input[value=urgent]:checked+label{border-color:#f59e0b;background:#fffbeb;color:#b45309}
.prio-opt input[value=emergency]:checked+label{border-color:#ef4444;background:#fef2f2;color:#b91c1c}

/* Alerts */
.alert-err{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:11px;padding:12px 16px;font-size:.875rem;color:#991b1b;display:flex;align-items:center;gap:8px;margin-bottom:16px}

/* Sidebar summary */
.summary-card{background:#fff;border-radius:14px;border:1px solid var(--s200);box-shadow:0 1px 4px rgba(0,0,0,.05);padding:18px;position:sticky;top:74px}
.summary-title{font-size:.875rem;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:6px}
.sum-row{display:flex;align-items:flex-start;gap:8px;padding:8px 0;border-bottom:1px solid var(--s50);font-size:.8125rem}
.sum-row:last-of-type{border-bottom:none}
.sum-row i{color:var(--s400);width:14px;flex-shrink:0;margin-top:2px;font-size:12px}
.sum-lbl{font-size:.5rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--s400);margin-bottom:2px}
.sum-val{font-weight:600;color:var(--s800)}
.btn-submit{width:100%;padding:12px;background:linear-gradient(135deg,var(--blue),#0873df);color:#fff;border:none;border-radius:11px;font-family:inherit;font-size:.9375rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:7px;margin-top:14px}
.btn-submit:hover{opacity:.92;transform:translateY(-1px)}
.btn-cancel{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:10px;background:var(--s50);border:1.5px solid var(--s200);border-radius:10px;font-family:inherit;font-size:.875rem;font-weight:600;color:var(--s600);cursor:pointer;margin-top:8px;text-decoration:none;transition:all .15s}
.btn-cancel:hover{border-color:var(--blue);color:var(--blue)}

/* Doctor card preview */
.doc-preview{display:none;margin-top:10px;padding:10px 12px;background:rgba(0,90,180,.04);border:1px solid rgba(0,90,180,.15);border-radius:9px}
.doc-preview.show{display:flex;align-items:center;gap:9px}

.toast{position:fixed;bottom:22px;right:22px;z-index:9999;background:#065f46;color:#fff;padding:11px 17px;border-radius:11px;font-size:.875rem;font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.15);transform:translateY(70px);opacity:0;transition:all .3s;display:flex;align-items:center;gap:8px}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:1024px){.db-main{margin-left:0}.cp-sidebar{transform:translateX(-100%)}.cp-sidebar.open{transform:translateX(0)}.db-hamb{display:flex}}
@media(max-width:900px){.form-grid{grid-template-columns:1fr}.summary-card{position:static}}
@media(max-width:640px){.g2,.g3{grid-template-columns:1fr}.prio-grid{grid-template-columns:repeat(3,1fr)}.db-content{padding:14px 16px}}
</style>
</head>
<body>
<div class="db-wrap">

<!-- SIDEBAR -->
<aside class="cp-sidebar" id="cpSidebar">
  <div class="cp-sidebar-brand">
    <?php if($logoPath):?>
    <img src="<?=htmlspecialchars($logoPath)?>" alt="" style="height:28px;object-fit:contain;margin-bottom:5px;border-radius:5px;display:block">
    <?php else:?>
    <div class="cp-sidebar-brand-name">Planeazzy</div>
    <?php endif;?>
    <div class="cp-sidebar-brand-sub">Provider Dashboard</div>
    <div class="cp-sb-facility">
      <div class="cp-sb-fac-ic"><span class="material-symbols-outlined" style="font-size:14px;color:#fff">local_hospital</span></div>
      <div style="min-width:0">
        <div style="font-size:.75rem;font-weight:700;color:var(--blue);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px"><?=htmlspecialchars($facilityName)?></div>
        <div style="font-size:.5625rem;color:#73777f">Provider Admin</div>
      </div>
    </div>
  </div>
  <div style="flex:1;overflow-y:auto;padding:6px 0">
    <div class="cp-sb-section">MAIN</div>
    <?php foreach([
      ['overview','dashboard','Overview',0],
      ['appointments','calendar_today','Appointments',$pendingCount],
      ['doctors','medical_services','Doctors',0],
      ['services','business_center','Services',0],
    ] as [$k,$ic,$lb,$bd]):?>
    <a href="/hospital/onboarding/dashboard.php?tab=<?=$k?>" class="cp-nav-item <?=$k==='appointments'?'active':''?>">
      <span class="material-symbols-outlined ms"><?=$ic?></span><span><?=$lb?></span>
      <?php if($bd>0):?><span class="cp-nav-badge"><?=$bd?></span><?php endif;?>
    </a>
    <?php endforeach;?>
    <div class="cp-sb-section" style="margin-top:4px">REPORTS</div>
    <?php foreach([
      ['insurance','verified_user','Insurance',0],
      ['analytics','analytics','Analytics',0],
      ['notifications','notifications','Notifications',$unread],
      ['settings','settings','Settings',0],
    ] as [$k,$ic,$lb,$bd]):?>
    <a href="/hospital/onboarding/dashboard.php?tab=<?=$k?>" class="cp-nav-item">
      <span class="material-symbols-outlined ms"><?=$ic?></span><span><?=$lb?></span>
      <?php if($bd>0):?><span class="cp-nav-badge"><?=$bd?></span><?php endif;?>
    </a>
    <?php endforeach;?>
  </div>
  <div class="cp-sb-footer">
    <a href="/hospital/onboarding/logout.php" class="cp-nav-item" style="color:#ba1a1a">
      <span class="material-symbols-outlined ms">logout</span><span>Logout</span>
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="db-main" id="dbMain">
  <header class="db-topbar">
    <button class="db-hamb" id="mobToggle"><span class="material-symbols-outlined" style="font-size:20px">menu</span></button>
    <a href="/hospital/onboarding/dashboard.php?tab=appointments" class="back-link">
      <span class="material-symbols-outlined" style="font-size:16px">arrow_back</span> Appointments
    </a>
    <div style="flex:1;min-width:0">
      <div style="font-size:.9375rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">Add New Appointment</div>
    </div>
    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--blue),#0873df);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden">
      <?php if($logoPath):?><img src="<?=htmlspecialchars($logoPath)?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else:?><?=htmlspecialchars($initials)?><?php endif;?>
    </div>
  </header>

  <div class="db-content">
    <div class="page-inner">
      <div class="top-row">
        <div>
          <div class="page-title"><i class="fa-solid fa-calendar-plus" style="color:var(--blue);margin-right:7px"></i>Add New Appointment</div>
          <div class="page-sub">Schedule a patient appointment at <?=htmlspecialchars($facilityName)?></div>
        </div>
      </div>

      <?php if($error):?>
      <div class="alert-err"><i class="fa-solid fa-circle-exclamation"></i><?=htmlspecialchars($error)?></div>
      <?php endif;?>

      <form method="POST" id="apptForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">

      <div class="form-grid">
        <!-- Main column -->
        <div>

          <!-- Patient Information -->
          <div class="card">
            <div class="card-head">
              <div class="card-icon"><i class="fa-solid fa-user"></i></div>
              <div><div class="card-title">Patient Information</div><div class="card-sub">Who is this appointment for?</div></div>
            </div>
            <div class="card-body">
              <div class="field">
                <label class="label">Full Name <span class="req">*</span></label>
                <input class="inp" type="text" name="patient_name" id="pname" required placeholder="Patient's full name"
                       value="<?=htmlspecialchars($_POST['patient_name']??'')?>" oninput="updateSummary()">
              </div>
              <div class="g2">
                <div class="field">
                  <label class="label">Phone Number</label>
                  <input class="inp" type="tel" name="patient_phone" placeholder="+254 700 000 000" value="<?=htmlspecialchars($_POST['patient_phone']??'')?>">
                </div>
                <div class="field">
                  <label class="label">Email Address</label>
                  <input class="inp" type="email" name="patient_email" placeholder="patient@email.com" value="<?=htmlspecialchars($_POST['patient_email']??'')?>">
                </div>
              </div>
              <div class="field" style="margin-bottom:0">
                <label class="label">Insurance Scheme</label>
                <select class="inp" name="insurance">
                  <option value="">None / Self-pay</option>
                  <?php foreach(['NHIF','Jubilee Health','AXA Mansard','AAR Healthcare','Aon Minet','CIC Insurance','Britam','Other'] as $ins):?>
                  <option value="<?=$ins?>" <?=($_POST['insurance']??'')===$ins?'selected':''?>><?=$ins?></option>
                  <?php endforeach;?>
                </select>
              </div>
            </div>
          </div>

          <!-- Appointment Details -->
          <div class="card">
            <div class="card-head">
              <div class="card-icon"><i class="fa-solid fa-calendar-check"></i></div>
              <div><div class="card-title">Appointment Details</div><div class="card-sub">When, where and with whom</div></div>
            </div>
            <div class="card-body">
              <div class="g2">
                <div class="field">
                  <label class="label">Date &amp; Time <span class="req">*</span></label>
                  <input class="inp" type="datetime-local" name="appointment_at" id="apptAt" required
                         min="<?=$minDate?>" value="<?=htmlspecialchars($_POST['appointment_at']??'')?>" oninput="updateSummary()">
                </div>
                <div class="field">
                  <label class="label">Department</label>
                  <select class="inp" name="department" id="deptSel" oninput="updateSummary()">
                    <option value="">— General —</option>
                    <?php foreach($depts as $d):?>
                    <option value="<?=htmlspecialchars($d['name'])?>" <?=($_POST['department']??'')===$d['name']?'selected':''?>><?=htmlspecialchars($d['name'])?></option>
                    <?php endforeach;?>
                  </select>
                </div>
              </div>

              <!-- Doctor assignment -->
              <div class="field">
                <label class="label">Assign Doctor <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:.6875rem;color:var(--s400)">(optional — patient will be notified)</span></label>
                <?php if(empty($doctors)):?>
                <div style="background:var(--s50);border:1px solid var(--s200);border-radius:9px;padding:12px;font-size:.8125rem;color:var(--s500);display:flex;align-items:center;gap:8px">
                  <i class="fa-solid fa-circle-info" style="color:var(--s400)"></i>
                  No doctors on file yet. <a href="/hospital/onboarding/dashboard.php?tab=doctors" style="color:var(--blue)">Add doctors →</a>
                </div>
                <?php else:?>
                <select class="inp" name="doctor_id" id="docSelect" onchange="showDocPreview()">
                  <option value="">— Auto-assign / Walk-in —</option>
                  <?php foreach($doctors as $doc):?>
                  <option value="<?=$doc['id']?>"
                    data-name="Dr. <?=htmlspecialchars($doc['name'])?>"
                    data-spec="<?=htmlspecialchars($doc['specialty']??'General')?>"
                    <?=($_POST['doctor_id']??'')==$doc['id']?'selected':''?>>
                    Dr. <?=htmlspecialchars($doc['name'])?><?=$doc['specialty']?' — '.htmlspecialchars($doc['specialty']):''?>
                  </option>
                  <?php endforeach;?>
                </select>
                <div class="doc-preview" id="docPreview">
                  <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#0873df,var(--blue));display:flex;align-items:center;justify-content:center;font-size:.6875rem;font-weight:800;color:#fff;flex-shrink:0" id="docAvatar">—</div>
                  <div><div style="font-size:.8125rem;font-weight:700" id="docPreviewName"></div><div style="font-size:.6875rem;color:var(--s500)" id="docPreviewSpec"></div></div>
                  <div style="margin-left:auto;font-size:.625rem;background:rgba(0,90,180,.08);color:var(--blue);padding:2px 8px;border-radius:9999px;font-weight:700">Will be notified</div>
                </div>
                <?php endif;?>
              </div>

              <div class="field" style="margin-bottom:0">
                <label class="label">Visit Type <span class="req">*</span></label>
                <div class="vtype-grid">
                  <div class="vtype-opt">
                    <input type="radio" name="visit_type" id="vt-ip" value="in-person" <?=($_POST['visit_type']??'in-person')==='in-person'?'checked':''?>>
                    <label for="vt-ip">
                      <div class="vt-icon"><i class="fa-solid fa-location-dot"></i></div>
                      <div><div style="font-size:.875rem;font-weight:700">In-Person</div><div style="font-size:.6875rem;color:var(--s500)">Patient visits facility</div></div>
                    </label>
                  </div>
                  <div class="vtype-opt">
                    <input type="radio" name="visit_type" id="vt-tc" value="tele-consult" <?=($_POST['visit_type']??'')==='tele-consult'?'checked':''?>>
                    <label for="vt-tc">
                      <div class="vt-icon"><i class="fa-solid fa-video"></i></div>
                      <div><div style="font-size:.875rem;font-weight:700">Tele-consult</div><div style="font-size:.6875rem;color:var(--s500)">Video or phone call</div></div>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Clinical Notes -->
          <div class="card">
            <div class="card-head">
              <div class="card-icon"><i class="fa-solid fa-notes-medical"></i></div>
              <div><div class="card-title">Clinical Information</div><div class="card-sub">Reason for visit and notes</div></div>
            </div>
            <div class="card-body">
              <div class="field">
                <label class="label">Reason for Visit <span class="req">*</span></label>
                <input class="inp" type="text" name="reason" placeholder="e.g., General check-up, Chest pain, Vaccination" value="<?=htmlspecialchars($_POST['reason']??'')?>">
              </div>
              <div class="field" style="margin-bottom:0">
                <label class="label">Additional Notes</label>
                <textarea class="inp" name="notes" rows="3" placeholder="Medical history, allergies, special requirements…"><?=htmlspecialchars($_POST['notes']??'')?></textarea>
              </div>
            </div>
          </div>

          <!-- Priority -->
          <div class="card">
            <div class="card-head">
              <div class="card-icon"><i class="fa-solid fa-flag"></i></div>
              <div><div class="card-title">Priority Level</div><div class="card-sub">How urgent is this appointment?</div></div>
            </div>
            <div class="card-body">
              <div class="prio-grid">
                <div class="prio-opt">
                  <input type="radio" name="priority" id="p-n" value="normal" <?=($_POST['priority']??'normal')==='normal'?'checked':''?>>
                  <label for="p-n"><i class="fa-solid fa-circle-check" style="color:#22c55e"></i>Normal<span style="font-weight:400;font-size:.5625rem;color:var(--s500)">Routine</span></label>
                </div>
                <div class="prio-opt">
                  <input type="radio" name="priority" id="p-u" value="urgent" <?=($_POST['priority']??'')==='urgent'?'checked':''?>>
                  <label for="p-u"><i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b"></i>Urgent<span style="font-weight:400;font-size:.5625rem;color:var(--s500)">Same day</span></label>
                </div>
                <div class="prio-opt">
                  <input type="radio" name="priority" id="p-e" value="emergency" <?=($_POST['priority']??'')==='emergency'?'checked':''?>>
                  <label for="p-e"><i class="fa-solid fa-heart-pulse" style="color:#ef4444"></i>Emergency<span style="font-weight:400;font-size:.5625rem;color:var(--s500)">Immediate</span></label>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /main col -->

        <!-- Summary sidebar -->
        <div>
          <div class="summary-card">
            <div class="summary-title"><i class="fa-solid fa-clipboard-list" style="color:var(--blue)"></i> Appointment Summary</div>
            <div class="sum-row"><i class="fa-solid fa-user"></i><div><div class="sum-lbl">Patient</div><div class="sum-val" id="sumName">—</div></div></div>
            <div class="sum-row"><i class="fa-regular fa-calendar"></i><div><div class="sum-lbl">Date &amp; Time</div><div class="sum-val" id="sumDate">—</div></div></div>
            <div class="sum-row"><i class="fa-solid fa-stethoscope"></i><div><div class="sum-lbl">Department</div><div class="sum-val" id="sumDept">General</div></div></div>
            <div class="sum-row" id="sumDocRow" style="display:none"><i class="fa-solid fa-user-doctor"></i><div><div class="sum-lbl">Doctor</div><div class="sum-val" id="sumDoc">—</div></div></div>
            <div class="sum-row"><i class="fa-solid fa-hospital"></i><div><div class="sum-lbl">Facility</div><div class="sum-val"><?=htmlspecialchars($facilityName)?></div></div></div>
            <div class="sum-row"><i class="fa-solid fa-tag"></i><div><div class="sum-lbl">Status</div><div class="sum-val" style="color:var(--blue)"><i class="fa-solid fa-clock" style="margin-right:4px"></i>Pending</div></div></div>
            <button type="submit" class="btn-submit"><i class="fa-solid fa-calendar-plus"></i> Add Appointment</button>
            <a href="/hospital/onboarding/dashboard.php?tab=appointments" class="btn-cancel"><i class="fa-solid fa-xmark"></i> Cancel</a>
          </div>
          <div style="margin-top:14px;background:#fff;border-radius:12px;border:1px solid var(--s200);padding:14px">
            <div style="font-size:.75rem;font-weight:700;color:var(--s700);margin-bottom:8px;display:flex;align-items:center;gap:5px"><i class="fa-solid fa-lightbulb" style="color:#f59e0b;font-size:13px"></i> Quick Tips</div>
            <ul style="font-size:.6875rem;color:var(--s500);line-height:1.75;padding-left:13px">
              <li>Patient receives SMS &amp; email if contact details provided</li>
              <li>Assigned doctor will be named in patient notification</li>
              <li>Use "Urgent" for same-day slots</li>
              <li>Update status from the Appointments tab after visit</li>
            </ul>
          </div>
        </div>
      </div><!-- /form-grid -->
      </form>
    </div>
  </div>

  <footer style="padding:14px 24px;border-top:1px solid rgba(193,198,213,.15);font-size:.6875rem;color:#73777f;background:#fff;display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px">
    <span>© <?=date('Y')?> Planeazzy · KEPDA Compliant</span>
    <a href="/hospital/onboarding/dashboard.php" style="color:#73777f">Back to Dashboard</a>
  </footer>
</div>
</div>

<div class="cp-mob-overlay" id="mobOverlay" onclick="closeSidebar()"></div>
<div class="toast" id="toast"></div>

<script>
// Doctors data for preview
const DOCTORS = {
<?php foreach($doctors as $doc): ?>
  <?=$doc['id']?>: {name:'Dr. <?=addslashes($doc['name'])?>',spec:'<?=addslashes($doc['specialty']??'General')?>'},
<?php endforeach; ?>
};

function updateSummary() {
  const name = document.getElementById('pname')?.value || '—';
  const at   = document.getElementById('apptAt')?.value;
  const dept = document.getElementById('deptSel')?.value || 'General';
  document.getElementById('sumName').textContent = name;
  document.getElementById('sumDept').textContent = dept;
  if (at) {
    const d = new Date(at);
    document.getElementById('sumDate').textContent =
      d.toLocaleDateString('en-KE',{weekday:'short',month:'short',day:'numeric',year:'numeric'}) + ' · ' +
      d.toLocaleTimeString('en-KE',{hour:'2-digit',minute:'2-digit'});
  } else {
    document.getElementById('sumDate').textContent = '—';
  }
}

function showDocPreview() {
  const sel = document.getElementById('docSelect');
  const preview = document.getElementById('docPreview');
  const sumDocRow = document.getElementById('sumDocRow');
  if (!sel || !sel.value) {
    preview?.classList.remove('show');
    if(sumDocRow) sumDocRow.style.display='none';
    return;
  }
  const doc = DOCTORS[sel.value];
  if (doc && preview) {
    document.getElementById('docPreviewName').textContent = doc.name;
    document.getElementById('docPreviewSpec').textContent = doc.spec;
    document.getElementById('docAvatar').textContent = doc.name.replace('Dr. ','').split(' ').map(w=>w[0]?.toUpperCase()||'').join('').slice(0,2);
    preview.classList.add('show');
  }
  if (doc && sumDocRow) {
    document.getElementById('sumDoc').textContent = doc.name;
    sumDocRow.style.display = 'flex';
  }
}

function toggleSidebar() {
  const sb = document.getElementById('cpSidebar');
  const ov = document.getElementById('mobOverlay');
  const o = sb.classList.toggle('open');
  ov.classList.toggle('open', o);
  document.body.style.overflow = o ? 'hidden' : '';
}
function closeSidebar() {
  document.getElementById('cpSidebar')?.classList.remove('open');
  document.getElementById('mobOverlay')?.classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('mobToggle')?.addEventListener('click', toggleSidebar);

// Client-side validation
document.getElementById('apptForm')?.addEventListener('submit', function(e) {
  const name = document.getElementById('pname')?.value.trim();
  const at   = document.getElementById('apptAt')?.value;
  if (!name) { e.preventDefault(); showError('Patient name is required.'); return; }
  if (!at)   { e.preventDefault(); showError('Appointment date and time is required.'); return; }
});
function showError(msg) {
  let el = document.querySelector('.alert-err');
  if (!el) { el = document.createElement('div'); el.className = 'alert-err'; document.querySelector('.page-inner').prepend(el); }
  el.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i>' + msg;
  el.scrollIntoView({behavior:'smooth',block:'center'});
}

updateSummary();
showDocPreview();
</script>
</body>
</html>
