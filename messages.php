<?php
/**
 * Planeazzy — Appointment Messaging Page
 * Works for hospital / doctor / patient portals
 * URL: /messages.php?appt_id=N&appt_type=hospital|standard&back=/path
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/services/Security.php';
require_once __DIR__ . '/services/Database.php';
Security::startSession();

/*  Auth  */
$portal=''; $myId=0; $myType=''; $myName=''; $myAvatar=null;
if (!empty($_SESSION['hospital_id']) && !empty($_SESSION['hospital_auth'])) {
    $portal='hospital'; $myId=(int)$_SESSION['hospital_id']; $myType='hospital';
} elseif (!empty($_SESSION['doctor_id']) && !empty($_SESSION['is_doctor'])) {
    $portal='doctor'; $myId=(int)$_SESSION['doctor_id']; $myType='doctor';
} elseif (!empty($_SESSION['patient_id']) && !empty($_SESSION['authenticated'])) {
    $portal='patient'; $myId=(int)$_SESSION['patient_id']; $myType='patient';
} else { header('Location: /'); exit; }

$db = Database::getInstance();
try { $db->query("CREATE TABLE IF NOT EXISTS appointment_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    appt_id INT UNSIGNED NOT NULL,
    appt_type ENUM('hospital','standard') NOT NULL DEFAULT 'hospital',
    sender_type ENUM('patient','hospital','doctor') NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    sender_name VARCHAR(120) NOT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_appt (appt_id, appt_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e) {}

/*  Params  */
$apptId   = (int)($_GET['appt_id'] ?? 0);
$apptType = ($_GET['appt_type'] ?? '') === 'standard' ? 'standard' : 'hospital';
$customBack = trim($_GET['back'] ?? '');
$defaultBack = match($portal) {
    'hospital' => '/hospital/onboarding/dashboard.php?tab=appointments',
    'doctor'   => '/doctors/dashboard.php?tab=appointments',
    default    => '/patients/dashboard.php?tab=appointments',
};
$backUrl = ($customBack && str_starts_with($customBack,'/')) ? $customBack : $defaultBack;
if (!$apptId) { header('Location:'.$backUrl); exit; }

/*  My profile  */
if ($portal==='hospital') {
    $me=$db->fetchOne('SELECT facility_name name,logo_path avatar,county sub,facility_type FROM hospital_providers WHERE id=:id',[':id'=>$myId]);
} elseif ($portal==='doctor') {
    $me=$db->fetchOne('SELECT CONCAT(first_name," ",last_name) name,avatar_path avatar,specialty sub FROM doctors WHERE id=:id',[':id'=>$myId]);
    if ($me) $me['name']='Dr.'.$me['name'];
} else {
    $me=$db->fetchOne('SELECT CONCAT(first_name," ",last_name) name,avatar_path avatar,phone sub FROM patients WHERE id=:id',[':id'=>$myId]);
}
$myName=$me['name']??ucfirst($portal);
$myAvatar=$me['avatar']??null;

/*  Appointment + all parties  */
$appt=null;
$otherName=''; $otherAvatar=null; $otherSpec=''; $otherType=''; $otherPhone=''; $otherEmail='';
$patientName=''; $patientPhone=''; $patientEmail='';
$doctorName=''; $doctorSpec=''; $doctorAvatar=null; $doctorPhone=''; $doctorEmail=''; $doctorLicence='';
$hospName=''; $hospLogo=null; $hospCounty=''; $hospPhone='';
$apptDate=''; $apptStatus='pending'; $apptDept=''; $apptVisitType=''; $apptNotes='';

if ($apptType==='hospital') {
    $appt=$db->fetchOne(
        'SELECT ha.*,
                hp.facility_name,hp.logo_path hosp_logo,hp.county hosp_county,
                hp.phone hosp_phone,hp.email hosp_email,hp.facility_type,
                hd.name doc_name,hd.avatar_path doc_av,hd.specialty doc_spec,
                hd.email doc_email,hd.phone doc_phone,hd.kmpdc_licence,
                hd.years_exp,hd.consult_fee,hd.education,hd.bio,hd.languages,
                hd.accepts_tele,hd.accepts_walkin,
                dep.name dept_name
         FROM hospital_appointments ha
         LEFT JOIN hospital_providers hp ON hp.id=ha.hospital_id
         LEFT JOIN hospital_doctors hd ON hd.id=ha.doctor_id
         LEFT JOIN hospital_departments dep ON dep.id=hd.department_id
         WHERE ha.id=:id',[':id'=>$apptId]
    );
    if (!$appt) { header('Location:'.$backUrl); exit; }

    // Access check
    $ok=false;
    if ($portal==='hospital') $ok=($appt['hospital_id']==$myId);
    elseif ($portal==='doctor') $ok=($appt['doctor_id']==$myId);
    elseif ($portal==='patient') {
        $pe=$db->fetchOne('SELECT email FROM patients WHERE id=:id',[':id'=>$myId])['email']??'';
        $ok=($appt['patient_email']===$pe);
    }
    if (!$ok) { header('Location:'.$backUrl); exit; }

    // Pull all party data
    $patientName=$appt['patient_name']??'Patient';
    $patientPhone=$appt['patient_phone']??'';
    $patientEmail=$appt['patient_email']??'';
    $doctorName=$appt['doc_name']?'Dr. '.trim($appt['doc_name']):'';
    $doctorSpec=$appt['doc_spec']??'';
    $doctorAvatar=$appt['doc_av']??null;
    $doctorPhone=$appt['doc_phone']??'';
    $doctorEmail=$appt['doc_email']??'';
    $doctorLicence=$appt['kmpdc_licence']??'';
    $hospName=$appt['facility_name']??'Hospital';
    $hospLogo=$appt['hosp_logo']??null;
    $hospCounty=$appt['hosp_county']??'';
    $hospPhone=$appt['hosp_phone']??'';
    $apptDate=$appt['appointment_at']?date('D, M j, Y · g:i A',strtotime($appt['appointment_at'])):'';
    $apptStatus=$appt['status']??'pending';
    $apptDept=$appt['dept_name']??$appt['department']??$doctorSpec;
    $apptVisitType=$appt['visit_type']??'in-person';
    $apptNotes=$appt['notes']??'';

    // Determine who the OTHER party is from this portal's perspective
    if ($portal==='hospital') {
        $otherType='patient'; $otherName=$patientName; $otherPhone=$patientPhone; $otherEmail=$patientEmail;
    } elseif ($portal==='doctor') {
        $otherType='patient'; $otherName=$patientName; $otherPhone=$patientPhone; $otherEmail=$patientEmail;
    } else { // patient
        if ($doctorName) {
            $otherType='doctor'; $otherName=$doctorName; $otherAvatar=$doctorAvatar;
            $otherSpec=$doctorSpec; $otherPhone=$doctorPhone; $otherEmail=$doctorEmail;
        } else {
            $otherType='hospital'; $otherName=$hospName; $otherAvatar=$hospLogo;
            $otherSpec=ucfirst($appt['facility_type']??'Hospital'); $otherPhone=$hospPhone;
        }
    }

} else {
    // Standard appointments table
    $appt=$db->fetchOne(
        'SELECT a.*,
                p.first_name pat_fn,p.last_name pat_ln,p.email pat_email,
                p.phone pat_phone,p.avatar_path pat_av,p.date_of_birth pat_dob,p.gender pat_gender,
                d.first_name doc_fn,d.last_name doc_ln,d.avatar_path doc_av,
                d.specialty,d.email doc_email,d.phone doc_phone,
                d.kmpdc_licence,d.years_exp,d.consult_fee,d.bio,d.education,d.languages,
                pr.name prov_name,pr.type prov_type,pr.description prov_desc,
                pr.phone prov_phone,pr.email prov_email,
                hp.facility_name hosp_name,hp.logo_path hosp_logo,
                hp.phone hosp_phone,hp.county hosp_county
         FROM appointments a
         LEFT JOIN patients p ON p.id=a.patient_id
         LEFT JOIN doctors d ON d.id=a.doctor_id
         LEFT JOIN providers pr ON pr.id=a.provider_id
         LEFT JOIN hospital_providers hp ON hp.id=a.hospital_provider_id
         WHERE a.id=:id',[':id'=>$apptId]
    );
    if (!$appt) { header('Location:'.$backUrl); exit; }

    $ok=false;
    if ($portal==='patient') $ok=($appt['patient_id']==$myId);
    elseif ($portal==='doctor') $ok=($appt['doctor_id']==$myId);
    elseif ($portal==='hospital') $ok=($appt['hospital_provider_id']==$myId);
    if (!$ok) { header('Location:'.$backUrl); exit; }

    $patientName=trim(($appt['pat_fn']??'').' '.($appt['pat_ln']??''))?:'Patient';
    $patientPhone=$appt['pat_phone']??''; $patientEmail=$appt['pat_email']??'';
    $doctorName=($appt['doc_fn']??null)?'Dr. '.trim($appt['doc_fn'].' '.($appt['doc_ln']??'')):'';
    $doctorSpec=$appt['specialty']??''; $doctorAvatar=$appt['doc_av']??null;
    $doctorPhone=$appt['doc_phone']??''; $doctorEmail=$appt['doc_email']??'';
    $doctorLicence=$appt['kmpdc_licence']??'';
    $hospName=$appt['hosp_name']??$appt['prov_name']??'';
    $hospLogo=$appt['hosp_logo']??null; $hospPhone=$appt['hosp_phone']??$appt['prov_phone']??'';
    $apptDate=$appt['appointment_at']?date('D, M j, Y · g:i A',strtotime($appt['appointment_at'])):'';
    $apptStatus=$appt['status']??'scheduled';
    $apptDept=$appt['specialty']??ucfirst($appt['service_type']??'');
    $apptVisitType=$appt['location_type']??'in_person';
    $apptNotes=$appt['notes']??'';

    if ($portal==='patient') {
        if ($doctorName) {
            $otherType='doctor'; $otherName=$doctorName; $otherAvatar=$doctorAvatar;
            $otherSpec=$doctorSpec; $otherPhone=$doctorPhone; $otherEmail=$doctorEmail;
        } else {
            $otherType='hospital'; $otherName=$hospName; $otherAvatar=$hospLogo;
            $otherSpec=ucfirst($appt['prov_type']??'Provider'); $otherPhone=$hospPhone;
        }
    } elseif ($portal==='doctor') {
        $otherType='patient'; $otherName=$patientName; $otherAvatar=$appt['pat_av']??null;
        $otherPhone=$patientPhone; $otherEmail=$patientEmail;
    }
}

/*  Messages  */
$messages=$db->fetchAll(
    'SELECT * FROM appointment_messages WHERE appt_id=:id AND appt_type=:t ORDER BY created_at ASC LIMIT 400',
    [':id'=>$apptId,':t'=>$apptType]
)??[];
try { $db->query('UPDATE appointment_messages SET is_read=1 WHERE appt_id=:id AND appt_type=:t AND sender_type!=:st AND is_read=0',
    [':id'=>$apptId,':t'=>$apptType,':st'=>$myType]); } catch(Exception $e){}

/*  Helpers  */
$csrf=Security::csrfToken();
$ini=fn($n)=>strtoupper(implode('',array_map(fn($w)=>$w[0]??'',array_slice(array_filter(explode(' ',preg_replace('/^Dr\.\s*/i','',$n??'?'))),0,2))));
$myInit=$ini($myName);
$otherInit=$ini($otherName?:'?');
$sCfg=['pending'=>['#fef9c3','#854d0e','clock'],'confirmed'=>['#dcfce7','#166534','circle-check'],
    'completed'=>['#dbeafe','#1e40af','check-double'],'cancelled'=>['#fee2e2','#b91c1c','circle-xmark'],
    'scheduled'=>['#eff6ff','#1d4ed8','clock'],'in_progress'=>['#faf5ff','#7e22ce','spinner']];
[$sBg,$sFg,$sIc]=$sCfg[$apptStatus]??['#f1f5f9','#475569','circle-dot'];

/*  Hospital sidebar  */
$hid=($portal==='hospital')?$myId:0;
$hosp=($portal==='hospital')?$db->fetchOne('SELECT * FROM hospital_providers WHERE id=:id',[':id'=>$hid]):null;
$currentPage='appointments';
if ($portal==='hospital'&&$hosp) require __DIR__.'/hospital/onboarding/_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Chat · <?=htmlspecialchars($otherName)?> · Planeazzy</title>
  <link rel="icon" type="image/png" href="/assets/images/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/clinical.css">
<style>
:root{--blue:#005ab4;--teal:#006a6a;--green:#16a34a;--red:#dc2626;
  --s50:#f8fafc;--s100:#f1f5f9;--s200:#e2e8f0;--s300:#cbd5e1;--s400:#94a3b8;
  --s500:#64748b;--s600:#475569;--s700:#334155;--s900:#0f172a;
  --sb-w:220px;--r:10px;
  --shadow:0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.04);
  --shadow-md:0 4px 12px rgba(0,0,0,.08);}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:var(--s900);height:100vh;overflow:hidden}
a{text-decoration:none;color:inherit}

/* Shell */
.shell{display:flex;height:100vh;overflow:hidden}
.shell-main{flex:1;display:flex;flex-direction:column;min-width:0;
  margin-left:<?=$portal==='hospital'?'var(--sb-w)':'0'?>;}

/* Nav sidebar for doctor/patient */
.nav-sb{position:fixed;left:0;top:0;bottom:0;width:var(--sb-w);background:#fff;
  border-right:1.5px solid var(--s200);display:flex;flex-direction:column;z-index:200;
  transform:<?=$portal!=='hospital'?'translateX(-100%)':'translateX(0)'?>;transition:transform .25s;}
<?php if($portal==='hospital'):?>.nav-sb{display:none}<?php endif;?>
.nav-sb.open{transform:translateX(0)!important}
.nav-brand{padding:15px 16px 11px;border-bottom:1px solid var(--s100)}
.nav-brand-n{font-size:.9375rem;font-weight:900;color:var(--blue);letter-spacing:-.03em}
.nav-brand-s{font-size:.5rem;text-transform:uppercase;letter-spacing:.12em;color:var(--s400);margin-top:1px}
.nav-prof{padding:12px 14px;border-bottom:1px solid var(--s100);display:flex;align-items:center;gap:10px}
.nav-av{width:38px;height:38px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,#0873df,var(--blue));display:flex;align-items:center;justify-content:center;font-size:.8125rem;font-weight:800;color:#fff;flex-shrink:0}
.nav-av img{width:100%;height:100%;object-fit:cover}
.nav-body{flex:1;overflow-y:auto;padding:6px 0}
.nav-sec{font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:var(--s400);padding:10px 14px 4px}
.nav-it{display:flex;align-items:center;gap:9px;padding:8px 12px;margin:1px 7px;border-radius:9px;font-size:.8rem;font-weight:500;color:var(--s600);text-decoration:none;transition:all .12s}
.nav-it:hover{background:var(--s100);color:var(--s900)}
.nav-it.active{background:rgba(0,90,180,.09);color:var(--blue);font-weight:700}
.nav-it i{width:16px;text-align:center;font-size:13px;flex-shrink:0}
.nav-foot{padding:8px 7px;border-top:1px solid var(--s100)}

/* Top bar */
.top-bar{height:54px;background:#fff;border-bottom:1.5px solid var(--s200);
  padding:0 18px;display:flex;align-items:center;gap:10px;flex-shrink:0;position:sticky;top:0;z-index:100;}
.ham{width:32px;height:32px;border-radius:8px;border:1.5px solid var(--s200);background:#fff;cursor:pointer;display:none;align-items:center;justify-content:center;color:var(--s500)}
.ham:hover{background:var(--s100)}
.back-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border:1.5px solid var(--s200);border-radius:8px;font-size:.8125rem;font-weight:600;color:var(--s600);background:#fff;transition:all .15s;flex-shrink:0}
.back-btn:hover{border-color:var(--blue);color:var(--blue)}
.top-who{display:flex;align-items:center;gap:9px;flex:1;min-width:0}
.top-av{width:34px;height:34px;border-radius:50%;overflow:hidden;flex-shrink:0;background:linear-gradient(135deg,#0873df,var(--blue));display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff}
.top-av img{width:100%;height:100%;object-fit:cover}
.top-contact{display:flex;gap:6px;align-items:center;flex-shrink:0}
.icon-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1.5px solid var(--s200);background:#fff;color:var(--s500);cursor:pointer;transition:all .15s;font-size:12px;text-decoration:none}
.icon-btn:hover{border-color:var(--blue);color:var(--blue);background:rgba(0,90,180,.04)}

/* Chat body */
.chat-body{flex:1;display:flex;min-height:0;overflow:hidden}

/* Profile panel */
.profile-panel{width:272px;flex-shrink:0;background:#fff;border-right:1.5px solid var(--s200);
  display:flex;flex-direction:column;overflow:hidden;}
.pp-hero{padding:24px 18px 20px;text-align:center;flex-shrink:0;position:relative;overflow:hidden;
  background:linear-gradient(160deg,var(--blue) 0%,#0c5ecf 50%,#0d73e8 100%);}
.pp-hero::after{content:'';position:absolute;bottom:-24px;right:-24px;width:100px;height:100px;
  border-radius:50%;background:rgba(255,255,255,.05);}
.pp-av-wrap{position:relative;width:80px;height:80px;margin:0 auto 12px;z-index:1}
.pp-av{width:80px;height:80px;border-radius:50%;overflow:hidden;border:3px solid rgba(255,255,255,.3);
  background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;
  font-size:1.625rem;font-weight:800;color:#fff;}
.pp-av img{width:100%;height:100%;object-fit:cover}
.pp-name{font-size:.9375rem;font-weight:800;color:#fff;margin-bottom:2px;position:relative;z-index:1}
.pp-role{font-size:.625rem;color:rgba(255,255,255,.7);margin-bottom:10px;position:relative;z-index:1}
.pp-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:9999px;
  background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);
  font-size:.5625rem;font-weight:700;color:#fff;position:relative;z-index:1;}

/* Profile body scroll */
.pp-body{flex:1;overflow-y:auto;font-size:.8125rem}
.pp-body::-webkit-scrollbar{width:3px}.pp-body::-webkit-scrollbar-thumb{background:var(--s200);border-radius:3px}
.pp-sec{padding:12px 14px;border-bottom:1px solid var(--s100)}
.pp-sec-title{font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:var(--s400);margin-bottom:8px}
.pp-row{display:flex;align-items:flex-start;gap:8px;padding:4px 0}
.pp-row i{color:var(--s400);width:13px;font-size:11px;margin-top:2px;flex-shrink:0;text-align:center}
.pp-val{color:var(--s700);font-weight:500;flex:1;line-height:1.45;word-break:break-word}
.pp-val a{color:var(--blue)}

/* Appointment card in profile */
.appt-card{border-radius:10px;border:1.5px solid var(--s200);overflow:hidden}
.appt-card-head{padding:8px 12px;background:var(--s100);border-bottom:1px solid var(--s200);display:flex;align-items:center;gap:8px}
.appt-card-body{padding:10px 12px;display:flex;flex-direction:column;gap:5px}
.appt-row{display:flex;align-items:flex-start;gap:7px}
.appt-row i{color:var(--s400);width:13px;font-size:11px;margin-top:2px;flex-shrink:0}
.s-pill{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:9999px;font-size:.5625rem;font-weight:700}

/* Thread */
.thread{flex:1;display:flex;flex-direction:column;min-width:0;background:#f8fafc;overflow:hidden}
.th-header{padding:12px 18px;background:#fff;border-bottom:1.5px solid var(--s200);
  display:flex;align-items:center;gap:10px;flex-shrink:0;}
.th-header-av{width:34px;height:34px;border-radius:50%;overflow:hidden;flex-shrink:0;
  background:linear-gradient(135deg,#0873df,var(--blue));display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff;}
.th-header-av img{width:100%;height:100%;object-fit:cover}
.msgs{flex:1;overflow-y:auto;padding:20px 22px;display:flex;flex-direction:column;gap:3px}
.msgs::-webkit-scrollbar{width:3px}.msgs::-webkit-scrollbar-thumb{background:var(--s300);border-radius:3px}

/* Date separator */
.date-sep{text-align:center;margin:12px 0 6px;position:relative}
.date-sep::before{content:'';position:absolute;top:50%;left:0;right:0;height:1px;background:var(--s200)}
.date-sep span{background:#f8fafc;padding:0 12px;font-size:.5rem;font-weight:700;color:var(--s400);
  text-transform:uppercase;letter-spacing:.06em;position:relative;}

/* Messages */
.msg-grp{display:flex;flex-direction:column;margin:5px 0}
.msg-grp.mine{align-items:flex-end}.msg-grp.theirs{align-items:flex-start}
.msg-sender{font-size:.5rem;font-weight:700;color:var(--s400);margin-bottom:2px;padding:0 4px}
.msg-row{display:flex;align-items:flex-end;gap:6px;max-width:74%}
.msg-row.mine{flex-direction:row-reverse}
.m-av{width:28px;height:28px;border-radius:50%;overflow:hidden;flex-shrink:0;
  background:linear-gradient(135deg,#0873df,var(--blue));display:flex;align-items:center;
  justify-content:center;font-size:.5rem;font-weight:800;color:#fff;}
.m-av img{width:100%;height:100%;object-fit:cover}
.msg-bubble{padding:9px 13px;font-size:.875rem;line-height:1.55;word-break:break-word;
  box-shadow:var(--shadow);border-radius:16px;}
.msg-grp.mine  .msg-bubble{background:var(--blue);color:#fff;border-radius:16px 16px 4px 16px}
.msg-grp.theirs .msg-bubble{background:#fff;color:var(--s900);border:1.5px solid var(--s200);border-radius:16px 16px 16px 4px}
.msg-meta{font-size:.4375rem;color:var(--s400);padding:0 4px;margin-top:2px}
.msg-grp.mine .msg-meta{text-align:right;color:rgba(255,255,255,.5)}
.msg-grp.theirs .msg-meta{text-align:left}
.msg-status{font-size:.4375rem;margin-top:1px;text-align:right;color:rgba(255,255,255,.6)}

/* Empty */
.msgs-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--s400);text-align:center;padding:36px}
.empty-ic{width:72px;height:72px;border-radius:50%;background:var(--s100);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:30px;color:var(--s300)}

/* Compose */
.compose{padding:14px 18px;background:#fff;border-top:1.5px solid var(--s200);flex-shrink:0}
.compose-box{display:flex;gap:8px;align-items:flex-end;background:var(--s50);
  border:1.5px solid var(--s200);border-radius:14px;padding:8px 8px 8px 14px;transition:all .2s;}
.compose-box:focus-within{border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(0,90,180,.08)}
.compose-inp{flex:1;border:none;background:none;outline:none;font-family:inherit;font-size:.9375rem;
  line-height:1.5;resize:none;max-height:110px;min-height:24px;color:var(--s900);}
.compose-inp::placeholder{color:var(--s400)}
.send-btn{width:38px;height:38px;border-radius:10px;background:var(--blue);border:none;color:#fff;
  cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s;}
.send-btn:hover{background:#0048a0;transform:scale(1.06)}
.send-btn:active{transform:scale(.95)}
.send-btn:disabled{opacity:.4;cursor:not-allowed;transform:none}
.compose-hint{font-size:.5625rem;color:var(--s400);margin-top:7px;display:flex;justify-content:space-between}

/* Toast */
.toast-el{position:fixed;bottom:22px;right:22px;z-index:9999;padding:11px 16px;border-radius:11px;
  font-size:.875rem;font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.18);
  transform:translateY(60px);opacity:0;transition:all .3s;display:flex;align-items:center;gap:8px}
.toast-el.show{transform:translateY(0);opacity:1}
.toast-el.ok{background:#065f46;color:#fff;border-left:4px solid #34d399}
.toast-el.err{background:#7f1d1d;color:#fff;border-left:4px solid #f87171}

/* Mob overlay */
.mob-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:199}
.mob-ov.open{display:block}

@media(max-width:1024px){.shell-main{margin-left:0!important}.nav-sb:not(.cp-sidebar){transform:translateX(-100%)}.cp-sidebar{transform:translateX(-100%)!important}.ham{display:flex}}
@media(max-width:860px){.profile-panel{display:none}}
@media(max-width:600px){.msgs{padding:12px 14px}}
</style>
</head>
<body>
<div class="shell">

<?php if($portal!=='hospital'): ?>
<aside class="nav-sb" id="navSb">
  <div class="nav-brand"><div class="nav-brand-n">Planeazzy</div><div class="nav-brand-s"><?=ucfirst($portal)?> Portal</div></div>
  <div class="nav-prof">
    <div class="nav-av"><?php if($myAvatar):?><img src="<?=htmlspecialchars($myAvatar)?>" alt=""><?php else:?><?=htmlspecialchars($myInit)?><?php endif;?></div>
    <div style="min-width:0"><div style="font-size:.8125rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($myName)?></div><div style="font-size:.5625rem;color:var(--s400)"><?=ucfirst($portal)?></div></div>
  </div>
  <nav class="nav-body">
    <?php if($portal==='doctor'): ?>
    <div class="nav-sec">MAIN</div>
    <?php foreach([['/doctors/dashboard.php?tab=overview','fa-house','Overview'],['/doctors/dashboard.php?tab=appointments','fa-calendar-check','Appointments'],['/doctors/dashboard.php?tab=patients','fa-users','Patients'],['/doctors/dashboard.php?tab=schedule','fa-clock','Schedule'],['/doctors/dashboard.php?tab=analytics','fa-chart-bar','Analytics'],['/doctors/dashboard.php?tab=notifications','fa-bell','Notifications'],['/doctors/dashboard.php?tab=settings','fa-gear','Settings']] as [$h,$i,$l]):?>
    <a href="<?=$h?>" class="nav-it <?=str_contains($h,'appointments')?'active':''?>"><i class="fa-solid <?=$i?>"></i><span><?=$l?></span></a>
    <?php endforeach;?>
    <div class="nav-sec">ACCOUNT</div>
    <a href="/doctors/logout.php" class="nav-it" style="color:var(--red)"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
    <?php else: ?>
    <div class="nav-sec">MAIN</div>
    <?php foreach([['/patients/dashboard.php?tab=overview','fa-house','Overview'],['/patients/dashboard.php?tab=appointments','fa-calendar-check','Appointments'],['/patients/dashboard.php?tab=nearby','fa-location-dot','Find Care'],['/patients/dashboard.php?tab=insurance','fa-shield-halved','Insurance'],['/patients/dashboard.php?tab=notifications','fa-bell','Notifications'],['/patients/dashboard.php?tab=settings','fa-gear','Settings']] as [$h,$i,$l]):?>
    <a href="<?=$h?>" class="nav-it <?=str_contains($h,'appointments')?'active':''?>"><i class="fa-solid <?=$i?>"></i><span><?=$l?></span></a>
    <?php endforeach;?>
    <div class="nav-sec">ACCOUNT</div>
    <a href="/patients/login.php" class="nav-it" style="color:var(--red)"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
    <?php endif;?>
  </nav>
</aside>
<?php endif;?>

<div class="shell-main">
  <!-- Top bar -->
  <div class="top-bar">
    <button class="ham" id="hamBtn"><i class="fa-solid fa-bars" style="font-size:14px"></i></button>
    <a href="<?=htmlspecialchars($backUrl)?>" class="back-btn"><i class="fa-solid fa-arrow-left" style="font-size:11px"></i> Back</a>
    <div class="top-who">
      <div class="top-av"><?php if($otherAvatar):?><img src="<?=htmlspecialchars($otherAvatar)?>" alt=""><?php else:?><?=htmlspecialchars($otherInit)?><?php endif;?></div>
      <div style="min-width:0">
        <div style="font-size:.875rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($otherName)?></div>
        <div style="font-size:.625rem;color:var(--s400)"><?=htmlspecialchars($apptDate)?></div>
      </div>
    </div>
    <div class="top-contact">
      <?php if($otherPhone):?><a href="tel:<?=htmlspecialchars($otherPhone)?>" class="icon-btn" title="Call"><i class="fa-solid fa-phone"></i></a><?php endif;?>
      <?php if($otherEmail):?><a href="mailto:<?=htmlspecialchars($otherEmail)?>" class="icon-btn" title="Email"><i class="fa-solid fa-envelope"></i></a><?php endif;?>
    </div>
  </div>

  <div class="chat-body">
    <!-- Profile panel -->
    <div class="profile-panel">
      <div class="pp-hero">
        <div class="pp-av-wrap">
          <div class="pp-av"><?php if($otherAvatar):?><img src="<?=htmlspecialchars($otherAvatar)?>" alt=""><?php else:?><?=htmlspecialchars($otherInit)?><?php endif;?></div>
        </div>
        <div class="pp-name"><?=htmlspecialchars($otherName)?></div>
        <div class="pp-role"><?=htmlspecialchars($otherSpec)?:ucfirst($otherType)?></div>
        <div class="pp-badge"><i class="fa-solid fa-<?=$otherType==='doctor'?'user-doctor':($otherType==='hospital'?'hospital':'user')?>" style="font-size:9px"></i><?=ucfirst($otherType)?></div>
      </div>

      <div class="pp-body">
        <!-- Other party contact -->
        <?php if($otherPhone||$otherEmail): ?>
        <div class="pp-sec">
          <div class="pp-sec-title">Contact</div>
          <?php if($otherPhone):?><div class="pp-row"><i class="fa-solid fa-phone"></i><div class="pp-val"><a href="tel:<?=htmlspecialchars($otherPhone)?>"><?=htmlspecialchars($otherPhone)?></a></div></div><?php endif;?>
          <?php if($otherEmail):?><div class="pp-row"><i class="fa-solid fa-envelope"></i><div class="pp-val"><a href="mailto:<?=htmlspecialchars($otherEmail)?>"><?=htmlspecialchars($otherEmail)?></a></div></div><?php endif;?>
        </div>
        <?php endif;?>

        <!-- Doctor details (when doctor is in the appointment) -->
        <?php if($doctorName && $portal!=='doctor'): ?>
        <div class="pp-sec">
          <div class="pp-sec-title">Doctor</div>
          <div style="display:flex;align-items:center;gap:9px;margin-bottom:8px;padding:8px 10px;background:var(--s50);border-radius:8px;border:1px solid var(--s200)">
            <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0;background:linear-gradient(135deg,#0873df,var(--blue));display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff">
              <?php if($doctorAvatar):?><img src="<?=htmlspecialchars($doctorAvatar)?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else:?><?=htmlspecialchars($ini($doctorName))?><?php endif;?>
            </div>
            <div style="min-width:0">
              <div style="font-size:.8125rem;font-weight:700"><?=htmlspecialchars($doctorName)?></div>
              <?php if($doctorSpec):?><div style="font-size:.625rem;color:var(--s500)"><?=htmlspecialchars($doctorSpec)?></div><?php endif;?>
            </div>
          </div>
          <?php if($doctorLicence):?><div class="pp-row"><i class="fa-solid fa-id-card"></i><div class="pp-val"><?=htmlspecialchars($doctorLicence)?></div></div><?php endif;?>
          <?php if($doctorPhone):?><div class="pp-row"><i class="fa-solid fa-phone"></i><div class="pp-val"><a href="tel:<?=htmlspecialchars($doctorPhone)?>"><?=htmlspecialchars($doctorPhone)?></a></div></div><?php endif;?>
          <?php if($doctorEmail):?><div class="pp-row"><i class="fa-solid fa-envelope"></i><div class="pp-val"><a href="mailto:<?=htmlspecialchars($doctorEmail)?>"><?=htmlspecialchars($doctorEmail)?></a></div></div><?php endif;?>
          <?php if(!empty($appt['years_exp'])):?><div class="pp-row"><i class="fa-solid fa-clock-rotate-left"></i><div class="pp-val"><?=$appt['years_exp']?> yr<?=$appt['years_exp']>1?'s':''?> experience</div></div><?php endif;?>
          <?php if(!empty($appt['consult_fee'])&&$appt['consult_fee']>0):?><div class="pp-row"><i class="fa-solid fa-money-bill-wave"></i><div class="pp-val">KES <?=number_format($appt['consult_fee'],0)?></div></div><?php endif;?>
          <?php if(!empty($appt['languages'])):?><div class="pp-row"><i class="fa-solid fa-language"></i><div class="pp-val"><?=htmlspecialchars($appt['languages'])?></div></div><?php endif;?>
          <?php if(!empty($appt['accepts_tele'])):?><div class="pp-row"><i class="fa-solid fa-video"></i><div class="pp-val" style="color:var(--blue)">Tele-consult available</div></div><?php endif;?>
        </div>
        <?php endif;?>

        <!-- Hospital details -->
        <?php if($hospName && $portal!=='hospital'): ?>
        <div class="pp-sec">
          <div class="pp-sec-title">Hospital / Facility</div>
          <div style="display:flex;align-items:center;gap:9px;margin-bottom:8px;padding:8px 10px;background:var(--s50);border-radius:8px;border:1px solid var(--s200)">
            <?php if($hospLogo):?>
            <img src="<?=htmlspecialchars($hospLogo)?>" style="width:32px;height:32px;border-radius:7px;object-fit:contain;flex-shrink:0" alt="">
            <?php else:?>
            <div style="width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--blue),#0873df);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa-solid fa-hospital" style="color:#fff;font-size:13px"></i></div>
            <?php endif;?>
            <div style="min-width:0"><div style="font-size:.8125rem;font-weight:700"><?=htmlspecialchars($hospName)?></div><?php if($hospCounty):?><div style="font-size:.625rem;color:var(--s500)"><?=htmlspecialchars($hospCounty)?></div><?php endif;?></div>
          </div>
          <?php if($hospPhone):?><div class="pp-row"><i class="fa-solid fa-phone"></i><div class="pp-val"><a href="tel:<?=htmlspecialchars($hospPhone)?>"><?=htmlspecialchars($hospPhone)?></a></div></div><?php endif;?>
        </div>
        <?php endif;?>

        <!-- Appointment details -->
        <div class="pp-sec">
          <div class="pp-sec-title">Appointment</div>
          <div class="appt-card">
            <div class="appt-card-head">
              <span class="s-pill" style="background:<?=$sBg?>;color:<?=$sFg?>">
                <i class="fa-solid fa-<?=$sIc?>" style="font-size:8px"></i><?=ucfirst($apptStatus)?>
              </span>
            </div>
            <div class="appt-card-body">
              <?php if($apptDate):?><div class="appt-row"><i class="fa-regular fa-calendar"></i><span style="font-size:.8rem;color:var(--s700);font-weight:500"><?=htmlspecialchars($apptDate)?></span></div><?php endif;?>
              <?php if($apptDept):?><div class="appt-row"><i class="fa-solid fa-stethoscope"></i><span style="font-size:.8rem;color:var(--s700)"><?=htmlspecialchars($apptDept)?></span></div><?php endif;?>
              <?php if($apptVisitType):?><div class="appt-row"><i class="fa-solid fa-<?=str_contains($apptVisitType,'tele')||str_contains($apptVisitType,'telehealth')?'video':'location-dot'?>"></i><span style="font-size:.8rem;color:var(--s700)"><?=ucwords(str_replace(['-','_'],' ',$apptVisitType))?></span></div><?php endif;?>
              <?php if($patientName):?><div class="appt-row"><i class="fa-solid fa-user"></i><span style="font-size:.8rem;color:var(--s700)"><?=htmlspecialchars($patientName)?></span></div><?php endif;?>
              <?php if($apptNotes):?><div class="appt-row" style="margin-top:4px"><i class="fa-solid fa-note-medical"></i><span style="font-size:.75rem;color:var(--s500);line-height:1.5"><?=htmlspecialchars($apptNotes)?></span></div><?php endif;?>
            </div>
          </div>
        </div>

        <!-- Thread stats -->
        <div class="pp-sec">
          <div class="pp-sec-title">Conversation</div>
          <?php $mc=count($messages); $myc=count(array_filter($messages,fn($m)=>$m['sender_type']===$myType)); ?>
          <div style="display:flex;gap:10px">
            <div style="flex:1;text-align:center;padding:9px;background:var(--s50);border-radius:8px;border:1px solid var(--s200)">
              <div style="font-size:1.125rem;font-weight:800;color:var(--blue)"><?=$mc?></div>
              <div style="font-size:.5rem;font-weight:700;color:var(--s400);text-transform:uppercase">Total</div>
            </div>
            <div style="flex:1;text-align:center;padding:9px;background:var(--s50);border-radius:8px;border:1px solid var(--s200)">
              <div style="font-size:1.125rem;font-weight:800;color:var(--teal)"><?=$myc?></div>
              <div style="font-size:.5rem;font-weight:700;color:var(--s400);text-transform:uppercase">Yours</div>
            </div>
          </div>
        </div>

        <div class="pp-sec">
          <div style="display:flex;align-items:center;gap:6px;padding:8px 10px;background:var(--s50);border-radius:8px;border:1px solid var(--s200)">
            <i class="fa-solid fa-lock" style="color:var(--s400);font-size:10px;flex-shrink:0"></i>
            <span style="font-size:.5625rem;color:var(--s500);line-height:1.5">Private to this appointment only</span>
          </div>
        </div>
      </div>
    </div><!-- /profile-panel -->

    <!-- Thread -->
    <div class="thread">
      <div class="th-header">
        <div class="th-header-av"><?php if($otherAvatar):?><img src="<?=htmlspecialchars($otherAvatar)?>" alt=""><?php else:?><?=htmlspecialchars($otherInit)?><?php endif;?></div>
        <div style="flex:1">
          <div style="font-size:.875rem;font-weight:700"><?=htmlspecialchars($otherName)?></div>
          <div style="font-size:.5625rem;color:var(--s400);display:flex;align-items:center;gap:4px">
            <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block"></span> Active
          </div>
        </div>
        <span style="font-size:.625rem;color:var(--s400);background:var(--s100);padding:2px 8px;border-radius:9999px;font-weight:600"><?=count($messages)?> msg<?=count($messages)!=1?'s':''?></span>
      </div>

      <div class="msgs" id="msgsDiv">
        <?php if(empty($messages)): ?>
        <div class="msgs-empty">
          <div class="empty-ic"><i class="fa-regular fa-comments"></i></div>
          <h3 style="font-size:.9375rem;font-weight:700;color:var(--s600);margin-bottom:5px">No messages yet</h3>
          <p style="font-size:.8125rem;line-height:1.6;max-width:240px">Start the conversation — only you and <?=htmlspecialchars($otherName)?> can see these messages.</p>
        </div>
        <?php else:
          $lastDate='';
          foreach($messages as $msg):
            $mine=($msg['sender_type']===$myType && (int)$msg['sender_id']===$myId);
            $d=new DateTime($msg['created_at']);
            $dateStr=$d->format('l, F j, Y');
            $timeStr=$d->format('g:i A');
            $si=$ini($msg['sender_name']??'?');
        ?>
        <?php if($dateStr!==$lastDate):$lastDate=$dateStr;?>
        <div class="date-sep"><span><?=$dateStr?></span></div>
        <?php endif;?>
        <div class="msg-grp <?=$mine?'mine':'theirs'?>">
          <?php if(!$mine):?><div class="msg-sender"><?=htmlspecialchars($msg['sender_name']??'')?></div><?php endif;?>
          <div class="msg-row <?=$mine?'mine':'theirs'?>">
            <?php if(!$mine):?>
            <div class="m-av"><?php if($msg['avatar_path']??null):?><img src="<?=htmlspecialchars($msg['avatar_path'])?>" alt=""><?php else:?><?=htmlspecialchars($si)?><?php endif;?></div>
            <?php endif;?>
            <div>
              <div class="msg-bubble"><?=nl2br(htmlspecialchars($msg['message']))?></div>
              <?php if($mine):?>
              <div class="msg-status"><i class="fa-solid fa-check-double"></i> <?=$timeStr?></div>
              <?php else:?>
              <div class="msg-meta"><?=$timeStr?></div>
              <?php endif;?>
            </div>
          </div>
        </div>
        <?php endforeach;endif;?>
      </div>

      <div class="compose">
        <div class="compose-box">
          <textarea class="compose-inp" id="cInput" placeholder="Message <?=htmlspecialchars($otherName)?>…" rows="1"
            onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"
            oninput="autoH(this)"></textarea>
          <button class="send-btn" id="sendBtn" onclick="sendMsg()">
            <span class="material-symbols-outlined" style="font-size:19px">send</span>
          </button>
        </div>
        <div class="compose-hint">
          <span><i class="fa-solid fa-lock" style="font-size:8px"></i> Private chat</span>
          <span>Enter to send · Shift+Enter new line</span>
        </div>
      </div>
    </div><!-- /thread -->
  </div><!-- /chat-body -->
</div><!-- /shell-main -->
</div><!-- /shell -->

<div class="mob-ov" id="mobOv" onclick="closeSb()"></div>
<div class="toast-el" id="toastEl"></div>

<script>
const APPT_ID=<?=$apptId?>,APPT_TYPE='<?=$apptType?>',MY_TYPE='<?=$myType?>',MY_ID=<?=$myId?>;
const MAPI='/api/appointment-messages.php';
let _last=<?=count($messages)?>,_poll=null;

function toast(msg,type='ok'){const e=document.getElementById('toastEl');e.className='toast-el '+type+' show';e.textContent=msg;setTimeout(()=>e.classList.remove('show'),3500);}
function autoH(el){el.style.height='auto';el.style.height=Math.min(el.scrollHeight,110)+'px';}
function esc(t){return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');}
function init2(n){return String(n).replace(/^Dr\.\s*/i,'').split(' ').filter(Boolean).map(w=>w[0]?.toUpperCase()||'').join('').slice(0,2)||'?';}

function scrollBot(force=false){const d=document.getElementById('msgsDiv');if(!d)return;const atBot=d.scrollHeight-d.scrollTop-d.clientHeight<100;if(force||atBot)d.scrollTop=d.scrollHeight;}

function buildBubble(msg){
  const mine=(msg.sender_type===MY_TYPE&&parseInt(msg.sender_id||0)===MY_ID);
  const d=new Date(msg.created_at);
  const ts=d.toLocaleTimeString('en-KE',{hour:'2-digit',minute:'2-digit'});
  const si=init2(msg.sender_name||'?');
  const avHtml=msg.avatar_path?`<img src="${esc(msg.avatar_path)}" alt="" style="width:100%;height:100%;object-fit:cover">`:si;
  const g=document.createElement('div');g.className='msg-grp '+(mine?'mine':'theirs');
  g.innerHTML=(mine?'':'<div class="msg-sender">'+esc(msg.sender_name||'')+'</div>')+
    `<div class="msg-row ${mine?'mine':'theirs'}">
      ${mine?'':`<div class="m-av">${avHtml}</div>`}
      <div>
        <div class="msg-bubble">${esc(msg.message)}</div>
        ${mine?`<div class="msg-status"><i class="fa-solid fa-check-double"></i> ${ts}</div>`:`<div class="msg-meta">${ts}</div>`}
      </div>
    </div>`;
  return g;
}

async function sendMsg(){
  const inp=document.getElementById('cInput'),btn=document.getElementById('sendBtn');
  const msg=inp.value.trim();if(!msg)return;
  btn.disabled=true;
  try{
    const r=await fetch(MAPI,{
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/json','Accept':'application/json'},
      body:JSON.stringify({appt_id:APPT_ID,appt_type:APPT_TYPE,message:msg})
    });
    if(!r.ok){
      let em='Server error ('+r.status+')';
      try{const ej=await r.json();em=ej.msg||em;}catch(e2){}
      toast(em,'err');btn.disabled=false;inp.focus();return;
    }
    const j=await r.json();
    if(j.ok){
      inp.value='';inp.style.height='auto';
      const d=document.getElementById('msgsDiv');
      d.querySelector('.msgs-empty')?.remove();
      d.appendChild(buildBubble(j));scrollBot(true);_last++;
    }else toast(j.msg||'Failed to send','err');
  }catch(e){
    console.error('[Planeazzy] sendMsg error:',e);
    toast('Connection error. Please check your internet and try again.','err');
  }
  btn.disabled=false;inp.focus();
}

async function poll(){
  if(document.hidden)return;
  try{
    const r=await fetch(MAPI+'?appt_id='+APPT_ID+'&appt_type='+APPT_TYPE,{credentials:'same-origin',headers:{'Accept':'application/json'}});
    const j=await r.json();
    if(j.ok&&j.messages&&j.messages.length>_last){
      const d=document.getElementById('msgsDiv');
      d.querySelector('.msgs-empty')?.remove();
      j.messages.slice(_last).forEach(m=>{d.appendChild(buildBubble(m));});
      scrollBot();_last=j.messages.length;
    }
  }catch(e){}
}

function toggleSb(){
  const sb=document.getElementById('navSb')||document.getElementById('cpSidebar');
  const ov=document.getElementById('mobOv');
  const o=sb?.classList.toggle('open');ov?.classList.toggle('open',o);
  document.body.style.overflow=o?'hidden':'';
}
function closeSb(){
  (document.getElementById('navSb')||document.getElementById('cpSidebar'))?.classList.remove('open');
  document.getElementById('mobOv')?.classList.remove('open');
  document.body.style.overflow='';
}
document.getElementById('hamBtn')?.addEventListener('click',toggleSb);

scrollBot(true);
_poll=setInterval(poll,8000);
window.addEventListener('focus',poll);
document.getElementById('cInput')?.focus();
</script>
</body>
</html>
