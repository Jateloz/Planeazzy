<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();

if (empty($_SESSION['hospital_id']) || empty($_SESSION['hospital_auth'])) { header('Location: /hospital/onboarding/login.php'); exit; }
$hid = (int)$_SESSION['hospital_id'];
$db  = Database::getInstance();
$hosp = $db->fetchOne('SELECT * FROM hospital_providers WHERE id=:id', [':id'=>$hid]);
if (!$hosp || $hosp['status'] !== 'approved' || !$hosp['is_active']) { header('Location: /hospital/onboarding/pending.php'); exit; }
$did = (int)($_GET['id'] ?? 0);
if (!$did) { header('Location: /hospital/onboarding/dashboard.php?tab=doctors'); exit; }
$doc = $db->fetchOne('SELECT d.*,dep.name dept_name FROM hospital_doctors d LEFT JOIN hospital_departments dep ON dep.id=d.department_id WHERE d.id=:id AND d.hospital_id=:h AND d.is_active=1', [':id'=>$did,':h'=>$hid]);
if (!$doc) { header('Location: /hospital/onboarding/dashboard.php?tab=doctors'); exit; }
$depts = $db->fetchAll('SELECT id,name FROM hospital_departments WHERE hospital_id=:h ORDER BY name',[':h'=>$hid]);
$csrf = Security::csrfToken();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!Security::verifyCsrf($_POST['csrf_token']??'')) { $error='Security token invalid.'; }
    else {
        $avail=[];
        foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day) {
            if (!empty($_POST['avail_'.$day])) $avail[$day]=trim($_POST['time_'.$day]??'9:00-17:00');
        }
        $langArr=$_POST['languages']??[];
        $langStr=is_array($langArr)?implode(', ',array_map('trim',$langArr)):trim($langArr);
        try {
            $db->query('UPDATE hospital_doctors SET name=:name,specialty=:sp,email=:em,phone=:ph,kmpdc_licence=:lic,gender=:gen,years_exp=:ye,consult_fee=:fee,languages=:lang,education=:edu,bio=:bio,accepts_tele=:tele,accepts_walkin=:walkin,availability=:av,status=:st,department_id=:dept WHERE id=:id AND hospital_id=:h',
                [':name'=>trim($_POST['name']??$doc['name']),':sp'=>trim($_POST['specialty']??''),':em'=>trim($_POST['email']??''),':ph'=>trim($_POST['phone']??''),':lic'=>trim($_POST['kmpdc_licence']??''),':gen'=>in_array($_POST['gender']??'',['male','female','other'])?$_POST['gender']:null,':ye'=>(int)($_POST['years_exp']??0),':fee'=>(float)($_POST['consult_fee']??0),':lang'=>$langStr,':edu'=>trim($_POST['education']??''),':bio'=>trim($_POST['bio']??''),':tele'=>!empty($_POST['accepts_tele'])?1:0,':walkin'=>!empty($_POST['accepts_walkin'])?1:0,':av'=>$avail?json_encode($avail):null,':st'=>in_array($_POST['status']??'',['on-duty','off-duty','on-break','suspended'])?$_POST['status']:'off-duty',':dept'=>!empty($_POST['department_id'])?(int)$_POST['department_id']:null,':id'=>$did,':h'=>$hid]);
            if (!empty($_FILES['avatar']['name'])&&$_FILES['avatar']['error']===UPLOAD_ERR_OK) {
                $mime=mime_content_type($_FILES['avatar']['tmp_name']);
                $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
                if ($ext&&$_FILES['avatar']['size']<=4*1024*1024) {
                    $dir=UPLOAD_DIR.'hospital_doctor_avatars/'; if(!is_dir($dir))@mkdir($dir,0775,true);
                    $fn='doc_'.$hid.'_'.$did.'_'.time().'.'.$ext;
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'],$dir.$fn)) {
                        $db->query('UPDATE hospital_doctors SET avatar_path=:p WHERE id=:id',[':p'=>'/storage/uploads/hospital_doctor_avatars/'.$fn,':id'=>$did]);
                        $doc['avatar_path']='/storage/uploads/hospital_doctor_avatars/'.$fn;
                    }
                } else { $error=$error?:'Avatar must be JPG/PNG/WebP under 4MB.'; }
            }
            if (!$error) { $doc=$db->fetchOne('SELECT d.*,dep.name dept_name FROM hospital_doctors d LEFT JOIN hospital_departments dep ON dep.id=d.department_id WHERE d.id=:id',[':id'=>$did]); $success='Profile saved.'; }
        } catch(Exception $e) { $error='Save failed: '.substr($e->getMessage(),0,80); }
    }
}

$avail   = !empty($doc['availability']??'')?json_decode($doc['availability'],true)??[]:[];
$selLang = array_map('trim',explode(',',$doc['languages']??'English'));
$docInit = strtoupper(substr($doc['name'],0,1).(strpos($doc['name'],' ')!==false?substr($doc['name'],strrpos($doc['name'],' ')+1,1):''));
$allLangs= ['English','Swahili','Kikuyu','Luo','Luhya','Kamba','Kalenjin','Somali','Arabic','French','Other'];
$days    = ['Mon'=>'Mon','Tue'=>'Tue','Wed'=>'Wed','Thu'=>'Thu','Fri'=>'Fri','Sat'=>'Sat','Sun'=>'Sun'];
$dotColor= match($doc['status']??'off-duty'){'on-duty'=>'#22c55e','on-break'=>'#f59e0b','suspended'=>'#ef4444',default=>'#94a3b8'};
$facilityName=$hosp['facility_name']??$hosp['admin_name']??'Hospital';
$logoPath=$hosp['logo_path']??'';
$adminName=$hosp['admin_name']??'Admin';
$initials=strtoupper(implode('',array_map(fn($w)=>$w[0],array_slice(explode(' ',trim($adminName)),0,2))));
$unreadNotifs=$db->fetchOne('SELECT COUNT(*) c FROM hospital_notifications WHERE hospital_id=:h AND is_read=0',[':h'=>$hid])['c']??0;
$pendingCount=$db->fetchOne('SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND status="pending"',[':h'=>$hid])['c']??0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Edit Dr. <?=htmlspecialchars($doc['name'])?> — <?=htmlspecialchars($facilityName)?></title>
<link rel="icon" type="image/png" href="/assets/images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<style>
:root{--blue:#005ab4;--teal:#006a6a;--green:#16a34a;--red:#dc2626;--amber:#d97706;--s50:#f8fafc;--s100:#f1f5f9;--s200:#e2e8f0;--s300:#cbd5e1;--s400:#94a3b8;--s500:#64748b;--s600:#475569;--s700:#334155;--s900:#0f172a;--r:8px;--r-lg:12px;--shadow:0 1px 3px rgba(0,0,0,.07);--sb-w:220px}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Inter',sans-serif;background:#f0f2f5;color:var(--s900);min-height:100vh;font-size:14px}
a{text-decoration:none;color:inherit}
.db-wrap{display:flex;min-height:100vh}
/*  Sidebar  */
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
/*  Main  */
.db-main{margin-left:var(--sb-w);flex:1;display:flex;flex-direction:column;min-width:0}
/*  Topbar  */
.topbar{position:sticky;top:0;z-index:100;background:rgba(255,255,255,.97);backdrop-filter:blur(10px);border-bottom:1px solid rgba(193,198,213,.2);padding:9px 22px;display:flex;align-items:center;gap:10px}
.db-hamb{display:none;width:34px;height:34px;border:none;background:none;cursor:pointer;border-radius:var(--r);align-items:center;justify-content:center;color:var(--s500)}
.breadcrumb{display:flex;align-items:center;gap:5px;font-size:.8rem;color:var(--s500);flex:1;min-width:0}
.breadcrumb a{color:var(--blue);font-weight:600}
.breadcrumb i{font-size:9px;color:var(--s300)}
.topbar-right{display:flex;align-items:center;gap:5px}
.ico-btn{width:34px;height:34px;border:none;background:none;cursor:pointer;border-radius:var(--r);display:flex;align-items:center;justify-content:center;color:var(--s500);position:relative;transition:background .15s;text-decoration:none}
.ico-btn:hover{background:var(--s100)}
.ndot{position:absolute;top:5px;right:5px;width:7px;height:7px;border-radius:50%;background:#ef4444;border:1.5px solid #fff}
.db-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#005ab4,#0873df);display:flex;align-items:center;justify-content:center;font-size:.625rem;font-weight:800;color:#fff;overflow:hidden;flex-shrink:0;text-decoration:none}
.db-avatar img{width:100%;height:100%;object-fit:cover}
/*  Content  */
.content{padding:18px 24px;flex:1;display:flex;flex-direction:column;gap:14px}
/*  Page header  */
.pg-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.pg-title{font-size:1.125rem;font-weight:900;letter-spacing:-.04em}
.pg-sub{font-size:.8rem;color:var(--s500);margin-top:1px}
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:var(--r);border:none;font-family:inherit;font-size:.8rem;font-weight:700;cursor:pointer;transition:opacity .15s;text-decoration:none}
.btn:hover{opacity:.88}
.btn-primary{background:var(--blue);color:#fff}
.btn-ghost{background:var(--s100);color:var(--s700);border:1.5px solid var(--s200)}
/*  Alerts  */
.alert{padding:10px 14px;border-radius:var(--r);font-size:.8125rem;display:flex;align-items:center;gap:7px}
.alert-ok{background:#f0fdf4;border:1.5px solid #86efac;color:#166534}
.alert-err{background:#fef2f2;border:1.5px solid #fca5a5;color:#991b1b}
/*  Body layout: 3-col  */
.body-grid{display:grid;grid-template-columns:230px 1fr 220px;gap:14px;align-items:start}
/*  Cards  */
.card{background:#fff;border-radius:var(--r-lg);border:1px solid var(--s200);box-shadow:var(--shadow);overflow:hidden}
.card-head{padding:12px 16px;border-bottom:1px solid var(--s100);font-size:.8375rem;font-weight:700;display:flex;align-items:center;gap:7px;color:var(--s700)}
.card-head i{color:var(--blue);font-size:13px}
.card-body{padding:14px 16px}
/*  Avatar section  */
.av-wrap{text-align:center;padding:16px}
.av-circle{width:76px;height:76px;border-radius:50%;margin:0 auto 10px;position:relative;display:inline-block}
.av-img{width:76px;height:76px;border-radius:50%;object-fit:cover;border:2.5px solid var(--s200)}
.av-initials{width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,var(--blue),#0873df);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;color:#fff;border:2.5px solid var(--s200)}
.av-cam{position:absolute;bottom:-1px;right:-1px;width:24px;height:24px;border-radius:50%;background:var(--blue);border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer}
.av-cam input{position:absolute;inset:0;opacity:0;cursor:pointer;border-radius:50%}
.av-cam i{font-size:10px;color:#fff}
.doc-name{font-size:.9375rem;font-weight:800;color:var(--s900);margin-bottom:2px}
.doc-spec{font-size:.75rem;color:var(--s500);margin-bottom:6px}
.status-dot{display:inline-flex;align-items:center;gap:4px;font-size:.6875rem;font-weight:700;padding:3px 9px;border-radius:9999px;background:var(--s100)}
/*  Quick details in sidebar  */
.q-row{display:flex;align-items:flex-start;gap:7px;padding:6px 0;border-bottom:1px solid var(--s100);font-size:.78rem}
.q-row:last-child{border-bottom:none}
.q-row i{color:var(--s300);width:14px;font-size:11px;margin-top:1px;flex-shrink:0}
.q-val{color:var(--s600);font-weight:500;flex:1;line-height:1.4}
/*  Form fields  */
.fg{margin-bottom:10px}
.fg:last-child{margin-bottom:0}
.lbl{font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--s500);margin-bottom:4px;display:block}
.lbl .req{color:var(--red)}
.inp{width:100%;padding:8px 11px;background:var(--s50);border:1.5px solid var(--s200);border-radius:var(--r);font-family:inherit;font-size:.8375rem;color:var(--s900);outline:none;transition:all .18s}
.inp:focus{background:#fff;border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,90,180,.08)}
textarea.inp{resize:vertical;min-height:70px;line-height:1.5}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
/*  Status radio  */
.status-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.st-opt{position:relative}
.st-opt input{position:absolute;opacity:0;width:0;height:0}
.st-opt label{display:flex;flex-direction:column;align-items:center;gap:3px;padding:8px 4px;border:1.5px solid var(--s200);border-radius:var(--r);cursor:pointer;font-size:.6rem;font-weight:700;color:var(--s500);text-align:center;transition:all .15s}
.st-opt input[value=on-duty]:checked+label{border-color:#22c55e;background:#f0fdf4;color:#15803d}
.st-opt input[value=off-duty]:checked+label{border-color:var(--s300);background:var(--s50);color:var(--s600)}
.st-opt input[value=on-break]:checked+label{border-color:#f59e0b;background:#fffbeb;color:#b45309}
.st-opt input[value=suspended]:checked+label{border-color:var(--red);background:#fef2f2;color:#b91c1c}
.st-dot{width:8px;height:8px;border-radius:50%}
/*  Toggle  */
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--s100)}
.toggle-row:last-child{border-bottom:none}
.tgl{position:relative;width:40px;height:22px;flex-shrink:0}
.tgl input{opacity:0;width:0;height:0;position:absolute}
.tgl-track{display:block;width:40px;height:22px;border-radius:9999px;background:var(--s300);cursor:pointer;transition:background .18s}
.tgl input:checked+.tgl-track{background:var(--blue)}
.tgl-knob{position:absolute;top:2px;left:2px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:left .18s;pointer-events:none}
.tgl input:checked~.tgl-knob{left:20px}
/*  Language grid  */
.lang-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:5px}
.lang-item{display:flex;align-items:center;gap:5px;padding:5px 7px;border:1.5px solid var(--s200);border-radius:var(--r);cursor:pointer;font-size:.75rem;transition:all .12s}
.lang-item:hover{border-color:var(--blue)}
.lang-item.ck{border-color:var(--blue);background:rgba(0,90,180,.06);color:var(--blue);font-weight:600}
.lang-item input{width:13px;height:13px;accent-color:var(--blue)}
/*  Availability grid  */
.avail-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:5px}
.avail-col{text-align:center}
.avail-col label{display:flex;flex-direction:column;align-items:center;gap:4px;cursor:pointer}
.avail-col input[type=checkbox]{accent-color:var(--blue);width:14px;height:14px}
.avail-day-name{font-size:.55rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--s500)}
.avail-time{width:100%;padding:3px 2px;border:1px solid var(--s200);border-radius:5px;font-size:.5625rem;text-align:center;background:var(--s50);font-family:inherit;color:var(--s700);outline:none}
.avail-time:focus{border-color:var(--blue)}
.avail-col.active .avail-day-name{color:var(--blue)}
/*  Submit bar  */
.submit-bar{background:#fff;border-radius:var(--r-lg);border:1px solid var(--s200);box-shadow:var(--shadow);padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
/*  Mobile  */
.mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:199}
.mob-overlay.open{display:block}
.mob-toggle{display:none;position:fixed;bottom:20px;right:20px;z-index:300;width:46px;height:46px;border-radius:50%;background:var(--blue);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(0,90,180,.4);align-items:center;justify-content:center}
@media(max-width:1100px){.body-grid{grid-template-columns:1fr 1fr}}
@media(max-width:1024px){.db-main{margin-left:0}.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.db-hamb{display:flex}.mob-toggle{display:flex}}
@media(max-width:700px){.body-grid{grid-template-columns:1fr}.grid2{grid-template-columns:1fr}.lang-grid{grid-template-columns:repeat(2,1fr)}.avail-grid{grid-template-columns:repeat(4,1fr)}}
</style>
</head>
<body>
<div class="db-wrap">

<!--  SIDEBAR  -->
<aside class="sidebar" id="cpSidebar">
  <div class="sb-brand">
    <?php if($logoPath):?><img src="<?=htmlspecialchars($logoPath)?>" alt="" style="height:28px;object-fit:contain;margin-bottom:5px;border-radius:5px">
    <?php else:?><div class="sb-brand-name">Planeazzy</div><?php endif;?>
    <div class="sb-brand-sub">Provider Dashboard</div>
    <div class="sb-facility">
      <div class="sb-fac-icon"><span class="material-symbols-outlined" style="font-size:13px;color:#fff">local_hospital</span></div>
      <div style="min-width:0"><div style="font-size:.75rem;font-weight:700;color:var(--blue);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px"><?=htmlspecialchars($facilityName)?></div><div style="font-size:.55rem;color:var(--s400)">Provider Admin</div></div>
    </div>
  </div>
  <div style="padding:6px 0;flex:1;overflow-y:auto">
    <div class="sb-section">MAIN</div>
    <?php foreach([['overview','dashboard','Overview',0,''],['appointments','calendar_today','Appointments',$pendingCount,''],['doctors','medical_services','Doctors',0,'active'],['services','business_center','Services',0,'']] as [$k,$ic,$lb,$bd,$cls]):?>
    <a href="/hospital/onboarding/dashboard.php?tab=<?=$k?>" class="nav-item <?=$cls?>">
      <span class="material-symbols-outlined" style="font-size:17px"><?=$ic?></span><span><?=$lb?></span>
      <?php if($bd>0):?><span class="nav-badge"><?=$bd?></span><?php endif;?>
    </a>
    <?php endforeach;?>
    <div class="sb-section" style="margin-top:3px">REPORTS</div>
    <?php foreach([['insurance','verified_user','Insurance',0],['analytics','analytics','Analytics',0],['notifications','notifications','Notifications',$unreadNotifs]] as [$k,$ic,$lb,$bd]):?>
    <a href="/hospital/onboarding/dashboard.php?tab=<?=$k?>" class="nav-item">
      <span class="material-symbols-outlined" style="font-size:17px"><?=$ic?></span><span><?=$lb?></span>
      <?php if($bd>0):?><span class="nav-badge"><?=$bd?></span><?php endif;?>
    </a>
    <?php endforeach;?>
    <div class="sb-section" style="margin-top:3px">SYSTEM</div>
    <a href="/hospital/onboarding/dashboard.php?tab=settings" class="nav-item"><span class="material-symbols-outlined" style="font-size:17px">settings</span><span>Settings</span></a>
  </div>
  <div class="sb-footer">
    <a href="mailto:support@planeazzy.co.ke" class="nav-item"><span class="material-symbols-outlined" style="font-size:17px">contact_support</span><span>Support</span></a>
    <a href="/hospital/onboarding/logout.php" class="nav-item" style="color:var(--red)"><span class="material-symbols-outlined" style="font-size:17px">logout</span><span>Logout</span></a>
  </div>
</aside>

<!--  MAIN  -->
<div class="db-main" id="dbMain">
  <header class="topbar">
    <button class="db-hamb" id="mobToggle"><span class="material-symbols-outlined">menu</span></button>
    <div class="breadcrumb">
      <a href="/hospital/onboarding/dashboard.php?tab=doctors"><i class="fa-solid fa-users-rectangle" style="margin-right:3px"></i>Doctors</a>
      <i class="fa-solid fa-chevron-right"></i>
      <a href="/hospital/onboarding/doctor-profile.php?id=<?=$did?>">Dr. <?=htmlspecialchars($doc['name'])?></a>
      <i class="fa-solid fa-chevron-right"></i>
      <span style="color:var(--s700);font-weight:600">Edit</span>
    </div>
    <div class="topbar-right">
      <a href="/hospital/onboarding/doctor-profile.php?id=<?=$did?>" class="btn btn-ghost" style="font-size:.75rem;padding:6px 12px"><i class="fa-solid fa-arrow-left"></i> Back</a>
      <a href="/hospital/onboarding/dashboard.php?tab=notifications" class="ico-btn">
        <span class="material-symbols-outlined" style="font-size:19px">notifications</span>
        <?php if($unreadNotifs>0):?><div class="ndot"></div><?php endif;?>
      </a>
      <a href="/hospital/onboarding/dashboard.php?tab=settings" class="db-avatar">
        <?php if($logoPath):?><img src="<?=htmlspecialchars($logoPath)?>" alt=""><?php else:?><?=htmlspecialchars($initials)?><?php endif;?>
      </a>
    </div>
  </header>

  <form method="POST" enctype="multipart/form-data" id="f" novalidate>
  <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">

  <div class="content">
    <!-- Page header -->
    <div class="pg-head">
      <div>
        <div class="pg-title">Edit Doctor Profile</div>
        <div class="pg-sub">Dr. <?=htmlspecialchars($doc['name'])?> · <?=htmlspecialchars($doc['specialty']??'General')?></div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
    </div>

    <?php if($success):?><div class="alert alert-ok"><i class="fa-solid fa-circle-check"></i><?=htmlspecialchars($success)?></div><?php endif;?>
    <?php if($error):?><div class="alert alert-err"><i class="fa-solid fa-circle-exclamation"></i><?=htmlspecialchars($error)?></div><?php endif;?>

    <div class="body-grid">

      <!--  LEFT: Doctor summary  -->
      <div style="display:flex;flex-direction:column;gap:12px">
        <!-- Avatar card -->
        <div class="card">
          <div class="av-wrap">
            <div class="av-circle">
              <?php if(!empty($doc['avatar_path']??'')):?>
              <img src="<?=htmlspecialchars($doc['avatar_path'])?>" class="av-img" id="avPreview" alt="">
              <?php else:?>
              <div class="av-initials" id="avPreview"><?=htmlspecialchars($docInit)?></div>
              <?php endif;?>
              <div class="av-cam"><input type="file" name="avatar" accept="image/*" onchange="previewAv(this)"><i class="fa-solid fa-camera"></i></div>
            </div>
            <div class="doc-name">Dr. <?=htmlspecialchars($doc['name'])?></div>
            <div class="doc-spec"><?=htmlspecialchars($doc['specialty']??'General')?></div>
            <div class="status-dot"><span style="width:7px;height:7px;border-radius:50%;background:<?=$dotColor?>;display:inline-block"></span><?=ucwords(str_replace('-',' ',$doc['status']))?></div>
          </div>
        </div>
        <!-- Quick info -->
        <div class="card">
          <div class="card-head"><i class="fa-solid fa-circle-info"></i> Quick Info</div>
          <div class="card-body" style="padding:10px 14px">
            <?php $qrows=[['fa-envelope',$doc['email']??null],['fa-phone',$doc['phone']??null],['fa-id-card',$doc['kmpdc_licence']??null],['fa-graduation-cap',$doc['education']??null],['fa-clock-rotate-left',($doc['years_exp']??0)?($doc['years_exp'].'yr exp'):null],['fa-briefcase-medical',$doc['dept_name']??null]];
            foreach($qrows as [$ic,$val]): if(!$val) continue;?>
            <div class="q-row"><i class="fa-solid <?=$ic?>"></i><div class="q-val"><?=htmlspecialchars($val)?></div></div>
            <?php endforeach;?>
          </div>
        </div>
        <!-- Status -->
        <div class="card">
          <div class="card-head"><i class="fa-solid fa-circle"></i> Status</div>
          <div class="card-body" style="padding:10px 14px">
            <div class="status-grid">
              <?php foreach(['on-duty'=>['#22c55e','On Duty'],'off-duty'=>['#94a3b8','Off Duty'],'on-break'=>['#f59e0b','On Break'],'suspended'=>['#ef4444','Suspended']] as $sv=>[$sc,$sl]):?>
              <div class="st-opt"><input type="radio" name="status" id="st-<?=$sv?>" value="<?=$sv?>" <?=($doc['status']??'')===$sv?'checked':''?>>
                <label for="st-<?=$sv?>"><div class="st-dot" style="background:<?=$sc?>"></div><?=$sl?></label>
              </div>
              <?php endforeach;?>
            </div>
          </div>
        </div>
        <!-- Preferences -->
        <div class="card">
          <div class="card-head"><i class="fa-solid fa-sliders"></i> Preferences</div>
          <div class="card-body" style="padding:10px 14px">
            <div class="toggle-row">
              <div><div style="font-size:.8125rem;font-weight:600">Walk-in Patients</div><div style="font-size:.6875rem;color:var(--s500)">Accept unscheduled visits</div></div>
              <label class="tgl"><input type="checkbox" name="accepts_walkin" value="1" <?=!empty($doc['accepts_walkin']??1)?'checked':''?>><span class="tgl-track"></span><span class="tgl-knob"></span></label>
            </div>
            <div class="toggle-row">
              <div><div style="font-size:.8125rem;font-weight:600">Tele-consultation</div><div style="font-size:.6875rem;color:var(--s500)">Video/phone consults</div></div>
              <label class="tgl"><input type="checkbox" name="accepts_tele" value="1" <?=!empty($doc['accepts_tele']??0)?'checked':''?>><span class="tgl-track"></span><span class="tgl-knob"></span></label>
            </div>
          </div>
        </div>
      </div>

      <!--  CENTRE: Main form  -->
      <div style="display:flex;flex-direction:column;gap:12px">
        <!-- Personal -->
        <div class="card">
          <div class="card-head"><i class="fa-solid fa-user"></i> Personal Information</div>
          <div class="card-body">
            <div class="grid2">
              <div class="fg"><label class="lbl">Full Name <span class="req">*</span></label><input class="inp" type="text" name="name" required value="<?=htmlspecialchars($doc['name'])?>"></div>
              <div class="fg"><label class="lbl">Gender</label>
                <select class="inp" name="gender"><option value="">— Select —</option><?php foreach(['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l):?><option value="<?=$v?>" <?=($doc['gender']??'')===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select>
              </div>
            </div>
            <div class="grid2">
              <div class="fg"><label class="lbl">Email</label><input class="inp" type="email" name="email" placeholder="doctor@email.com" value="<?=htmlspecialchars($doc['email']??'')?>"></div>
              <div class="fg"><label class="lbl">Phone</label><input class="inp" type="tel" name="phone" placeholder="+254 700 000 000" value="<?=htmlspecialchars($doc['phone']??'')?>"></div>
            </div>
          </div>
        </div>
        <!-- Credentials -->
        <div class="card">
          <div class="card-head"><i class="fa-solid fa-stethoscope"></i> Credentials</div>
          <div class="card-body">
            <div class="grid2">
              <div class="fg"><label class="lbl">Specialty <span class="req">*</span></label><input class="inp" type="text" name="specialty" required placeholder="e.g. Cardiologist" value="<?=htmlspecialchars($doc['specialty']??'')?>"></div>
              <div class="fg"><label class="lbl">KMPDC Licence</label><input class="inp" type="text" name="kmpdc_licence" placeholder="KMPDC/0001/2024" value="<?=htmlspecialchars($doc['kmpdc_licence']??'')?>"></div>
            </div>
            <div class="grid2">
              <div class="fg"><label class="lbl">Years Experience</label><input class="inp" type="number" name="years_exp" min="0" max="60" value="<?=(int)($doc['years_exp']??0)?>"></div>
              <div class="fg"><label class="lbl">Consult Fee (KES)</label><input class="inp" type="number" name="consult_fee" min="0" step="100" value="<?=(float)($doc['consult_fee']??0)?>"></div>
            </div>
            <div class="grid2">
              <div class="fg"><label class="lbl">Department</label>
                <select class="inp" name="department_id"><option value="">— None —</option><?php foreach($depts as $d):?><option value="<?=$d['id']?>" <?=($doc['department_id']??0)==$d['id']?'selected':''?>><?=htmlspecialchars($d['name'])?></option><?php endforeach;?></select>
              </div>
              <div class="fg"><label class="lbl">Education</label><input class="inp" type="text" name="education" placeholder="MBChB, University of Nairobi" value="<?=htmlspecialchars($doc['education']??'')?>"></div>
            </div>
            <div class="fg"><label class="lbl">Professional Bio</label><textarea class="inp" name="bio" rows="3" placeholder="Brief professional background..."><?=htmlspecialchars($doc['bio']??'')?></textarea></div>
          </div>
        </div>
        <!-- Languages -->
        <div class="card">
          <div class="card-head"><i class="fa-solid fa-language"></i> Languages Spoken</div>
          <div class="card-body">
            <div class="lang-grid">
              <?php foreach($allLangs as $lang): $ck=in_array($lang,$selLang);?>
              <label class="lang-item <?=$ck?'ck':''?>" id="ll-<?=md5($lang)?>">
                <input type="checkbox" name="languages[]" value="<?=htmlspecialchars($lang)?>" <?=$ck?'checked':''?> onchange="toggleLang(this)">
                <?=htmlspecialchars($lang)?>
              </label>
              <?php endforeach;?>
            </div>
          </div>
        </div>
      </div>

      <!--  RIGHT: Availability  -->
      <div style="display:flex;flex-direction:column;gap:12px">
        <div class="card">
          <div class="card-head"><i class="fa-solid fa-calendar-week"></i> Weekly Schedule</div>
          <div class="card-body" style="padding:12px 14px">
            <div style="font-size:.6875rem;color:var(--s500);margin-bottom:10px">Check days available. Set time range (e.g. 9:00-17:00)</div>
            <div class="avail-grid">
              <?php foreach($days as $abbr=>$full): $isOn=!empty($avail[$abbr]); $tv=$avail[$abbr]??'9:00-17:00';?>
              <div class="avail-col <?=$isOn?'active':''?>" id="aday-<?=$abbr?>">
                <label>
                  <input type="checkbox" name="avail_<?=$abbr?>" value="1" <?=$isOn?'checked':''?> onchange="toggleDay('<?=$abbr?>',this.checked)">
                  <div class="avail-day-name"><?=$abbr?></div>
                  <input type="text" class="avail-time" name="time_<?=$abbr?>" id="atime-<?=$abbr?>" value="<?=htmlspecialchars($tv)?>" style="display:<?=$isOn?'block':'none'?>">
                </label>
              </div>
              <?php endforeach;?>
            </div>
          </div>
        </div>
        <!-- Summary stats -->
        <div class="card">
          <div class="card-head"><i class="fa-solid fa-chart-simple"></i> Profile Stats</div>
          <div class="card-body" style="padding:10px 14px">
            <?php $docAppts=$db->fetchOne('SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND doctor_id=:d',[':h'=>$hid,':d'=>$did])['c']??0;
            $docPending=$db->fetchOne('SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND doctor_id=:d AND status="pending"',[':h'=>$hid,':d'=>$did])['c']??0;
            foreach([['Total Bookings',$docAppts,'var(--blue)'],['Pending',$docPending,'var(--amber)'],['Languages',count(array_filter($selLang)),'var(--teal)']] as [$sl,$sv,$sc]):?>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--s100);font-size:.8125rem">
              <span style="color:var(--s600)"><?=$sl?></span>
              <span style="font-weight:700;color:<?=$sc?>"><?=$sv?></span>
            </div>
            <?php endforeach;?>
            <div style="padding:6px 0;font-size:.8125rem;display:flex;justify-content:space-between">
              <span style="color:var(--s600)">Rating</span>
              <span style="font-weight:700;color:var(--amber)">
                <?=number_format($doc['rating']??0,1)?> <i class="fa-solid fa-star" style="font-size:10px"></i>
              </span>
            </div>
          </div>
        </div>
        <!-- Save card -->
        <div class="card">
          <div class="card-body" style="padding:14px">
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:10px"><i class="fa-solid fa-floppy-disk"></i> Save All Changes</button>
            <a href="/hospital/onboarding/doctor-profile.php?id=<?=$did?>" class="btn btn-ghost" style="width:100%;justify-content:center;padding:9px;margin-top:8px"><i class="fa-solid fa-arrow-left"></i> Cancel</a>
          </div>
        </div>
      </div>

    </div><!-- /body-grid -->
  </div><!-- /content -->
  </form>
</div><!-- /db-main -->
</div><!-- /db-wrap -->

<div class="mob-overlay" id="mobOverlay" onclick="closeSb()"></div>
<button class="mob-toggle" onclick="toggleSb()"><span class="material-symbols-outlined">menu</span></button>

<script>
function toggleSb(){const sb=document.getElementById('cpSidebar'),ov=document.getElementById('mobOverlay');const o=sb.classList.toggle('open');ov.classList.toggle('open',o);document.body.style.overflow=o?'hidden':'';}
function closeSb(){document.getElementById('cpSidebar')?.classList.remove('open');document.getElementById('mobOverlay')?.classList.remove('open');document.body.style.overflow='';}
document.getElementById('mobToggle')?.addEventListener('click',toggleSb);
function previewAv(input){if(!input.files[0])return;const r=new FileReader();r.onload=e=>{const prev=document.getElementById('avPreview');if(prev.tagName==='IMG'){prev.src=e.target.result;}else{const img=document.createElement('img');img.src=e.target.result;img.className='av-img';img.id='avPreview';prev.replaceWith(img);}};r.readAsDataURL(input.files[0]);}
function toggleLang(cb){cb.closest('.lang-item')?.classList.toggle('ck',cb.checked);}
function toggleDay(abbr,on){document.getElementById('aday-'+abbr)?.classList.toggle('active',on);const t=document.getElementById('atime-'+abbr);if(t)t.style.display=on?'block':'none';}
</script>
</body>
</html>
