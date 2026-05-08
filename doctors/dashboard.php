<?php
/**
 * Planeazzy — Doctor Dashboard (Professional v2)
 * /doctors/dashboard.php
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Database.php';
Security::startSession();

if (empty($_SESSION['doctor_id']) || empty($_SESSION['is_doctor'])) {
    header('Location: /doctors/onboarding/login.php'); exit;
}
$docId = (int)$_SESSION['doctor_id'];
$db    = Database::getInstance();
$tab   = in_array($_GET['tab'] ?? '', ['overview','appointments','patients','schedule','analytics','notifications','settings'])
       ? ($_GET['tab'] ?? 'overview') : 'overview';
$csrf  = Security::csrfToken();

// Ensure tables / columns exist
try { $db->query("CREATE TABLE IF NOT EXISTS doctor_notifications (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, doctor_id INT UNSIGNED NOT NULL, type VARCHAR(40) DEFAULT 'appointment', title VARCHAR(160) NOT NULL, message TEXT NOT NULL, appointment_id INT UNSIGNED DEFAULT NULL, is_read TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_dn (doctor_id,is_read)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $e) {}
try { $db->query('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS doctor_id INT UNSIGNED DEFAULT NULL'); } catch(Throwable $e) {}
try { $db->query('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS cancel_reason TEXT DEFAULT NULL'); } catch(Throwable $e) {}

$doc = $db->fetchOne('SELECT * FROM doctors WHERE id=:id', [':id'=>$docId]);
if (!$doc) { session_destroy(); header('Location: /doctors/onboarding/login.php'); exit; }

$dName    = htmlspecialchars(trim($doc['first_name'].' '.$doc['last_name']));
$initials = strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', trim($dName)), 0, 2))));
$hasAv    = !empty($doc['avatar_path']);
$avSrc    = $hasAv ? htmlspecialchars($doc['avatar_path']) : '';

// Appointments from registered patients (include standalone doctor bookings by doctor_id OR hosp_doctor_id)
try { $db->query('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS doctor_id INT UNSIGNED DEFAULT NULL'); } catch(Throwable $e) {}
try { $db->query('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS hosp_doctor_id INT UNSIGNED DEFAULT NULL'); } catch(Throwable $e) {}
// Appointments from registered patients - UPDATED TO HANDLE OFFSETS
$allAppts = $db->fetchAll(
    'SELECT a.*, p.first_name pat_fn, p.last_name pat_ln, p.phone pat_phone, p.email pat_email, p.avatar_path pat_av
     FROM appointments a 
     LEFT JOIN patients p ON a.patient_id=p.id
     WHERE 
        -- Look for standalone doctor ID (Real or Offset)
        a.doctor_id = :did OR a.doctor_id = :did + 30000 
        OR 
        -- Look for hospital doctor ID (Real or Offset)
        a.hosp_doctor_id = :did OR a.hosp_doctor_id = :did + 20000
     ORDER BY a.appointment_at DESC LIMIT 300',
    [':did' => $docId]
) ?? [];

// Merge guest bookings
$docProvId = $docId + 30000;
try {
    $guestBookings = $db->fetchAll(
        "SELECT g.id+900000 AS id, 0 AS patient_id, NULL AS provider_id, :did AS doctor_id,
                g.service_type, COALESCE(g.provider_name,'Guest Booking') AS title,
                g.reason AS notes, g.appointment_at, g.location_type,
                CASE g.status WHEN 'confirmed' THEN 'confirmed' WHEN 'cancelled' THEN 'cancelled' ELSE 'scheduled' END AS status,
                g.created_at, g.guest_name AS pat_fn, '' AS pat_ln,
                g.guest_phone AS pat_phone, g.guest_email AS pat_email, NULL AS pat_av, NULL AS cancel_reason, 0 AS missed_count
         FROM guest_bookings g
         WHERE (g.provider_id=:dpid OR g.provider_name LIKE :dname) AND g.status!='cancelled'
         ORDER BY g.created_at DESC LIMIT 100",
        [':did'=>$docId, ':dpid'=>$docProvId, ':dname'=>'%'.trim($doc['first_name'].' '.$doc['last_name']).'%']
    );
    $existingTimes = array_column($allAppts, 'appointment_at');
    foreach ($guestBookings as $gb) {
        if ($gb['appointment_at'] && !in_array($gb['appointment_at'], $existingTimes)) $allAppts[] = $gb;
    }
    usort($allAppts, fn($a,$b)=>strtotime($b['appointment_at']??$b['created_at']??'0')-strtotime($a['appointment_at']??$a['created_at']??'0'));
} catch(Throwable $e) {}

// Smart display_status
foreach ($allAppts as &$da) {
    $at   = strtotime($da['appointment_at'] ?? '');
    $now  = time(); $diff = $at ? ($now - $at)/3600 : 0;
    $base = $da['status'] ?? 'scheduled';
    if (in_array($base, ['scheduled','confirmed']) && $at && $now > $at) {
        if ($diff<=3) $da['display_status']='pending_checkin';
        elseif ($diff<=6) $da['display_status']='awaiting_confirmation';
        else $da['display_status']='unconfirmed';
    } else { $da['display_status']=$base; }
}
unset($da);

$today     = array_values(array_filter($allAppts, fn($a)=>date('Y-m-d',strtotime($a['appointment_at']??''))===date('Y-m-d')));
$upcoming  = array_values(array_filter($allAppts, fn($a)=>$a['status']==='scheduled'&&strtotime($a['appointment_at']??'')>=time()));
$completed = array_values(array_filter($allAppts, fn($a)=>$a['status']==='completed'));
$cancelled = array_values(array_filter($allAppts, fn($a)=>$a['status']==='cancelled'));
$nextAppt  = $upcoming[0] ?? null;

// Patients map
$patMap = [];
foreach($allAppts as $a) {
    $pid=$a['patient_id'];if(!$pid)continue;
    if(!isset($patMap[$pid])) $patMap[$pid]=['name'=>trim(($a['pat_fn']??'').' '.($a['pat_ln']??'')),'phone'=>$a['pat_phone']??'','email'=>$a['pat_email']??'','avatar'=>$a['pat_av']??'','visits'=>0,'last'=>$a['appointment_at']??'','status'=>$a['status']??''];
    $patMap[$pid]['visits']++;
}

// Notifications
$notifs = []; $unread = 0;
try { $notifs=$db->fetchAll('SELECT * FROM doctor_notifications WHERE doctor_id=:did ORDER BY created_at DESC LIMIT 40',[':did'=>$docId])??[]; $unread=count(array_filter($notifs,fn($n)=>!$n['is_read'])); } catch(Throwable $e) {}

// Revenue
$fee = (float)($doc['consultation_fee']??0);
$monthCompleted = count(array_filter($allAppts, fn($a)=>$a['status']==='completed'&&date('Y-m',strtotime($a['appointment_at']??''))===date('Y-m')));
$estRevenue = $fee * $monthCompleted;

// Helpers
function dpill(string $s): string {
    $m=['scheduled'=>['#dbeafe','#1e40af','fa-clock','Scheduled'],'confirmed'=>['#dcfce7','#166534','fa-circle-check','Confirmed'],
        'in_progress'=>['#ede9fe','#5b21b6','fa-spinner','In Progress'],'completed'=>['#f0fdf4','#15803d','fa-check-double','Completed'],
        'cancelled'=>['#fee2e2','#991b1b','fa-circle-xmark','Cancelled'],'no_show'=>['#f1f5f9','#475569','fa-user-slash','No Show'],
        'pending_checkin'=>['#fef3c7','#92400e','fa-hourglass-half','Pending Check-in'],
        'awaiting_confirmation'=>['#fefce8','#854d0e','fa-hourglass','Awaiting Confirm.'],
        'unconfirmed'=>['#fee2e2','#991b1b','fa-triangle-exclamation','Unconfirmed']];
    [$bg,$tc,$ic,$l]=$m[$s]??['#f1f5f9','#475569','fa-circle-dot',ucfirst($s)];
    return "<span style='background:$bg;color:$tc;padding:3px 10px;border-radius:6px;font-size:10.5px;font-weight:700;display:inline-flex;align-items:center;gap:4px'><i class=\"fa-solid $ic\" style=\"font-size:9px\"></i>$l</span>";
}
function dav(string $name, string $av='', int $sz=34): string {
    $init=strtoupper(implode('',array_map(fn($w)=>$w[0],array_slice(explode(' ',trim($name)),0,2))));
    if($av) return "<img src='".htmlspecialchars($av)."' style='width:{$sz}px;height:{$sz}px;border-radius:50%;object-fit:cover;flex-shrink:0' alt=''>";
    return "<div style='width:{$sz}px;height:{$sz}px;border-radius:50%;background:linear-gradient(135deg,#1e40af,#1978e5);display:flex;align-items:center;justify-content:center;font-size:".($sz/3.2)."px;font-weight:800;color:#fff;flex-shrink:0;letter-spacing:-.5px'>$init</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dr. <?=$dName?> — Planeazzy</title>
<link rel="icon" type="image/png" href="/assets/images/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --blue:#1e40af;--blue2:#1978e5;--blue-10:rgba(30,64,175,.08);--blue-20:rgba(30,64,175,.14);
  --green:#15803d;--red:#dc2626;--amber:#d97706;
  --s50:#f8fafc;--s100:#f1f5f9;--s200:#e2e8f0;--s300:#cbd5e1;--s400:#94a3b8;
  --s500:#64748b;--s600:#475569;--s700:#334155;--s900:#0f172a;
  --bg:#f4f6f9;--white:#fff;
  --r:8px;--r-lg:12px;--r-xl:16px;
  --sb-w:232px;--hdr-h:56px;
  --sh:0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.05);
  --sh-md:0 4px 12px rgba(0,0,0,.08);
}
html,body{height:100%;font-family:'Inter',sans-serif;background:var(--bg);color:var(--s900);overflow-x:hidden}
a{text-decoration:none;color:inherit}
a:hover{text-decoration:none}
button{font-family:inherit;cursor:pointer}
button:hover{text-decoration:none}
input,select,textarea{font-family:inherit}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-thumb{background:var(--s200);border-radius:4px}

/*  Topbar  */
.hdr{position:sticky;top:0;z-index:300;height:var(--hdr-h);background:var(--white);border-bottom:1px solid var(--s200);display:flex;align-items:center;padding:0 20px;gap:12px;box-shadow:var(--sh)}
.hdr-brand{font-size:1.0625rem;font-weight:900;letter-spacing:-.03em;color:var(--s900);flex-shrink:0;text-decoration:none}
.hdr-brand span{color:var(--blue2)}
.hdr-sep{width:1px;height:20px;background:var(--s200)}
.hdr-badge{background:var(--blue-10);color:var(--blue);font-size:11px;font-weight:700;padding:3px 10px;border-radius:99px;flex-shrink:0;display:flex;align-items:center;gap:5px}
.hdr-space{flex:1}
.hdr-icon{width:34px;height:34px;border-radius:50%;background:none;border:none;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--s500);position:relative;cursor:pointer;transition:background .15s}
.hdr-icon:hover{background:var(--s100);text-decoration:none}
.notif-dot{position:absolute;top:5px;right:5px;width:7px;height:7px;border-radius:50%;background:var(--red);border:2px solid var(--white)}
.hdr-av{width:32px;height:32px;border-radius:50%;overflow:hidden;border:2px solid var(--blue-20);cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1e40af,#1978e5);color:var(--white);font-size:11px;font-weight:800}
.hdr-av img{width:100%;height:100%;object-fit:cover}
.hamb{display:none;width:32px;height:32px;border-radius:var(--r);border:none;background:none;font-size:15px;color:var(--s700);align-items:center;justify-content:center;cursor:pointer}
.hamb:hover{background:var(--s100)}

/*  Layout  */
.wrap{display:flex;min-height:calc(100vh - var(--hdr-h))}

/*  Sidebar  */
.sb{width:var(--sb-w);flex-shrink:0;background:var(--white);border-right:1px solid var(--s200);display:flex;flex-direction:column;position:sticky;top:var(--hdr-h);height:calc(100vh - var(--hdr-h));overflow-y:auto}
.sb-profile{padding:16px;border-bottom:1px solid var(--s100)}
.sb-av{width:42px;height:42px;border-radius:50%;border:2px solid var(--blue-20);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1e40af,#1978e5);color:var(--white);font-size:13px;font-weight:800}
.sb-av img{width:100%;height:100%;object-fit:cover}
.sb-name{font-size:13px;font-weight:800;color:var(--s900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sb-spec{font-size:11px;color:var(--s500);margin-top:1px}
.sb-status{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;margin-top:4px}
.sb-status.active{background:#dcfce7;color:#15803d}
.sb-status.pending{background:#fef9c3;color:#92400e}
.sb-nav{flex:1;padding:8px}
.sb-sec{font-size:10px;font-weight:700;color:var(--s400);letter-spacing:.08em;text-transform:uppercase;padding:10px 10px 3px;margin-top:2px}
.sb-link{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r);font-size:12.5px;font-weight:500;color:var(--s600);text-decoration:none;transition:all .12s;border:none;background:none;width:100%;text-align:left;cursor:pointer}
.sb-link:hover{background:var(--s50);color:var(--s900);text-decoration:none}
.sb-link.on{background:var(--blue-10);color:var(--blue);font-weight:700}
.sb-link i{font-size:14px;flex-shrink:0;width:17px;text-align:center}
.sb-cnt{min-width:17px;height:17px;border-radius:99px;background:var(--red);color:var(--white);font-size:9px;font-weight:800;display:flex;align-items:center;justify-content:center;padding:0 4px;flex-shrink:0;margin-left:auto}
.sb-cnt.blue{background:var(--blue)}
.sb-foot{padding:10px;border-top:1px solid var(--s100)}

/*  Main  */
.main{flex:1;min-width:0;padding:22px 24px}
.pg{display:none}.pg.on{display:block}
.pg-hdr{margin-bottom:22px}
.pg-title{font-size:1.25rem;font-weight:900;color:var(--s900);letter-spacing:-.04em;display:flex;align-items:center;gap:10px}
.pg-title i{font-size:18px;color:var(--blue2)}
.pg-sub{font-size:12.5px;color:var(--s500);margin-top:3px}

/*  Stat grid  */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.stat{background:var(--white);border-radius:var(--r-xl);padding:18px;border:1px solid var(--s200);box-shadow:var(--sh);position:relative}
.stat-icon{width:40px;height:40px;border-radius:var(--r-lg);display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:12px;background:var(--blue-10);color:var(--blue)}
.stat-n{font-size:1.75rem;font-weight:900;color:var(--s900);letter-spacing:-.05em;line-height:1}
.stat-l{font-size:11.5px;color:var(--s500);margin-top:4px;font-weight:500}
.stat-sub{font-size:10.5px;color:var(--s400);margin-top:5px;display:flex;align-items:center;gap:4px}

/*  Next appt hero  */
.hero{background:linear-gradient(135deg,#0f2460 0%,#1e40af 60%,#1d4ed8 100%);border-radius:var(--r-xl);padding:22px 24px;color:var(--white);margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;position:relative;overflow:hidden}
.hero::after{content:'';position:absolute;right:-24px;top:-24px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.05)}
.hero-badge{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);border-radius:99px;padding:4px 12px;font-size:10.5px;font-weight:700;display:inline-flex;align-items:center;gap:6px;margin-bottom:10px}
.hero-pulse{width:6px;height:6px;border-radius:50%;background:#6ee7b7;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
.hero-name{font-size:1.375rem;font-weight:900;letter-spacing:-.04em;margin-bottom:6px}
.hero-meta{font-size:13px;color:rgba(255,255,255,.8);display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.hero-meta span{display:flex;align-items:center;gap:5px}
.hero-btns{display:flex;gap:8px;flex-shrink:0;position:relative;z-index:1}

/*  Cards  */
.card{background:var(--white);border-radius:var(--r-xl);border:1px solid var(--s200);box-shadow:var(--sh);overflow:hidden;margin-bottom:16px}
.card:last-child{margin-bottom:0}
.card-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--s100)}
.card-title{font-size:13.5px;font-weight:800;color:var(--s900);display:flex;align-items:center;gap:8px}
.card-title i{font-size:13px;color:var(--blue2)}
.card-body{padding:0}
.card-foot{padding:11px 18px;background:var(--s50);border-top:1px solid var(--s100);text-align:center}
.card-foot a{font-size:12px;font-weight:700;color:var(--blue2)}
.card-foot a:hover{text-decoration:none}

/*  Grid  */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:16px}

/*  Appointment row  */
.arow{display:flex;align-items:center;gap:11px;padding:11px 18px;border-bottom:1px solid var(--s100);transition:background .12s}
.arow:last-child{border-bottom:none}
.arow:hover{background:var(--s50)}
.arow-info{flex:1;min-width:0}
.arow-name{font-size:12.5px;font-weight:700;color:var(--s900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.arow-meta{font-size:11px;color:var(--s500);margin-top:2px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.arow-meta span{display:flex;align-items:center;gap:4px}
.arow-acts{display:flex;gap:5px;flex-shrink:0}

/*  Patient row  */
.prow{display:flex;align-items:center;gap:11px;padding:10px 18px;border-bottom:1px solid var(--s100)}
.prow:last-child{border-bottom:none}
.prow-info{flex:1;min-width:0}
.prow-name{font-size:12.5px;font-weight:700;color:var(--s900)}
.prow-meta{font-size:11px;color:var(--s500);margin-top:2px}
.prow-badge{font-size:10px;font-weight:700;background:var(--blue-10);color:var(--blue);padding:2px 8px;border-radius:99px;flex-shrink:0}

/*  Table  */
.tbl{width:100%;border-collapse:collapse}
.tbl th{font-size:10.5px;font-weight:700;color:var(--s400);text-transform:uppercase;letter-spacing:.05em;padding:10px 14px;border-bottom:1.5px solid var(--s200);background:var(--s50);text-align:left;white-space:nowrap}
.tbl td{padding:11px 14px;border-bottom:1px solid var(--s100);vertical-align:middle;font-size:12.5px}
.tbl tr:last-child td{border-bottom:none}
.tbl tbody tr:hover td{background:var(--s50)}
.tbl-wrap{overflow-x:auto}
.empty-state{text-align:center;padding:40px 20px;color:var(--s400)}
.empty-state i{font-size:30px;display:block;margin-bottom:10px;opacity:.4}
.empty-state p{font-size:12.5px}

/*  Buttons  */
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:var(--r);border:1.5px solid transparent;font-size:12.5px;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap;text-decoration:none}
.btn:hover{text-decoration:none;opacity:.88}
.btn i{font-size:.85em}
.btn-primary{background:var(--blue);color:var(--white);border-color:var(--blue)}
.btn-outline{background:var(--white);color:var(--s700);border-color:var(--s200)}
.btn-outline:hover{border-color:var(--s400)}
.btn-ghost{background:transparent;color:var(--s500);border-color:transparent}
.btn-ghost:hover{background:var(--s100);color:var(--s900)}
.btn-success{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
.btn-danger{background:#fef2f2;color:var(--red);border-color:#fecaca}
.btn-chat{background:rgba(30,64,175,.07);color:var(--blue);border-color:rgba(30,64,175,.2)}
.btn-white{background:rgba(255,255,255,.15);color:var(--white);border-color:rgba(255,255,255,.3)}
.btn-white:hover{background:rgba(255,255,255,.25)}
.btn-sm{padding:4px 9px;font-size:11.5px}
.btn-xs{padding:3px 7px;font-size:11px}

/*  Filter tabs  */
.tabs{display:flex;gap:3px;padding:4px;background:var(--s100);border-radius:var(--r);margin-bottom:14px;width:fit-content}
.tab{padding:5px 13px;border-radius:6px;font-size:12px;font-weight:600;color:var(--s500);cursor:pointer;border:none;background:none;transition:all .12s}
.tab.on{background:var(--white);color:var(--s900);font-weight:800;box-shadow:var(--sh)}

/*  Settings  */
.settings-wrap{display:grid;grid-template-columns:260px 1fr;gap:20px}
.settings-nav-card{background:var(--white);border:1px solid var(--s200);border-radius:var(--r-xl);box-shadow:var(--sh);overflow:hidden;position:sticky;top:calc(var(--hdr-h)+22px)}
.settings-content{display:flex;flex-direction:column;gap:16px}
.scard{background:var(--white);border:1px solid var(--s200);border-radius:var(--r-xl);box-shadow:var(--sh);overflow:hidden}
.scard-hdr{padding:16px 20px;border-bottom:1px solid var(--s100);display:flex;align-items:center;gap:8px}
.scard-hdr i{color:var(--blue2);font-size:14px}
.scard-title{font-size:13px;font-weight:800;color:var(--s900)}
.scard-sub{font-size:11.5px;color:var(--s500);margin-top:1px}
.scard-body{padding:20px}
.field{margin-bottom:13px}
.field:last-child{margin-bottom:0}
.field label{display:block;font-size:11px;font-weight:700;color:var(--s600);margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em}
.fi{width:100%;padding:8px 11px;border:1.5px solid var(--s200);border-radius:var(--r);font-size:13px;color:var(--s900);background:var(--white);outline:none;transition:all .2s}
.fi:focus{border-color:var(--blue);box-shadow:0 0 0 3px var(--blue-10)}
.fi-ta{resize:vertical;min-height:76px}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}

/*  Analytics  */
.bar-wrap{display:flex;flex-direction:column;gap:9px}
.bar-row{display:flex;align-items:center;gap:10px}
.bar-lbl{font-size:11px;color:var(--s500);width:80px;flex-shrink:0}
.bar-bg{flex:1;height:7px;background:var(--s100);border-radius:99px;overflow:hidden}
.bar-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--blue),var(--blue2));transition:width .6s}
.bar-val{font-size:11px;font-weight:700;color:var(--s700);width:28px;text-align:right;flex-shrink:0}

/*  Notif  */
.notif-row{display:flex;gap:11px;padding:12px 18px;border-bottom:1px solid var(--s100);transition:background .12s}
.notif-row.unread{background:#f8fafc}
.notif-row:last-child{border-bottom:none}
.notif-row:hover{background:var(--s50)}
.notif-ic{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.notif-ic.appointment{background:var(--blue-10);color:var(--blue)}
.notif-ic.cancellation{background:#fef2f2;color:var(--red)}
.notif-ic.system{background:var(--s100);color:var(--s500)}
.notif-body{flex:1;min-width:0}
.notif-title{font-size:12.5px;font-weight:700;color:var(--s900)}
.notif-msg{font-size:11.5px;color:var(--s500);margin-top:2px;line-height:1.5}
.notif-time{font-size:10px;color:var(--s400);margin-top:3px}
.unread-dot{width:7px;height:7px;border-radius:50%;background:var(--blue);flex-shrink:0;margin-top:5px}

/*  Schedule  */
.sched-day{background:var(--white);border:1.5px solid var(--s200);border-radius:var(--r-lg);margin-bottom:8px;overflow:hidden}
.sched-day-hdr{display:flex;align-items:center;justify-content:space-between;padding:11px 16px;background:var(--s50)}
.sched-day-name{font-size:12.5px;font-weight:800;color:var(--s900)}
.toggle-sw{width:40px;height:22px;border-radius:99px;background:var(--s300);border:none;cursor:pointer;position:relative;transition:background .2s;flex-shrink:0}
.toggle-sw.on{background:var(--blue)}
.toggle-sw::after{content:'';position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:var(--white);transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.toggle-sw.on::after{left:21px}

/*  Modals  */
.modal-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);z-index:500;display:none;align-items:center;justify-content:center;padding:20px}
.modal-ov.open{display:flex}
.modal{background:var(--white);border-radius:var(--r-xl);box-shadow:0 24px 64px rgba(0,0,0,.2);width:100%;max-width:460px;max-height:90vh;overflow-y:auto;animation:su .22s ease}
@keyframes su{from{transform:translateY(12px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--s100)}
.modal-title{font-size:14px;font-weight:800;color:var(--s900);display:flex;align-items:center;gap:8px}
.modal-title i{font-size:14px}
.modal-close{width:30px;height:30px;border-radius:50%;border:none;background:var(--s100);color:var(--s500);font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.modal-close:hover{background:var(--s200)}
.modal-body{padding:20px 22px}
.modal-foot{padding:14px 22px;border-top:1px solid var(--s100);display:flex;gap:8px;justify-content:flex-end}

/*  Messaging panel  */
.msg-panel{position:fixed;right:0;top:0;bottom:0;width:350px;background:var(--white);border-left:1px solid var(--s200);box-shadow:-4px 0 24px rgba(0,0,0,.1);z-index:400;display:none;flex-direction:column}
.msg-panel.open{display:flex}
.msg-hdr{padding:13px 16px;border-bottom:1px solid var(--s100);display:flex;align-items:center;gap:10px;flex-shrink:0}
.msg-body{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:8px;background:var(--s50)}
.msg-bubble{max-width:80%;padding:8px 12px;border-radius:12px;font-size:12.5px;line-height:1.5}
.msg-mine{background:var(--blue);color:var(--white);align-self:flex-end;border-radius:12px 12px 3px 12px}
.msg-theirs{background:var(--white);color:var(--s900);align-self:flex-start;border:1px solid var(--s200);border-radius:12px 12px 12px 3px}
.msg-sender{font-size:10px;font-weight:700;margin-bottom:3px;opacity:.7}
.msg-time{font-size:9.5px;margin-top:3px;opacity:.6;text-align:right}
.msg-foot{padding:11px 14px;border-top:1px solid var(--s100);display:flex;gap:8px;flex-shrink:0}
.msg-inp{flex:1;padding:8px 12px;background:var(--s50);border:1.5px solid var(--s200);border-radius:9px;font-family:inherit;font-size:13px;outline:none;resize:none;max-height:80px;transition:border .15s}
.msg-inp:focus{border-color:var(--blue);background:var(--white)}
.msg-send{width:36px;height:36px;border-radius:9px;background:var(--blue);border:none;color:var(--white);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.msg-send:hover{opacity:.88}
.msg-ov{position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:399;display:none}
.msg-ov.open{display:block}

/*  Alert  */
.alert{padding:10px 14px;border-radius:var(--r);font-size:12.5px;font-weight:500;display:none;align-items:center;gap:8px;margin-bottom:14px;border:1px solid transparent}
.alert.ok{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
.alert.err{background:#fef2f2;color:#991b1b;border-color:#fecaca}

/*  Danger zone  */
.danger-zone{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:var(--r-xl);padding:20px;margin-top:20px}

/*  Mobile  */
.sb-mob-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:190}
@media(max-width:1100px){.stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.g2,.settings-wrap{grid-template-columns:1fr}}
@media(max-width:768px){
  :root{--sb-w:260px}
  .sb{position:fixed;top:var(--hdr-h);left:0;height:calc(100vh - var(--hdr-h));transform:translateX(-100%);z-index:200;transition:transform .25s}
  .sb.open{transform:translateX(0)}
  .sb-mob-ov.open{display:block}
  .hamb{display:flex}
  .main{padding:14px 12px}
  .msg-panel{width:100%}
}
@media(max-width:480px){.stats{grid-template-columns:1fr}.g3{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<!--  Topbar  -->
<header class="hdr">
  <button class="hamb" id="hambBtn" onclick="openSb()"><i class="fa-solid fa-bars"></i></button>
  <a href="/" class="hdr-brand">Plane<span>azzy</span></a>
  <div class="hdr-sep"></div>
  <div class="hdr-badge"><i class="fa-solid fa-stethoscope"></i> Doctor Portal</div>
  <div class="hdr-space"></div>
  <button class="hdr-icon" onclick="nav('notifications')" title="Notifications">
    <i class="fa-solid fa-bell"></i>
    <?php if($unread>0):?><span class="notif-dot"></span><?php endif;?>
  </button>
  <div class="hdr-av" title="Dr. <?=$dName?>">
    <?php if($hasAv):?><img src="<?=$avSrc?>" alt=""><?php else:?><?=$initials?><?php endif;?>
  </div>
</header>

<div class="sb-mob-ov" id="sbOv" onclick="closeSb()"></div>

<div class="wrap">
<!--  Sidebar  -->
<aside class="sb" id="sidebar">
  <div class="sb-profile">
    <div style="display:flex;align-items:center;gap:11px">
      <div class="sb-av">
        <?php if($hasAv):?><img src="<?=$avSrc?>" alt=""><?php else:?><?=$initials?><?php endif;?>
      </div>
      <div style="min-width:0">
        <div class="sb-name">Dr. <?=$dName?></div>
        <div class="sb-spec"><?=htmlspecialchars($doc['specialty']??'Doctor')?></div>
        <div class="sb-status <?=$doc['status']==='active'?'active':'pending'?>">
          <span style="width:5px;height:5px;border-radius:50%;background:currentColor;display:inline-block"></span>
          <?=$doc['status']==='active'?'Active':'Pending Review'?>
        </div>
      </div>
    </div>
  </div>
  <nav class="sb-nav">
    <div class="sb-sec">Main</div>
    <?php
    $navItems = [
      ['overview',      'fa-gauge',         'Overview',       0],
      ['appointments',  'fa-calendar-check','Appointments',   count($upcoming)],
      ['patients',      'fa-users',         'My Patients',    0],
      ['schedule',      'fa-clock',         'My Schedule',    0],
    ];
    foreach($navItems as [$pg,$ic,$lb,$cnt]):?>
    <button class="sb-link <?=$tab===$pg?'on':''?>" onclick="nav('<?=$pg?>')">
      <i class="fa-solid <?=$ic?>"></i><span><?=$lb?></span>
      <?php if($cnt>0):?><span class="sb-cnt blue"><?=$cnt?></span><?php endif;?>
    </button>
    <?php endforeach;?>
    <div class="sb-sec">Reports</div>
    <?php
    $navItems2 = [
      ['analytics',     'fa-chart-line',    'Analytics',      0],
      ['notifications', 'fa-bell',          'Notifications',  $unread],
      ['settings',      'fa-gear',          'Settings',       0],
    ];
    foreach($navItems2 as [$pg,$ic,$lb,$cnt]):?>
    <button class="sb-link <?=$tab===$pg?'on':''?>" onclick="nav('<?=$pg?>')">
      <i class="fa-solid <?=$ic?>"></i><span><?=$lb?></span>
      <?php if($cnt>0):?><span class="sb-cnt"><?=$cnt?></span><?php endif;?>
    </button>
    <?php endforeach;?>
  </nav>
  <div class="sb-foot">
    <a href="/doctors/onboarding/logout.php" class="sb-link" style="color:var(--red)">
      <i class="fa-solid fa-right-from-bracket"></i><span>Sign Out</span>
    </a>
  </div>
</aside>

<!--  Main  -->
<main class="main">

<!-- OVERVIEW -->
<div class="pg <?=$tab==='overview'?'on':''?>" id="pg-overview">
  <div class="pg-hdr">
    <div class="pg-title"><i class="fa-solid fa-gauge"></i>Overview</div>
    <div class="pg-sub"><?=date('l, F j, Y')?> — Welcome back, Dr. <?=htmlspecialchars($doc['first_name'])?></div>
  </div>

  <div class="stats">
    <div class="stat"><div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div><div class="stat-n"><?=count($today)?></div><div class="stat-l">Today's Appointments</div><div class="stat-sub"><i class="fa-regular fa-clock" style="font-size:10px"></i><?=date('D, M j')?></div></div>
    <div class="stat"><div class="stat-icon"><i class="fa-solid fa-calendar-days"></i></div><div class="stat-n"><?=count($upcoming)?></div><div class="stat-l">Upcoming</div><div class="stat-sub"><i class="fa-solid fa-arrow-right" style="font-size:10px"></i>Scheduled</div></div>
    <div class="stat"><div class="stat-icon"><i class="fa-solid fa-users"></i></div><div class="stat-n"><?=count($patMap)?></div><div class="stat-l">Total Patients</div><div class="stat-sub"><i class="fa-solid fa-chart-line" style="font-size:10px"></i>All time</div></div>
    <div class="stat"><div class="stat-icon"><i class="fa-solid fa-check-double"></i></div><div class="stat-n"><?=$monthCompleted?></div><div class="stat-l">Sessions This Month</div><div class="stat-sub"><i class="fa-regular fa-calendar" style="font-size:10px"></i><?=date('F Y')?></div></div>
  </div>

  <?php if($nextAppt): $nn=htmlspecialchars(trim(($nextAppt['pat_fn']??'').' '.($nextAppt['pat_ln']??'Patient')));?>
  <div class="hero">
    <div style="position:relative;z-index:1">
      <div class="hero-badge"><div class="hero-pulse"></div>NEXT APPOINTMENT</div>
      <div class="hero-name"><?=$nn?></div>
      <div class="hero-meta">
        <span><i class="fa-regular fa-clock"></i><?=date('D M j \a\t g:ia',strtotime($nextAppt['appointment_at']))?></span>
        <span><i class="fa-solid fa-tag"></i><?=ucfirst($nextAppt['service_type']??'Consultation')?></span>
        <?php if($nextAppt['pat_phone']):?><span><i class="fa-solid fa-phone"></i><?=htmlspecialchars($nextAppt['pat_phone'])?></span><?php endif;?>
      </div>
    </div>
    <div class="hero-btns">
      <button class="btn btn-white btn-sm" onclick="openReschedule(<?=$nextAppt['id']?>,'<?=htmlspecialchars(addslashes($nn))?>','<?=$nextAppt['appointment_at']?>')"><i class="fa-solid fa-calendar-pen"></i> Reschedule</button>
      <button class="btn btn-white btn-sm" style="background:rgba(255,255,255,.25)" onclick="confirmAppt(<?=$nextAppt['id']?>)"><i class="fa-solid fa-check"></i> Confirm</button>
    </div>
  </div>
  <?php else:?>
  <div style="background:var(--white);border:1.5px dashed var(--s200);border-radius:var(--r-xl);padding:28px;text-align:center;margin-bottom:20px">
    <i class="fa-regular fa-calendar" style="font-size:32px;color:var(--s300);display:block;margin-bottom:10px"></i>
    <div style="font-size:13.5px;font-weight:700;color:var(--s700);margin-bottom:3px">No upcoming appointments</div>
    <div style="font-size:12px;color:var(--s400)">Patients can book you through the Planeazzy platform</div>
  </div>
  <?php endif;?>

  <div class="g2">
    <div class="card">
      <div class="card-hdr"><div class="card-title"><i class="fa-solid fa-calendar-day"></i>Today's Schedule</div><span style="font-size:11px;color:var(--s400)"><?=count($today)?> appointment<?=count($today)!==1?'s':''?></span></div>
      <div class="card-body">
        <?php if($today): foreach(array_slice($today,0,6) as $a): $pn=htmlspecialchars(trim(($a['pat_fn']??'').' '.($a['pat_ln']??'Patient')));?>
        <div class="arow">
          <?=dav($pn,$a['pat_av']??'')?>
          <div class="arow-info">
            <div class="arow-name"><?=$pn?></div>
            <div class="arow-meta"><span><i class="fa-regular fa-clock" style="font-size:9px"></i><?=date('g:ia',strtotime($a['appointment_at']))?></span><?=dpill($a['status']??'scheduled')?></div>
          </div>
          <div class="arow-acts">
            <button class="btn btn-success btn-xs" onclick="completeAppt(<?=$a['id']?>)" title="Complete"><i class="fa-solid fa-check"></i></button>
            <button class="btn btn-danger btn-xs" onclick="openCancel(<?=$a['id']?>,'<?=htmlspecialchars(addslashes($pn))?>','<?=date('D M j g:ia',strtotime($a['appointment_at']))?>')" title="Cancel"><i class="fa-solid fa-xmark"></i></button>
          </div>
        </div>
        <?php endforeach; else:?>
        <div class="empty-state"><i class="fa-regular fa-calendar-xmark"></i><p>No appointments today</p></div>
        <?php endif;?>
      </div>
      <div class="card-foot"><a href="javascript:void(0)" onclick="nav('appointments')">View all appointments <i class="fa-solid fa-arrow-right" style="font-size:10px"></i></a></div>
    </div>

    <div class="card">
      <div class="card-hdr"><div class="card-title"><i class="fa-solid fa-users"></i>Recent Patients</div><span style="font-size:11px;color:var(--s400)"><?=count($patMap)?> total</span></div>
      <div class="card-body">
        <?php if($patMap): foreach(array_slice(array_values($patMap),0,6) as $p):?>
        <div class="prow">
          <?=dav($p['name'],$p['avatar']??'')?>
          <div class="prow-info">
            <div class="prow-name"><?=htmlspecialchars($p['name'])?></div>
            <div class="prow-meta"><?=htmlspecialchars($p['phone']??'')?><?=$p['phone']&&$p['last']?' · ':''?><?=$p['last']?'Last: '.date('M j',strtotime($p['last'])):''?></div>
          </div>
          <span class="prow-badge"><?=$p['visits']?> visit<?=$p['visits']!==1?'s':''?></span>
        </div>
        <?php endforeach; else:?>
        <div class="empty-state"><i class="fa-solid fa-user-slash"></i><p>No patients yet</p></div>
        <?php endif;?>
      </div>
      <div class="card-foot"><a href="javascript:void(0)" onclick="nav('patients')">View all patients <i class="fa-solid fa-arrow-right" style="font-size:10px"></i></a></div>
    </div>
  </div>

  <div class="g3">
    <div class="card"><div class="card-body" style="padding:18px;text-align:center">
      <i class="fa-solid fa-star" style="font-size:24px;color:#f59e0b;display:block;margin-bottom:8px"></i>
      <div style="font-size:1.625rem;font-weight:900;color:var(--s900);letter-spacing:-.05em"><?=number_format((float)($doc['rating']??0),1)?></div>
      <div style="font-size:11.5px;color:var(--s500);margin-top:3px">Average Rating</div>
      <div style="font-size:10.5px;color:var(--s400);margin-top:2px"><?=($doc['review_count']??0)?> reviews</div>
    </div></div>
    <div class="card"><div class="card-body" style="padding:18px;text-align:center">
      <i class="fa-solid fa-syringe" style="font-size:24px;color:var(--blue2);display:block;margin-bottom:8px"></i>
      <div style="font-size:1.625rem;font-weight:900;color:var(--s900);letter-spacing:-.05em"><?=$monthCompleted?></div>
      <div style="font-size:11.5px;color:var(--s500);margin-top:3px">Consultations This Month</div>
      <div style="font-size:10.5px;color:var(--s400);margin-top:2px"><?=date('F Y')?></div>
    </div></div>
    <div class="card"><div class="card-body" style="padding:18px;text-align:center">
      <i class="fa-solid fa-briefcase-medical" style="font-size:24px;color:var(--green);display:block;margin-bottom:8px"></i>
      <div style="font-size:1.625rem;font-weight:900;color:var(--s900);letter-spacing:-.05em"><?=$doc['years_experience']??$doc['years_exp']??0?></div>
      <div style="font-size:11.5px;color:var(--s500);margin-top:3px">Years of Experience</div>
      <div style="font-size:10.5px;color:var(--s400);margin-top:2px"><?=htmlspecialchars($doc['specialty']??'Medicine')?></div>
    </div></div>
  </div>
</div><!-- /overview -->

<!-- APPOINTMENTS -->
<div class="pg <?=$tab==='appointments'?'on':''?>" id="pg-appointments">
  <div class="pg-hdr">
    <div class="pg-title"><i class="fa-solid fa-calendar-check"></i>Appointments</div>
    <div class="pg-sub">Manage and action all patient bookings</div>
  </div>
  <div class="alert" id="appt-alert"></div>
  <div class="tabs">
    <button class="tab on" onclick="filterAppts('upcoming',this)">Upcoming (<?=count($upcoming)?>)</button>
    <button class="tab" onclick="filterAppts('today',this)">Today (<?=count($today)?>)</button>
    <button class="tab" onclick="filterAppts('completed',this)">Completed (<?=count($completed)?>)</button>
    <button class="tab" onclick="filterAppts('cancelled',this)">Cancelled (<?=count($cancelled)?>)</button>
    <button class="tab" onclick="filterAppts('all',this)">All (<?=count($allAppts)?>)</button>
  </div>
  <div class="card">
    <div class="card-hdr" style="flex-wrap:wrap;gap:8px">
      <div class="card-title"><i class="fa-solid fa-table-list"></i>Patient Appointments</div>
      <div style="display:flex;align-items:center;gap:7px;background:var(--s50);border:1px solid var(--s200);border-radius:var(--r);padding:5px 11px;min-width:200px">
        <i class="fa-solid fa-magnifying-glass" style="color:var(--s400);font-size:11px"></i>
        <input type="text" placeholder="Search patients…" style="border:none;background:none;outline:none;font-family:inherit;font-size:12px;color:var(--s900);width:100%" oninput="searchAppts(this.value)">
      </div>
    </div>
    <div class="tbl-wrap">
      <table class="tbl" id="apptTbl">
        <thead><tr>
          <th>Patient</th><th>Date &amp; Time</th><th>Service</th><th>Location</th><th>Status</th><th style="text-align:right">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach($allAppts as $a):
          $pname = htmlspecialchars(trim(($a['pat_fn']??'').' '.($a['pat_ln']??'Patient')));
          $st = $a['status']??'scheduled';
          $isGuest = ($a['id']??0) >= 900000;
          $realId  = $isGuest ? (($a['id'])-900000) : ($a['id']??0);
          $canAct  = in_array($st,['scheduled','confirmed']);
          $cls = match(true){
            $st==='cancelled'||$st==='no_show'=>'cancelled',
            $st==='completed'=>'completed',
            !empty($a['appointment_at'])&&date('Y-m-d',strtotime($a['appointment_at']))===date('Y-m-d')=>'today',
            !empty($a['appointment_at'])&&strtotime($a['appointment_at'])>=time()=>'upcoming',
            default=>'past'
          };
        ?>
        <tr data-filter="<?=$cls?>">
          <td>
            <div style="display:flex;align-items:center;gap:9px">
              <?=dav($pname,$a['pat_av']??'',30)?>
              <div>
                <div style="font-weight:700"><?=$pname?></div>
                <div style="font-size:10.5px;color:var(--s400)"><?=htmlspecialchars($a['pat_email']??'')?></div>
                <?php if($isGuest):?><span style="font-size:9.5px;background:#fef9c3;color:#854d0e;padding:1px 5px;border-radius:4px;font-weight:700">GUEST</span><?php endif;?>
              </div>
            </div>
          </td>
          <td>
            <div style="font-weight:700;font-size:12px"><?=!empty($a['appointment_at'])?date('D, M j Y',strtotime($a['appointment_at'])):'—'?></div>
            <div style="font-size:11px;color:var(--s500)"><?=!empty($a['appointment_at'])?date('g:ia',strtotime($a['appointment_at'])):''?></div>
          </td>
          <td style="font-size:12px;color:var(--s700);font-weight:600"><?=ucfirst($a['service_type']??'consultation')?></td>
          <td style="font-size:11.5px;color:var(--s500)"><?=ucwords(str_replace('_',' ',$a['location_type']??'in-person'))?></td>
          <td><?=dpill($a['display_status']??$st)?></td>
          <td>
            <div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap">
              <?php if($canAct):?>
              <button class="btn btn-success btn-sm" onclick="confirmAppt(<?=$a['id']?>)" title="Confirm"><i class="fa-solid fa-check"></i></button>
              <button class="btn btn-outline btn-sm" onclick="openReschedule(<?=$a['id']?>,'<?=htmlspecialchars(addslashes($pname))?>','<?=htmlspecialchars($a['appointment_at']??'')?>')" title="Reschedule"><i class="fa-solid fa-calendar-pen"></i></button>
              <button class="btn btn-danger btn-sm" onclick="openCancel(<?=$a['id']?>,'<?=htmlspecialchars(addslashes($pname))?>','<?=!empty($a['appointment_at'])?date('D M j g:ia',strtotime($a['appointment_at'])):''?>')" title="Cancel"><i class="fa-solid fa-xmark"></i></button>
              <?php elseif($st==='in_progress'):?>
              <button class="btn btn-primary btn-sm" onclick="completeAppt(<?=$a['id']?>)"><i class="fa-solid fa-check-double"></i> Complete</button>
              <?php endif;?>
              <?php if(!$isGuest && $realId && in_array($st,['scheduled','confirmed','completed'])):?>
              <button class="btn btn-chat btn-sm" onclick="openMsg(<?=$realId?>,'standard','<?=htmlspecialchars(addslashes($pname))?>')" title="Message patient"><i class="fa-solid fa-comment-dots"></i> Chat</button>
              <?php elseif($isGuest && !empty($a['pat_phone']??'')):?>
              <a href="tel:<?=htmlspecialchars($a['pat_phone'])?>" class="btn btn-outline btn-sm" title="Call"><i class="fa-solid fa-phone"></i></a>
              <?php elseif($isGuest && !empty($a['pat_email']??'')):?>
              <a href="mailto:<?=htmlspecialchars($a['pat_email'])?>" class="btn btn-outline btn-sm" title="Email"><i class="fa-solid fa-envelope"></i></a>
              <?php endif;?>
            </div>
          </td>
        </tr>
        <?php endforeach;?>
        <?php if(!$allAppts):?>
        <tr><td colspan="6"><div class="empty-state"><i class="fa-regular fa-calendar-xmark"></i><p>No appointments yet</p></div></td></tr>
        <?php endif;?>
        </tbody>
      </table>
    </div>
  </div>
</div><!-- /appointments -->

<!-- PATIENTS -->
<div class="pg <?=$tab==='patients'?'on':''?>" id="pg-patients">
  <div class="pg-hdr">
    <div class="pg-title"><i class="fa-solid fa-users"></i>My Patients</div>
    <div class="pg-sub"><?=count($patMap)?> registered patients</div>
  </div>
  <div class="card">
    <div class="card-hdr">
      <div class="card-title"><i class="fa-solid fa-address-book"></i>Patient Registry</div>
      <div style="display:flex;align-items:center;gap:7px;background:var(--s50);border:1px solid var(--s200);border-radius:var(--r);padding:5px 11px;min-width:200px">
        <i class="fa-solid fa-magnifying-glass" style="color:var(--s400);font-size:11px"></i>
        <input type="text" placeholder="Search patients…" style="border:none;background:none;outline:none;font-family:inherit;font-size:12px;width:100%" oninput="filterTable('patTbl',this.value)">
      </div>
    </div>
    <div class="tbl-wrap">
      <table class="tbl" id="patTbl">
        <thead><tr><th>Patient</th><th>Contact</th><th>Visits</th><th>Last Appointment</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($patMap as $pid=>$p):?>
        <tr>
          <td><div style="display:flex;align-items:center;gap:9px"><?=dav($p['name'],$p['avatar']??'')?><div style="font-weight:700"><?=htmlspecialchars($p['name'])?></div></div></td>
          <td style="font-size:11.5px;color:var(--s500)"><?=htmlspecialchars($p['phone']??'—')?><?=$p['email']?'<br><span style="color:var(--s400)">'.htmlspecialchars($p['email']).'</span>':''?></td>
          <td><span class="prow-badge"><?=$p['visits']?> visit<?=$p['visits']!==1?'s':''?></span></td>
          <td style="font-size:11.5px;color:var(--s500)"><?=$p['last']?date('M j, Y',strtotime($p['last'])):'—'?></td>
          <td><?=dpill($p['status'])?></td>
        </tr>
        <?php endforeach;?>
        <?php if(!$patMap):?>
        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-user-slash"></i><p>No patients yet</p></div></td></tr>
        <?php endif;?>
        </tbody>
      </table>
    </div>
  </div>
</div><!-- /patients -->

<!-- SCHEDULE -->
<div class="pg <?=$tab==='schedule'?'on':''?>" id="pg-schedule">
  <div class="pg-hdr">
    <div class="pg-title"><i class="fa-solid fa-clock"></i>My Schedule</div>
    <div class="pg-sub">Set your availability for patient bookings</div>
  </div>
  <div class="alert" id="sched-alert"></div>
  <?php
  $avail = !empty($doc['availability']) ? (json_decode($doc['availability'],true)??[]) : [];
  $days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
  ?>
  <div class="card"><div class="card-hdr"><div class="card-title"><i class="fa-solid fa-calendar-week"></i>Weekly Availability</div></div><div class="card-body" style="padding:16px">
    <?php foreach($days as $i=>$d):
      $on   = !empty($avail[$d]);
      $from = $avail[$d]['from'] ?? '08:00';
      $to   = $avail[$d]['to']   ?? '17:00';
    ?>
    <div class="sched-day">
      <div class="sched-day-hdr">
        <span class="sched-day-name"><?=$d?></span>
        <div style="display:flex;align-items:center;gap:10px">
          <span id="status-<?=$i?>" style="font-size:11px;font-weight:700;color:<?=$on?'var(--green)':'var(--s400)'?>"><?=$on?'Available':'Unavailable'?></span>
          <button class="toggle-sw <?=$on?'on':''?>" id="toggle-<?=$i?>" onclick="toggleDay(<?=$i?>,this)" aria-label="Toggle <?=$d?>"></button>
        </div>
      </div>
      <div id="slots-<?=$i?>" style="display:<?=$on?'flex':'none'?>;align-items:center;gap:12px;padding:10px 16px;flex-wrap:wrap">
        <div><label style="font-size:10.5px;font-weight:700;color:var(--s500);display:block;margin-bottom:3px">From</label><input type="time" class="fi" id="from-<?=$i?>" value="<?=$from?>" style="width:130px"></div>
        <div><label style="font-size:10.5px;font-weight:700;color:var(--s500);display:block;margin-bottom:3px">To</label><input type="time" class="fi" id="to-<?=$i?>" value="<?=$to?>" style="width:130px"></div>
      </div>
    </div>
    <?php endforeach;?>
    <div style="margin-top:14px;display:flex;justify-content:flex-end">
      <button class="btn btn-primary" onclick="saveSchedule()"><i class="fa-solid fa-save"></i> Save Schedule</button>
    </div>
  </div></div>
</div><!-- /schedule -->

<!-- ANALYTICS -->
<div class="pg <?=$tab==='analytics'?'on':''?>" id="pg-analytics">
  <div class="pg-hdr">
    <div class="pg-title"><i class="fa-solid fa-chart-line"></i>Analytics</div>
    <div class="pg-sub">Your practice performance overview</div>
  </div>
  <?php
  $totalAll = count($allAppts);
  $statuses = ['scheduled','confirmed','completed','cancelled','no_show'];
  ?>
  <div class="g2">
    <div class="card"><div class="card-hdr"><div class="card-title"><i class="fa-solid fa-chart-bar"></i>Appointment Breakdown</div></div><div class="card-body" style="padding:18px">
      <div class="bar-wrap">
        <?php foreach($statuses as $s):
          $c = count(array_filter($allAppts,fn($a)=>($a['status']??'')===$s));
          $pct = $totalAll>0 ? round($c/$totalAll*100) : 0;
        ?>
        <div class="bar-row">
          <div class="bar-lbl"><?=ucfirst($s)?></div>
          <div class="bar-bg"><div class="bar-fill" style="width:<?=$pct?>%"></div></div>
          <div class="bar-val"><?=$c?></div>
        </div>
        <?php endforeach;?>
      </div>
    </div></div>

    <div class="card"><div class="card-hdr"><div class="card-title"><i class="fa-solid fa-circle-info"></i>Practice Summary</div></div><div class="card-body" style="padding:18px">
      <?php $rows=[
        ['Total Appointments',$totalAll,'fa-calendar-check'],
        ['Completion Rate',($totalAll>0?round(count($completed)/$totalAll*100):0).'%','fa-check-double'],
        ['Cancellation Rate',($totalAll>0?round(count($cancelled)/$totalAll*100):0).'%','fa-circle-xmark'],
        ['Avg Rating',number_format((float)($doc['rating']??0),1).' / 5.0','fa-star'],
        ['Consultation Fee','KES '.number_format($fee,0),'fa-money-bill'],
        ['Est. Revenue This Month','KES '.number_format($estRevenue,0),'fa-chart-line'],
      ]; foreach($rows as [$lb,$val,$ic]):?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--s100);font-size:12.5px">
        <span style="color:var(--s500);display:flex;align-items:center;gap:7px"><i class="fa-solid <?=$ic?>" style="width:14px;color:var(--blue2);font-size:11px"></i><?=$lb?></span>
        <span style="font-weight:700;color:var(--s900)"><?=$val?></span>
      </div>
      <?php endforeach;?>
    </div></div>
  </div>
</div><!-- /analytics -->

<!-- NOTIFICATIONS -->
<div class="pg <?=$tab==='notifications'?'on':''?>" id="pg-notifications">
  <div class="pg-hdr">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div><div class="pg-title"><i class="fa-solid fa-bell"></i>Notifications</div><div class="pg-sub"><?=$unread?> unread</div></div>
      <?php if($unread>0):?>
      <button class="btn btn-outline btn-sm" onclick="markAllRead()"><i class="fa-solid fa-check-double"></i> Mark all read</button>
      <?php endif;?>
    </div>
  </div>
  <div class="card">
    <div class="card-body">
      <?php if($notifs): foreach($notifs as $n): $t=$n['type']??'system'; $unr=!$n['is_read'];?>
      <div class="notif-row <?=$unr?'unread':''?>">
        <?php if($unr):?><div class="unread-dot"></div><?php else:?><div style="width:7px;flex-shrink:0"></div><?php endif;?>
        <div class="notif-ic <?=$t?>">
          <i class="fa-solid <?=match($t){'appointment'=>'fa-calendar-check','cancellation'=>'fa-circle-xmark','reschedule'=>'fa-calendar-pen','message'=>'fa-comment-dots',default=>'fa-info-circle'}?>"></i>
        </div>
        <div class="notif-body">
          <div class="notif-title"><?=htmlspecialchars($n['title'])?></div>
          <div class="notif-msg"><?=htmlspecialchars($n['message'])?></div>
          <div class="notif-time"><?=date('M j, Y g:ia',strtotime($n['created_at']))?></div>
        </div>
      </div>
      <?php endforeach; else:?>
      <div class="empty-state" style="padding:40px"><i class="fa-regular fa-bell-slash"></i><p>No notifications yet</p></div>
      <?php endif;?>
    </div>
  </div>
</div><!-- /notifications -->

<!-- SETTINGS -->
<div class="pg <?=$tab==='settings'?'on':''?>" id="pg-settings">
  <div class="pg-hdr">
    <div class="pg-title"><i class="fa-solid fa-gear"></i>Settings</div>
    <div class="pg-sub">Manage your profile and account preferences</div>
  </div>
  <div class="alert" id="settings-alert"></div>
  <div class="settings-wrap">
    <div>
      <!-- Avatar -->
      <div class="scard" style="margin-bottom:14px">
        <div class="scard-hdr"><i class="fa-solid fa-camera"></i><div><div class="scard-title">Profile Photo</div></div></div>
        <div class="scard-body" style="text-align:center">
          <div style="position:relative;display:inline-block;cursor:pointer;margin-bottom:12px" onclick="document.getElementById('avatarInput').click()">
            <?php if($hasAv):?>
            <img id="avPreview" src="<?=$avSrc?>" style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid var(--blue-20)">
            <?php else:?>
            <div id="avPreview" style="width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,#1e40af,#1978e5);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:900;color:#fff;border:3px solid var(--blue-20)"><?=$initials?></div>
            <?php endif;?>
            <div style="position:absolute;bottom:0;right:0;width:26px;height:26px;border-radius:50%;background:var(--blue);border:2px solid #fff;display:flex;align-items:center;justify-content:center"><i class="fa-solid fa-camera" style="font-size:10px;color:#fff"></i></div>
          </div>
          <input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="uploadAvatar(this)">
          <div id="avatarProgress" style="display:none;font-size:11px;color:var(--s500);margin-top:6px"><i class="fa-solid fa-circle-notch fa-spin"></i> Uploading…</div>
          <div style="font-size:11.5px;color:var(--s500)">Click to change photo</div>
        </div>
      </div>
      <!-- Nav shortcuts -->
      <div class="scard">
        <div class="scard-hdr"><i class="fa-solid fa-list"></i><div><div class="scard-title">Quick Nav</div></div></div>
        <div style="padding:8px">
          <?php foreach([['overview','fa-gauge','Dashboard'],['appointments','fa-calendar-check','Appointments'],['patients','fa-users','Patients'],['schedule','fa-clock','Schedule'],['analytics','fa-chart-line','Analytics']] as [$pg,$ic,$lb]):?>
          <button class="sb-link" onclick="nav('<?=$pg?>')" style="width:100%"><i class="fa-solid <?=$ic?>"></i><span><?=$lb?></span></button>
          <?php endforeach;?>
        </div>
      </div>
    </div>

    <div class="settings-content">
      <!-- Profile -->
      <div class="scard">
        <div class="scard-hdr"><i class="fa-solid fa-user"></i><div><div class="scard-title">Personal Information</div><div class="scard-sub">Your public profile details</div></div></div>
        <div class="scard-body">
          <div class="field-row">
            <div class="field"><label>First Name</label><input class="fi" id="s_first_name" type="text" value="<?=htmlspecialchars($doc['first_name']??'')?>"></div>
            <div class="field"><label>Last Name</label><input class="fi" id="s_last_name" type="text" value="<?=htmlspecialchars($doc['last_name']??'')?>"></div>
          </div>
          <div class="field-row">
            <div class="field"><label>Specialty</label><input class="fi" id="s_specialty" type="text" value="<?=htmlspecialchars($doc['specialty']??'')?>"></div>
            <div class="field"><label>Years Experience</label><input class="fi" id="s_years_experience" type="number" min="0" max="60" value="<?=(int)($doc['years_experience']??$doc['years_exp']??0)?>"></div>
          </div>
          <div class="field-row">
            <div class="field"><label>Phone</label><input class="fi" id="s_phone" type="tel" value="<?=htmlspecialchars($doc['phone']??'')?>"></div>
            <div class="field"><label>Consultation Fee (KES)</label><input class="fi" id="s_consultation_fee" type="number" min="0" value="<?=(float)($doc['consultation_fee']??$doc['consult_fee']??0)?>"></div>
          </div>
          <!-- Fee display badge -->
          <div class="fee-badge" style="margin-bottom:10px">
            <i class="fa-solid fa-credit-card" style="color:var(--primary);font-size:1rem"></i>
            <div>
              <div class="fee-badge-val" id="feeBadgeVal">KES <?=number_format((float)($doc['consultation_fee']??$doc['consult_fee']??0),0)?></div>
              <div class="fee-badge-lbl">Per consultation &middot; shown to patients during booking</div>
            </div>
          </div>
          <div class="field-row">
            <div class="field"><label>City</label><input class="fi" id="s_city" type="text" value="<?=htmlspecialchars($doc['city']??'')?>"></div>
            <div class="field"><label>County</label><input class="fi" id="s_address" type="text" value="<?=htmlspecialchars($doc['county']??$doc['address']??'')?>"></div>
          </div>
          <!-- Geofencing -->
          <div style="background:#f8fafc;border:1px solid var(--s200);border-radius:10px;padding:14px;margin-bottom:12px">
            <div style="font-size:.8125rem;font-weight:700;color:var(--s700);margin-bottom:10px;display:flex;align-items:center;gap:6px">
              <i class="fa-solid fa-location-dot" style="color:var(--primary)"></i> Location &amp; Geofencing
            </div>
            <div class="field-row" style="margin-bottom:8px">
              <div class="field" style="margin-bottom:0">
                <label>Practice Address</label>
                <input class="fi" id="s_practice_address" type="text" value="<?=htmlspecialchars($doc['address']??'')?>" placeholder="e.g. Westlands Medical Centre">
              </div>
              <div class="field" style="margin-bottom:0">
                <label>Visibility Radius</label>
                <select class="fi" id="s_geo_radius" style="height:40px">
                  <option value="5">5 km &mdash; Very local</option>
                  <option value="10">10 km &mdash; Nearby</option>
                  <option value="25" selected>25 km &mdash; City-wide</option>
                  <option value="50">50 km &mdash; County-wide</option>
                  <option value="0">No limit &mdash; All Kenya</option>
                </select>
              </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <button type="button" class="btn btn-ghost btn-sm" onclick="detectDocLocation()" style="font-size:.75rem">
                <i class="fa-solid fa-crosshairs"></i> Auto-detect Location
              </button>
              <span class="geo-coords" id="docGeoCoords" style="display:none"></span>
            </div>
            <div class="geo-map-box" style="margin-top:10px">
              <i class="fa-solid fa-map-location-dot"></i>
              <span>Your location pin appears here</span>
              <span class="geo-coords" id="docGeoDisplay">Set your location to appear in nearby searches</span>
            </div>
            <input type="hidden" id="s_lat" value="<?=htmlspecialchars($doc['latitude']??$doc['lat']??'')?>">
            <input type="hidden" id="s_lng" value="<?=htmlspecialchars($doc['longitude']??$doc['lng']??'')?>">
          </div>
          <div class="field"><label>Languages Spoken</label><input class="fi" id="s_languages" type="text" placeholder="e.g. English, Swahili" value="<?=htmlspecialchars($doc['languages']??'')?>"></div>
          <div class="field"><label>Professional Bio</label><textarea class="fi fi-ta" id="s_bio" rows="3"><?=htmlspecialchars($doc['bio']??'')?></textarea></div>
          <div class="field"><label>KMPDC Licence No.</label><input class="fi" id="s_kmpdc" type="text" value="<?=htmlspecialchars($doc['kmpdc_licence']??'')?>"></div>
          <div style="margin-top:4px"><button class="btn btn-primary" onclick="saveProfile()"><i class="fa-solid fa-save"></i> Save Changes</button></div>
        </div>
      </div>

      <!-- Security -->
      <div class="scard">
        <div class="scard-hdr"><i class="fa-solid fa-shield-halved"></i><div><div class="scard-title">Account Security</div><div class="scard-sub">Email address and credentials</div></div></div>
        <div class="scard-body">
          <div class="field"><label>Email Address</label><input class="fi" type="email" value="<?=htmlspecialchars($doc['email']??'')?>" disabled style="opacity:.7;cursor:not-allowed"></div>
          <div style="font-size:11.5px;color:var(--s500);margin-top:4px"><i class="fa-solid fa-lock" style="font-size:10px;margin-right:4px"></i>Contact support to change your email address.</div>
        </div>
      </div>

      <!-- Danger Zone -->
      <div class="danger-zone">
        <h4 style="font-size:13px;font-weight:800;color:#991b1b;margin-bottom:5px;display:flex;align-items:center;gap:6px"><i class="fa-solid fa-triangle-exclamation"></i>Danger Zone</h4>
        <p style="font-size:12px;color:#7f1d1d;margin-bottom:12px">Permanently delete your account, profile, and all associated data. This action cannot be undone.</p>
        <button onclick="openDelDocModal()" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Delete My Account</button>
      </div>
    </div>
  </div>
</div><!-- /settings -->

</main>
</div><!-- /wrap -->

<!-- Message panel -->
<div class="msg-ov" id="msgOv" onclick="closeMsg()"></div>
<div class="msg-panel" id="msgPanel">
  <div class="msg-hdr">
    <button onclick="closeMsg()" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i></button>
    <div style="flex:1">
      <div style="font-size:13px;font-weight:700" id="msgPatName">Patient</div>
      <div style="font-size:10.5px;color:var(--s400)">Appointment Chat</div>
    </div>
    <i class="fa-solid fa-circle-notch fa-spin" id="msgSpinner" style="display:none;color:var(--blue);font-size:13px"></i>
  </div>
  <div class="msg-body" id="msgBody"><div style="text-align:center;color:var(--s400);font-size:12.5px;margin:auto">Loading messages…</div></div>
  <div class="msg-foot">
    <textarea class="msg-inp" id="msgInp" rows="1" placeholder="Type a message…" onkeydown="msgKey(event)"></textarea>
    <button class="msg-send" onclick="sendMsg()"><i class="fa-solid fa-paper-plane" style="font-size:13px"></i></button>
  </div>
</div>

<!-- Cancel modal -->
<div class="modal-ov" id="modalCancel">
  <div class="modal">
    <div class="modal-hdr">
      <div class="modal-title" style="color:var(--red)"><i class="fa-solid fa-circle-xmark"></i>Cancel Appointment</div>
      <button class="modal-close" onclick="closeModal('modalCancel')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div id="cancelInfo" style="background:var(--s50);border-radius:var(--r);padding:11px;margin-bottom:14px;font-size:12.5px;color:var(--s700)"></div>
      <div class="field"><label>Reason (optional)</label><textarea class="fi fi-ta" id="cancelReason" rows="3" placeholder="e.g. Doctor unavailable…"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modalCancel')">Keep Appointment</button>
      <button class="btn" style="background:var(--red);color:#fff;border-color:var(--red)" onclick="doCancelAppt()"><i class="fa-solid fa-xmark"></i> Cancel Appointment</button>
    </div>
  </div>
</div>

<!-- Reschedule modal -->
<div class="modal-ov" id="modalResched">
  <div class="modal">
    <div class="modal-hdr">
      <div class="modal-title"><i class="fa-solid fa-calendar-pen" style="color:var(--blue2)"></i>Reschedule Appointment</div>
      <button class="modal-close" onclick="closeModal('modalResched')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div id="reschedInfo" style="background:var(--s50);border-radius:var(--r);padding:11px;margin-bottom:14px;font-size:12.5px;color:var(--s700)"></div>
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:var(--r);padding:10px 12px;margin-bottom:14px;font-size:12px;color:#92400e"><i class="fa-solid fa-triangle-exclamation" style="margin-right:5px"></i>The patient will be notified by email and SMS.</div>
      <div class="field"><label>New Date &amp; Time</label><input type="datetime-local" class="fi" id="newDateTime" min="<?=date('Y-m-d\TH:i')?>"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modalResched')">Cancel</button>
      <button class="btn btn-primary" onclick="doResched()"><i class="fa-solid fa-calendar-check"></i> Confirm Reschedule</button>
    </div>
  </div>
</div>

<!-- Delete Account modal -->
<div class="modal-ov" id="delDocModal">
  <div class="modal">
    <div class="modal-hdr">
      <div class="modal-title" style="color:var(--red)"><i class="fa-solid fa-triangle-exclamation"></i>Delete Account</div>
      <button class="modal-close" onclick="closeModal('delDocModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--s600);margin-bottom:16px;line-height:1.6">All your appointments, profile, and data will be permanently deleted. This cannot be undone.</p>
      <div id="delDocErr" style="display:none;background:#fef2f2;border:1px solid #fca5a5;border-radius:var(--r);padding:10px 12px;font-size:12.5px;color:#991b1b;margin-bottom:12px"></div>
      <div class="field"><label>Your Password</label><input type="password" class="fi" id="delDocPass" placeholder="Enter your password to confirm"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('delDocModal')">Cancel</button>
      <button class="btn" id="delDocBtn" style="background:var(--red);color:#fff;border-color:var(--red)" onclick="confirmDelDoc()"><i class="fa-solid fa-trash"></i> Delete Forever</button>
    </div>
  </div>
</div>

<input type="hidden" id="csrf" value="<?=htmlspecialchars($csrf)?>">

<script>
/**
 * Planeazzy Doctor Dashboard Logic
 */
document.addEventListener('DOMContentLoaded', function() {
    //  1. Constants & State 
    const csrfEl = document.getElementById('csrf');
    const CSRF = csrfEl ? csrfEl.value : '';
    const MSGAPI = '/api/appointment-messages.php';
    
    let activeApptId = null;
    let _msgApptId = 0;
    let _msgType = 'standard';
    let _msgPoll = null;

    //  2. Navigation 
    window.nav = function(name) {
        // Update Page Visibility
        document.querySelectorAll('.pg').forEach(p => p.classList.remove('on'));
        const targetPg = document.getElementById('pg-' + name);
        if (targetPg) targetPg.classList.add('on');

        // Update Sidebar UI
        document.querySelectorAll('.sb-link').forEach(l => {
            l.classList.remove('on');
            const attr = l.getAttribute('onclick') || '';
            if (attr.includes("'" + name + "'") || attr.includes('"' + name + '"')) {
                l.classList.add('on');
            }
        });

        // Browser State & Mobile UI
        history.replaceState(null, '', '?tab=' + name);
        window.closeSb();
    };

    //  3. Sidebar 
    window.openSb = function() {
        document.getElementById('sidebar').classList.add('open');
        document.getElementById('sbOv').classList.add('open');
    };
    
    window.closeSb = function() {
        const sb = document.getElementById('sidebar');
        const ov = document.getElementById('sbOv');
        if(sb) sb.classList.remove('open');
        if(ov) ov.classList.remove('open');
    };

    //  4. Filtering 
    window.filterTable = function(id, q) {
        q = q.toLowerCase();
        document.querySelectorAll('#' + id + ' tbody tr').forEach(r => {
            r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    };

    window.searchAppts = function(q) { 
        window.filterTable('apptTbl', q); 
    };

    window.filterAppts = function(type, btn) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('on'));
        if(btn) btn.classList.add('on');
        document.querySelectorAll('#apptTbl tbody tr').forEach(r => {
            r.style.display = (type === 'all' || r.dataset.filter === type) ? '' : 'none';
        });
    };

    //  5. Modals 
    window.openModal = function(id) {
        const el = document.getElementById(id);
        if(el) {
            el.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(id) {
        const el = document.getElementById(id);
        if(el) {
            el.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    // Close modal on backdrop click
    document.querySelectorAll('.modal-ov').forEach(m => {
        m.addEventListener('click', function(e) {
            if (e.target === m) window.closeModal(m.id);
        });
    });

    //  6. API Helper 
    window.api = async function(url, data) {
        try {
            const r = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ csrf_token: CSRF, ...data })
            });
            return await r.json();
        } catch (e) {
            console.error("API Error:", e);
            return { success: false, message: "Network or Server Error" };
        }
    };

    //  7. Appointment Actions 
    window.openCancel = function(id, name, dt) {
        activeApptId = id;
        document.getElementById('cancelInfo').innerHTML = `<strong>${window.esc(name)}</strong> — ${window.esc(dt)}`;
        document.getElementById('cancelReason').value = '';
        window.openModal('modalCancel');
    };

    window.openReschedule = function(id, name, dt) {
        activeApptId = id;
        const displayDate = dt ? new Date(dt).toLocaleString() : 'N/A';
        document.getElementById('reschedInfo').innerHTML = `<strong>${window.esc(name)}</strong> — currently: ${window.esc(displayDate)}`;
        document.getElementById('newDateTime').value = '';
        window.openModal('modalResched');
    };

    window.confirmAppt = async function(id) {
        const res = await window.api('/api/doctor/manage-appointment.php', { action: 'confirm', appointment_id: id });
        window.showAlert('appt-alert', res.message || 'Done', res.success);
        if (res.success) setTimeout(() => location.reload(), 900);
    };

    window.completeAppt = async function(id) {
        const res = await window.api('/api/doctor/manage-appointment.php', { action: 'complete', appointment_id: id });
        window.showAlert('appt-alert', res.message || 'Done', res.success);
        if (res.success) setTimeout(() => location.reload(), 900);
    };

    window.doCancelAppt = async function() {
        const reason = document.getElementById('cancelReason').value.trim();
        const res = await window.api('/api/doctor/manage-appointment.php', { action: 'cancel', appointment_id: activeApptId, reason });
        window.closeModal('modalCancel');
        window.showAlert('appt-alert', res.message || 'Done', res.success);
        if (res.success) setTimeout(() => location.reload(), 1200);
    };

    window.doResched = async function() {
        const dt = document.getElementById('newDateTime').value;
        if (!dt) { window.showAlert('appt-alert', 'Please select a new date and time.', false); return; }
        const res = await window.api('/api/doctor/manage-appointment.php', { action: 'reschedule', appointment_id: activeApptId, new_datetime: dt });
        window.closeModal('modalResched');
        window.showAlert('appt-alert', res.message || 'Done', res.success);
        if (res.success) setTimeout(() => location.reload(), 1200);
    };

    window.showAlert = function(id, msg, ok) {
        const el = document.getElementById(id);
        if (!el) return;
        el.className = 'alert ' + (ok ? 'ok' : 'err');
        el.innerHTML = (ok ? '<i class="fa-solid fa-check-circle"></i>' : '<i class="fa-solid fa-circle-xmark"></i>') + ' ' + window.esc(msg);
        el.style.display = 'flex';
        if (ok) setTimeout(() => el.style.display = 'none', 3500);
    };

    //  8. Messaging 
    window.openMsg = function(apptId, type, patName) {
        _msgApptId = apptId;
        _msgType = type;
        document.getElementById('msgPatName').textContent = patName;
        document.getElementById('msgPanel').classList.add('open');
        document.getElementById('msgOv').classList.add('open');
        document.body.style.overflow = 'hidden';
        window.loadMsgs();
        if(_msgPoll) clearInterval(_msgPoll);
        _msgPoll = setInterval(window.loadMsgs, 8000);
    };

    window.closeMsg = function() {
        document.getElementById('msgPanel').classList.remove('open');
        document.getElementById('msgOv').classList.remove('open');
        document.body.style.overflow = '';
        if(_msgPoll) { clearInterval(_msgPoll); _msgPoll = null; }
    };

    window.loadMsgs = async function() {
        if (!_msgApptId) return;
        const spin = document.getElementById('msgSpinner');
        if (spin) spin.style.display = 'inline';
        try {
            const r = await fetch(MSGAPI + '?appt_id=' + _msgApptId + '&appt_type=' + _msgType, { credentials: 'same-origin' });
            const j = await r.json();
            if (j.ok) window.renderMsgs(j.messages || []);
        } catch (e) {
            console.error("Msg Load Error", e);
        } finally {
            if (spin) spin.style.display = 'none';
        }
    };

    window.renderMsgs = function(msgs) {
        const body = document.getElementById('msgBody');
        if (!msgs.length) {
            body.innerHTML = '<div style="text-align:center;color:var(--s400);font-size:12.5px;margin:auto;padding:20px">No messages yet.</div>';
            return;
        }
        const atBot = body.scrollHeight - body.scrollTop - body.clientHeight < 60;
        body.innerHTML = msgs.map(m => {
            const mine = m.sender_type === 'doctor';
            const t = new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            return `<div style="display:flex;flex-direction:column;align-items:${mine ? 'flex-end' : 'flex-start'}">
                <div class="msg-bubble ${mine ? 'msg-mine' : 'msg-theirs'}">
                <div class="msg-sender">${mine ? 'You' : window.esc(m.sender_name || 'Patient')}</div>
                ${window.esc(m.message)}
                <div class="msg-time">${t}</div>
                </div></div>`;
        }).join('');
        if (atBot || msgs.length <= 3) body.scrollTop = body.scrollHeight;
    };

    window.sendMsg = async function() {
        const inp = document.getElementById('msgInp');
        const msg = inp.value.trim();
        if (!msg || !_msgApptId) return;
        inp.value = '';
        inp.style.height = 'auto';
        try {
            const r = await fetch(MSGAPI, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ appt_id: _msgApptId, appt_type: _msgType, message: msg })
            });
            const j = await r.json();
            if (j.ok) window.loadMsgs();
            else window.showAlert('appt-alert', j.msg || 'Send failed', false);
        } catch(e) {
            window.showAlert('appt-alert', 'Network error sending message', false);
        }
    };

    window.msgKey = function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            window.sendMsg();
        }
        e.target.style.height = 'auto';
        e.target.style.height = Math.min(e.target.scrollHeight, 80) + 'px';
    };

    window.esc = function(t) {
        return String(t || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    };

    //  9. Settings & Schedule 
    window.saveProfile = async function() {
        const fee = document.getElementById('s_consultation_fee').value;
        // Update fee badge live
        const feeB = document.getElementById('feeBadgeVal');
        if (feeB && fee) feeB.textContent = 'KES ' + parseInt(fee).toLocaleString();
        const data = {
            first_name: document.getElementById('s_first_name').value.trim(),
            last_name: document.getElementById('s_last_name').value.trim(),
            phone: document.getElementById('s_phone').value.trim(),
            specialty: document.getElementById('s_specialty').value.trim(),
            years_experience: document.getElementById('s_years_experience').value,
            consultation_fee: fee,
            languages: document.getElementById('s_languages').value.trim(),
            bio: document.getElementById('s_bio').value.trim(),
            city: document.getElementById('s_city').value.trim(),
            address: document.getElementById('s_address').value.trim(),
            practice_address: document.getElementById('s_practice_address')?.value.trim() || '',
            lat: document.getElementById('s_lat')?.value || null,
            lng: document.getElementById('s_lng')?.value || null,
            geo_radius: document.getElementById('s_geo_radius')?.value || '25',
        };
        const res = await window.api('/api/doctor/update-profile.php', data);
        window.showAlert('settings-alert', res.message || 'Saved', res.success);
    };

    window.detectDocLocation = function() {
        if (!navigator.geolocation) { window.showAlert('settings-alert','Geolocation not supported.',false); return; }
        navigator.geolocation.getCurrentPosition(pos => {
            const { latitude: lat, longitude: lng } = pos.coords;
            document.getElementById('s_lat').value = lat;
            document.getElementById('s_lng').value = lng;
            const coord = lat.toFixed(4) + ', ' + lng.toFixed(4);
            const el = document.getElementById('docGeoCoords');
            const disp = document.getElementById('docGeoDisplay');
            if (el) { el.textContent = coord; el.style.display = 'inline'; }
            if (disp) disp.textContent = 'Location set: ' + coord;
            window.showAlert('settings-alert','Location detected. Save changes to update.', true);
        }, () => window.showAlert('settings-alert','Could not detect location. Enter address manually.',false));
    };

    window.uploadAvatar = async function(input) {
        const file = input.files[0];
        if (!file) return;
        document.getElementById('avatarProgress').style.display = 'block';
        const fd = new FormData();
        fd.append('avatar', file);
        try {
            const r = await fetch('/api/doctor/upload-avatar.php', { 
                method: 'POST', 
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
                credentials: 'same-origin', 
                body: fd 
            });
            const res = await r.json();
            document.getElementById('avatarProgress').style.display = 'none';
            if (res.success) {
                location.reload(); // Refresh to update all avatar instances
            } else {
                window.showAlert('settings-alert', res.message || 'Upload failed', false);
            }
        } catch (e) {
            document.getElementById('avatarProgress').style.display = 'none';
            window.showAlert('settings-alert', 'Upload error. Please try again.', false);
        }
    };

    window.toggleDay = function(i, btn) {
        btn.classList.toggle('on');
        const s = document.getElementById('slots-' + i);
        const st = document.getElementById('status-' + i);
        const on = btn.classList.contains('on');
        if(s) s.style.display = on ? 'flex' : 'none';
        if(st) {
            st.textContent = on ? 'Available' : 'Unavailable';
            st.style.color = on ? 'var(--green)' : 'var(--s400)';
        }
    };

    window.saveSchedule = async function() {
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        const schedule = days.map((d, i) => ({
            day: i + 1,
            name: d,
            available: document.getElementById('toggle-' + i).classList.contains('on'),
            from: document.getElementById('from-' + i)?.value || '08:00',
            to: document.getElementById('to-' + i)?.value || '17:00'
        }));
        const res = await window.api('/api/doctor/save-schedule.php', { schedule });
        window.showAlert('sched-alert', res.message || 'Schedule saved!', res.success);
    };

    window.markAllRead = async function() {
        await fetch('/api/doctor/mark-notifications-read.php', { 
            method: 'POST', 
            credentials: 'same-origin', 
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, 
            body: JSON.stringify({ csrf_token: CSRF }) 
        });
        document.querySelectorAll('.notif-row.unread').forEach(n => n.classList.remove('unread'));
        document.querySelectorAll('.unread-dot').forEach(d => d.remove());
        const hDot = document.querySelector('.notif-dot');
        if (hDot) hDot.remove();
    };

    window.openDelDocModal = function() {
        window.openModal('delDocModal');
        document.getElementById('delDocPass').value = '';
        document.getElementById('delDocErr').style.display = 'none';
    };

    window.confirmDelDoc = async function() {
        const pass = document.getElementById('delDocPass').value;
        const err = document.getElementById('delDocErr');
        const btn = document.getElementById('delDocBtn');
        err.style.display = 'none';
        if (!pass) { err.textContent = 'Password required.'; err.style.display = 'block'; return; }
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Deleting…';
        try {
            const r = await fetch('/api/auth/delete-account.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ csrf_token: CSRF, password: pass })
            });
            const j = await r.json();
            if (j.success) {
                window.location.href = j.redirect || '/doctors/onboarding/login.php';
            } else {
                err.textContent = j.message || 'Error.';
                err.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Forever';
            }
        } catch (e) {
            err.textContent = 'Network error.';
            err.style.display = 'block';
            btn.disabled = false;
        }
    };
});
</script>
</body>
</html>
