<?php
/**
 * Planeazzy — patients/dashboard.php
 * Full patient dashboard with all tabs
 */
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
require_once dirname(__DIR__). '/services/Database.php';
Security::requireAuth('/patients/login.php');
$db  = Database::getInstance();
$pid = (int)$_SESSION['patient_id'];

$tab = Security::clean($_GET['tab'] ?? 'overview');
$validTabs = ['overview','appointments','nearby','records','vitals','prescriptions','emergency','notifications','settings'];
if (!in_array($tab, $validTabs)) $tab = 'overview';

$tabMap = [
  'overview'      => ['icon'=>'dashboard',       'label'=>'Overview'],
  'appointments'  => ['icon'=>'calendar_month',  'label'=>'Appointments'],
  'nearby'        => ['icon'=>'near_me',         'label'=>'Nearby Services'],
  'records'       => ['icon'=>'folder_health',   'label'=>'Health Records'],
  'vitals'        => ['icon'=>'monitor_heart',   'label'=>'Vitals'],
  'prescriptions' => ['icon'=>'medication',      'label'=>'Prescriptions'],
  'emergency'     => ['icon'=>'emergency',       'label'=>'Emergency'],
  'notifications' => ['icon'=>'notifications',   'label'=>'Notifications'],
  'settings'      => ['icon'=>'manage_accounts', 'label'=>'Settings'],
];

// Data
$appointments = $db->fetchAll(
  'SELECT a.*,p.name as pname,p.type as ptype,p.address as paddress,p.phone as pphone
   FROM appointments a LEFT JOIN providers p ON a.provider_id=p.id
   WHERE a.patient_id=:pid ORDER BY a.appointment_at ASC LIMIT 20',
  [':pid'=>$pid]
);
$upcomingCount = (int)($db->fetchOne('SELECT COUNT(*) c FROM appointments WHERE patient_id=:pid AND status IN("scheduled","confirmed") AND appointment_at>NOW()',[':pid'=>$pid])['c']??0);
$rxCount       = (int)($db->fetchOne('SELECT COUNT(*) c FROM prescriptions WHERE patient_id=:pid AND status="active"',[':pid'=>$pid])['c']??0);
$notifications = $db->fetchAll('SELECT * FROM notifications WHERE patient_id=:pid ORDER BY created_at DESC LIMIT 20',[':pid'=>$pid]);
$unread        = count(array_filter($notifications, fn($n)=>!$n['is_read']));
$records       = $db->fetchAll('SELECT * FROM health_records WHERE patient_id=:pid ORDER BY record_date DESC LIMIT 20',[':pid'=>$pid]);
$vitals        = $db->fetchAll('SELECT * FROM patient_vitals WHERE patient_id=:pid ORDER BY recorded_at DESC LIMIT 30',[':pid'=>$pid]);
$prescriptions = $db->fetchAll('SELECT pr.*,p.name pname FROM prescriptions pr LEFT JOIN providers p ON pr.provider_id=p.id WHERE pr.patient_id=:pid ORDER BY pr.created_at DESC LIMIT 20',[':pid'=>$pid]);
$nearby = $db->fetchAll(
  'SELECT *,ROUND(111.045*DEGREES(ACOS(LEAST(1,COS(RADIANS(-1.2921))*COS(RADIANS(latitude))*COS(RADIANS(longitude)-RADIANS(36.8219))+SIN(RADIANS(-1.2921))*SIN(RADIANS(latitude))))),2) as dist_km FROM providers WHERE is_active=1 AND latitude IS NOT NULL ORDER BY dist_km ASC LIMIT 12',
  []
);
$patData = $db->fetchOne('SELECT * FROM patients WHERE id=:id',[':id'=>$pid]);
$prefSvc = $patData['preferred_service'] ?? '';
$city    = $patData['city'] ?? 'Nairobi';

$typeFilter = Security::clean($_GET['type'] ?? 'all');
if ($typeFilter !== 'all') {
  $nearby = array_values(array_filter($nearby, fn($p)=>$p['type']===$typeFilter));
}

$activeTab = $tab;
$tabIcon   = $tabMap[$tab]['icon'];
$tabLabel  = $tabMap[$tab]['label'];
include dirname(__DIR__). '/includes/header.php';
?>
<main class="page-content fade-up">
<?php
$H = '<span class="material-symbols-outlined">';
$C = '</span>';

// ── HELPERS ──────────────────────────────────────────────
function statusPill(string $s): string {
  $map = ['scheduled'=>'blue','confirmed'=>'green','completed'=>'gray','cancelled'=>'red','in_progress'=>'teal','no_show'=>'orange'];
  $cls = $map[$s] ?? 'gray';
  return "<span class=\"pill pill-$cls\">".ucfirst($s)."</span>";
}
function typeIcon(string $t): string {
  $icons = ['hospital'=>'domain','doctor'=>'stethoscope','clinic'=>'local_pharmacy','ambulance'=>'ambulance','pharmacy'=>'pill','lab'=>'biotech'];
  return $icons[$t] ?? 'medical_services';
}

if ($tab === 'overview'):
  $hour = (int)date('H');
  $greet = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
  $fn = htmlspecialchars(explode(' ',$patName)[0]);
?>
<div class="pg-head-row">
  <div>
    <h1><?= $greet ?>, <?= $fn ?>!</h1>
    <p>Your health dashboard · <?= date('l, F j, Y') ?></p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="openModal('bookModal')">
    <span class="material-symbols-outlined">add</span> Book Appointment
  </button>
</div>

<div class="stats-row mb3">
  <div class="stat-card"><div class="stat-ic blue"><span class="material-symbols-outlined">calendar_month</span></div><div><div class="stat-val"><?= $upcomingCount ?></div><div class="stat-lbl">Upcoming Visits</div><div class="stat-sub">Scheduled appointments</div></div></div>
  <div class="stat-card"><div class="stat-ic teal"><span class="material-symbols-outlined">medication</span></div><div><div class="stat-val"><?= $rxCount ?></div><div class="stat-lbl">Active Prescriptions</div><div class="stat-sub">Currently active</div></div></div>
  <div class="stat-card"><div class="stat-ic green"><span class="material-symbols-outlined">near_me</span></div><div><div class="stat-val"><?= count($nearby) ?></div><div class="stat-lbl">Nearby Providers</div><div class="stat-sub">Within radius</div></div></div>
  <div class="stat-card"><div class="stat-ic <?= $unread>0?'red':'blue' ?>"><span class="material-symbols-outlined">notifications</span></div><div><div class="stat-val"><?= $unread ?></div><div class="stat-lbl">Notifications</div><div class="stat-sub">Unread alerts</div></div></div>
</div>

<!-- Quick actions -->
<div class="card mb3">
  <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">bolt</span>Quick Actions</span></div>
  <div class="card-body">
    <div class="qa-grid">
      <?php
      $qas=[
        ['href'=>'?tab=nearby&type=doctor',    'ic'=>'stethoscope',    'lbl'=>'Find Doctor'],
        ['href'=>'?tab=nearby&type=clinic',    'ic'=>'local_pharmacy', 'lbl'=>'Find Clinic'],
        ['href'=>'?tab=nearby&type=hospital',  'ic'=>'domain',         'lbl'=>'Hospital'],
        ['href'=>'?tab=nearby&type=ambulance', 'ic'=>'ambulance',      'lbl'=>'Ambulance','emerg'=>true],
        ['href'=>'/patients/telehealth.php',   'ic'=>'video_chat',     'lbl'=>'Telehealth'],
        ['href'=>'?tab=nearby&type=lab',       'ic'=>'biotech',        'lbl'=>'Lab Tests'],
        ['href'=>'?tab=nearby&type=pharmacy',  'ic'=>'pill',           'lbl'=>'Pharmacy'],
        ['href'=>'?tab=records',               'ic'=>'folder_health',  'lbl'=>'My Records'],
        ['href'=>'?tab=vitals',                'ic'=>'monitor_heart',  'lbl'=>'Log Vitals'],
        ['href'=>'?tab=emergency',             'ic'=>'emergency',      'lbl'=>'Emergency','emerg'=>true],
      ];
      foreach($qas as $q): ?>
      <a href="<?= $q['href'] ?>" class="qa-item">
        <div class="qa-ic <?= !empty($q['emerg'])?'emerg':'' ?>"><span class="material-symbols-outlined"><?= $q['ic'] ?></span></div>
        <span class="qa-lbl"><?= $q['lbl'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Appointments + Notifications -->
<div class="grid-2 mb3">
  <div class="card">
    <div class="card-head">
      <span class="card-title"><span class="material-symbols-outlined">calendar_month</span>Upcoming Appointments</span>
      <a href="?tab=appointments" class="card-action">View all <span class="material-symbols-outlined">arrow_forward</span></a>
    </div>
    <div class="card-body">
      <?php $upcoming=array_filter($appointments,fn($a)=>strtotime($a['appointment_at'])>time()&&in_array($a['status'],['scheduled','confirmed']));
      if(empty($upcoming)): ?>
      <div class="empty-state"><span class="material-symbols-outlined">event_busy</span><h3>No upcoming appointments</h3><p>Book your first appointment.</p><button class="btn btn-primary btn-sm mt2" onclick="openModal('bookModal')">Book Now</button></div>
      <?php else: foreach(array_slice($upcoming,0,4) as $a): $dt=new DateTime($a['appointment_at']); ?>
      <div class="appt-item">
        <div class="appt-date"><span class="day"><?= $dt->format('d') ?></span><span class="mon"><?= $dt->format('M') ?></span></div>
        <div class="appt-info">
          <div class="appt-title"><?= htmlspecialchars($a['title']??ucfirst($a['service_type']).' Visit') ?></div>
          <div class="appt-meta"><span class="material-symbols-outlined">schedule</span><?= $dt->format('g:i A') ?><?= $a['pname']?' · '.htmlspecialchars($a['pname']):'' ?></div>
        </div>
        <?= statusPill($a['status']) ?>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head">
      <span class="card-title"><span class="material-symbols-outlined">notifications</span>Recent Notifications</span>
      <a href="?tab=notifications" class="card-action">View all <span class="material-symbols-outlined">arrow_forward</span></a>
    </div>
    <div class="card-body" style="padding:0">
      <?php if(empty($notifications)): ?>
      <div class="empty-state"><span class="material-symbols-outlined">notifications_off</span><h3>No notifications</h3></div>
      <?php else:
      $nicons=['appointment'=>'event_available','reminder'=>'medication','result'=>'lab_research','emergency'=>'emergency','system'=>'info','promotion'=>'local_offer'];
      $ncols =['appointment'=>'blue','reminder'=>'teal','result'=>'orange','emergency'=>'red','system'=>'blue','promotion'=>'yellow'];
      foreach(array_slice($notifications,0,5) as $n):
        $ic=$nicons[$n['type']]??'notifications'; $cl=$ncols[$n['type']]??'blue'; ?>
      <div class="notif-item <?= !$n['is_read']?'unread':'' ?>">
        <div class="notif-ico" style="background:var(--<?=$cl?>-l)"><span class="material-symbols-outlined" style="color:var(--<?=$cl?>)"><?=$ic?></span></div>
        <div style="flex:1;min-width:0">
          <div class="notif-title"><?=htmlspecialchars($n['title'])?></div>
          <div class="notif-msg"><?=htmlspecialchars(mb_strimwidth($n['message'],0,70,'…'))?></div>
          <div class="notif-time"><?=date('M j, g:i A',strtotime($n['created_at']))?></div>
        </div>
        <?php if(!$n['is_read']): ?><div class="notif-dot"></div><?php endif; ?>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- Location + H3 panel -->
<div class="grid-2">
  <div class="map-card">
    <div class="map-box">
      <div class="map-bg-img"></div><div class="map-overlay"></div><div class="map-hex-bg"></div>
      <div class="map-ping"><div class="map-ring1"><div class="map-ring2"><div class="map-center"></div></div></div></div>
      <div class="map-labels">
        <div class="map-loc-pill"><span class="material-symbols-outlined">near_me</span><span id="mapLocText"><?= htmlspecialchars($city) ?>, Kenya</span></div>
        <div class="map-count-pill"><?= count($nearby) ?> nearby</div>
      </div>
    </div>
    <div class="map-body">
      <h3 style="font-size:14px;font-weight:800;margin-bottom:5px">Your Location</h3>
      <p style="font-size:12px;color:var(--muted);margin-bottom:12px">Used to match you with nearby healthcare services and dispatch emergency responders.</p>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary btn-sm" onclick="requestLocation()"><span class="material-symbols-outlined">near_me</span> Update</button>
        <button class="btn btn-ghost btn-sm" onclick="openModal('locationModal')"><span class="material-symbols-outlined">edit_location_alt</span> Manual</button>
      </div>
    </div>
  </div>
  <div class="h3-panel">
    <div class="h3-panel-bg"></div>
    <div class="h3-content">
      <h3 class="h3-title">Real-Time Spatial Matching</h3>
      <p class="h3-sub">Planeazzy uses H3 hexagonal spatial indexing (like Uber) to instantly match you with the nearest available healthcare providers.</p>
      <div class="h3-id-box"><span class="h3-id-lbl">Hex-ID (R9)</span><span class="h3-id-val" id="hexId">8926e8d89ffffff</span></div>
      <div class="h3-stats">
        <div><div class="h3-stat-v"><?= count($nearby) ?></div><div class="h3-stat-l">In range</div></div>
        <div><div class="h3-stat-v">&lt;200ms</div><div class="h3-stat-l">Latency</div></div>
        <div><div class="h3-stat-v">K=2</div><div class="h3-stat-l">K-ring</div></div>
      </div>
      <div style="margin-top:14px">
        <div class="kring-label">K-Ring Neighbor Cells</div>
        <div class="kring-cells">
          <span class="kring-cell center">8926e8d89ffffff</span>
          <span class="kring-cell">8926e8d8bffffff</span>
          <span class="kring-cell">8926e8d99ffffff</span>
          <span class="kring-cell">8926e8c89ffffff</span>
          <span class="kring-cell">8926e8d8dffffff</span>
          <span class="kring-cell">8926e8d8fffffff</span>
        </div>
      </div>
    </div>
  </div>
</div>

<?php elseif($tab==='appointments'): ?>
<div class="pg-head-row">
  <div><h1>Appointments</h1><p>Manage your healthcare appointments.</p></div>
  <button class="btn btn-primary btn-sm" onclick="openModal('bookModal')"><span class="material-symbols-outlined">add</span> Book New</button>
</div>
<?php $apptTab=Security::clean($_GET['at']??'upcoming'); ?>
<div class="tab-bar mb3">
  <a href="?tab=appointments&at=upcoming" class="tab-item <?=$apptTab==='upcoming'?'active':''?>">Upcoming</a>
  <a href="?tab=appointments&at=past"     class="tab-item <?=$apptTab==='past'?'active':''?>">Past</a>
</div>
<div class="card">
  <div class="card-body" style="padding:0">
    <?php $filtered=array_filter($appointments,function($a) use($apptTab){
      $fut=strtotime($a['appointment_at'])>time();
      return $apptTab==='upcoming'?($fut&&in_array($a['status'],['scheduled','confirmed'])):(!$fut||$a['status']==='completed');
    });
    if(empty($filtered)): ?>
    <div class="empty-state"><span class="material-symbols-outlined">calendar_today</span><h3>No <?=$apptTab?> appointments</h3><?php if($apptTab==='upcoming'):?><button class="btn btn-primary btn-sm mt2" onclick="openModal('bookModal')">Book Now</button><?php endif;?></div>
    <?php else: foreach($filtered as $a): $dt=new DateTime($a['appointment_at']); ?>
    <div style="display:flex;align-items:center;gap:14px;padding:16px 18px;border-bottom:1px solid var(--border)">
      <div class="appt-date"><span class="day"><?=$dt->format('d')?></span><span class="mon"><?=$dt->format('M')?></span></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:14px;font-weight:800;margin-bottom:3px"><?=htmlspecialchars($a['title']??ucfirst($a['service_type']).' Appointment')?></div>
        <div class="appt-meta">
          <span class="material-symbols-outlined">schedule</span><?=$dt->format('g:i A, l')?>
          <?php if($a['pname']):?><span>·</span><span class="material-symbols-outlined">person</span><?=htmlspecialchars($a['pname'])?><?php endif;?>
          <?php if($a['paddress']):?><span>·</span><?=htmlspecialchars($a['paddress'])?><?php endif;?>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <?= statusPill($a['status']) ?>
        <?php if(in_array($a['status'],['scheduled','confirmed'])): ?>
        <button class="btn btn-ghost btn-sm">Reschedule</button>
        <?php endif; ?>
        <?php if($a['location_type']==='telehealth'): ?>
        <a href="/patients/telehealth.php?appt=<?=$a['id']?>" class="btn btn-teal btn-sm"><span class="material-symbols-outlined">video_chat</span> Join</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php elseif($tab==='nearby'): ?>
<div class="pg-head"><h1>Nearby Healthcare Services</h1><p>Providers matched to your location using H3 spatial indexing.</p></div>
<?php $types=['all'=>'All','doctor'=>'Doctors','hospital'=>'Hospitals','clinic'=>'Clinics','ambulance'=>'Ambulance','pharmacy'=>'Pharmacy','lab'=>'Lab Tests']; ?>
<div class="filter-strip mb3">
  <?php foreach($types as $k=>$v): ?>
  <a href="?tab=nearby&type=<?=$k?>" class="filter-chip <?=$typeFilter===$k?'active':''?>"><?=$v?></a>
  <?php endforeach; ?>
</div>
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:12px 16px;background:var(--navy);border-radius:var(--r-lg);margin-bottom:18px">
  <div style="display:flex;align-items:center;gap:10px">
    <div style="width:34px;height:34px;border-radius:var(--r);background:var(--blue);display:flex;align-items:center;justify-content:center"><span class="material-symbols-outlined" style="color:#fff;font-size:18px">near_me</span></div>
    <div>
      <div style="font-size:13px;font-weight:800;color:#fff" id="nearbyLocText"><?= htmlspecialchars($city) ?>, Kenya</div>
      <div style="font-size:10px;color:rgba(255,255,255,.4);font-family:monospace">Hex: 8926e8d89ffffff · K=2</div>
    </div>
  </div>
  <button class="btn btn-sm" style="background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.15)" onclick="openModal('locationModal')"><span class="material-symbols-outlined">edit_location_alt</span> Change</button>
</div>
<?php if(empty($nearby)): ?>
<div class="card"><div class="empty-state"><span class="material-symbols-outlined">search_off</span><h3>No providers found nearby</h3><p>Try changing your location or service filter.</p></div></div>
<?php else: ?>
<div class="prov-grid">
  <?php foreach($nearby as $p): $ic=typeIcon($p['type']); ?>
  <div class="prov-card">
    <div class="prov-card-ico ptype-<?=$p['type']?>"><span class="material-symbols-outlined"><?=$ic?></span></div>
    <div class="prov-name"><?=htmlspecialchars($p['name'])?></div>
    <div class="prov-spec"><?=htmlspecialchars($p['specialty']??ucfirst($p['type']))?> · <?=htmlspecialchars($p['city']??'Nairobi')?></div>
    <div class="prov-meta">
      <div class="prov-dist"><span class="material-symbols-outlined">near_me</span><?=number_format($p['dist_km']??0,1)?> km</div>
      <div class="prov-rating"><span class="material-symbols-outlined">star</span><?=number_format($p['rating'],1)?> (<?=number_format($p['review_count'])?>)</div>
    </div>
    <?php if($p['is_available']): ?><div class="prov-avail"><span class="avail-dot"></span> Available now</div><?php endif; ?>
    <?php if($p['phone']): ?><div style="font-size:11px;color:var(--silver);margin-top:6px;display:flex;align-items:center;gap:3px"><span class="material-symbols-outlined" style="font-size:13px">call</span><?=htmlspecialchars($p['phone'])?></div><?php endif; ?>
    <div style="display:flex;gap:8px;margin-top:12px">
      <button class="btn btn-primary btn-sm" style="flex:2" onclick="openModal('bookModal')">Book Now</button>
      <?php if($p['phone']): ?>
      <a href="tel:<?=$p['phone']?>" class="btn btn-outline btn-sm" style="flex:1"><span class="material-symbols-outlined">call</span></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif($tab==='records'): ?>
<div class="pg-head-row">
  <div><h1>Health Records</h1><p>Your complete medical history.</p></div>
  <button class="btn btn-primary btn-sm"><span class="material-symbols-outlined">upload</span> Upload</button>
</div>
<div class="card">
  <div class="card-body" style="padding:0">
    <?php
    $ricons=['diagnosis'=>['stethoscope','teal'],'prescription'=>['medication','blue'],'lab_result'=>['biotech','purple'],'imaging'=>['radiology','blue'],'vaccination'=>['vaccines','green'],'allergy'=>['allergy','red'],'vital'=>['monitor_heart','orange'],'note'=>['note_alt','yellow']];
    if(empty($records)): ?>
    <div class="empty-state"><span class="material-symbols-outlined">folder_open</span><h3>No health records</h3><p>Upload your medical documents or they'll appear after a visit.</p></div>
    <?php else: foreach($records as $r): [$rico,$rcl]=$ricons[$r['record_type']]??['description','blue']; ?>
    <div style="display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--border)">
      <div class="rec-ico" style="background:var(--<?=$rcl?>-l)"><span class="material-symbols-outlined" style="color:var(--<?=$rcl?>)"><?=$rico?></span></div>
      <div style="flex:1;min-width:0">
        <div class="rec-title"><?=htmlspecialchars($r['title'])?></div>
        <div class="rec-meta"><span class="pill pill-<?=$rcl?>"><?=ucfirst(str_replace('_',' ',$r['record_type']))?></span><?php if($r['provider_name']): ?><span><?=htmlspecialchars($r['provider_name'])?></span><?php endif;?><span><?=date('M j, Y',strtotime($r['record_date']))?></span></div>
      </div>
      <button class="btn btn-ghost btn-sm"><span class="material-symbols-outlined">visibility</span></button>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php elseif($tab==='vitals'): ?>
<div class="pg-head-row">
  <div><h1>Vitals &amp; Metrics</h1><p>Track your health measurements over time.</p></div>
  <button class="btn btn-primary btn-sm" onclick="openModal('vitalModal')"><span class="material-symbols-outlined">add</span> Log Vital</button>
</div>
<?php
$vdefs=[['type'=>'heart_rate','emoji'=>'❤️','unit'=>'bpm','label'=>'Heart Rate'],['type'=>'blood_pressure','emoji'=>'🩸','unit'=>'mmHg','label'=>'Blood Pressure'],['type'=>'temperature','emoji'=>'🌡️','unit'=>'°C','label'=>'Temperature'],['type'=>'blood_glucose','emoji'=>'💉','unit'=>'mg/dL','label'=>'Blood Glucose'],['type'=>'oxygen_saturation','emoji'=>'💨','unit'=>'%','label'=>'O₂ Saturation'],['type'=>'weight','emoji'=>'⚖️','unit'=>'kg','label'=>'Weight'],['type'=>'height','emoji'=>'📏','unit'=>'cm','label'=>'Height'],['type'=>'bmi','emoji'=>'🏃','unit'=>'','label'=>'BMI']];
foreach($vitals as $v){ foreach($vdefs as &$d){ if($d['type']===$v['metric_type']){ $d['val']=$v['value_str']??number_format($v['value_num'],1); break; } } }
?>
<div class="vit-grid mb3">
  <?php foreach($vdefs as $vd): ?>
  <div class="vit-card"><div class="vit-ico"><?=$vd['emoji']?></div><div><span class="vit-val"><?=$vd['val']??'--'?></span><span class="vit-unit"><?=$vd['unit']?></span></div><div class="vit-lbl"><?=$vd['label']?></div></div>
  <?php endforeach; ?>
</div>
<div class="card">
  <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">history</span>History</span></div>
  <div class="card-body" style="padding:0">
    <?php if(empty($vitals)): ?>
    <div class="empty-state"><span class="material-symbols-outlined">monitor_heart</span><h3>No vitals recorded</h3></div>
    <?php else: foreach($vitals as $v): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:12px 18px;border-bottom:1px solid var(--border)">
      <div class="stat-ic blue" style="width:36px;height:36px;border-radius:var(--r);flex-shrink:0"><span class="material-symbols-outlined" style="font-size:17px">monitor_heart</span></div>
      <div style="flex:1"><div style="font-size:13px;font-weight:700"><?=ucfirst(str_replace('_',' ',$v['metric_type']))?></div><div style="font-size:11px;color:var(--silver)"><?=date('M j, Y g:i A',strtotime($v['recorded_at']))?></div></div>
      <div style="font-family:var(--ff-head);font-size:18px;font-weight:900;color:var(--blue)"><?=$v['value_str']??number_format($v['value_num'],1)?> <span style="font-size:11px;font-weight:600;color:var(--muted)"><?=$v['unit']??''?></span></div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php elseif($tab==='prescriptions'): ?>
<div class="pg-head"><h1>Prescriptions</h1><p>Your active and past medication prescriptions.</p></div>
<div class="card">
  <div class="card-body" style="padding:0">
    <?php if(empty($prescriptions)): ?>
    <div class="empty-state"><span class="material-symbols-outlined">medication</span><h3>No prescriptions</h3><p>Prescriptions from your doctors will appear here.</p></div>
    <?php else: foreach($prescriptions as $rx): ?>
    <div class="rx-item" style="padding:14px 18px">
      <div class="rx-ico"><span class="material-symbols-outlined">medication</span></div>
      <div style="flex:1;min-width:0">
        <div class="rx-name"><?=htmlspecialchars($rx['medication_name'])?></div>
        <div class="rx-dosage"><?=htmlspecialchars($rx['dosage']??'')?><?=$rx['frequency']?' · '.htmlspecialchars($rx['frequency']):''?></div>
        <?php if($rx['pname']): ?><div style="font-size:11px;color:var(--silver);margin-top:2px">By <?=htmlspecialchars($rx['pname'])?></div><?php endif;?>
      </div>
      <div style="text-align:right">
        <span class="pill <?=$rx['status']==='active'?'pill-green':'pill-gray'?>"><?=ucfirst($rx['status'])?></span>
        <?php if($rx['refills_left']>0): ?><div style="font-size:11px;color:var(--muted);margin-top:4px"><?=$rx['refills_left']?> refill<?=$rx['refills_left']>1?'s':''?> left</div><?php endif;?>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php elseif($tab==='emergency'): ?>
<div class="pg-head"><h1>Emergency Services</h1><p>24/7 immediate healthcare dispatch. Your location will be shared with responders.</p></div>
<div id="emergOkBox" class="alert alert-ok hidden"><span class="material-symbols-outlined">check_circle</span><span id="emergOk"></span></div>
<div id="emergErrBox" class="alert alert-err hidden"><span class="material-symbols-outlined">error</span><span id="emergErr"></span></div>

<div class="card mb3" style="text-align:center;padding:40px 20px">
  <button id="sosBtn" onclick="triggerSOS()" style="width:156px;height:156px;border-radius:50%;background:var(--red);border:none;cursor:pointer;color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;margin:0 auto;box-shadow:0 0 0 20px rgba(220,38,38,.1),0 0 0 40px rgba(220,38,38,.06);animation:pulse-red 2.5s infinite;font-family:var(--ff-head)">
    <span class="material-symbols-outlined" style="font-size:52px">emergency</span>
    <span style="font-size:18px;font-weight:900">SOS</span>
    <span style="font-size:11px;opacity:.8">Tap to dispatch</span>
  </button>
  <h2 style="font-size:18px;font-weight:900;margin:20px 0 8px">Request Emergency Services</h2>
  <p style="font-size:13px;color:var(--muted);max-width:400px;margin:0 auto">Pressing SOS will immediately share your location and dispatch the nearest available responder.</p>
</div>
<div class="grid-3 mb3">
<?php
$etypes=[['type'=>'ambulance','icon'=>'ambulance','label'=>'Ambulance','desc'=>'Request immediate dispatch'],['type'=>'cardiac','icon'=>'cardiology','label'=>'Cardiac Emergency','desc'=>'Heart attack or arrest'],['type'=>'trauma','icon'=>'personal_injury','label'=>'Trauma / Injury','desc'=>'Accident or severe injury'],['type'=>'respiratory','icon'=>'air','label'=>'Breathing Emergency','desc'=>'Difficulty breathing'],['type'=>'other','icon'=>'medical_services','label'=>'Other Emergency','desc'=>'Any urgent medical need']];
foreach($etypes as $e): ?>
<div class="stat-card" style="flex-direction:column;gap:10px">
  <div class="stat-ic red"><span class="material-symbols-outlined"><?=$e['icon']?></span></div>
  <div><div style="font-size:13px;font-weight:800;margin-bottom:3px"><?=$e['label']?></div><div style="font-size:12px;color:var(--muted)"><?=$e['desc']?></div></div>
  <button class="btn btn-red btn-sm btn-full" onclick="triggerSOS()"><span class="material-symbols-outlined">call</span> Request Now</button>
</div>
<?php endforeach; ?>
</div>
<?php $ambs=array_filter($nearby,fn($p)=>$p['type']==='ambulance'); if(!empty($ambs)): ?>
<div class="card">
  <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">ambulance</span>Nearest Ambulances</span></div>
  <div class="card-body" style="padding:0">
    <?php foreach($ambs as $a): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--border)">
      <div class="stat-ic red" style="width:42px;height:42px;border-radius:var(--r)"><span class="material-symbols-outlined">ambulance</span></div>
      <div style="flex:1"><div style="font-size:14px;font-weight:800"><?=htmlspecialchars($a['name'])?></div><div style="font-size:12px;color:var(--muted)"><?=number_format($a['dist_km']??0,1)?> km · <?=htmlspecialchars($a['phone']??'')?></div></div>
      <div style="display:flex;gap:8px">
        <?php if($a['phone']): ?><a href="tel:<?=$a['phone']?>" class="btn btn-red btn-sm"><span class="material-symbols-outlined">call</span> Call</a><?php endif;?>
        <button class="btn btn-outline btn-sm" onclick="triggerSOS()">Dispatch</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<script>
function triggerSOS(){
  if(!confirm('EMERGENCY SOS\n\nThis will share your location and dispatch emergency services.\n\nContinue?'))return;
  navigator.geolocation?.getCurrentPosition(pos=>{
    const el=document.getElementById('emergOk');
    if(el){el.textContent=`Emergency request sent. Responder dispatched to: ${pos.coords.latitude.toFixed(4)}, ${pos.coords.longitude.toFixed(4)}.`;document.getElementById('emergOkBox').classList.remove('hidden');}
  },()=>{
    const el=document.getElementById('emergErr');
    if(el){el.textContent='Could not get location. Call 999 or +254 20 229 6000 directly.';document.getElementById('emergErrBox').classList.remove('hidden');}
  });
}
</script>

<?php elseif($tab==='notifications'): ?>
<div class="pg-head-row">
  <div><h1>Notifications</h1><p><?=$unread?> unread.</p></div>
  <?php if($unread>0): ?><a href="?tab=notifications&markread=1" class="btn btn-ghost btn-sm"><span class="material-symbols-outlined">done_all</span> Mark all read</a><?php endif;?>
</div>
<div class="card">
  <div class="card-body" style="padding:0">
    <?php if(empty($notifications)): ?>
    <div class="empty-state"><span class="material-symbols-outlined">notifications_off</span><h3>No notifications</h3></div>
    <?php else:
    $ni=['appointment'=>'event_available','reminder'=>'medication','result'=>'lab_research','emergency'=>'emergency','system'=>'info','promotion'=>'local_offer'];
    $nc=['appointment'=>'blue','reminder'=>'teal','result'=>'orange','emergency'=>'red','system'=>'blue','promotion'=>'yellow'];
    foreach($notifications as $n): $ic=$ni[$n['type']]??'notifications'; $cl=$nc[$n['type']]??'blue'; ?>
    <div class="notif-item <?=!$n['is_read']?'unread':''?>">
      <div class="notif-ico" style="background:var(--<?=$cl?>-l)"><span class="material-symbols-outlined" style="color:var(--<?=$cl?>)"><?=$ic?></span></div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
          <div class="notif-title"><?=htmlspecialchars($n['title'])?></div>
          <?php if(!$n['is_read']): ?><div class="notif-dot" style="flex-shrink:0;margin-top:4px"></div><?php endif;?>
        </div>
        <div class="notif-msg"><?=htmlspecialchars($n['message'])?></div>
        <div class="notif-time"><?=date('M j, Y g:i A',strtotime($n['created_at']))?></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php elseif($tab==='settings'): ?>
<div class="pg-head"><h1>Account Settings</h1><p>Manage your profile, preferences and security.</p></div>
<div id="prefAlert" class="alert hidden"></div>
<div class="grid-2">
  <div class="card">
    <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">person</span>Profile</span></div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
        <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--teal));display:flex;align-items:center;justify-content:center;font-family:var(--ff-head);font-size:20px;font-weight:900;color:#fff"><?=$initials?></div>
        <div><div style="font-size:17px;font-weight:900"><?=$patName?></div><div style="font-size:13px;color:var(--muted)"><?=$patEmail?></div></div>
      </div>
      <?php foreach(['Full Name'=>$patName,'Email'=>$patEmail,'Phone'=>htmlspecialchars($patData['phone']??''),'Date of Birth'=>htmlspecialchars($patData['date_of_birth']??'')] as $lbl=>$val): ?>
      <div class="form-group"><label class="form-label"><?=$lbl?></label><input type="text" value="<?=$val?>" class="form-input" readonly></div>
      <?php endforeach; ?>
      <button class="btn btn-outline btn-sm"><span class="material-symbols-outlined">edit</span> Edit Profile</button>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">tune</span>Service Preferences</span></div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--muted);margin-bottom:14px">Change your primary healthcare service.</p>
      <div class="svc-grid" id="prefGrid">
        <?php
        $prefs=[['val'=>'healthcare','icon'=>'medical_information','title'=>'Healthcare','desc'=>'General health management'],['val'=>'doctors','icon'=>'stethoscope','title'=>'Doctors','desc'=>'Find &amp; book specialists'],['val'=>'clinics','icon'=>'local_pharmacy','title'=>'Clinics','desc'=>'Nearby outpatient clinics'],['val'=>'ambulance','icon'=>'ambulance','title'=>'Ambulance','desc'=>'Emergency dispatch'],['val'=>'telehealth','icon'=>'video_chat','title'=>'Telehealth','desc'=>'Video consultations'],['val'=>'pharmacy','icon'=>'pill','title'=>'Pharmacy','desc'=>'Order medications']];
        foreach($prefs as $pf): ?>
        <label class="svc-label" onclick="setPref('<?=$pf['val']?>')">
          <input type="radio" name="pref" value="<?=$pf['val']?>" <?=$prefSvc===$pf['val']?'checked':''?>>
          <div class="svc-card" data-val="<?=$pf['val']?>" <?=$prefSvc===$pf['val']?'style="border-color:var(--blue);background:var(--blue-l)"':''?>>
            <div class="svc-ico"><span class="material-symbols-outlined"><?=$pf['icon']?></span></div>
            <div><div class="svc-title"><?=$pf['title']?></div><div class="svc-desc"><?=$pf['desc']?></div></div>
            <span class="material-symbols-outlined svc-check">check_circle</span>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-primary btn-full mt2" onclick="savePreferences()"><span class="material-symbols-outlined">save</span> Save</button>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title"><span class="material-symbols-outlined">lock</span>Security</span></div>
    <div class="card-body">
      <div class="form-group"><label class="form-label">Current Password</label><input type="password" class="form-input" placeholder="Enter current password"></div>
      <div class="form-group"><label class="form-label">New Password</label><input type="password" class="form-input" placeholder="New password"></div>
      <div class="form-group"><label class="form-label">Confirm Password</label><input type="password" class="form-input" placeholder="Confirm new password"></div>
      <button class="btn btn-primary btn-sm"><span class="material-symbols-outlined">key</span> Change Password</button>
      <div style="height:1px;background:var(--border);margin:16px 0"></div>
      <a href="/api/auth/logout.php" class="btn btn-ghost btn-sm btn-full" style="justify-content:center"><span class="material-symbols-outlined">logout</span> Sign Out</a>
    </div>
  </div>
</div>
<script>
var _pref='<?= htmlspecialchars($prefSvc) ?>';
document.querySelectorAll('.pref-card').forEach(c=>c.classList.toggle('selected',c.dataset.val===_pref));
</script>
<?php endif; ?>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
