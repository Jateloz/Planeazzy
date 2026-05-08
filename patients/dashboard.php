<?php
/**
 * Planeazzy — Patient Dashboard v3
 * Production-ready, fully mobile-responsive, real DB data
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Database.php';
Security::requireAuth('/patients/login.php');

$pid   = (int)$_SESSION['patient_id'];
$db    = Database::getInstance();
$tab   = in_array($_GET['tab'] ?? '', ['overview','appointments','nearby','insurance','notifications','emergency','settings'])
       ? ($_GET['tab'] ?? 'overview') : 'overview';
$csrf  = Security::csrfToken();

/*  Patient data  */
$pat    = $db->fetchOne('SELECT * FROM patients WHERE id=:id', [':id' => $pid]);
// Ensure avatar_path column exists
try { $db->query('ALTER TABLE patients ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) DEFAULT NULL'); } catch(Exception $e) {}
$hasAvatar = !empty($pat['avatar_path']);
$avatarSrc = $hasAvatar ? htmlspecialchars($pat['avatar_path']) . '?t=' . time() : '';
// Ensure columns exist
try { $db->query('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS hospital_provider_id INT UNSIGNED DEFAULT NULL'); } catch(Exception $e) {}
try { $db->query('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS missed_count TINYINT UNSIGNED DEFAULT 0'); } catch(Exception $e) {}

// Main appointments (logged-in bookings)
$appts = $db->fetchAll(
    'SELECT a.*,
            COALESCE(
                CONCAT("Dr. ", sd.first_name, " ", sd.last_name),
                CONCAT("Dr. ", hd.name),
                hp.facility_name,
                a.title
            ) AS prov_name,
            COALESCE(sd.specialty, hd.specialty, hp.facility_type, a.service_type) AS prov_type,
            COALESCE(sd.specialty, hd.specialty) AS specialty,
            COALESCE(sd.rating, 4.5) AS prov_rating,
            hp.facility_name AS hosp_name,
            hp.county AS hosp_county,
            COALESCE(sd.avatar_path, hd.avatar_path, hp.logo_path) AS prov_avatar
     FROM appointments a
     LEFT JOIN doctors sd ON a.doctor_id = sd.id
     LEFT JOIN hospital_doctors hd ON a.hosp_doctor_id = hd.id
     LEFT JOIN hospital_providers hp ON a.hospital_provider_id = hp.id
     WHERE a.patient_id=:pid ORDER BY a.appointment_at DESC LIMIT 80',
    [':pid' => $pid]
);

// Also pull guest_bookings made with same email (guest who registered later)
$patEmail = $pat['email'] ?? '';
$guestAppts = [];
if ($patEmail) {
    try {
        $guestRows = $db->fetchAll(
            "SELECT
                g.id + 100000 AS id,
                g.provider_id,
                NULL AS hospital_provider_id,
                g.service_type,
                COALESCE(g.provider_name, CONCAT('Guest Booking — ', UPPER(g.service_type))) AS title,
                g.reason AS notes,
                g.appointment_at,
                g.location_type,
                g.status AS status,
                g.created_at,
                g.updated_at,
                COALESCE(g.provider_name, CONCAT(UPPER(g.service_type), ' Provider')) AS prov_name,
                g.service_type AS prov_type,
                NULL AS specialty,
                4.5 AS prov_rating,
                NULL AS hosp_name,
                NULL AS hosp_county,
                NULL AS doctor_id,
                NULL AS cancel_reason,
                NULL AS reschedule_reason,
                0 AS missed_count,
                NULL AS meeting_url,
                NULL AS meeting_token
             FROM guest_bookings g
             WHERE g.guest_email = :email
             AND g.status != 'cancelled'
             ORDER BY g.created_at DESC LIMIT 40",
            [':email' => $patEmail]
        );
        // Normalise guest status to appointments-compatible values
        foreach ($guestRows as &$gr) {
            $gr['patient_id'] = $pid;
            $gr['duration_min'] = 30;
            $gr['reminder_sent'] = 0;
            // Map guest status to appointment status
            $gr['status'] = match($gr['status'] ?? 'pending') {
                'confirmed'  => 'confirmed',
                'cancelled'  => 'cancelled',
                'contacted'  => 'scheduled',
                default      => 'scheduled',
            };
        }
        unset($gr);
        // Merge, avoiding duplicate appointment_at combos
        $appts = array_merge($appts, $guestRows);
        // Re-sort by appointment_at DESC
        usort($appts, function($a, $b) {
            return strtotime($b['appointment_at'] ?? $b['created_at'] ?? '0')
                 - strtotime($a['appointment_at'] ?? $a['created_at'] ?? '0');
        });
    } catch (Exception $ge) {
        // guest_bookings or column mismatch - skip gracefully
        error_log('[patient dashboard guest appts] ' . $ge->getMessage());
    }
}

// Compute time-based smart status for each appointment
foreach ($appts as &$appt) {
    $at    = strtotime($appt['appointment_at']);
    $now   = time();
    $diff  = ($now - $at) / 3600; // hours since appointment time
    $base  = $appt['status'];
    // Only mutate display status for scheduled/confirmed that have passed their time
    if (in_array($base, ['scheduled','confirmed']) && $now > $at) {
        if ($diff <= 3)       $appt['display_status'] = 'pending_checkin';
        elseif ($diff <= 6)   $appt['display_status'] = 'awaiting_confirmation';
        else                  $appt['display_status'] = 'unconfirmed';
    } else {
        $appt['display_status'] = $base;
    }
}
unset($appt);

// Count missed (unconfirmed > 6h past)
$missedCount = count(array_filter($appts, fn($a) => $a['display_status'] === 'unconfirmed'));
$notifs = $db->fetchAll('SELECT * FROM notifications WHERE patient_id=:pid ORDER BY created_at DESC LIMIT 40', [':pid' => $pid]);
// Find Care: merge ALL provider sources for comprehensive listing
$nearby = [];
try {
    // 1. Seed providers table (doctors, clinics, hospitals, ambulance, pharmacy, lab)
    $np1 = $db->fetchAll(
        "SELECT id, name, type, specialty, address, city, county,
                latitude AS lat, longitude AS lng, rating, phone,
                '' AS avatar_path, '' AS hosp_name, 'providers' AS source,
                NULL AS fee, is_available
         FROM doctors WHERE is_active=1 AND status='active' AND (is_verified=1 OR email_verified=1)
         ORDER BY rating DESC LIMIT 60"
    ) ?? [];

    // 2. Registered hospitals (hospital_providers)
    $np2 = $db->fetchAll(
        "SELECT id+10000 AS id, facility_name AS name, facility_type AS type,
                '' AS specialty, address, county AS city, county,
                latitude AS lat, longitude AS lng,
                4.5 AS rating, phone,
                logo_path AS avatar_path, '' AS hosp_name, 'hospital_providers' AS source,
                NULL AS fee, emergency_24h AS is_available
         FROM hospital_providers
         WHERE status='approved' AND is_active=1 AND facility_name IS NOT NULL
         ORDER BY facility_name ASC LIMIT 40"
    ) ?? [];

    // 3. Hospital doctors — auto-verified by approved hospital
    $np3 = [];
    try {
        $np3 = $db->fetchAll(
            "SELECT hd.id+20000 AS id, CONCAT('Dr. ',hd.name) AS name, 'doctor' AS type,
                    hd.specialty, hp.address, hp.county AS city, hp.county,
                    NULL AS lat, NULL AS lng,
                    4.2 AS rating, hp.phone,
                    hd.avatar_path, hp.facility_name AS hosp_name, 'hospital_doctors' AS source,
                    hd.consult_fee AS fee, hd.accepts_tele AS is_available
             FROM hospital_doctors hd
             JOIN hospital_providers hp ON hp.id=hd.hospital_id
             WHERE hd.is_active=1 AND hp.status='approved' AND hp.is_active=1
             ORDER BY hd.name ASC LIMIT 50"
        ) ?? [];
    } catch(Exception $e) { error_log('[nearby np3] '.$e->getMessage()); }

    // 4. Standalone doctors (doctor portal) — verified ones
    $np4 = [];
    try {
        $np4 = $db->fetchAll(
            "SELECT d.id+30000 AS id, CONCAT('Dr. ',d.first_name,' ',d.last_name) AS name,
                    'doctor' AS type, d.specialty, d.address, d.city, d.county,
                    d.latitude AS lat, d.longitude AS lng,
                    COALESCE(d.rating, 4.0) AS rating, d.phone,
                    d.avatar_path, '' AS hosp_name, 'standalone_doctors' AS source,
                    d.consult_fee AS fee, d.accepts_tele AS is_available
             FROM doctors d
             WHERE d.is_active=1 AND d.status='active'
               AND (d.email_verified=1 OR d.is_verified=1)
             ORDER BY d.first_name ASC LIMIT 50"
        ) ?? [];
    } catch(Exception $e) { error_log('[nearby np4] '.$e->getMessage()); }

    $nearby = array_merge($np1, $np2, $np3, $np4);
    // Sort: hospitals first, then by rating
    usort($nearby, function($a, $b) {
        $order = ['hospital_providers'=>0, 'hospital_doctors'=>1, 'standalone_doctors'=>2, 'providers'=>3];
        $ao = $order[$a['source']??''] ?? 4;
        $bo = $order[$b['source']??''] ?? 4;
        if ($ao !== $bo) return $ao - $bo;
        return floatval($b['rating']??0) <=> floatval($a['rating']??0);
    });
} catch (Exception $e) {
    error_log('[patient dashboard nearby] ' . $e->getMessage());
    try { $nearby = $db->fetchAll("SELECT id+30000 AS id, CONCAT('Dr. ',first_name,' ',last_name) AS name, 'doctor' AS type, specialty, city, county, latitude AS lat, longitude AS lng, rating, phone, avatar_path, '' AS hosp_name, 'standalone_doctor' AS source, consult_fee AS fee, is_available FROM doctors WHERE is_active=1 AND status='active' AND (is_verified=1 OR email_verified=1) ORDER BY rating DESC LIMIT 40") ?? []; } catch(Exception $e2) {}
}
$insDocs = [];
try { $insDocs = $db->fetchAll('SELECT * FROM insurance_documents WHERE patient_id=:pid AND status="active" ORDER BY created_at DESC', [':pid'=>$pid]); } catch(Exception $e) {}
$consents = []; $defaultConsents = ['data_sharing'=>true,'insurance_sharing'=>true,'marketing'=>false,'telehealth'=>false,'research'=>false];
try { foreach($db->fetchAll('SELECT consent_type,granted FROM patient_consents WHERE patient_id=:pid',[':pid'=>$pid]) as $r) $consents[$r['consent_type']]=(bool)$r['granted']; } catch(Exception $e) {}
foreach($defaultConsents as $k=>$v) if(!isset($consents[$k])) $consents[$k]=$v;

/*  Computed vars  */
$upcoming  = array_values(array_filter($appts, fn($a) => $a['status']==='scheduled' && strtotime($a['appointment_at']) >= time()));
$past      = array_values(array_filter($appts, fn($a) => $a['status']==='completed' || strtotime($a['appointment_at']) < time()));
$unread    = count(array_filter($notifs, fn($n) => !$n['is_read']));
$fname     = htmlspecialchars($pat['first_name'] ?? 'Patient');
$lname     = htmlspecialchars($pat['last_name']  ?? '');
$fullName  = trim("$fname $lname");
$initials  = strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', trim($fullName)), 0, 2))));
$patId     = '#' . str_pad($pid, 5, '0', STR_PAD_LEFT);
$nextAppt  = $upcoming[0] ?? null;
$hour      = (int)date('G');
$greetEn   = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$greetSw   = $hour < 12 ? 'Habari za asubuhi' : ($hour < 17 ? 'Habari za mchana' : 'Habari za jioni');
$myDoctors = []; $seen = [];
foreach($appts as $a) { if(!empty($a['prov_name'])&&!in_array($a['prov_name'],$seen)){$myDoctors[]=$a;$seen[]=$a['prov_name'];if(count($myDoctors)>=4)break;} }

/*  Real stats  */
$totalAppts  = count($appts);
$completedCt = count(array_filter($appts, fn($a)=>$a['status']==='completed'));
$upcomingCt  = count($upcoming);

/*  Emergency history  */
$activeEmergencies = [];
try { $activeEmergencies = $db->fetchAll('SELECT * FROM emergency_requests WHERE patient_id=:pid ORDER BY requested_at DESC LIMIT 5', [':pid'=>$pid]); } catch(Exception $e) {}

$_SESSION['patient_name'] = $fullName;
?>
<!DOCTYPE html>
<html lang="en" id="htmlRoot">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>Patient Dashboard — Planeazzy</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="stylesheet" href="/assets/css/upgrade.css">
  <link rel="icon" href="/assets/images/favicon.png" type="image/svg+xml">
<style>
/* 
   PATIENT DASHBOARD — Design System v3
 */
:root {
  --primary:#1978e5; --primary-10:rgba(25,120,229,.1); --primary-20:rgba(25,120,229,.2);
  --teal:#0d9488; --green:#16a34a; --red:#dc2626; --yellow:#d97706;
  --slate-50:#f8fafc; --slate-100:#f1f5f9; --slate-200:#e2e8f0;
  --slate-400:#94a3b8; --slate-500:#64748b; --slate-700:#334155; --slate-900:#0f172a;
  --white:#fff; --shadow:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06);
  --shadow-md:0 4px 6px -1px rgba(0,0,0,.07),0 2px 4px rgba(0,0,0,.05);
  --sb-w:228px; --sb-col:60px; --hdr-h:52px; --r:8px; --r-lg:12px; --r-xl:16px;
}

/*  Global icon sizing fix  */
.fa-solid,.fa-regular,.fa-brands{line-height:1;vertical-align:middle}
.btn i,.pat-btn-white i,.pat-hdr-icon i{
  font-size:.7em;display:inline-flex;align-items:center;
  justify-content:center;vertical-align:middle;line-height:1;flex-shrink:0;
}
.btn-sm i{font-size:.65em}
.pat-sb-item i{font-size:15px;flex-shrink:0;width:18px;text-align:center;display:inline-flex;align-items:center;justify-content:center}
.qa-btn i{font-size:15px;display:inline-flex;align-items:center;justify-content:center}
.pat-stat-ic i{font-size:16px}
.notif-ic i{font-size:14px}
.appt-ic i{font-size:14px}
.doc-av{display:flex;align-items:center;justify-content:center}
/* Book button specific */
.btn-primary i, .btn-ghost i{font-size:.7em;line-height:1}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;font-size:13px;color:var(--slate-900);background:#f5f7fa;min-height:100vh;display:flex;flex-direction:column;line-height:1.5}
a{color:inherit;text-decoration:none}
button{font-family:inherit;cursor:pointer}
input,select,textarea{font-family:inherit}

/*  Patient header  */
.pat-hdr{position:sticky;top:0;z-index:200;height:var(--hdr-h);background:rgba(255,255,255,.97);border-bottom:1px solid var(--slate-200);backdrop-filter:blur(16px);display:flex;align-items:center;padding:0 16px;gap:10px;width:100%;box-sizing:border-box}
.pat-hdr-brand{display:flex;align-items:center;gap:0;text-decoration:none;flex-shrink:0}
.pat-hdr-brand img{height:30px;width:auto;display:block}
.pat-hdr-center{flex:1;display:flex;align-items:center;justify-content:center;max-width:380px;margin:0 auto;position:relative}
.pat-hdr-search{width:100%;padding:7px 12px 7px 34px;background:var(--slate-100);border:1.5px solid transparent;border-radius:9999px;font-size:12.5px;color:var(--slate-900);outline:none;transition:all .2s}
.pat-hdr-search:focus{background:#fff;border-color:var(--primary-20);box-shadow:0 0 0 3px var(--primary-10)}
.pat-hdr-search-ico{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--slate-400);font-size:12px;pointer-events:none}
.pat-hdr-actions{display:flex;align-items:center;gap:6px;flex-shrink:0}
.pat-hdr-icon{width:32px;height:32px;border-radius:50%;background:none;border:none;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--slate-500);position:relative;cursor:pointer;transition:background .15s}
.pat-hdr-icon:hover{background:var(--slate-100)}
.pat-hdr-notif-dot{position:absolute;top:5px;right:5px;width:7px;height:7px;border-radius:50%;background:var(--red);border:2px solid #fff}
.pat-hdr-avatar{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--teal));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;flex-shrink:0;border:2px solid var(--primary-20);cursor:pointer}
.pat-hdr-lang{display:flex;align-items:center;gap:3px;padding:4px 9px;border-radius:20px;font-size:11px;font-weight:700;color:var(--primary);background:none;border:1.5px solid var(--primary-20);cursor:pointer;transition:all .15s}
.pat-hdr-lang:hover{background:var(--primary-10)}
.pat-hamb{display:none;width:32px;height:32px;border-radius:var(--r);border:none;background:none;font-size:16px;color:var(--slate-700);align-items:center;justify-content:center}
.pat-hamb:hover{background:var(--slate-100)}

/*  App layout  */
.pat-layout{display:flex;flex:1;min-height:calc(100vh - var(--hdr-h));width:100%}

/*  Sidebar  */
.pat-sb{width:var(--sb-w);flex-shrink:0;background:#fff;border-right:1px solid var(--slate-200);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;height:100vh;overflow-y:auto;transition:transform .25s cubic-bezier(.4,0,.2,1),width .2s;z-index:150}
.pat-sb::-webkit-scrollbar{width:3px}
.pat-sb::-webkit-scrollbar-thumb{background:var(--slate-200);border-radius:9999px}
.pat-sb.collapsed{width:var(--sb-col)}
.pat-sb-profile{padding:14px 14px 10px;border-bottom:1px solid var(--slate-100)}
.pat-sb-av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--teal));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0}
.pat-sb-name{font-size:12.5px;font-weight:700;color:var(--slate-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pat-sb-id{font-size:10.5px;color:var(--slate-400)}
.pat-sb.collapsed .pat-sb-profile-info{display:none}
.pat-sb-nav{flex:1;padding:8px}
.pat-sb-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:var(--r);font-size:12.5px;font-weight:500;color:var(--slate-600);text-decoration:none;transition:all .15s;position:relative;white-space:nowrap}
.pat-sb-item:hover{background:var(--slate-50);color:var(--slate-900)}
.pat-sb-item.active{background:var(--primary-10);color:var(--primary);font-weight:700}
.pat-sb-item i{font-size:15px;flex-shrink:0;width:18px;text-align:center}
.pat-sb-label{flex:1;overflow:hidden;text-overflow:ellipsis}
.pat-sb-badge{min-width:17px;height:17px;border-radius:9999px;background:var(--red);color:#fff;font-size:9.5px;font-weight:800;display:flex;align-items:center;justify-content:center;padding:0 4px;flex-shrink:0}
.pat-sb.collapsed .pat-sb-label,.pat-sb.collapsed .pat-sb-badge,.pat-sb.collapsed .pat-sb-section-label{display:none}
.pat-sb.collapsed .pat-sb-item{justify-content:center;padding:10px 0}
.pat-sb-section-label{font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--slate-400);padding:10px 10px 4px}
.pat-sb-footer{padding:8px;border-top:1px solid var(--slate-100)}
.pat-sb-toggle{width:100%;display:flex;align-items:center;justify-content:flex-end;padding:4px;background:none;border:none;color:var(--slate-400);font-size:13px;cursor:pointer}
.pat-sb.collapsed .pat-sb-toggle{justify-content:center}

/*  Main area  */
.pat-main{flex:1;display:flex;flex-direction:column;min-width:0;overflow:visible;margin-left:var(--sb-w)}
.pat-content{padding:16px 20px;flex:1;width:100%;box-sizing:border-box;min-width:0;overflow-y:auto}

/*  Page heading  */
.pat-page-hdr{margin-bottom:16px}
.pat-page-title{font-size:1.125rem;font-weight:800;letter-spacing:-.03em;color:var(--slate-900);margin-bottom:3px}
.pat-page-sub{font-size:.75rem;color:var(--slate-500)}

/*  Stat cards  */
.pat-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px;width:100%}
.pat-stat{background:#fff;border-radius:var(--r-lg);padding:14px;border:1px solid var(--slate-200);box-shadow:var(--shadow);display:flex;align-items:center;gap:10px}
.pat-stat-ic{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;overflow:hidden}
.pat-stat-val{font-size:1.25rem;font-weight:800;letter-spacing:-.04em;color:var(--slate-900);line-height:1}
.pat-stat-lbl{font-size:.6875rem;color:var(--slate-400);margin-top:2px;font-weight:500}

/*  Dashboard grid  */
.pat-grid{display:grid;grid-template-columns:1fr 310px;gap:16px;width:100%;align-items:start}
.pat-panel{background:#fff;border-radius:var(--r-lg);border:1px solid var(--slate-200);box-shadow:var(--shadow);overflow:hidden;width:100%}
.pat-panel-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--slate-100)}
.pat-panel-title{font-size:.875rem;font-weight:700;color:var(--slate-900)}
.pat-panel-link{font-size:.75rem;font-weight:600;color:var(--primary)}
.pat-panel-body{padding:12px 16px}

/*  Welcome banner  */
.pat-welcome{background:linear-gradient(135deg,#1462c4,#1978e5 50%,#0d9488);border-radius:var(--r-xl);padding:20px 22px;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px;position:relative;overflow:hidden}
.pat-welcome::before{content:'';position:absolute;top:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.07);pointer-events:none}
.pat-welcome-title{font-size:1rem;font-weight:800;letter-spacing:-.03em;margin-bottom:4px}
.pat-welcome-sub{font-size:.75rem;color:rgba(219,234,254,.85);line-height:1.6}
.pat-welcome-btns{display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap}
.pat-btn-white{background:#fff;color:var(--primary);padding:8px 16px;border-radius:9999px;font-size:.75rem;font-weight:700;border:none;cursor:pointer;white-space:nowrap;transition:all .15s}
.pat-btn-white:hover{background:rgba(255,255,255,.9)}
.pat-btn-ghost{background:rgba(255,255,255,.15);color:#fff;padding:8px 16px;border-radius:9999px;font-size:.75rem;font-weight:700;border:1.5px solid rgba(255,255,255,.3);cursor:pointer;white-space:nowrap;transition:all .15s}
.pat-btn-ghost:hover{background:rgba(255,255,255,.22)}

/*  Appointment cards  */
.appt-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--slate-100)}
.appt-row:last-child{border-bottom:none}
.appt-ic{width:38px;height:38px;border-radius:var(--r);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.appt-ic.tele{background:var(--primary-10);color:var(--primary)}
.appt-ic.inperson{background:rgba(13,148,136,.1);color:var(--teal)}
.appt-name{font-size:.8125rem;font-weight:700;color:var(--slate-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.appt-meta{font-size:.6875rem;color:var(--slate-400);margin-top:1px}
.appt-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:9999px;font-size:.5625rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.appt-pill.scheduled{background:var(--primary-10);color:var(--primary)}
.appt-pill.confirmed{background:rgba(22,163,74,.1);color:#15803d}
.appt-pill.completed{background:rgba(22,163,74,.1);color:var(--green)}
.appt-pill.cancelled{background:rgba(239,68,68,.1);color:#dc2626}
.appt-pill.no_show{background:rgba(239,68,68,.12);color:#b91c1c}
.appt-pill.pending_checkin{background:rgba(245,158,11,.12);color:#b45309}
.appt-pill.awaiting_confirmation{background:rgba(234,179,8,.1);color:#854d0e}
.appt-pill.unconfirmed{background:rgba(239,68,68,.1);color:#b91c1c}
.appt-pill.tele{background:var(--primary-10);color:var(--primary)}
.appt-pill.inp{background:rgba(13,148,136,.1);color:var(--teal)}
.warn-banner{background:#fff7ed;border:1.5px solid #fdba74;border-radius:12px;padding:14px 16px;display:flex;align-items:flex-start;gap:12px;margin-bottom:18px}
.warn-banner i{color:#ea580c;font-size:18px;flex-shrink:0;margin-top:1px}
.warn-banner .wb-title{font-size:.8125rem;font-weight:700;color:#9a3412;margin-bottom:3px}
.warn-banner .wb-sub{font-size:.75rem;color:#c2410c;line-height:1.5}

/*  Quick actions grid  */
.qa-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.qa-btn{display:flex;flex-direction:column;align-items:center;gap:5px;padding:12px 6px;background:var(--slate-50);border:1px solid var(--slate-100);border-radius:var(--r);text-decoration:none;cursor:pointer;transition:all .15s;text-align:center}
.qa-btn:hover{background:#fff;border-color:var(--primary-20);box-shadow:var(--shadow-md);transform:translateY(-1px)}
.qa-ic{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.qa-lbl{font-size:.625rem;font-weight:700;color:var(--slate-700);line-height:1.2}

/*  Doctor row  */
.doc-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--slate-100)}
.doc-row:last-child{border-bottom:none}
.doc-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--teal));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;flex-shrink:0}
.doc-name{font-size:.8125rem;font-weight:700;color:var(--slate-900)}
.doc-spec{font-size:.6875rem;color:var(--slate-400)}

/*  Provider/Hospital cards (Find Care tab)  */
.prov-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
.prov-card{background:#fff;border-radius:var(--r-lg);border:1px solid var(--slate-200);box-shadow:var(--shadow);overflow:hidden;transition:all .2s;cursor:pointer}
.prov-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);border-color:var(--primary-20)}
.prov-card-top{padding:14px 14px 10px;display:flex;align-items:flex-start;gap:10px}
.prov-card-ic{width:42px;height:42px;border-radius:var(--r);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.prov-card-name{font-size:.8125rem;font-weight:700;color:var(--slate-900);margin-bottom:2px;line-height:1.3}
.prov-card-type{font-size:.6875rem;color:var(--slate-400);text-transform:capitalize}
.prov-card-bottom{padding:8px 14px 12px;border-top:1px solid var(--slate-100);display:flex;align-items:center;justify-content:space-between}
.prov-card-dist{font-size:.625rem;color:var(--slate-400);display:flex;align-items:center;gap:3px}
.prov-card-rating{font-size:.625rem;font-weight:700;color:#d97706;display:flex;align-items:center;gap:3px}
.prov-card-avail{font-size:.5625rem;font-weight:700;color:var(--green);display:flex;align-items:center;gap:3px;background:rgba(22,163,74,.08);padding:2px 7px;border-radius:9999px}
.prov-card-book{width:100%;margin:0 14px 12px;width:calc(100% - 28px);padding:8px;background:var(--primary);color:#fff;border:none;border-radius:var(--r);font-size:.75rem;font-weight:700;cursor:pointer;transition:background .15s;display:flex;align-items:center;justify-content:center;gap:5px}
.prov-card-book:hover{background:#1462c4}
.prov-filter-bar{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.prov-filter-btn{padding:5px 12px;border-radius:9999px;font-size:.6875rem;font-weight:700;border:1.5px solid var(--slate-200);background:#fff;color:var(--slate-500);cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:5px}
.prov-filter-btn.active,.prov-filter-btn:hover{background:var(--primary);color:#fff;border-color:var(--primary)}

/*  Notification items  */
.notif-item{display:flex;gap:12px;padding:12px;border-radius:var(--r-lg);border:1.5px solid var(--slate-200);background:#fff;cursor:pointer;transition:all .15s;margin-bottom:8px}
.notif-item.unread{border-color:var(--primary-20);background:rgba(25,120,229,.02)}
.notif-item:hover{border-color:var(--primary-20);box-shadow:var(--shadow)}
.notif-ic{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.notif-title{font-size:.8125rem;font-weight:700;color:var(--slate-900);margin-bottom:2px}
.notif-msg{font-size:.75rem;color:var(--slate-500);line-height:1.5}
.notif-time{font-size:.625rem;color:var(--slate-400);margin-top:3px}
.notif-dot{width:7px;height:7px;border-radius:50%;background:var(--primary);flex-shrink:0;margin-top:4px}

/*  Forms / settings  */
.form-group{margin-bottom:14px}
.form-label{display:block;font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--slate-500);margin-bottom:5px}
.form-input,.form-select,.form-textarea{width:100%;padding:9px 12px;background:var(--slate-50);border:1.5px solid var(--slate-200);border-radius:var(--r);font-size:.875rem;color:var(--slate-900);outline:none;transition:all .2s}
.form-input:focus,.form-select:focus,.form-textarea:focus{background:#fff;border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-10)}
.form-textarea{resize:vertical;min-height:80px}
.settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}

/*  Toggle switch  */
.toggle-sw{position:relative;display:inline-block;width:38px;height:21px;flex-shrink:0}
.toggle-sw input{opacity:0;width:0;height:0;position:absolute}
.toggle-track{position:absolute;inset:0;border-radius:9999px;background:var(--slate-200);transition:.2s;cursor:pointer}
.toggle-sw input:checked + .toggle-track{background:var(--primary)}
.toggle-thumb{position:absolute;top:3px;left:3px;width:15px;height:15px;border-radius:50%;background:#fff;transition:.2s;pointer-events:none;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.toggle-sw input:checked ~ .toggle-thumb{transform:translateX(17px)}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--slate-100)}
.toggle-row:last-child{border-bottom:none}
.toggle-label{font-size:.8125rem;color:var(--slate-700);font-weight:500;flex:1;padding-right:12px}

/*  Modals  */
.modal-ov{display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:500;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(4px)}
.modal-ov.open{display:flex}
.modal-box{background:#fff;border-radius:var(--r-xl);box-shadow:0 25px 50px -12px rgba(0,0,0,.25);max-height:90vh;overflow-y:auto;width:100%;max-width:480px}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:16px 18px 12px;border-bottom:1px solid var(--slate-100);position:sticky;top:0;background:#fff;z-index:2;border-radius:var(--r-xl) var(--r-xl) 0 0}
.modal-title{font-size:.9375rem;font-weight:700;color:var(--slate-900);display:flex;align-items:center;gap:7px}
.modal-close{width:28px;height:28px;border-radius:50%;background:var(--slate-100);border:none;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--slate-500);cursor:pointer}
.modal-body{padding:16px 18px}

/*  Buttons  */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 18px;border-radius:var(--r);font-size:.8125rem;font-weight:700;border:none;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap}
.btn:active{transform:scale(.97)}
.btn-primary{background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(25,120,229,.25)}
.btn-primary:hover{background:#1462c4}
.btn-teal{background:var(--teal);color:#fff}
.btn-teal:hover{background:#0a7d74}
.btn-red{background:var(--red);color:#fff}
.btn-ghost{background:transparent;color:var(--slate-700);border:1.5px solid var(--slate-200)}
.btn-ghost:hover{background:var(--slate-50);border-color:var(--slate-300)}
.btn-sm{padding:6px 13px;font-size:.75rem}
.btn-full{width:100%;justify-content:center}
.btn-join{background:var(--primary);color:#fff;padding:8px 16px;border-radius:9999px;font-size:.75rem;font-weight:700;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}

/*  Toast  */
.toast{position:fixed;bottom:20px;right:20px;z-index:9999;padding:11px 16px;border-radius:var(--r-lg);font-size:.8125rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.15);transform:translateY(60px);opacity:0;transition:all .3s;max-width:300px}
.toast.show{transform:translateY(0);opacity:1}
.toast.ok{background:#065f46;color:#fff;border-left:4px solid #34d399}
.toast.err{background:#7f1d1d;color:#fff;border-left:4px solid #f87171}
.toast.info{background:#1e3a5f;color:#fff;border-left:4px solid #60a5fa}

/*  Mobile overlay + FAB  */
.mob-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.52);z-index:149;backdrop-filter:blur(2px)}
.mob-ov.show{display:block}
.mob-fab{display:none;position:fixed;bottom:24px;right:20px;z-index:200;width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#1462c4,#1978e5);color:#fff;border:none;cursor:pointer;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(25,120,229,.45);transition:transform .15s}

/*  Emergency SOS  */
.sos-card{background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:var(--r-xl);padding:28px;text-align:center;margin-bottom:16px;position:relative;overflow:hidden}
.sos-btn{width:100px;height:100px;border-radius:50%;background:#fff;color:#dc2626;border:5px solid rgba(255,255,255,.3);font-family:'Inter',sans-serif;font-size:19px;font-weight:900;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-direction:column;margin:0 auto 16px;gap:3px;box-shadow:0 0 0 10px rgba(255,255,255,.15),0 16px 32px rgba(0,0,0,.25)}

/*  History table  */
.hist-table{width:100%;border-collapse:collapse;font-size:.75rem}
.hist-table th{font-size:.5625rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--slate-400);padding:8px 10px;background:var(--slate-50);border-bottom:1px solid var(--slate-100);text-align:left;white-space:nowrap}
.hist-table td{padding:10px;border-bottom:1px solid var(--slate-100);vertical-align:middle;color:var(--slate-700)}
.hist-table tr:last-child td{border-bottom:none}
.hist-table tr:hover td{background:var(--slate-50)}
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}

/*  Alert / empty states  */
.alert{display:flex;align-items:flex-start;gap:8px;padding:10px 14px;border-radius:var(--r);font-size:.8125rem;font-weight:500;margin-bottom:14px}
.alert.ok{background:rgba(22,163,74,.08);border:1px solid rgba(22,163,74,.2);color:#14532d}
.alert.err{background:rgba(186,26,26,.08);border:1px solid rgba(186,26,26,.2);color:#7f1d1d}
.alert.hidden{display:none}
.empty-state{text-align:center;padding:36px 20px}
.empty-state i{font-size:36px;color:var(--slate-200);display:block;margin-bottom:12px}
.empty-state h3{font-size:.9375rem;font-weight:700;color:var(--slate-500);margin-bottom:6px}
.empty-state p{font-size:.8125rem;color:var(--slate-400);line-height:1.6;max-width:320px;margin:0 auto 16px}

/*  Tab bar  */
.tab-bar{display:flex;gap:4px;margin-bottom:14px;flex-wrap:wrap}
.tab-item{padding:6px 14px;border-radius:9999px;font-size:.75rem;font-weight:600;border:1.5px solid var(--slate-200);background:#fff;color:var(--slate-500);cursor:pointer;transition:all .15s}
.tab-item.active{background:var(--primary);color:#fff;border-color:var(--primary)}

/*  RESPONSIVE  */
@media(max-width:1200px){
  .pat-grid{grid-template-columns:1fr}
  .pat-stats{grid-template-columns:repeat(2,1fr)}
  .pat-sb{position:fixed;top:0;left:0;height:100vh;transform:translateX(-100%)}
  .pat-main{margin-left:0!important}
  .pat-sb.mob-open{transform:translateX(0);box-shadow:4px 0 24px rgba(0,0,0,.15)}
  .pat-main{margin-left:0!important}
  .mob-fab{display:flex}
  .pat-hamb{display:flex}
  .pat-hdr-center{display:none}
  .pat-hdr-center{max-width:260px}
  .settings-grid{grid-template-columns:1fr}
}
@media(max-width:768px){
  :root{--hdr-h:48px}
  .pat-hdr{position:sticky;top:0;z-index:200;height:var(--hdr-h);background:rgba(255,255,255,.97);border-bottom:1px solid var(--slate-200);backdrop-filter:blur(16px);display:flex;align-items:center;padding:0 16px;gap:10px;width:100%;box-sizing:border-box}
  .pat-hdr-brand img{height:26px}
  .pat-hdr-center{max-width:180px}
  .pat-hdr-search{font-size:12px;padding:6px 10px 6px 30px}
  .pat-content{padding:16px 20px;flex:1;width:100%;box-sizing:border-box;min-width:0;overflow-y:auto}
  .pat-stats{grid-template-columns:1fr 1fr;gap:8px}
  .pat-stat{padding:11px 10px;gap:8px}
  .pat-stat-val{font-size:1.125rem}
  .pat-welcome{padding:16px 14px;flex-direction:column;align-items:flex-start;gap:12px}
  .pat-welcome-title{font-size:.9375rem}
  .pat-welcome-sub{font-size:.6875rem}
  .pat-page-title{font-size:1rem}
  .prov-grid{grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:8px}
  .prov-card-name{font-size:.75rem}
  .qa-grid{grid-template-columns:repeat(3,1fr)}
  .qa-ic{width:32px;height:32px;font-size:13px}
  .qa-lbl{font-size:.5625rem}
  .sos-btn{width:86px;height:86px}
  .modal-box{max-width:100%;margin:0;border-radius:var(--r-xl) var(--r-xl) 0 0;position:fixed;bottom:0;left:0;right:0;max-height:90vh}
  .modal-ov{align-items:flex-end;padding:0}
}
@media(max-width:480px){
  .pat-stats{grid-template-columns:1fr 1fr}
  .pat-stat-lbl{font-size:.5625rem}
  .pat-hdr-center{display:none}
  .prov-grid{grid-template-columns:1fr 1fr;gap:8px}
  .tab-bar{gap:3px}
  .tab-item{padding:5px 10px;font-size:.6875rem}
  .settings-grid{grid-template-columns:1fr}
}
@media(max-width:360px){
  .pat-stats{grid-template-columns:1fr 1fr}
  .qa-grid{grid-template-columns:repeat(3,1fr)}
  .pat-welcome-btns{flex-direction:column}
}

/*  DEFINITIVE MOBILE SIDEBAR FIX  */
@media(max-width:1200px){
  .pat-sb{
    position:fixed!important;
    top:0!important;
    left:0!important;
    height:100vh!important;
    z-index:500!important;
    transform:translateX(-100%)!important;
    transition:transform .3s cubic-bezier(.4,0,.2,1)!important;
    width:270px!important;
    overflow-y:auto!important;
    overflow-x:hidden!important;
    box-shadow:none;
    -webkit-overflow-scrolling:touch;
  }
  .pat-sb.mob-open{
    transform:translateX(0)!important;
    box-shadow:6px 0 40px rgba(0,0,0,.28)!important;
  }
  .mob-ov{z-index:400!important;display:none}
  .mob-ov.show{display:block!important}
  .mob-fab{display:flex!important;z-index:300!important}
  .pat-hamb{display:flex!important}
  .pat-main{margin-left:0!important;width:100%!important}
  .pat-sb-toggle{display:none!important}
}
@media(max-width:600px){
  .pat-sb{width:260px!important}
  .mob-fab{width:46px!important;height:46px!important;bottom:18px!important;right:14px!important;font-size:16px!important}
  .pat-content{padding:10px 12px!important}
  .pat-hdr{padding:0 12px!important}
}
@media(max-width:390px){
  .pat-sb{width:245px!important}
  .pat-content{padding:8px 10px!important}
}
@media(max-width:360px){
  .pat-sb{width:240px!important}
}

</style>
</head>
<body>

<!--  PATIENT HEADER  -->
<header class="pat-hdr">
  <button class="pat-hamb" id="patHamb" aria-label="Menu">
    <i class="fa-solid fa-bars"></i>
  </button>
  <a href="/" class="pat-hdr-brand"><img src="/assets/images/favicon1.png" alt="Planeazzy" style="height:32px;width:auto;display:block">
  </a>
  <div class="pat-hdr-center">
    <i class="fa-solid fa-magnifying-glass pat-hdr-search-ico"></i>
    <input type="text" class="pat-hdr-search"
      data-en-placeholder="Search doctors, hospitals…"
      data-sw-placeholder="Tafuta madaktari, hospitali…"
      placeholder="Search doctors, hospitals…"
      onkeydown="if(event.key==='Enter')location.href='/patients/search.php?q='+encodeURIComponent(this.value)">
  </div>
  <div class="pat-hdr-actions">
    <button class="pat-hdr-lang" id="langToggle">
      <i class="fa-solid fa-language"></i>
      <span id="langLabel">SW</span>
    </button>
    <a href="?tab=notifications" class="pat-hdr-icon" title="Notifications">
      <i class="fa-solid fa-bell"></i>
      <?php if($unread>0):?><span class="pat-hdr-notif-dot"></span><?php endif;?>
    </a>
    <a href="?tab=settings" class="pat-hdr-avatar" title="Profile" style="overflow:hidden;padding:0">
      <?php if($hasAvatar):?><img src="<?=$avatarSrc?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"><?php else:?><?= htmlspecialchars($initials) ?><?php endif;?>
    </a>
  </div>
</header>

<!--  LAYOUT  -->
<div class="pat-layout">

  <!--  SIDEBAR  -->
  <aside class="pat-sb" id="patSb">
    <!-- Profile -->
    <div class="pat-sb-profile">
      <div style="display:flex;align-items:center;gap:10px">
        <div class="pat-sb-av" style="overflow:hidden;padding:0">
          <?php if($hasAvatar):?><img src="<?=$avatarSrc?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"><?php else:?><?= htmlspecialchars($initials) ?><?php endif;?>
        </div>
        <div class="pat-sb-profile-info" style="flex:1;min-width:0">
          <div class="pat-sb-name"><?= htmlspecialchars($fullName) ?></div>
          <div class="pat-sb-id"><?= htmlspecialchars($patId) ?></div>
        </div>
      </div>
    </div>

    <!-- Nav items -->
    <nav class="pat-sb-nav">
      <div class="pat-sb-section-label">MAIN</div>
      <?php foreach([
        ['overview','fa-gauge','Overview','Muhtasari'],
        ['appointments','fa-calendar-check','Appointments','Miadi'],
        ['nearby','fa-location-dot','Find Care','Tafuta Huduma'],
      ] as [$k,$ic,$en,$sw]): $a=$tab===$k; ?>
      <a href="?tab=<?=$k?>" class="pat-sb-item <?=$a?'active':''?>"
         data-en="<?=$en?>" data-sw="<?=$sw?>">
        <i class="fa-solid <?=$ic?>"></i>
        <span class="pat-sb-label"><?=$en?></span>
        <?php if($k==='appointments'&&$upcomingCt>0):?><span class="pat-sb-badge"><?=$upcomingCt?></span><?php endif;?>
      </a>
      <?php endforeach;?>

      <div class="pat-sb-section-label" style="margin-top:6px">MY HEALTH</div>
      <?php foreach([
        ['insurance','fa-shield','Insurance','Bima'],
        ['notifications','fa-bell','Notifications','Arifa'],
        ['emergency','fa-truck-medical','Emergency','Dharura'],
      ] as [$k,$ic,$en,$sw]): $a=$tab===$k; ?>
      <a href="?tab=<?=$k?>" class="pat-sb-item <?=$a?'active':''?>"
         data-en="<?=$en?>" data-sw="<?=$sw?>">
        <i class="fa-solid <?=$ic?>"></i>
        <span class="pat-sb-label"><?=$en?></span>
        <?php if($k==='notifications'&&$unread>0):?><span class="pat-sb-badge"><?=$unread?></span><?php endif;?>
      </a>
      <?php endforeach;?>

      <div class="pat-sb-section-label" style="margin-top:6px">ACCOUNT</div>
      <a href="?tab=settings" class="pat-sb-item <?=$tab==='settings'?'active':''?>">
        <i class="fa-solid fa-gear"></i>
        <span class="pat-sb-label" data-en="Settings" data-sw="Mipangilio">Settings</span>
      </a>
      <a href="/api/auth/logout.php" class="pat-sb-item" style="color:var(--red)">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span class="pat-sb-label" data-en="Sign Out" data-sw="Toka">Sign Out</span>
      </a>
    </nav>

    <!-- Sidebar toggle (desktop collapse) -->
    <div class="pat-sb-footer">
      <button class="pat-sb-toggle" id="sbToggle" title="Collapse">
        <i class="fa-solid fa-chevron-left" id="sbToggleIco"></i>
      </button>
    </div>
  </aside>

  <!-- Mobile overlay -->
  <div class="mob-ov" id="mobOv" onclick="closeSb()"></div>

  <!--  MAIN CONTENT  -->
  <main class="pat-main" id="patMain">
    <div class="pat-content">

    <!--  OVERVIEW  -->
    <?php if($tab==='overview'): ?>

    <!-- Welcome banner -->
    <div class="pat-welcome" style="width:100%">
      <div style="flex:1;min-width:0">
        <div class="pat-welcome-title">
          <span id="dashGreeting"><?= htmlspecialchars($greetEn) ?></span>, <?= $fname ?> 
        </div>
        <?php if($nextAppt):
          $nd=strtotime($nextAppt['appointment_at']);
          $when=date('Y-m-d',$nd)===date('Y-m-d')?'today':(date('Y-m-d',$nd)===date('Y-m-d',time()+86400)?'tomorrow':'on '.date('M j',$nd));
        ?>
        <div class="pat-welcome-sub">Appointment <?=$when?> at <?= date('g:i A',$nd) ?> with <?= htmlspecialchars($nextAppt['prov_name']??'your doctor') ?></div>
        <?php else:?>
        <div class="pat-welcome-sub" data-en="No upcoming appointments. Book one to get started." data-sw="Hakuna miadi inayokuja. Weka moja kuanza.">No upcoming appointments. Book one to get started.</div>
        <?php endif;?>
      </div>
      <div class="pat-welcome-btns">
        <button class="pat-btn-white" onclick="location.href='/patients/book.php'">
          <i class="fa-solid fa-calendar-plus" style="font-size:10px"></i> Book Appointment
        </button>
        <a href="?tab=nearby" class="pat-btn-ghost">
          <i class="fa-solid fa-location-dot"></i> Find Care
        </a>
      </div>
    </div>

    <!-- Stats -->
    <div class="pat-stats">
      <?php foreach([
        [$upcomingCt,'Upcoming','var(--primary)','rgba(25,120,229,.1)','fa-calendar-check'],
        [$completedCt,'Completed','var(--green)','rgba(22,163,74,.1)','fa-circle-check'],
        [count($myDoctors),'My Doctors','var(--teal)','rgba(13,148,136,.1)','fa-stethoscope'],
        [$unread,'Notifications','var(--red)','rgba(220,38,38,.1)','fa-bell'],
      ] as [$val,$lbl,$col,$bg,$ic]):?>
      <div class="pat-stat">
        <div class="pat-stat-ic" style="background:<?=$bg?>;color:<?=$col?>"><i class="fa-solid <?=$ic?>"></i></div>
        <div>
          <div class="pat-stat-val" style="color:<?=$col?>"><?=$val?></div>
          <div class="pat-stat-lbl"><?=$lbl?></div>
        </div>
      </div>
      <?php endforeach;?>
    </div>

    <!-- Main grid -->
    <div class="pat-grid">
      <!-- LEFT -->
      <div style="display:flex;flex-direction:column;gap:14px">

        <!-- Upcoming Appointments -->
        <div class="pat-panel">
          <div class="pat-panel-hdr">
            <span class="pat-panel-title" data-en="Upcoming Appointments" data-sw="Miadi Inayokuja">Upcoming Appointments</span>
            <a href="?tab=appointments" class="pat-panel-link" data-en="View all" data-sw="Ona yote">View all</a>
          </div>
          <div class="pat-panel-body">
            <?php if(empty($upcoming)):?>
            <div class="empty-state">
              <i class="fa-regular fa-calendar-xmark"></i>
              <h3 data-en="No upcoming appointments" data-sw="Hakuna miadi inayokuja">No upcoming appointments</h3>
              <p data-en="Book your first appointment to get started." data-sw="Weka miadi yako ya kwanza kuanza.">Book your first appointment to get started.</p>
              <button class="btn btn-primary btn-sm" onclick="location.href='/patients/book.php'"><i class="fa-solid fa-plus" style="font-size:10px"></i> Book Now</button>
            </div>
            <?php else: foreach(array_slice($upcoming,0,3) as $a):
              $d=strtotime($a['appointment_at']);
              $isTele=($a['location_type']??'')==='telehealth';
            ?>
            <div class="appt-row">
              <div class="appt-ic <?=$isTele?'tele':'inperson'?>">
                <i class="fa-solid <?=$isTele?'fa-video':'fa-location-dot'?>"></i>
              </div>
              <div style="flex:1;min-width:0">
                <div class="appt-name"><?= htmlspecialchars($a['prov_name']??$a['title']??'Appointment') ?></div>
                <div class="appt-meta"><?= date('M j, Y · g:i A',$d) ?> · <?= htmlspecialchars($a['specialty']??ucfirst($a['prov_type']??'General')) ?></div>
                <div style="margin-top:4px">
                  <span class="appt-pill <?=$isTele?'tele':'inp'?>"><?=$isTele?'Telehealth':'In-Person'?></span>
                </div>
              </div>
              <?php if($isTele):?>
              <a href="/patients/telehealth.php" class="btn-join" style="font-size:.6875rem;padding:6px 12px">Join</a>
              <?php endif;?>
            </div>
            <?php endforeach;endif;?>
          </div>
        </div>

        <!-- Available Hospitals & Doctors (find care preview) -->
        <div class="pat-panel">
          <div class="pat-panel-hdr">
            <span class="pat-panel-title" data-en="Providers Near You" data-sw="Watoa Huduma Karibu Nawe">Providers Near You</span>
            <a href="?tab=nearby" class="pat-panel-link" data-en="See all" data-sw="Ona yote">See all →</a>
          </div>
          <div class="pat-panel-body" style="padding:8px 12px">
            <div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap">
              <?php foreach([['all','All','fa-border-all'],['doctor','Doctors','fa-stethoscope'],['hospital','Hospitals','fa-hospital'],['clinic','Clinics','fa-house-medical'],['pharmacy','Pharmacy','fa-pills']] as [$k,$lb,$ic]):?>
              <button class="prov-filter-btn <?=$k==='all'?'active':''?>" onclick="previewFilter('<?=$k?>',this)"
                      data-fkey="<?=$k?>">
                <i class="fa-solid <?=$ic?>" style="font-size:11px"></i> <?=$lb?>
              </button>
              <?php endforeach;?>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px" id="provPreviewGrid">
              <?php foreach(array_slice($nearby,0,6) as $p):
                $pt=$p['type']??'clinic';
                $icons=['doctor'=>'fa-stethoscope','clinic'=>'fa-house-medical','hospital'=>'fa-hospital','ambulance'=>'fa-truck-medical','pharmacy'=>'fa-pills'];
                $bgs=['doctor'=>'rgba(13,148,136,.1)','clinic'=>'rgba(5,150,105,.1)','hospital'=>'rgba(25,120,229,.1)','ambulance'=>'rgba(220,38,38,.1)','pharmacy'=>'rgba(217,119,6,.1)'];
                $cols=['doctor'=>'var(--teal)','clinic'=>'var(--green)','hospital'=>'var(--primary)','ambulance'=>'var(--red)','pharmacy'=>'var(--yellow)'];
                $ic=$icons[$pt]??'fa-hospital'; $bg=$bgs[$pt]??'rgba(25,120,229,.1)'; $col=$cols[$pt]??'var(--primary)';
              ?>
              <div class="prov-card" data-ptype="<?=$pt?>">
                <div class="prov-card-top">
                  <div class="prov-card-ic" style="background:<?=$bg?>;color:<?=$col?>"><i class="fa-solid <?=$ic?>"></i></div>
                  <div style="flex:1;min-width:0">
                    <div class="prov-card-name"><?= htmlspecialchars($p['name']??'Provider') ?></div>
                    <div class="prov-card-type"><?= htmlspecialchars($p['specialty']??ucfirst($pt)) ?></div>
                  </div>
                </div>
                <div class="prov-card-bottom">
                  <span class="prov-card-rating"><i class="fa-solid fa-star"></i> <?= number_format($p['rating']??4.5,1) ?></span>
                  <span class="prov-card-avail"><i class="fa-solid fa-circle" style="font-size:5px"></i> Available</span>
                </div>
                <button class="prov-card-book" onclick="location.href='/patients/book.php'">
                  <i class="fa-solid fa-calendar-plus" style="font-size:10px"></i> Book Now
                </button>
              </div>
              <?php endforeach;?>
            </div>
          </div>
        </div>

        <!-- Recent History -->
        <div class="pat-panel">
          <div class="pat-panel-hdr">
            <span class="pat-panel-title">Recent History</span>
            <a href="?tab=appointments" class="pat-panel-link">View all</a>
          </div>
          <div class="table-wrap">
            <table class="hist-table">
              <thead><tr><th>Date</th><th>Reason</th><th>Physician</th><th>Status</th></tr></thead>
              <tbody>
              <?php if(empty($past)):?>
              <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--slate-400)">No past visits yet.</td></tr>
              <?php else: foreach(array_slice($past,0,4) as $a):?>
              <tr>
                <td style="white-space:nowrap;font-size:.6875rem"><?= date('M j, Y',strtotime($a['appointment_at'])) ?></td>
                <td>
                  <div style="font-size:.75rem;font-weight:600"><?= htmlspecialchars($a['title']??'Consultation') ?></div>
                  <div style="font-size:.6875rem;color:var(--slate-400)"><?= htmlspecialchars($a['notes']??'') ?></div>
                </td>
                <td style="font-size:.75rem"><?= htmlspecialchars($a['prov_name']??'—') ?></td>
                <td><span class="appt-pill <?= htmlspecialchars($a['status']??'completed') ?>"><?= ucfirst($a['status']??'completed') ?></span></td>
              </tr>
              <?php endforeach;endif;?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div style="display:flex;flex-direction:column;gap:14px">

        <!-- Quick Actions -->
        <div class="pat-panel">
          <div class="pat-panel-hdr">
            <span class="pat-panel-title" data-en="Quick Actions" data-sw="Vitendo vya Haraka">Quick Actions</span>
          </div>
          <div class="pat-panel-body">
            <div class="qa-grid">
              <?php foreach([
                ['fa-stethoscope','rgba(13,148,136,.1)','var(--teal)','Find Doctor','?tab=nearby',''],
                ['fa-calendar-plus','rgba(25,120,229,.1)','var(--primary)','Book Appt','/patients/book.php',''],
                ['fa-hospital','rgba(25,120,229,.1)','var(--primary)','Hospitals','/patients/search.php?type=hospital',''],
                ['fa-video','rgba(13,148,136,.1)','var(--teal)','Telehealth','/patients/telehealth.php',''],
                ['fa-truck-medical','rgba(220,38,38,.1)','var(--red)','Ambulance','?tab=emergency',''],
                ['fa-pills','rgba(217,119,6,.1)','var(--yellow)','Pharmacy','/patients/search.php?type=pharmacy',''],
              ] as [$ic,$bg,$col,$lbl,$href,$onclick]):?>
              <a href="<?=$href?>" class="qa-btn" <?=$onclick?"onclick=\"$onclick\"":''?>>
                <div class="qa-ic" style="background:<?=$bg?>;color:<?=$col?>"><i class="fa-solid <?=$ic?>"></i></div>
                <span class="qa-lbl"><?=$lbl?></span>
              </a>
              <?php endforeach;?>
            </div>
          </div>
        </div>

        <!-- My Doctors -->
        <div class="pat-panel">
          <div class="pat-panel-hdr">
            <span class="pat-panel-title" data-en="My Doctors" data-sw="Madaktari Wangu">My Doctors</span>
            <a href="?tab=nearby" class="pat-panel-link">Find more</a>
          </div>
          <div class="pat-panel-body">
            <?php if(empty($myDoctors)):?>
            <div class="empty-state" style="padding:20px">
              <i class="fa-solid fa-user-doctor" style="font-size:28px"></i>
              <p style="font-size:.75rem;margin-bottom:10px">Book an appointment to add doctors here.</p>
              <button class="btn btn-primary btn-sm" onclick="location.href='?tab=nearby'">Find a Doctor</button>
            </div>
            <?php else: foreach($myDoctors as $m):
              $init=strtoupper(substr($m['prov_name']??'Dr',0,1).(strpos($m['prov_name'],' ')!==false?substr($m['prov_name'],strrpos($m['prov_name'],' ')+1,1):''));
            ?>
            <div class="doc-row">
              <div class="doc-av"><?= htmlspecialchars($init) ?></div>
              <div style="flex:1;min-width:0">
                <div class="doc-name"><?= htmlspecialchars($m['prov_name']??'Doctor') ?></div>
                <div class="doc-spec"><?= htmlspecialchars($m['specialty']??ucfirst($m['prov_type']??'Specialist')) ?></div>
              </div>
              <button class="btn btn-sm" style="background:var(--primary-10);color:var(--primary);border:none;font-size:.625rem;padding:5px 9px;border-radius:6px" onclick="bookWithProvider(this)">
                Book Again
              </button>
            </div>
            <?php endforeach;endif;?>
            <a href="?tab=nearby" class="btn btn-ghost btn-full btn-sm" style="margin-top:10px">
              <i class="fa-solid fa-plus"></i> Find New Doctor
            </a>
          </div>
        </div>

        <!-- Insurance status -->
        <div class="pat-panel">
          <div class="pat-panel-hdr">
            <span class="pat-panel-title"><i class="fa-solid fa-shield" style="color:var(--primary)"></i> Insurance</span>
            <a href="?tab=insurance" class="pat-panel-link">Manage</a>
          </div>
          <div class="pat-panel-body">
            <?php if(empty($insDocs)):?>
            <div style="text-align:center;padding:12px 0">
              <p style="font-size:.75rem;color:var(--slate-400);margin-bottom:10px">No insurance documents added yet.</p>
              <button class="btn btn-primary btn-sm" onclick="location.href='?tab=insurance'"><i class="fa-solid fa-upload"></i> Upload Card</button>
            </div>
            <?php else: $ins=$insDocs[0];?>
            <div style="background:var(--primary-10);border-radius:var(--r);padding:12px">
              <div style="font-size:.5625rem;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px">Active Coverage</div>
              <div style="font-size:.875rem;font-weight:700;color:var(--slate-900);margin-bottom:2px"><?= htmlspecialchars($ins['provider_name']) ?></div>
              <div style="font-size:.6875rem;color:var(--slate-500)"><?= htmlspecialchars($ins['coverage_type']??'Health Insurance') ?></div>
              <?php if($ins['policy_number']):?><div style="font-size:.625rem;color:var(--primary);margin-top:5px;font-weight:600">Policy: <?= htmlspecialchars($ins['policy_number']) ?></div><?php endif;?>
            </div>
            <?php endif;?>
          </div>
        </div>
      </div>
    </div><!-- /pat-grid -->

    <?php /*  APPOINTMENTS  */ elseif($tab==='appointments'): ?>

    <div class="pat-page-hdr" style="width:100%">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
          <div class="pat-page-title" data-en="My Appointments" data-sw="Miadi Yangu">My Appointments</div>
          <div class="pat-page-sub"><?=$totalAppts?> total · <?=$upcomingCt?> upcoming</div>
        </div>
        <a href="/patients/book.php" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-calendar-plus" style="font-size:10px"></i> Book New
        </a>
      </div>
    </div>

    <?php if($missedCount >= 2): ?>
    <div class="warn-banner">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <div>
        <div class="wb-title">
          <?php if($missedCount >= 3): ?>
          <i class="fa-solid fa-ban" style="color:#dc2626"></i> Warning: Your booking access is at risk
          <?php else: ?>
          <i class="fa-solid fa-circle-exclamation" style="color:#ea580c"></i> Attendance Warning
          <?php endif; ?>
        </div>
        <div class="wb-sub">
          You have <strong><?=$missedCount?> unconfirmed appointment<?=$missedCount>1?'s':''?></strong> that passed without check-in.
          <?php if($missedCount >= 3): ?>
          Repeated missed appointments may result in your booking privileges being suspended. Please attend your scheduled appointments or cancel in advance.
          <?php else: ?>
          If you miss <?=3-$missedCount?> more appointment<?=(3-$missedCount)>1?'s':''?>, your ability to book may be restricted. Please attend or cancel at least 2 hours before your appointment.
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="tab-bar">
      <?php $af=$_GET['af']??'upcoming';?>
      <button class="tab-item <?=$af==='upcoming'?'active':''?>" onclick="setAf('upcoming')">Upcoming (<?=$upcomingCt?>)</button>
      <button class="tab-item <?=$af==='past'?'active':''?>"     onclick="setAf('past')">Past (<?=count($past)?>)</button>
      <button class="tab-item <?=$af==='all'?'active':''?>"      onclick="setAf('all')">All (<?=$totalAppts?>)</button>
    </div>

    <?php $show=$af==='past'?$past:($af==='all'?$appts:$upcoming);?>
    <?php if(empty($show)):?>
    <div class="pat-panel"><div class="empty-state">
      <i class="fa-regular fa-calendar-xmark"></i>
      <h3>No appointments found</h3>
      <p>Book your first appointment to get started.</p>
      <button class="btn btn-primary btn-sm" onclick="location.href='/patients/book.php'"><i class="fa-solid fa-plus" style="font-size:10px"></i> New Appointment</button>
    </div></div>
    <?php else: foreach($show as $a):
      $d = strtotime($a['appointment_at']);
      $isTele = ($a['location_type']??'')==='telehealth';
      $st  = $a['status'] ?? 'scheduled';
      $dst = $a['display_status'] ?? $st;
      $provName = $a['prov_name'] ?? $a['hosp_name'] ?? $a['title'] ?? 'Appointment';
      $canModify = in_array($st, ['scheduled','confirmed']) && $d > time();
      // Smart status label + icon
      $dstInfo = [
        'scheduled'             => ['fa-clock','Scheduled','var(--primary)'],
        'confirmed'             => ['fa-circle-check','Confirmed','#15803d'],
        'completed'             => ['fa-check-double','Completed','#15803d'],
        'cancelled'             => ['fa-circle-xmark','Cancelled','#dc2626'],
        'no_show'               => ['fa-user-slash','No Show','#b91c1c'],
        'pending_checkin'       => ['fa-hourglass-half','Pending Check-in','#b45309'],
        'awaiting_confirmation' => ['fa-hourglass','Awaiting Confirmation','#92400e'],
        'unconfirmed'           => ['fa-triangle-exclamation','Unconfirmed — Missed','#dc2626'],
        'in_progress'           => ['fa-spinner','In Progress','#0369a1'],
      ];
      [$dstIcon,$dstLabel,$dstColor] = $dstInfo[$dst] ?? ['fa-circle-question',ucfirst($dst),'#64748b'];
    ?>
    <div class="pat-panel" style="margin-bottom:8px;border-left:3px solid <?=match($dst){'cancelled','unconfirmed','no_show'=>'#ef4444','confirmed','completed'=>'#22c55e','pending_checkin','awaiting_confirmation'=>'#f59e0b',default=>'#3b82f6'}?>">
      <div style="display:flex;gap:10px;padding:11px 14px;align-items:flex-start">
        <div style="width:36px;height:36px;border-radius:10px;background:<?=$isTele?'rgba(13,148,136,.1)':'rgba(25,120,229,.08)'?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fa-solid <?=$isTele?'fa-video':'fa-hospital'?>" style="color:<?=$isTele?'#0d9488':'var(--primary)'?>;font-size:16px"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:.875rem;font-weight:700;color:#111827;margin-bottom:1px"><?= htmlspecialchars($provName) ?></div>
          <div style="font-size:.75rem;color:#64748b;margin-bottom:4px">
            <i class="fa-regular fa-calendar" style="margin-right:4px"></i><?= date('D, M j, Y · g:i A',$d) ?>
            <?php if(!empty($a['specialty'])):?> &nbsp;·&nbsp; <i class="fa-solid fa-stethoscope" style="margin-right:3px"></i><?=htmlspecialchars($a['specialty'])?><?php endif;?>
          </div>
          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <span class="appt-pill <?=$isTele?'tele':'inp'?>">
              <i class="fa-solid <?=$isTele?'fa-video':'fa-person-walking'?>"></i>
              <?=$isTele?'Telehealth':'In-Person'?>
            </span>
            <span class="appt-pill <?=str_replace('_','-',$dst)?>" style="color:<?=$dstColor?>">
              <i class="fa-solid <?=$dstIcon?>"></i>
              <?=$dstLabel?>
            </span>
            <?php if(in_array($dst,['pending_checkin','awaiting_confirmation'])):?>
            <span style="font-size:.625rem;color:#92400e;font-style:italic">
              <i class="fa-solid fa-circle-info"></i>
              <?=$dst==='pending_checkin'?'Check in within 3 hours':'Contact provider to confirm'?>
            </span>
            <?php endif;?>
            <?php if($dst==='unconfirmed'):?>
            <span style="font-size:.625rem;color:#dc2626;font-weight:600">
              <i class="fa-solid fa-exclamation-triangle"></i> This absence is recorded
            </span>
            <?php endif;?>
          </div>
          <?php if(!empty($a['title'])):?>
          <div style="margin-top:6px;font-size:.75rem;color:#94a3b8"><i class="fa-solid fa-note-medical" style="margin-right:3px;font-size:10px"></i><?=htmlspecialchars($a['title'])?></div>
          <?php endif;?>
        </div>
        <div style="display:flex;flex-direction:column;gap:5px;flex-shrink:0;align-items:flex-end">
          <?php if($isTele&&$canModify):?>
          <a href="/patients/telehealth.php" class="btn btn-teal btn-sm"><i class="fa-solid fa-video" style="font-size:.65em"></i> Join</a>
          <?php endif;?>
          <?php if($canModify):?>
          <button class="btn btn-ghost btn-sm" style="font-size:.625rem;padding:4px 9px" onclick="openReschedule(<?=$a['id']?>,'<?=date('Y-m-d H:i',strtotime($a['appointment_at']))?>')"><i class="fa-solid fa-calendar-day" style="font-size:.65em"></i> Reschedule</button>
          <button class="btn btn-sm" style="font-size:.625rem;padding:4px 9px;background:rgba(220,38,38,.08);color:#dc2626;border:1px solid rgba(220,38,38,.15);border-radius:6px;cursor:pointer" onclick="openCancel(<?=$a['id']?>,'<?=htmlspecialchars(addslashes($a['prov_name']??'Provider'))?>')"><i class="fa-solid fa-xmark" style="font-size:.65em"></i> Cancel</button>
          <?php endif;?>
          <?php
          // 1. Get the real Database ID
          // IDs over 100,000 are guest bookings; we strip the 100k offset.
          $_rawId = (int)($a['id'] ?? 0);
          $_cleanId = ($_rawId >= 100000) ? ($_rawId - 100000) : $_rawId;

          // 2. FORCE 'standard' type to stop the blinking/redirect loop
          // The messaging system can find ANY appointment if we use 'standard'
          $_msgType = 'standard';

          // 3. Define the sender/receiver name for the chat
          $_recipName = urlencode($a['prov_name'] ?? 'Provider');
          
          // 4. Status Check
          $_st = $a['status'] ?? 'scheduled';
          ?>

          <?php if($_cleanId > 0 && in_array($_st, ['scheduled', 'confirmed', 'completed', 'in_progress'])): ?>
            <a href="/messages.php?appt_id=<?= $_cleanId ?>&appt_type=<?= $_msgType ?>&name=<?= $_recipName ?>" 
               class="btn btn-sm" 
               style="font-size:.625rem;padding:4px 9px;background:rgba(0,90,180,.07);color:var(--primary);border:1px solid rgba(0,90,180,.15);border-radius:6px;display:inline-flex;align-items:center;gap:3px">
              <i class="fa-solid fa-comment" style="font-size:.65em"></i> Message
            </a>
          <?php endif; ?>
          <?php if($st==='completed'):?>
          <button class="btn btn-sm" style="font-size:.625rem;padding:4px 9px;background:rgba(245,158,11,.1);color:#b45309;border:1px solid rgba(245,158,11,.25);border-radius:6px;display:inline-flex;align-items:center;gap:3px" onclick="openFbModal(<?=$a['id']?>,'<?=htmlspecialchars(addslashes($a['prov_name']??'Provider'))?>')">
            <i class="fa-solid fa-star" style="font-size:.65em"></i> Rate Visit
          </button>
          <?php endif;?>
        </div>
      </div>
    </div>
    <?php endforeach;endif;?>

    <?php /*  FIND CARE (NEARBY)  */ elseif($tab==='nearby'): ?>

    <div class="pat-page-hdr" style="width:100%">
      <div class="pat-page-title" data-en="Find Care Near You" data-sw="Tafuta Huduma Karibu Nawe">Find Care Near You</div>
      <div class="pat-page-sub" id="nearbySubtitle">Showing all available hospitals and doctors. Click "Near Me" to sort by distance.</div>
    </div>

    <!-- Search bar -->
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
      <div style="flex:1;min-width:180px;position:relative">
        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--slate-400);font-size:12px"></i>
        <input type="text" id="provSearch" class="form-input" placeholder="Search by name or specialty…"
               style="padding-left:34px;font-size:.8125rem" oninput="liveSearch(this.value)">
      </div>
      <button class="btn btn-primary btn-sm" onclick="initNearby()">
        <i class="fa-solid fa-location-crosshairs"></i> Near Me
      </button>
      <a href="/patients/search.php" class="btn btn-ghost btn-sm">
        <i class="fa-solid fa-sliders"></i> Full Search
      </a>
    </div>

    <!-- Filter bar -->
    <div class="prov-filter-bar" id="nearbyFilterBar">
      <?php foreach([['all','All','fa-border-all'],['doctor','Doctors','fa-stethoscope'],['clinic','Clinics','fa-house-medical'],['hospital','Hospitals','fa-hospital'],['ambulance','Ambulance','fa-truck-medical'],['pharmacy','Pharmacy','fa-pills']] as [$k,$lb,$ic]):?>
      <button class="prov-filter-btn <?=$k==='all'?'active':''?>" onclick="filterNearby('<?=$k?>',this)" data-ftype="<?=$k?>">
        <i class="fa-solid <?=$ic?>" style="font-size:11px"></i> <?=$lb?>
      </button>
      <?php endforeach;?>
      <span id="nearbyLocLabel" style="margin-left:auto;font-size:.6875rem;color:var(--slate-400);display:flex;align-items:center;gap:4px">
        <i class="fa-solid fa-location-dot" style="color:var(--primary)"></i>
        <span id="locText">All providers</span>
      </span>
    </div>

    <!-- Location denied -->
    <div id="locDenied" style="display:none;padding:12px 16px;background:#fef2f2;border-radius:var(--r);border:1px solid rgba(220,38,38,.2);margin-bottom:12px;font-size:.8125rem;color:#991b1b">
      <i class="fa-solid fa-triangle-exclamation" style="margin-right:6px"></i>Location denied. Showing all providers.
    </div>

    <!-- Loading -->
    <div id="nearbyLoading" style="display:none;text-align:center;padding:40px">
      <i class="fa-solid fa-circle-notch fa-spin" style="font-size:28px;color:var(--primary)"></i>
      <p style="margin-top:10px;font-size:.8125rem;color:var(--slate-400)">Finding providers near you…</p>
    </div>

    <!-- Providers grid -->
    <div class="prov-grid" id="nearbyGrid">
      <?php if(empty($nearby)): ?>
      <div style="grid-column:1/-1;text-align:center;padding:48px 24px;color:var(--slate-400)">
        <i class="fa-solid fa-hospital-user" style="font-size:36px;display:block;margin-bottom:12px;opacity:.4"></i>
        <div style="font-size:.9375rem;font-weight:700;color:var(--slate-500);margin-bottom:6px">No providers found</div>
        <div style="font-size:.8125rem">Providers will appear here once they join Planeazzy.</div>
        <a href="/patients/search.php" class="btn btn-primary btn-sm" style="margin-top:14px;display:inline-flex"><i class="fa-solid fa-magnifying-glass"></i> Search All Providers</a>
      </div>
      <?php else: foreach($nearby as $p):
        $pt = $p['type'] ?? 'clinic';
        // Normalize hospital-type from hospital_providers
        $src = $p['source'] ?? 'providers';
        if ($src === 'hospital_providers') $pt = 'hospital';
        $icons=['doctor'=>'fa-user-doctor','clinic'=>'fa-house-medical','hospital'=>'fa-hospital','ambulance'=>'fa-truck-medical','pharmacy'=>'fa-pills','diagnostic'=>'fa-flask','laboratory'=>'fa-flask'];
        $bgs=['doctor'=>'rgba(13,148,136,.1)','clinic'=>'rgba(5,150,105,.1)','hospital'=>'rgba(25,120,229,.1)','ambulance'=>'rgba(220,38,38,.1)','pharmacy'=>'rgba(217,119,6,.1)','diagnostic'=>'rgba(124,58,237,.1)'];
        $cols=['doctor'=>'var(--teal)','clinic'=>'var(--green)','hospital'=>'var(--primary)','ambulance'=>'var(--red)','pharmacy'=>'var(--yellow)','diagnostic'=>'#7c3aed'];
        $ic=$icons[$pt]??'fa-hospital'; $bg=$bgs[$pt]??'rgba(25,120,229,.1)'; $col=$cols[$pt]??'var(--primary)';
        $isHosp = in_array($src, ['hospital_providers']);
        $isHospDoc = ($src === 'hospital_doctors');
        $pid_val = (int)($p['id'] ?? 0);
        $pname_safe = addslashes(htmlspecialchars($p['name'] ?? ''));
        $pcity_safe = htmlspecialchars($p['city'] ?? $p['county'] ?? '');
        $pfee = !empty($p['fee']) && floatval($p['fee']) > 0 ? number_format(floatval($p['fee']), 2) : 'null';
        // Avatar or initials
        $hasAvatar = !empty($p['avatar_path']);
        $initials = '';
        if ($pt === 'doctor' && !$hasAvatar) {
            $nameParts = explode(' ', trim($p['name'] ?? ''));
            $nameParts = array_filter($nameParts, fn($w) => $w !== 'Dr.');
            $initials = strtoupper(implode('', array_map(fn($w) => $w[0] ?? '', array_slice($nameParts, 0, 2))));
        }
      ?>
      <div class="prov-card" data-ptype="<?=$pt?>" data-name="<?=strtolower(htmlspecialchars($p['name']??''))?>" data-spec="<?=strtolower(htmlspecialchars($p['specialty']??''))?>" data-lat="<?=floatval($p['lat']??0)?>" data-lng="<?=floatval($p['lng']??0)?>">
        <div class="prov-card-top">
          <?php if($hasAvatar): ?>
          <div style="width:44px;height:44px;border-radius:<?=$pt==='doctor'?'50%':'10px'?>;overflow:hidden;flex-shrink:0;border:2px solid rgba(0,0,0,.06)">
            <img src="<?=htmlspecialchars($p['avatar_path'])?>" alt="" style="width:100%;height:100%;object-fit:cover">
          </div>
          <?php elseif($pt === 'doctor' && $initials): ?>
          <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#1978e5,#0d9488);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;flex-shrink:0;letter-spacing:-.5px">
            <?=htmlspecialchars($initials)?>
          </div>
          <?php else: ?>
          <div class="prov-card-ic" style="background:<?=$bg?>;color:<?=$col?>;width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa-solid <?=$ic?>"></i></div>
          <?php endif; ?>
          <div style="flex:1;min-width:0">
            <div class="prov-card-name"><?=htmlspecialchars($p['name']??'Provider')?></div>
            <div class="prov-card-type"><?=htmlspecialchars($p['specialty']??ucfirst($pt))?></div>
            <?php if(!empty($p['hosp_name'])): ?>
            <div style="font-size:.6rem;color:var(--slate-400);margin-top:1px;display:flex;align-items:center;gap:3px"><i class="fa-solid fa-hospital"></i><?=htmlspecialchars($p['hosp_name'])?></div>
            <?php elseif($isHosp): ?>
            <div style="font-size:.6rem;color:var(--primary);margin-top:2px;display:flex;align-items:center;gap:3px"><i class="fa-solid fa-circle-check"></i>Verified Facility</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="prov-card-bottom">
          <span class="prov-card-dist" id="dist-<?=$pid_val?>">
            <i class="fa-solid fa-location-dot"></i>
            <?=htmlspecialchars($p['city']??$p['county']??'Kenya')?>
          </span>
          <span class="prov-card-rating"><i class="fa-solid fa-star"></i> <?=number_format(floatval($p['rating']??4.5),1)?></span>
        </div>
        <button class="prov-card-book" onclick="bookProvider(<?=$pid_val?>,<?=number_format(floatval($p['rating']??4.5),1)?>,'<?=$pname_safe?>',<?=$isHosp?'true':'false'?>,'<?=$pcity_safe?>',<?=$pfee?>)">
          <i class="fa-solid fa-calendar-plus" style="font-size:10px"></i> Book Now
        </button>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <?php /*  INSURANCE  */ elseif($tab==='insurance'): ?>

    <div class="pat-page-hdr" style="width:100%">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
          <div class="pat-page-title">Insurance Details</div>
          <div class="pat-page-sub">Add your health insurance policy details for use during bookings.</div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openModal('insModal')"><i class="fa-solid fa-plus"></i> Add Insurance</button>
      </div>
    </div>
    <div id="insAlert" class="alert hidden"></div>
    <?php if(empty($insDocs)):?>
    <div class="pat-panel"><div class="empty-state">
      <i class="fa-solid fa-shield" style="color:var(--primary-10)"></i>
      <h3>No insurance details yet</h3>
      <p>Add your insurance policy number so providers can process your claims during appointments.</p>
      <button class="btn btn-primary btn-sm" onclick="openModal('insModal')"><i class="fa-solid fa-plus"></i> Add Insurance</button>
    </div></div>
    <?php else:?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin-bottom:16px">
      <?php foreach($insDocs as $doc):?>
      <div class="pat-panel" style="border-radius:14px;overflow:hidden;border:1px solid rgba(0,0,0,.06);box-shadow:0 2px 12px rgba(0,0,0,.05)">
        <div style="background:linear-gradient(135deg,var(--primary),var(--teal));padding:16px;display:flex;align-items:center;gap:12px">
          <div style="width:40px;height:40px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;flex-shrink:0">
            <i class="fa-solid fa-shield-heart"></i>
          </div>
          <div style="min-width:0;flex:1">
            <div style="font-size:.875rem;font-weight:800;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($doc['provider_name']) ?></div>
            <div style="font-size:.6875rem;color:rgba(255,255,255,.8)"><?= htmlspecialchars($doc['coverage_type']??'Health Insurance') ?></div>
          </div>
          <span style="padding:3px 10px;border-radius:9999px;font-size:.5625rem;font-weight:800;text-transform:uppercase;background:rgba(255,255,255,.2);color:#fff;letter-spacing:.05em"><?= ucfirst($doc['status']) ?></span>
        </div>
        <div class="pat-panel-body" style="font-size:.8125rem;padding:14px 16px">
          <?php if($doc['policy_number']):?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(0,0,0,.06)">
            <span style="color:var(--slate-400);font-weight:600;display:flex;align-items:center;gap:5px"><i class="fa-solid fa-hashtag" style="font-size:10px;color:var(--primary)"></i>Policy Number</span>
            <span style="font-weight:700;color:var(--slate-900);font-family:monospace;font-size:.8125rem"><?= htmlspecialchars($doc['policy_number']) ?></span>
          </div>
          <?php endif;?>
          <?php if($doc['expiry_date']):
            $expired=strtotime($doc['expiry_date'])<time();?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(0,0,0,.06)">
            <span style="color:var(--slate-400);font-weight:600;display:flex;align-items:center;gap:5px"><i class="fa-regular fa-calendar" style="font-size:10px;color:var(--primary)"></i>Expires</span>
            <span style="font-weight:600;color:<?=$expired?'var(--red)':'var(--slate-900)'?>"><?=date('M j, Y',strtotime($doc['expiry_date']))?></span>
          </div>
          <?php endif;?>
          <div style="display:flex;gap:8px;margin-top:12px">
            <button class="btn btn-primary btn-sm" style="flex:1" onclick="location.href='/patients/book.php'"><i class="fa-solid fa-calendar-plus" style="font-size:10px"></i> Book Appointment</button>
            <button class="btn btn-ghost btn-sm" onclick="delIns(<?=$doc['id']?>,this)" title="Delete"><i class="fa-solid fa-trash" style="color:var(--red)"></i></button>
          </div>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="openModal('insModal')"><i class="fa-solid fa-plus"></i> Add Another Policy</button>
    <?php endif;?>

    <?php /*  NOTIFICATIONS  */ elseif($tab==='notifications'): ?>

    <div class="pat-page-hdr" style="width:100%">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
          <div class="pat-page-title" data-en="Notifications" data-sw="Arifa">Notifications</div>
          <div class="pat-page-sub"><?=count($notifs)?> notifications · <?=$unread?> unread</div>
        </div>
        <?php if($unread>0):?>
        <button class="btn btn-ghost btn-sm" onclick="markAllRead()"><i class="fa-solid fa-check-double"></i> Mark all read</button>
        <?php endif;?>
      </div>
    </div>
    <?php if(empty($notifs)):?>
    <div class="pat-panel"><div class="empty-state">
      <i class="fa-regular fa-bell-slash"></i>
      <h3>No notifications yet</h3>
      <p>Booking confirmations, reminders and alerts will appear here.</p>
    </div></div>
    <?php else:
    $ticons=['appointment'=>['fa-calendar-check','rgba(25,120,229,.1)','var(--primary)'],
             'reminder'=>['fa-clock','rgba(217,119,6,.1)','var(--yellow)'],
             'result'=>['fa-flask','rgba(13,148,136,.1)','var(--teal)'],
             'emergency'=>['fa-truck-medical','rgba(220,38,38,.1)','var(--red)'],
             'system'=>['fa-circle-info','rgba(25,120,229,.1)','var(--primary)']];
    foreach($notifs as $n):
      $unreadN=!$n['is_read'];
      [$nic,$nbg,$ncol]=$ticons[$n['type']]??['fa-bell','rgba(25,120,229,.1)','var(--primary)'];
    ?>
    <div id="ni-<?=$n['id']?>" class="notif-item <?=$unreadN?'unread':''?>" onclick="markRead(<?=$n['id']?>)">
      <div class="notif-ic" style="background:<?=$nbg?>;color:<?=$ncol?>"><i class="fa-solid <?=$nic?>"></i></div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:6px">
          <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
          <?php if($unreadN):?><div class="notif-dot"></div><?php endif;?>
        </div>
        <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
        <div class="notif-time"><i class="fa-regular fa-clock"></i> <?= date('M j, Y g:i A',strtotime($n['created_at'])) ?></div>
      </div>
    </div>
    <?php endforeach;endif;?>

    <?php /*  EMERGENCY  */ elseif($tab==='emergency'): ?>

    <div class="pat-page-hdr" style="width:100%">
      <div class="pat-page-title" data-en="Emergency Services" data-sw="Huduma za Dharura">Emergency Services</div>
      <div class="pat-page-sub">Request emergency assistance instantly using GPS location.</div>
    </div>
    <div class="sos-card">
      <div style="position:relative;z-index:1">
        <div style="font-size:.5625rem;font-weight:700;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.14em;margin-bottom:12px">EMERGENCY SOS</div>
        <button class="sos-btn" id="sosBtn" onclick="triggerSOS()">
          <i class="fa-solid fa-truck-medical" style="font-size:26px"></i>
          <span style="font-size:.625rem;font-weight:800;letter-spacing:.05em">SOS</span>
        </button>
        <p style="font-size:.875rem;color:rgba(255,255,255,.9);font-weight:600;margin-bottom:4px">Press to dispatch ambulance</p>
        <p style="font-size:.75rem;color:rgba(255,255,255,.6)">Your GPS location will be shared with the nearest available unit</p>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px">
      <?php foreach([['fa-clock','var(--red)','~4 min','Response Time'],['fa-truck-medical','var(--primary)','24/7','Ambulance Dispatch'],['fa-location-dot','var(--teal)','GPS','Live Tracking']] as [$ic,$col,$val,$lbl]):?>
      <div class="pat-panel" style="text-align:center;padding:14px 10px">
        <div style="width:36px;height:36px;border-radius:var(--r);background:<?=$col?>18;color:<?=$col?>;display:flex;align-items:center;justify-content:center;font-size:16px;margin:0 auto 8px"><i class="fa-solid <?=$ic?>"></i></div>
        <div style="font-size:1rem;font-weight:800;color:var(--slate-900);margin-bottom:2px"><?=$val?></div>
        <div style="font-size:.625rem;color:var(--slate-400)"><?=$lbl?></div>
      </div>
      <?php endforeach;?>
    </div>
    <?php if(!empty($activeEmergencies)):?>
    <div class="pat-panel" style="margin-bottom:16px">
      <div class="pat-panel-hdr"><span class="pat-panel-title">Recent Requests</span></div>
      <?php foreach($activeEmergencies as $er):
        $sm=['pending'=>'Pending','dispatched'=>'Dispatched','en_route'=>'En Route','completed'=>'Completed','cancelled'=>'Cancelled'];
        $sl=$sm[$er['status']]??ucfirst($er['status']);
      ?>
      <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--slate-100)">
        <div style="width:36px;height:36px;border-radius:var(--r);background:rgba(220,38,38,.08);color:var(--red);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa-solid fa-truck-medical"></i></div>
        <div style="flex:1">
          <div style="font-size:.8125rem;font-weight:600"><?= ucfirst($er['emergency_type']??'Ambulance') ?> Request</div>
          <div style="font-size:.6875rem;color:var(--slate-400)"><?= date('M j, Y g:i A',strtotime($er['requested_at'])) ?></div>
        </div>
        <span class="appt-pill scheduled" style="font-size:.5625rem"><?=$sl?></span>
      </div>
      <?php endforeach;?>
    </div>
    <?php endif;?>
    <div class="pat-panel">
      <div class="pat-panel-hdr"><span class="pat-panel-title"><i class="fa-solid fa-phone" style="color:var(--primary)"></i> Kenya Emergency Contacts</span></div>
      <?php foreach([['999 / 112','National Emergency','fa-tower-broadcast','var(--red)'],['0800 723 253','Kenya Red Cross (Free)','fa-heart','#dc2626'],['0722 202 020','St John Ambulance','fa-truck-medical','var(--primary)'],['+254 20 271 9000','Nairobi Hospital','fa-hospital','var(--teal)']] as [$num,$lbl,$ic,$col]):?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--slate-100)">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:32px;height:32px;border-radius:var(--r);background:<?=$col?>18;color:<?=$col?>;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0"><i class="fa-solid <?=$ic?>"></i></div>
          <div><div style="font-size:.8125rem;font-weight:700"><?=$num?></div><div style="font-size:.6875rem;color:var(--slate-400)"><?=$lbl?></div></div>
        </div>
        <a href="tel:<?= preg_replace('/[^0-9+]/','',$num) ?>" class="btn btn-primary btn-sm" style="font-size:.6875rem"><i class="fa-solid fa-phone"></i> Call</a>
      </div>
      <?php endforeach;?>
    </div>

    <?php /*  SETTINGS  */ elseif($tab==='settings'): ?>

    <div class="pat-page-hdr" style="width:100%">
      <div class="pat-page-title">Account Settings</div>
      <div class="pat-page-sub">Manage your profile, preferences and security.</div>
    </div>
    <div id="settAlert" class="alert hidden"></div>

    <!-- Profile Picture Card -->
    <div class="pat-panel" style="margin-bottom:16px">
      <div class="pat-panel-hdr"><span class="pat-panel-title"><i class="fa-solid fa-camera" style="color:var(--primary)"></i> Profile Picture</span></div>
      <div class="pat-panel-body">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
          <div style="position:relative;flex-shrink:0">
            <div id="avatarPreview" style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--teal));display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;color:#fff;overflow:hidden;border:3px solid var(--primary-20)">
              <?php if($hasAvatar):?><img src="<?=$avatarSrc?>" style="width:100%;height:100%;object-fit:cover"><?php else:?><?= htmlspecialchars($initials) ?><?php endif;?>
            </div>
            <label for="avatarInput" style="position:absolute;bottom:0;right:0;width:26px;height:26px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff;font-size:11px" title="Change photo">
              <i class="fa-solid fa-camera"></i>
            </label>
          </div>
          <div style="flex:1;min-width:200px">
            <div style="font-size:13.5px;font-weight:700;color:var(--slate-900);margin-bottom:4px">Profile Photo</div>
            <div style="font-size:12px;color:var(--slate-400);margin-bottom:12px;line-height:1.5">Upload a clear photo. Max 10 MB · JPG, PNG, WebP, GIF. Visible to hospitals when they view your profile.</div>
            <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="uploadAvatar(this)">
            <label for="avatarInput" style="display:inline-flex;align-items:center;gap:7px;padding:8px 18px;background:var(--primary);color:#fff;border-radius:8px;cursor:pointer;font-size:12.5px;font-weight:700;transition:background .15s" onmouseover="this.style.background='#1462c4'" onmouseout="this.style.background='var(--primary)'">
              <i class="fa-solid fa-cloud-arrow-up"></i> Upload Photo
            </label>
            <div id="avatarMsg" style="font-size:12px;margin-top:8px;display:none"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="settings-grid">
      <div class="pat-panel">
        <div class="pat-panel-hdr"><span class="pat-panel-title"><i class="fa-solid fa-user" style="color:var(--primary)"></i> Personal Info</span></div>
        <div class="pat-panel-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="form-group" style="margin-bottom:0"><label class="form-label">First Name</label><input type="text" id="sfname" class="form-input" value="<?= htmlspecialchars($pat['first_name']??'') ?>"></div>
            <div class="form-group" style="margin-bottom:0"><label class="form-label">Last Name</label><input type="text" id="slname" class="form-input" value="<?= htmlspecialchars($pat['last_name']??'') ?>"></div>
          </div>
          <div class="form-group" style="margin-top:12px"><label class="form-label">Email</label><input type="email" class="form-input" value="<?= htmlspecialchars($pat['email']??'') ?>" readonly style="opacity:.6"></div>
          <div class="form-group"><label class="form-label">Phone</label><input type="tel" id="sphone" class="form-input" value="<?= htmlspecialchars($pat['phone']??'') ?>"></div>
          <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" id="sdob" class="form-input" value="<?= htmlspecialchars($pat['date_of_birth']??'') ?>"></div>
          <button class="btn btn-primary btn-full" onclick="saveProfile()"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
        </div>
      </div>
      <div class="pat-panel">
        <div class="pat-panel-hdr"><span class="pat-panel-title"><i class="fa-solid fa-lock" style="color:var(--primary)"></i> Change Password</span></div>
        <div class="pat-panel-body">
          <div class="form-group"><label class="form-label">Current Password</label><input type="password" id="pw_cur" class="form-input" placeholder="Current password"></div>
          <div class="form-group"><label class="form-label">New Password</label><input type="password" id="pw_new" class="form-input" placeholder="Min 8 characters"></div>
          <div class="form-group"><label class="form-label">Confirm New Password</label><input type="password" id="pw_cnf" class="form-input" placeholder="Repeat new password"></div>
          <button class="btn btn-ghost btn-full" onclick="changePwd()"><i class="fa-solid fa-shield-halved"></i> Update Password</button>
        </div>
      </div>
      <div class="pat-panel">
        <div class="pat-panel-hdr"><span class="pat-panel-title"><i class="fa-solid fa-bell" style="color:var(--primary)"></i> Notifications</span></div>

    <!-- Danger Zone -->
    <div style="margin-top:28px;padding:20px;background:#fef2f2;border:2px solid #fca5a5;border-radius:14px">
      <h3 style="font-size:.9375rem;font-weight:800;color:#991b1b;margin-bottom:6px;display:flex;align-items:center;gap:7px">
        <i class="fa-solid fa-triangle-exclamation"></i> Danger Zone
      </h3>
      <p style="font-size:.8125rem;color:#7f1d1d;margin-bottom:14px">
        Permanently delete your account and all data including appointments, records, and insurance documents. This action is irreversible.
      </p>
      <button onclick="openDeleteAccModal()" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#dc2626;color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.8125rem;font-weight:700;cursor:pointer">
        <i class="fa-solid fa-trash"></i> Delete My Account Permanently
      </button>
    </div>
        <div class="pat-panel-body">
          <?php foreach(['Email appointment reminders'=>true,'SMS alerts'=>true,'Push notifications'=>false,'Emergency SOS alerts'=>true,'Weekly health summary'=>false] as $lbl=>$def):?>
          <div class="toggle-row">
            <span class="toggle-label"><?=$lbl?></span>
            <label class="toggle-sw"><input type="checkbox" <?=$def?'checked':''?>><div class="toggle-track"></div><div class="toggle-thumb"></div></label>
          </div>
          <?php endforeach;?>
        </div>
      </div>
      <div class="pat-panel">
        <div class="pat-panel-hdr"><span class="pat-panel-title"><i class="fa-solid fa-circle-info" style="color:var(--primary)"></i> Account Info</span></div>
        <div class="pat-panel-body" style="font-size:.8125rem">
          <?php foreach([['Patient ID',$patId],['Member Since',date('M Y',strtotime($pat['created_at']??'now'))],['Email Verified','Yes'],['Status','Active']] as [$lbl,$val]):?>
          <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--slate-100)">
            <span style="color:var(--slate-400)"><?=$lbl?></span>
            <span style="font-weight:600"><?=$val?></span>
          </div>
          <?php endforeach;?>
          <div style="margin-top:16px">
            <a href="/api/auth/logout.php" class="btn btn-ghost btn-full" style="font-size:.75rem"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
          </div>
        </div>
      </div>
    </div>

    <?php endif; // tabs ?>

    </div><!-- /pat-content -->

    <footer style="padding:12px 18px;border-top:1px solid var(--slate-100);text-align:center;font-size:.625rem;color:var(--slate-400)">
      © <?= date('Y') ?> Planeazzy · Kenya Data Protection Act 2019 Compliant · <a href="#" style="color:var(--primary)">Privacy</a> · <a href="#" style="color:var(--primary)">Terms</a>
    </footer>
  </main>
</div><!-- /pat-layout -->

<!--  5-STEP BOOK APPOINTMENT MODAL  -->
<div class="modal-ov" id="bkModal" onclick="if(event.target===this)closeBkModal()">
  <div class="modal-box" style="max-width:520px">
    <div class="modal-hdr">
      <div class="modal-title">
        <i class="fa-solid fa-calendar-plus" style="color:var(--primary)"></i>
        <div>
          <div>Book Appointment</div>
          <div id="bkProviderLabel" style="font-size:.6875rem;font-weight:400;color:var(--slate-400);margin-top:1px"></div>
        </div>
      </div>
      <button class="modal-close" onclick="closeBkModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- Steps bar -->
    <div class="bk-steps" id="bkStepsBar">
      <div class="bk-step-item active" data-bkstep="1"><div class="bk-dot">1</div><span class="bk-step-lbl">Reason</span></div>
      <div class="bk-step-item" data-bkstep="2"><div class="bk-dot">2</div><span class="bk-step-lbl">Date</span></div>
      <div class="bk-step-item" data-bkstep="3"><div class="bk-dot">3</div><span class="bk-step-lbl">Time</span></div>
      <div class="bk-step-item" data-bkstep="4"><div class="bk-dot">4</div><span class="bk-step-lbl">Type</span></div>
      <div class="bk-step-item" data-bkstep="5"><div class="bk-dot">5</div><span class="bk-step-lbl">Confirm</span></div>
    </div>

    <!-- Step 1: Reason -->
    <div class="bk-panel active" id="bkPanel1">
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label"><i class="fa-solid fa-stethoscope" style="color:var(--primary)"></i> Reason for Visit <span style="color:var(--red)">*</span></label>
        <select class="bk-reason-select" id="bkReason">
          <option value="">— Select a reason —</option>
          <option value="general_consultation">General Consultation</option>
          <option value="followup">Follow-up</option>
          <option value="checkup">Check-up</option>
          <option value="specialist">Specialist Visit</option>
          <option value="emergency">Emergency</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div id="bkOtherRow" style="display:none" class="form-group">
        <label class="form-label"><i class="fa-solid fa-pencil" style="color:var(--primary)"></i> Please describe <span style="color:var(--red)">*</span></label>
        <textarea class="form-textarea" id="bkOtherNote" rows="2" placeholder="Describe your reason for visiting…"></textarea>
      </div>
      <div class="alert" style="background:rgba(25,120,229,.05);border:1px solid rgba(25,120,229,.15);color:var(--primary);border-radius:9px;padding:9px 12px;font-size:.75rem;display:flex;align-items:center;gap:8px;margin-top:12px">
        <i class="fa-solid fa-circle-info"></i>
        An accurate reason helps your provider prepare for your visit.
      </div>
    </div>

    <!-- Step 2: Date -->
    <div class="bk-panel" id="bkPanel2">
      <div class="bk-hint-bar" id="bkNextHint" style="display:none">
        <i class="fa-regular fa-clock"></i>
        <span id="bkNextHintText"></span>
      </div>
      <div class="bk-hint-bar" id="bkHospitalHint" style="display:none;color:var(--teal);border-color:rgba(13,148,136,.2);background:rgba(13,148,136,.05)">
        <i class="fa-solid fa-hospital" style="color:var(--teal)"></i>
        <span>Available doctors matched automatically based on your chosen time.</span>
      </div>
      <div class="bk-cal-wrap">
        <div class="bk-cal-nav">
          <button class="bk-cal-nav-btn" id="bkCalPrev"><i class="fa-solid fa-chevron-left"></i></button>
          <span class="bk-cal-month" id="bkCalMonth"></span>
          <button class="bk-cal-nav-btn" id="bkCalNext"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
        <div class="bk-cal-grid" id="bkCalGrid"></div>
      </div>
    </div>

    <!-- Step 3: Time Slots -->
    <div class="bk-panel" id="bkPanel3">
      <div style="font-size:.8125rem;color:var(--slate-500);margin-bottom:10px">
        <i class="fa-regular fa-clock" style="color:var(--primary)"></i> Select an available time slot
      </div>
      <div id="bkSlotsContainer"></div>
    </div>

    <!-- Step 4: Visit Type + Notes -->
    <div class="bk-panel" id="bkPanel4">
      <div class="form-label" style="margin-bottom:10px"><i class="fa-solid fa-hospital-user" style="color:var(--primary)"></i> How would you like to be seen?</div>
      <div class="bk-type-grid">
        <button class="bk-type-btn selected" data-vtype="in_person" onclick="selectVisitType(this,'in_person')">
          <i class="fa-solid fa-house-medical"></i>
          In-Person
        </button>
        <button class="bk-type-btn" data-vtype="telehealth" onclick="selectVisitType(this,'telehealth')">
          <i class="fa-solid fa-video"></i>
          Telehealth
        </button>
      </div>
      <div class="form-group" style="margin-top:4px">
        <label class="form-label"><i class="fa-solid fa-note-sticky" style="color:var(--primary)"></i> Notes <span style="font-weight:400;color:var(--slate-400)">(optional)</span></label>
        <textarea class="form-textarea" id="bkNotes" rows="3" placeholder="Add anything the doctor should know…" style="resize:vertical"></textarea>
      </div>
    </div>

    <!-- Step 5: Summary + Confirm -->
    <div class="bk-panel" id="bkPanel5">
      <div class="bk-summary" id="bkSummaryBox">
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-solid fa-user-doctor"></i> Provider</span><span class="bk-sum-val" id="sumProvider">—</span></div>
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-solid fa-location-dot"></i> Location</span><span class="bk-sum-val" id="sumLocation">—</span></div>
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-regular fa-calendar"></i> Date</span><span class="bk-sum-val" id="sumDate">—</span></div>
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-regular fa-clock"></i> Time</span><span class="bk-sum-val" id="sumTime">—</span></div>
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-solid fa-hospital"></i> Visit Type</span><span class="bk-sum-val" id="sumVisitType">—</span></div>
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-solid fa-clipboard"></i> Reason</span><span class="bk-sum-val" id="sumReason">—</span></div>
        <div class="bk-summary-row" id="sumFeeRow" style="display:none"><span class="bk-sum-lbl"><i class="fa-solid fa-credit-card"></i> Fee</span><span class="bk-sum-val" id="sumFee">—</span></div>
      </div>
      <div style="text-align:center;font-size:.75rem;color:var(--slate-400);margin-bottom:4px">
        <i class="fa-solid fa-envelope" style="color:var(--primary)"></i> Email &amp; SMS confirmation will be sent to you instantly.
      </div>
    </div>

    <!-- Success screen -->
    <div class="bk-panel" id="bkSuccess" style="display:none;padding:28px 20px">
      <div class="bk-success">
        <div class="bk-success-icon"><i class="fa-solid fa-circle-check"></i></div>
        <h3 style="font-size:1.125rem;font-weight:800;margin-bottom:6px">Appointment Booked!</h3>
        <p style="font-size:.875rem;color:var(--slate-500);margin-bottom:10px">Your booking reference:</p>
        <div class="bk-ref" id="bkRefCode">PZY-000000</div>
        <p style="font-size:.8125rem;color:var(--slate-400);margin-top:8px">
          <i class="fa-solid fa-envelope" style="color:var(--primary)"></i> Confirmation email &amp; SMS sent. Check your inbox.
        </p>
        <div style="display:flex;gap:10px;justify-content:center;margin-top:20px">
          <button class="btn btn-primary" onclick="closeBkModal();location.href='?tab=appointments'">
            <i class="fa-solid fa-calendar-days"></i> View Appointments
          </button>
          <button class="btn btn-ghost" onclick="closeBkModal()">Close</button>
        </div>
      </div>
    </div>

    <!-- Alert row -->
    <div id="bkAlert" class="alert err" style="display:none;margin:0 20px 10px"></div>

    <!-- Footer navigation -->
    <div class="bk-modal-footer" id="bkModalFooter">
      <button class="btn btn-ghost" id="bkBackBtn" onclick="bkPrev()" style="display:none">
        <i class="fa-solid fa-arrow-left"></i> Back
      </button>
      <button class="btn btn-primary" id="bkNextBtn" onclick="bkNext()">
        Next <i class="fa-solid fa-arrow-right"></i>
      </button>
      <button class="btn btn-primary" id="bkConfirmBtn" onclick="submitBkModal()" style="display:none;background:linear-gradient(135deg,#1978e5,#0d9488)">
        <i class="fa-solid fa-calendar-check"></i> Confirm Appointment
      </button>
    </div>
  </div>
</div>


<!--  PATIENT: MESSAGE PROVIDER MODAL  -->
<div class="modal-ov" id="patMsgModal" onclick="if(event.target===this)closeModal('patMsgModal')">
  <div class="modal-box">
    <div class="modal-hdr">
      <div class="modal-title"><i class="fa-solid fa-envelope" style="color:var(--primary)"></i> Message Provider</div>
      <button class="modal-close" onclick="closeModal('patMsgModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div id="patMsgAlert" class="alert hidden"></div>


      <input type="hidden" id="patMsgProvId">
      <div style="background:var(--slate-50);border-radius:9px;padding:10px 13px;margin-bottom:13px;font-size:12.5px;color:var(--slate-500)">
        Sending message to: <strong id="patMsgProvName" style="color:var(--slate-900)"></strong>
      </div>
      <div class="form-group">
        <label class="form-label">Subject</label>
        <input type="text" id="patMsgSubject" class="form-input" placeholder="e.g. Question about my appointment…">
      </div>
      <div class="form-group">
        <label class="form-label">Message *</label>
        <textarea id="patMsgBody" class="form-textarea" rows="4" placeholder="Type your message here…"></textarea>
      </div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <input type="checkbox" id="patMsgSms" style="width:15px;height:15px;accent-color:var(--primary);cursor:pointer">
        <label for="patMsgSms" style="font-size:12.5px;color:var(--slate-600);cursor:pointer">Also send via SMS to provider</label>
      </div>
      <button class="btn btn-primary btn-full" id="patMsgSendBtn" onclick="submitPatMsg()">
        <i class="fa-solid fa-paper-plane" style="font-size:.75em"></i> Send Message
      </button>
    </div>
  </div>
</div>

<!--  CANCEL APPOINTMENT MODAL  -->
<div class="modal-ov" id="cancelModal" onclick="if(event.target===this)closeModal('cancelModal')">
  <div class="modal-box">
    <div class="modal-hdr">
      <div class="modal-title"><i class="fa-solid fa-xmark" style="color:var(--red)"></i> Cancel Appointment</div>
      <button class="modal-close" onclick="closeModal('cancelModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div id="cancelAlert" class="alert hidden"></div>
      <input type="hidden" id="cancelApptId">
      <div style="background:rgba(220,38,38,.05);border:1px solid rgba(220,38,38,.15);border-radius:10px;padding:14px;margin-bottom:14px">
        <div style="font-size:13px;font-weight:700;color:var(--red);margin-bottom:4px"><i class="fa-solid fa-triangle-exclamation"></i> Are you sure?</div>
        <div style="font-size:12.5px;color:var(--slate-600);line-height:1.6">This will cancel your appointment with <strong id="cancelProvName"></strong>. Both you and the provider will be notified by email and SMS.</div>
      </div>
      <div class="form-group">
        <label class="form-label">Reason for cancellation (optional)</label>
        <textarea class="form-textarea" id="cancelReason" rows="2" placeholder="e.g. Schedule conflict, feeling better…"></textarea>
      </div>
      <div style="display:flex;gap:10px;margin-top:4px">
        <button class="btn btn-full" id="cancelConfirmBtn" onclick="submitCancel()" style="background:var(--red);color:#fff;border:none;height:38px;font-size:13px;font-weight:700;border-radius:8px">
          <i class="fa-solid fa-xmark" style="font-size:.75em"></i> Yes, Cancel Appointment
        </button>
        <button class="btn btn-ghost" onclick="closeModal('cancelModal')" style="height:38px;padding:0 16px;font-size:13px">Keep It</button>
      </div>
    </div>
  </div>
</div>

<!--  RESCHEDULE APPOINTMENT MODAL  -->
<div class="modal-ov" id="reschedModal" onclick="if(event.target===this)closeModal('reschedModal')">
  <div class="modal-box">
    <div class="modal-hdr">
      <div class="modal-title"><i class="fa-solid fa-calendar-days" style="color:var(--primary)"></i> Reschedule Appointment</div>
      <button class="modal-close" onclick="closeModal('reschedModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div id="reschedAlert" class="alert hidden"></div>
      <input type="hidden" id="reschedApptId">
      <div style="font-size:12.5px;color:var(--slate-500);margin-bottom:14px;line-height:1.6">Choose a new date and time. Both you and the provider will receive confirmation by email and SMS.</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">New Date *</label>
          <input type="date" id="reschedDate" class="form-input" min="<?=date('Y-m-d')?>">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">New Time</label>
          <input type="time" id="reschedTime" class="form-input" value="09:00">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Reason for rescheduling (optional)</label>
        <textarea class="form-textarea" id="reschedReason" rows="2" placeholder="e.g. Work schedule, travel…"></textarea>
      </div>
      <button class="btn btn-primary btn-full" id="reschedConfirmBtn" onclick="submitReschedule()">
        <i class="fa-solid fa-calendar-check" style="font-size:.75em"></i> Confirm Reschedule
      </button>
    </div>
  </div>
</div>

<!--  INSURANCE POLICY MODAL  -->
<div class="modal-ov" id="insModal" onclick="if(event.target===this)closeModal('insModal')">
  <div class="modal-box">
    <div class="modal-hdr">
      <div class="modal-title"><i class="fa-solid fa-shield-heart" style="color:var(--primary)"></i> Add Insurance</div>
      <button class="modal-close" onclick="closeModal('insModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div id="insModalAlert" class="alert hidden"></div>
      <p style="font-size:.8125rem;color:var(--slate-500);margin-bottom:14px;line-height:1.6">
        <i class="fa-solid fa-circle-info" style="color:var(--primary);margin-right:4px"></i>
        Enter your insurance details. Your policy number will be shared with providers when you book appointments.
      </p>
      <div class="form-group"><label class="form-label">Insurance Provider *</label>
        <select id="insProviderName" class="form-select">
          <option value="">Select provider…</option>
          <option>NHIF</option>
          <option>Jubilee Health</option>
          <option>AXA Mansard</option>
          <option>AAR Healthcare</option>
          <option>CIC Insurance</option>
          <option>Britam</option>
          <option>Madison Insurance</option>
          <option>Resolution Insurance</option>
          <option>Other</option>
        </select>
      </div>
      <div id="insProviderOtherRow" style="display:none" class="form-group">
        <label class="form-label">Provider Name *</label>
        <input type="text" id="insProviderOther" class="form-input" placeholder="Enter insurance provider name">
      </div>
      <div class="form-group">
        <label class="form-label">Policy Number <span style="color:var(--red)">*</span></label>
        <input type="text" id="insPolicyNumber" class="form-input" placeholder="e.g. NHIF-123456789">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Coverage Type</label>
          <select id="insCoverageType" class="form-select">
            <option value="">Select…</option>
            <option>Inpatient &amp; Outpatient</option>
            <option>Outpatient Only</option>
            <option>Comprehensive</option>
            <option>Emergency Only</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Expiry Date</label>
          <input type="date" id="insExpiryDate" class="form-input">
        </div>
      </div>
      <button type="button" class="btn btn-primary btn-full" id="insUploadBtn" onclick="saveInsurance()" style="margin-top:16px">
        <i class="fa-solid fa-shield-heart"></i> Save Insurance Details
      </button>
    </div>
  </div>
</div>

<!-- Toast + CSRF -->
<input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf) ?>">
<div class="toast" id="toast"></div>
<button class="mob-fab" id="mobFab" onclick="openSb()" aria-label="Open menu">
  <i class="fa-solid fa-bars" style="font-size:16px"></i>
</button>

<!--  FEEDBACK / RATING MODAL  -->
<div class="modal-ov" id="fbModal" onclick="if(event.target===this)closeModal('fbModal')">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-hdr">
      <div class="modal-title"><i class="fa-solid fa-star" style="color:#f59e0b"></i> Rate Your Appointment</div>
      <button class="modal-close" onclick="closeModal('fbModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div id="fbAlert" class="alert hidden"></div>
      <input type="hidden" id="fbApptId">
      <p style="text-align:center;font-size:.875rem;color:var(--slate-600);margin-bottom:4px">How was your experience with</p>
      <p style="text-align:center;font-size:.9375rem;font-weight:700;margin-bottom:14px" id="fbProvName">your provider</p>
      <div class="fb-stars" id="fbStarsRow">
        <i class="fa-solid fa-star fb-star" data-val="1"></i>
        <i class="fa-solid fa-star fb-star" data-val="2"></i>
        <i class="fa-solid fa-star fb-star" data-val="3"></i>
        <i class="fa-solid fa-star fb-star" data-val="4"></i>
        <i class="fa-solid fa-star fb-star" data-val="5"></i>
      </div>
      <div class="fb-label" id="fbRatingLabel"></div>
      <div class="form-group" style="margin-top:10px">
        <label class="form-label"><i class="fa-solid fa-comment" style="color:var(--primary)"></i> Comments <span style="font-weight:400;color:var(--slate-400)">(optional)</span></label>
        <textarea class="form-textarea" id="fbComment" rows="3" placeholder="Share your experience with this provider…"></textarea>
      </div>
      <button class="btn btn-primary btn-full" id="fbSubmitBtn" onclick="submitFeedback()" style="margin-top:6px">
        <i class="fa-solid fa-paper-plane"></i> Submit Feedback
      </button>
    </div>
  </div>
</div>

<script>
/* 
   PLANEAZZY UPGRADE JS — 5-Step Booking Modal + Feedback
   All icons via Font Awesome. No emojis.
 */

/*  Booking modal state  */
const BK = {
  step: 1,
  reason: '', otherNote: '',
  date: null, time: null,
  visitType: 'in_person',
  notes: '',
  provider: null,
  calMonth: new Date(),
};
const REASON_MAP = {
  general_consultation:'General Consultation', followup:'Follow-up', checkup:'Check-up',
  specialist:'Specialist Visit', emergency:'Emergency', other:'Other'
};
const VISIT_MAP = { in_person:'<i class="fa-solid fa-house-medical"></i> In-Person', telehealth:'<i class="fa-solid fa-video"></i> Telehealth' };

function openBkModal(providerData) {
  BK.step=1; BK.reason=''; BK.otherNote=''; BK.date=null; BK.time=null;
  BK.visitType='in_person'; BK.notes=''; BK.calMonth=new Date();
  BK.provider = providerData || null;
  // Reset UI
  document.querySelectorAll('.bk-panel').forEach(p=>{p.classList.remove('active');p.style.display='';});
  document.getElementById('bkPanel1').classList.add('active');
  document.getElementById('bkSuccess').style.display='none';
  document.getElementById('bkStepsBar').style.display='';
  document.getElementById('bkModalFooter').style.display='';
  document.getElementById('bkAlert').style.display='none';
  document.getElementById('bkReason').value='';
  document.getElementById('bkOtherRow').style.display='none';
  document.getElementById('bkNotes').value='';
  document.querySelectorAll('.bk-type-btn').forEach(b=>{b.classList.toggle('selected', b.dataset.vtype==='in_person')});
  const lbl=document.getElementById('bkProviderLabel');
  if (lbl && providerData) lbl.textContent=(providerData.name||'') + (providerData.location ? ' · '+providerData.location : '');
  if (lbl && !providerData) lbl.textContent='';
  updateBkStepBar();
  updateBkNav();
  document.getElementById('bkModal').classList.add('open');
  document.body.style.overflow='hidden';
}

function closeBkModal() {
  document.getElementById('bkModal').classList.remove('open');
  document.body.style.overflow='';
}

function bkNext() {
  const a=document.getElementById('bkAlert');
  a.style.display='none';
  const show=(msg)=>{a.className='alert err';a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> '+msg;a.style.display='flex';};
  if (BK.step===1) {
    BK.reason=document.getElementById('bkReason').value;
    if (!BK.reason) { show('Please select a reason for your visit.'); return; }
    BK.otherNote=document.getElementById('bkOtherNote').value.trim();
    if (BK.reason==='other' && !BK.otherNote) { show('Please describe your reason.'); return; }
  }
  if (BK.step===2 && !BK.date) { show('Please select an available date.'); return; }
  if (BK.step===3 && !BK.time) { show('Please select a time slot.'); return; }
  if (BK.step===4) { BK.notes=document.getElementById('bkNotes').value.trim(); }
  BK.step++;
  showBkStep(BK.step);
}

function bkPrev() { if(BK.step>1){BK.step--;showBkStep(BK.step);} }

function showBkStep(s) {
  document.querySelectorAll('.bk-panel').forEach(p=>p.classList.remove('active'));
  document.getElementById(`bkPanel${s}`)?.classList.add('active');
  document.getElementById('bkAlert').style.display='none';
  updateBkStepBar();
  updateBkNav();
  if(s===2) renderBkCal();
  if(s===3) renderBkSlots();
  if(s===5) renderBkSummary();
}

function updateBkStepBar() {
  document.querySelectorAll('.bk-step-item').forEach((el,i)=>{
    el.classList.remove('active','done');
    if(i+1 < BK.step) el.classList.add('done');
    if(i+1 === BK.step) el.classList.add('active');
    el.querySelector('.bk-dot').innerHTML = i+1 < BK.step ? '<i class="fa-solid fa-check" style="font-size:.55rem"></i>' : (i+1);
  });
}

function updateBkNav() {
  const back=document.getElementById('bkBackBtn');
  const next=document.getElementById('bkNextBtn');
  const conf=document.getElementById('bkConfirmBtn');
  if(back) back.style.display=BK.step>1?'':'none';
  if(next) next.style.display=BK.step<5?'':'none';
  if(conf) conf.style.display=BK.step===5?'':'none';
}

/*  Calendar  */
function renderBkCal() {
  const today=new Date(); today.setHours(0,0,0,0);
  const yr=BK.calMonth.getFullYear(), mo=BK.calMonth.getMonth();
  const first=new Date(yr,mo,1), days=new Date(yr,mo+1,0).getDate();
  const months=['January','February','March','April','May','June','July','August','September','October','November','December'];
  document.getElementById('bkCalMonth').textContent=months[mo]+' '+yr;
  const grid=document.getElementById('bkCalGrid');
  // Sun-Mon-...-Sat headers
  const dns=['Su','Mo','Tu','We','Th','Fr','Sa'];
  let h=dns.map(d=>`<div class="bk-cal-dh">${d}</div>`).join('');
  // Empty cells
  for(let i=0;i<first.getDay();i++) h+=`<button class="bk-cal-d empty" disabled></button>`;
  for(let d=1;d<=days;d++){
    const dt=new Date(yr,mo,d), dow=dt.getDay();
    const isPast=dt<today, isToday=dt.toDateString()===today.toDateString();
    const isAvail=!isPast&&dow!==0; // Mon-Sat, not past
    const isSelected=BK.date && new Date(BK.date+'T00:00').toDateString()===dt.toDateString();
    const cls=['bk-cal-d',isToday?'today':'',isAvail?'avail':'',isPast?'past':'',isSelected?'selected':''].filter(Boolean).join(' ');
    const dateStr=`${yr}-${String(mo+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    h+=`<button class="${cls}" data-date="${dateStr}" ${(!isAvail||isPast)?'disabled':''} onclick="selectBkDate('${dateStr}',this)">${d}</button>`;
  }
  grid.innerHTML=h;
  // Next available hint
  let nextHint='';
  for(let d=1;d<=days;d++){
    const dt=new Date(yr,mo,d);
    if(dt>today&&dt.getDay()!==0){nextHint=dt.toLocaleDateString('en-KE',{weekday:'short',month:'short',day:'numeric'})+' at 9:00 AM';break;}
  }
  const hintEl=document.getElementById('bkNextHint');
  const hintTxt=document.getElementById('bkNextHintText');
  if(hintEl&&hintTxt&&nextHint){hintEl.style.display='flex';hintTxt.textContent='Next available: '+nextHint;}
  // Hospital hint
  const hospHint=document.getElementById('bkHospitalHint');
  if(hospHint) hospHint.style.display=(BK.provider?.isHospital)?'flex':'none';
  // Nav buttons
  document.getElementById('bkCalPrev').onclick=()=>{BK.calMonth.setMonth(mo-1);renderBkCal();};
  document.getElementById('bkCalNext').onclick=()=>{BK.calMonth.setMonth(mo+1);renderBkCal();};
}

function selectBkDate(dateStr, btn) {
  BK.date=dateStr; BK.time=null;
  document.querySelectorAll('.bk-cal-d').forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
}

/*  Time slots  */
function renderBkSlots() {
  const container=document.getElementById('bkSlotsContainer');
  const morning=['09:00','09:30','10:00','10:30','11:00','11:30'];
  const afternoon=['14:00','14:30','15:00','15:30','16:00','16:30'];
  const bookedIdx=new Set([1,4]); // simulated booked slots
  const fmt=(t)=>{const[h,m]=t.split(':').map(Number);const ap=h<12?'AM':'PM';return `${h>12?h-12:h||12}:${String(m).padStart(2,'0')} ${ap}`;};
  let html=`<div class="bk-period-lbl"><i class="fa-regular fa-sun" style="color:var(--yellow)"></i> Morning</div><div class="bk-slots-grid">`;
  morning.forEach((t,i)=>{
    const booked=bookedIdx.has(i); const sel=BK.time===t;
    html+=`<button class="bk-slot${sel?' selected':''}${booked?' disabled':''}" data-time="${t}" ${booked?'disabled':''} onclick="selectBkSlot('${t}',this)">${fmt(t)}${booked?'<br><small style="font-size:.6rem;opacity:.7">Booked</small>':''}</button>`;
  });
  html+=`</div><div class="bk-period-lbl"><i class="fa-regular fa-sun" style="color:var(--primary)"></i> Afternoon</div><div class="bk-slots-grid">`;
  afternoon.forEach((t,i)=>{
    const booked=bookedIdx.has(i+6); const sel=BK.time===t;
    html+=`<button class="bk-slot${sel?' selected':''}${booked?' disabled':''}" data-time="${t}" ${booked?'disabled':''} onclick="selectBkSlot('${t}',this)">${fmt(t)}${booked?'<br><small style="font-size:.6rem;opacity:.7">Booked</small>':''}</button>`;
  });
  html+=`</div>`;
  container.innerHTML=html;
}

function selectBkSlot(time, btn) {
  BK.time=time;
  document.querySelectorAll('.bk-slot').forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
}

function selectVisitType(btn, type) {
  BK.visitType=type;
  document.querySelectorAll('.bk-type-btn').forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
}

/*  Summary  */
function renderBkSummary() {
  const p=BK.provider;
  const fmtDate=(d)=>{if(!d)return'—';const dt=new Date(d+'T00:00');return dt.toLocaleDateString('en-KE',{weekday:'long',month:'long',day:'numeric'});};
  const fmtTime=(t)=>{if(!t)return'—';const[h,m]=t.split(':').map(Number);const ap=h<12?'AM':'PM';return `${h>12?h-12:h||12}:${String(m).padStart(2,'0')} ${ap}`;};
  document.getElementById('sumProvider').textContent=p?.name||'Provider';
  document.getElementById('sumLocation').textContent=p?.location||p?.city||'—';
  document.getElementById('sumDate').textContent=fmtDate(BK.date);
  document.getElementById('sumTime').textContent=fmtTime(BK.time);
  document.getElementById('sumVisitType').innerHTML=VISIT_MAP[BK.visitType]||BK.visitType;
  document.getElementById('sumReason').textContent=REASON_MAP[BK.reason]||BK.reason;
  const feeRow=document.getElementById('sumFeeRow');
  if(feeRow) {
    if(p?.fee&&!p?.isHospital){feeRow.style.display='';document.getElementById('sumFee').textContent='KSh '+Number(p.fee).toLocaleString();}
    else feeRow.style.display='none';
  }
}

/*  Submit  */
async function submitBkModal() {
  const btn=document.getElementById('bkConfirmBtn');
  const a=document.getElementById('bkAlert');
  a.style.display='none';
  if(!BK.date||!BK.time){a.className='alert err';a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> Missing date or time.';a.style.display='flex';return;}
  btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Booking…';
  const dt=BK.date+' '+BK.time+':00';
  const payload={
    service_type: BK.provider?.serviceType||'doctor',
    provider_id:  BK.provider?.id||null,
    appointment_at: dt,
    title: REASON_MAP[BK.reason]||BK.reason,
    notes: BK.otherNote||BK.notes,
    location_type: BK.visitType,
    csrf_token: document.getElementById('csrfToken')?.value||''
  };
  try {
    const r=await fetch('/api/patient/book-appointment.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload),credentials:'same-origin'}).then(r=>r.json());
    if(r.requires_login){window.location.href=r.redirect||'/patients/login.php';return;}
    if(r.success){
      const refEl=document.getElementById('bkRefCode');
      if(refEl) refEl.textContent='PZY-'+String(r.appointment_id||'').padStart(6,'0');
      document.querySelectorAll('.bk-panel').forEach(p=>{p.classList.remove('active');p.style.display='none';});
      document.getElementById('bkStepsBar').style.display='none';
      document.getElementById('bkModalFooter').style.display='none';
      document.getElementById('bkSuccess').style.display='block';
    } else {
      a.className='alert err';a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> '+(r.message||'Booking failed. Please try again.');a.style.display='flex';
      btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-calendar-check"></i> Confirm Appointment';
    }
  } catch(e) {
    a.className='alert err';a.innerHTML='<i class="fa-solid fa-wifi"></i> Network error. Please try again.';a.style.display='flex';
    btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-calendar-check"></i> Confirm Appointment';
  }
}

// Wire reason change
document.getElementById('bkReason').addEventListener('change',function(){
  document.getElementById('bkOtherRow').style.display=this.value==='other'?'block':'none';
});

/*  Override old bookProvider to open new modal  */
function bookProvider(id, rating, name, isHospital, city, fee) {
  openBkModal({id:id, name:name, isHospital:!!isHospital, location:city||'', city:city||'', fee:fee||null, serviceType:isHospital?'hospital':'doctor'});
}
function bookWithProvider(btn) {
  const prov=btn.closest('[data-prov]')?.dataset?.prov||'';
  const provId=btn.closest('[data-pid]')?.dataset?.pid||null;
  openBkModal({id:provId?parseInt(provId):null, name:prov||'Provider', isHospital:false, location:'', serviceType:'doctor'});
}

/*  FEEDBACK MODAL  */
let fbRating=0;
const FB_LABELS=['Poor — needs improvement','Fair — could be better','Good — satisfactory','Very Good — happy','Excellent — outstanding!'];

document.querySelectorAll('.fb-star').forEach((star,i)=>{
  star.addEventListener('mouseenter',()=>{
    document.querySelectorAll('.fb-star').forEach((s,j)=>s.classList.toggle('lit',j<=i));
    document.getElementById('fbRatingLabel').textContent=FB_LABELS[i];
  });
  star.addEventListener('mouseleave',()=>{
    document.querySelectorAll('.fb-star').forEach((s,j)=>s.classList.toggle('lit',j<fbRating));
    document.getElementById('fbRatingLabel').textContent=fbRating?FB_LABELS[fbRating-1]:'';
  });
  star.addEventListener('click',()=>{
    fbRating=i+1;
    document.querySelectorAll('.fb-star').forEach((s,j)=>s.classList.toggle('lit',j<fbRating));
    document.getElementById('fbRatingLabel').textContent=FB_LABELS[i];
  });
});

function openFbModal(apptId,provName){
  fbRating=0;
  document.getElementById('fbApptId').value=apptId;
  document.getElementById('fbProvName').textContent=provName||'your provider';
  document.getElementById('fbComment').value='';
  document.getElementById('fbAlert').className='alert hidden';
  document.querySelectorAll('.fb-star').forEach(s=>s.classList.remove('lit'));
  document.getElementById('fbRatingLabel').textContent='';
  document.getElementById('fbModal').classList.add('open');
}

async function submitFeedback(){
  if(!fbRating){
    const a=document.getElementById('fbAlert');
    a.className='alert err';a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> Please select a rating.';a.classList.remove('hidden');return;
  }
  const btn=document.getElementById('fbSubmitBtn');
  btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Submitting…';
  const apptId=document.getElementById('fbApptId').value;
  const comment=document.getElementById('fbComment').value.trim();
  try {
    const r=await fetch('/api/patient/submit-feedback.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({appointment_id:parseInt(apptId),rating:fbRating,comment,csrf_token:document.getElementById('csrfToken')?.value||''}),credentials:'same-origin'}).then(r=>r.json());
    if(r.success){
      closeModal('fbModal');
      toast('Thank you for your feedback!','ok');
    } else {
      const a=document.getElementById('fbAlert');
      a.className='alert err';a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> '+(r.message||'Could not submit. Try again.');a.classList.remove('hidden');
    }
  } catch(e){
    const a=document.getElementById('fbAlert');
    a.className='alert err';a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> Network error.';a.classList.remove('hidden');
  } finally {
    btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-paper-plane"></i> Submit Feedback';
  }
}
</script>
<script>
/*  Lang init  */
document.addEventListener('DOMContentLoaded', () => {
  // Safety: reset body overflow on page load (in case navigation happened while sidebar open)
  document.body.style.overflow = '';
  document.body.style.position = '';
  const mobOv = document.getElementById('mobOv');
  if (mobOv) { mobOv.classList.remove('show'); mobOv.style.display = 'none'; }
  if (typeof Lang !== 'undefined') Lang.init();
  document.getElementById('langToggle')?.addEventListener('click', () => Lang.toggle());
  // Sidebar items lang
  document.addEventListener('langchange', e => {
    const sw = e.detail.lang === 'sw';
    const h = new Date().getHours();
    const g = h<12?(sw?'Habari za asubuhi':'Good morning'):h<17?(sw?'Habari za mchana':'Good afternoon'):(sw?'Habari za jioni':'Good evening');
    const el = document.getElementById('dashGreeting');
    if (el) el.textContent = g;
  });
});

/*  Greeting  */
(function(){
  const h = new Date().getHours();
  const sw = document.documentElement.lang === 'sw';
  const g = h<12?(sw?'Habari za asubuhi':'Good morning'):h<17?(sw?'Habari za mchana':'Good afternoon'):(sw?'Habari za jioni':'Good evening');
  const el = document.getElementById('dashGreeting');
  if (el) el.textContent = g;
})();

/*  Toast  */


function toast(msg, type='info') {
  const el = document.getElementById('toast');
  el.textContent = msg; el.className = 'toast ' + type + ' show';
  setTimeout(() => el.classList.remove('show'), 3500);
}

/*  Sidebar mobile  */
function openSb() {
  const sb = document.getElementById('patSb');
  const ov = document.getElementById('mobOv');
  if (sb) { sb.classList.add('mob-open'); sb.setAttribute('aria-hidden','false'); }
  if (ov) { ov.style.display='block'; requestAnimationFrame(()=>ov.classList.add('show')); }
  document.body.style.overflow = 'hidden';
  document.body.style.position = 'relative';
}
function closeSb() {
  const sb = document.getElementById('patSb');
  const ov = document.getElementById('mobOv');
  if (sb) { sb.classList.remove('mob-open'); sb.setAttribute('aria-hidden','true'); }
  if (ov) { ov.classList.remove('show'); setTimeout(()=>{ if(!ov.classList.contains('show')) ov.style.display='none'; },300); }
  document.body.style.overflow = '';
  document.body.style.position = '';
  document.body.style.removeProperty('overflow');
  document.body.style.removeProperty('position');
}
// Bind hamburger and FAB
const _hamb = document.getElementById('patHamb');
const _fab = document.getElementById('mobFab');
if (_hamb) _hamb.addEventListener('click', openSb);
if (_fab) _fab.addEventListener('click', openSb);
// Close on overlay click
const _ov = document.getElementById('mobOv');
if (_ov) _ov.addEventListener('click', closeSb);
// Sidebar collapse (desktop)
let sbCollapsed = false;
document.getElementById('sbToggle')?.addEventListener('click', () => {
  sbCollapsed = !sbCollapsed;
  const sb = document.getElementById('patSb');
  const ico = document.getElementById('sbToggleIco');
  const main = document.getElementById('patMain');
  sb.classList.toggle('collapsed', sbCollapsed);
  if (ico) ico.style.transform = sbCollapsed ? 'rotate(180deg)' : '';
  if (main) main.style.marginLeft = sbCollapsed ? 'var(--sb-col)' : '';
});

// Close sidebar when nav item clicked on mobile
document.querySelectorAll('.pat-sb-item').forEach(el => {
  el.addEventListener('click', () => {
    if (window.innerWidth <= 1024) closeSb();
  });
});

/*  Modals  */
function openModal(id){const m=document.getElementById(id);if(m){m.classList.add('open');document.body.style.overflow='hidden';}}
function closeModal(id){const m=document.getElementById(id);if(m){m.classList.remove('open');document.body.style.overflow='';}}
function bookWithProvider(btn) {
  const prov = btn.closest('[data-prov]')?.dataset?.prov || '';
  const pid  = btn.closest('[data-pid]')?.dataset?.pid || null;
  openBkModal({id:pid?parseInt(pid):null, name:prov||'Provider', isHospital:false, location:'', serviceType:'doctor'});
}

/*  Appointment filter (URL)  */
function setAf(af) { location.href = '?tab=appointments&af=' + af; }

/*  Provider preview filter (overview tab)  */
function previewFilter(type, btn) {
  document.querySelectorAll('[data-fkey]').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#provPreviewGrid .prov-card').forEach(c => {
    c.style.display = (type==='all' || c.dataset.ptype===type) ? '' : 'none';
  });
}

/*  Nearby tab filtering + search  */
let _provData = <?php echo json_encode(array_values($nearby)); ?>;
let _currFilter = 'all';

function filterNearby(type, btn) {
  _currFilter = type;
  document.querySelectorAll('[data-ftype]').forEach(b => b.classList.remove('active'));
  btn?.classList.add('active');
  applyNearbyFilters();
}

function liveSearch(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#nearbyGrid .prov-card').forEach(c => {
    const name = c.dataset.name || '';
    const spec = c.dataset.spec || '';
    const typeOk = _currFilter === 'all' || c.dataset.ptype === _currFilter;
    const qOk = !q || name.includes(q) || spec.includes(q);
    c.style.display = (typeOk && qOk) ? '' : 'none';
  });
}

function applyNearbyFilters() {
  const q = (document.getElementById('provSearch')?.value || '').toLowerCase().trim();
  document.querySelectorAll('#nearbyGrid .prov-card').forEach(c => {
    const typeOk = _currFilter === 'all' || c.dataset.ptype === _currFilter;
    const qOk = !q || (c.dataset.name||'').includes(q) || (c.dataset.spec||'').includes(q);
    c.style.display = (typeOk && qOk) ? '' : 'none';
  });
}

function bookProvider(id, rating, name, isHospital, city, fee) {
  openBkModal({id:id, name:name, isHospital:!!isHospital, location:city||'', city:city||'', fee:fee||null, serviceType:isHospital?'hospital':'doctor'});
}

/*  Geolocation  */
function initNearby() {
  if (!navigator.geolocation) { toast('Geolocation not supported. Showing all providers.','info'); return; }
  document.getElementById('nearbyLoading').style.display = 'block';
  document.getElementById('nearbySubtitle').textContent = 'Detecting your location…';
  navigator.geolocation.getCurrentPosition(
    pos => gotLocation(pos.coords.latitude, pos.coords.longitude),
    err => {
      document.getElementById('nearbyLoading').style.display = 'none';
      if (err.code === 1) document.getElementById('locDenied').style.display = 'block';
      document.getElementById('nearbySubtitle').textContent = 'Showing all available providers.';
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
  );
}

function gotLocation(lat, lng) {
  document.getElementById('nearbyLoading').style.display = 'none';
  fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`)
    .then(r => r.json())
    .then(d => {
      const city = d.address?.suburb || d.address?.city_district || d.address?.city || 'Your Location';
      document.getElementById('locText').textContent = city;
      document.getElementById('nearbySubtitle').textContent = 'Showing providers near ' + city;
    }).catch(() => {});

  // Update distance labels using lat/lng from data attributes
  document.querySelectorAll('#nearbyGrid .prov-card').forEach(card => {
    const cardLat = parseFloat(card.dataset.lat || '0');
    const cardLng = parseFloat(card.dataset.lng || '0');
    // Find dist element by looking for dist-* id inside this card
    const distEl = card.querySelector('[id^="dist-"]');
    if (!distEl) return;
    if (cardLat && cardLng) {
      const d = haversineKm(lat, lng, cardLat, cardLng);
      distEl.innerHTML = '<i class="fa-solid fa-location-dot"></i> ' + d.toFixed(1) + ' km';
    }
  });
  // Sort cards by distance when location is known
  const grid = document.getElementById('nearbyGrid');
  if (grid) {
    const cards = [...grid.querySelectorAll('.prov-card')];
    cards.sort((a, b) => {
      const aLat = parseFloat(a.dataset.lat||'0'), aLng = parseFloat(a.dataset.lng||'0');
      const bLat = parseFloat(b.dataset.lat||'0'), bLng = parseFloat(b.dataset.lng||'0');
      const aDist = (aLat && aLng) ? haversineKm(lat, lng, aLat, aLng) : 999;
      const bDist = (bLat && bLng) ? haversineKm(lat, lng, bLat, bLng) : 999;
      return aDist - bDist;
    });
    cards.forEach(c => grid.appendChild(c));
  }
}

function haversineKm(lat1,lon1,lat2,lon2) {
  const R=6371,dLat=(lat2-lat1)*Math.PI/180,dLon=(lon2-lon1)*Math.PI/180;
  const a=Math.sin(dLat/2)**2+Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
  return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
}

// Auto-init if permission already granted
if (navigator.permissions) {
  navigator.permissions.query({name:'geolocation'}).then(r => { if (r.state==='granted') initNearby(); }).catch(()=>{});
}

/*  Booking  */
async function uploadAvatar(input) {
  const file = input.files[0];
  if (!file) return;
  const msg = document.getElementById('avatarMsg');
  if (msg) { msg.style.display = 'block'; msg.style.color = '#94a3b8'; msg.textContent = 'Uploading…'; }
  const fd = new FormData();
  fd.append('avatar', file);
  try {
    const resp = await fetch('/api/patient/upload-avatar.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });
    const rawText = await resp.text();
    let r;
    try { r = JSON.parse(rawText); }
    catch(parseErr) {
      console.error('Server returned non-JSON:', rawText.substring(0, 300));
      throw new Error('Server configuration error. Please contact support or check PHP error logs.');
    }
    if (!resp.ok) throw new Error(r.message || 'HTTP ' + resp.status);
    if (r.success) {
      if (msg) { msg.style.color = '#16a34a'; msg.textContent = '' + r.message; }
      const url = (r.url || '') + '?t=' + Date.now();
      const imgHtml = '<img src="'+url+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">';
      document.querySelectorAll('#avatarPreview').forEach(el => {
        el.innerHTML = '<img src="'+url+'" style="width:100%;height:100%;object-fit:cover;display:block">';
      });
      document.querySelectorAll('.pat-hdr-avatar, .pat-sb-av').forEach(el => {
        el.innerHTML = imgHtml;
      });
    } else {
      if (msg) { msg.style.color = '#dc2626'; msg.textContent = 'Error: ' + (r.message || 'Upload failed. Check file size/type.'); }
    }
  } catch(e) {
    console.error('Avatar upload error:', e);
    if (msg) {
      msg.style.color = '#dc2626';
      if (e.message && e.message.includes('JSON')) {
        msg.textContent = 'Server error. Check PHP error logs or ensure storage/uploads/avatars/ is writable.';
      } else {
        msg.textContent = 'Error: ' + (e.message || 'Upload failed. Try a smaller image under 10 MB.');
      }
    }
  }
  input.value = '';
}



/*  Patient: Message Provider  */
function openPatMsg(provId, provName) {
  document.getElementById('patMsgProvId').value = provId;
  document.getElementById('patMsgProvName').textContent = provName;
  document.getElementById('patMsgBody').value = '';
  document.getElementById('patMsgSubject').value = '';
  document.getElementById('patMsgAlert').className = 'alert hidden';
  openModal('patMsgModal');
}
async function submitPatMsg() {
  const provId  = document.getElementById('patMsgProvId').value;
  const subject = document.getElementById('patMsgSubject').value.trim()||'Message from Patient';
  const message = document.getElementById('patMsgBody').value.trim();
  const sendSms = document.getElementById('patMsgSms').checked;
  const btn     = document.getElementById('patMsgSendBtn');
  const alert   = document.getElementById('patMsgAlert');
  if (!message) { alert.className='alert err'; alert.textContent='Please type a message.'; alert.classList.remove('hidden'); return; }
  btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Sending…';
  try {
    const resp = await fetch('/api/patient/send-message.php',{
      method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body:JSON.stringify({provider_id:parseInt(provId),subject,message,send_sms:sendSms,csrf_token:document.getElementById('csrfToken')?.value||''}),
      credentials:'same-origin'
    });
    const raw=await resp.text(); let r; try{r=JSON.parse(raw);}catch(e){throw new Error('Server error');}
    if(r.success){closeModal('patMsgModal');toast(r.message,'ok');}
    else{alert.className='alert err';alert.textContent=r.message||'Send failed.';alert.classList.remove('hidden');}
  }catch(e){alert.className='alert err';alert.textContent='Error: '+e.message;alert.classList.remove('hidden');}
  finally{btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-paper-plane" style="font-size:.75em"></i> Send Message';}
}

/*  Cancel / Reschedule appointments  */
function openCancel(apptId, provName) {
  document.getElementById('cancelApptId').value = apptId;
  document.getElementById('cancelProvName').textContent = provName;
  document.getElementById('cancelReason').value = '';
  document.getElementById('cancelAlert').className = 'alert hidden';
  openModal('cancelModal');
}
function openReschedule(apptId, currentDatetime) {
  document.getElementById('reschedApptId').value = apptId;
  if (currentDatetime) {
    const dt = new Date(currentDatetime);
    const pad = n => String(n).padStart(2,'0');
    document.getElementById('reschedDate').value = dt.getFullYear()+'-'+pad(dt.getMonth()+1)+'-'+pad(dt.getDate());
    document.getElementById('reschedTime').value = pad(dt.getHours())+':'+pad(dt.getMinutes());
  }
  document.getElementById('reschedAlert').className = 'alert hidden';
  openModal('reschedModal');
}
async function submitCancel() {
  const apptId = document.getElementById('cancelApptId').value;
  const reason = document.getElementById('cancelReason').value.trim();
  const btn    = document.getElementById('cancelConfirmBtn');
  const alert  = document.getElementById('cancelAlert');
  btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Cancelling…';
  try {
    const resp = await fetch('/api/patient/manage-appointment.php',{
      method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body:JSON.stringify({action:'cancel',appointment_id:parseInt(apptId),reason,csrf_token:document.getElementById('csrfToken')?.value||''}),
      credentials:'same-origin'
    });
    const raw = await resp.text(); let r; try{r=JSON.parse(raw);}catch(e){throw new Error('Server error');}
    if(r.success){
      closeModal('cancelModal');
      toast(r.message||'Appointment cancelled. Email and SMS sent.','ok');
      setTimeout(()=>location.href='?tab=appointments',1500);
    } else {
      alert.className='alert err'; alert.textContent=r.message||'Cancellation failed.'; alert.classList.remove('hidden');
    }
  } catch(e) {
    alert.className='alert err'; alert.textContent='Network error: '+e.message; alert.classList.remove('hidden');
  } finally {
    btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-xmark" style="font-size:.75em"></i> Yes, Cancel Appointment';
  }
}
async function submitReschedule() {
  const apptId = document.getElementById('reschedApptId').value;
  const date   = document.getElementById('reschedDate').value;
  const time   = document.getElementById('reschedTime').value||'09:00';
  const reason = document.getElementById('reschedReason').value.trim();
  const btn    = document.getElementById('reschedConfirmBtn');
  const alert  = document.getElementById('reschedAlert');
  if(!date){alert.className='alert err';alert.textContent='Please select a date.';alert.classList.remove('hidden');return;}
  btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Rescheduling…';
  try {
    const resp = await fetch('/api/patient/manage-appointment.php',{
      method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body:JSON.stringify({action:'reschedule',appointment_id:parseInt(apptId),new_date:date,new_time:time,reason,csrf_token:document.getElementById('csrfToken')?.value||''}),
      credentials:'same-origin'
    });
    const raw = await resp.text(); let r; try{r=JSON.parse(raw);}catch(e){throw new Error('Server error');}
    if(r.success){
      closeModal('reschedModal');
      toast(r.message||'Appointment rescheduled. Confirmation sent.','ok');
      setTimeout(()=>location.href='?tab=appointments',1500);
    } else {
      alert.className='alert err'; alert.textContent=r.message||'Reschedule failed.'; alert.classList.remove('hidden');
    }
  } catch(e) {
    alert.className='alert err'; alert.textContent='Error: '+e.message; alert.classList.remove('hidden');
  } finally {
    btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-calendar-check" style="font-size:.75em"></i> Confirm Reschedule';
  }
}

async function submitBooking() {
  const date = document.getElementById('bookDate')?.value;
  const time = document.getElementById('bookTime')?.value || '09:00';
  const title = document.getElementById('bookTitle')?.value?.trim();
  const alertEl = document.getElementById('bookAlert');
  if (!date) { alertEl.className='alert err'; alertEl.textContent='Please select a date.'; alertEl.classList.remove('hidden'); return; }
  if (!title) { alertEl.className='alert err'; alertEl.textContent='Please enter a reason for the visit.'; alertEl.classList.remove('hidden'); return; }
  const btn = document.getElementById('bookBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Booking…';
  try {
    const r = await fetch('/api/patient/book-appointment.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body: JSON.stringify({
        service_type: document.getElementById('bookServiceType')?.value || 'doctor',
        appointment_at: date + ' ' + time + ':00',
        title, notes: document.getElementById('bookNotes')?.value || '',
        location_type: document.getElementById('bookServiceType')?.value === 'telehealth' ? 'telehealth' : 'in_person',
        insurance_doc_id: document.getElementById('bookInsDoc')?.value || null,
        csrf_token: document.getElementById('csrfToken')?.value || ''
      }),
      credentials: 'same-origin'
    }).then(r => r.json());
    if (r.success) {
      toast('Appointment booked! Check your email for confirmation.','ok');
      setTimeout(() => location.href='?tab=appointments', 1200);
    } else {
      alertEl.className='alert err'; alertEl.textContent=r.message||'Booking failed. Please try again.'; alertEl.classList.remove('hidden');
    }
  } catch(e) {
    alertEl.className='alert err'; alertEl.textContent='Network error. Please try again.'; alertEl.classList.remove('hidden');
  } finally {
    btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-calendar-check"></i> Confirm Booking';
  }
}

/*  Insurance save (policy number only)  */
async function saveInsurance() {
  const btn = document.getElementById('insUploadBtn');
  const alertEl = document.getElementById('insModalAlert');
  const selProv = document.getElementById('insProviderName');
  const provName = selProv?.value === 'Other' ? document.getElementById('insProviderOther')?.value?.trim() : selProv?.value;
  const policyNum = document.getElementById('insPolicyNumber')?.value?.trim();
  if (!provName) { alertEl.className='alert err'; alertEl.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> Please select an insurance provider.'; alertEl.classList.remove('hidden'); alertEl.style.display='flex'; return; }
  if (!policyNum) { alertEl.className='alert err'; alertEl.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> Policy number is required.'; alertEl.classList.remove('hidden'); alertEl.style.display='flex'; return; }
  btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Saving…';
  alertEl.style.display='none';
  try {
    const r = await fetch('/api/patient/save-insurance.php', {
      method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body:JSON.stringify({csrf_token:document.getElementById('csrfToken').value,provider_name:provName,policy_number:policyNum,coverage_type:document.getElementById('insCoverageType')?.value||'',expiry_date:document.getElementById('insExpiryDate')?.value||''}),
      credentials:'same-origin'
    }).then(r=>r.json());
    if (r.success) { toast('Insurance details saved!','ok'); closeModal('insModal'); setTimeout(()=>location.reload(),1000); }
    else { alertEl.className='alert err'; alertEl.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> '+(r.message||'Could not save.'); alertEl.classList.remove('hidden'); alertEl.style.display='flex'; }
  } catch(e) { alertEl.className='alert err'; alertEl.innerHTML='<i class="fa-solid fa-wifi"></i> Network error.'; alertEl.classList.remove('hidden'); alertEl.style.display='flex'; }
  finally { btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-shield-heart"></i> Save Insurance Details'; }
}
// Other provider toggle
setTimeout(()=>{document.getElementById('insProviderName')?.addEventListener('change',function(){const r=document.getElementById('insProviderOtherRow');if(r)r.style.display=this.value==='Other'?'block':'none';});},500);

function delIns(id, btn) {
  if (!confirm('Delete this insurance document?')) return;
  btn.disabled = true;
  fetch('/api/patient/delete-insurance.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({doc_id:id,csrf_token:document.getElementById('csrfToken')?.value||''}),credentials:'same-origin'})
    .then(r=>r.json()).then(r=>{ if(r.success){toast('Document deleted','ok');setTimeout(()=>location.reload(),800);}else toast(r.message||'Error','err'); })
    .catch(()=>toast('Network error','err'));
}

/*  Notifications  */
async function markRead(id) {
  document.getElementById('ni-'+id)?.classList.remove('unread');
  await fetch('/api/patient/mark-notification.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({notif_id:id,csrf_token:document.getElementById('csrfToken')?.value||''}),credentials:'same-origin'});
}
async function markAllRead() {
  document.querySelectorAll('.notif-item').forEach(el=>el.classList.remove('unread'));
  document.querySelectorAll('.notif-dot').forEach(el=>el.remove());
  await fetch('/api/patient/mark-notification.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({mark_all:true,csrf_token:document.getElementById('csrfToken')?.value||''}),credentials:'same-origin'});
  toast('All notifications marked as read','ok');
}

/*  Settings  */
async function saveProfile() {
  const r = await fetch('/api/patient/update-profile.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({first_name:document.getElementById('sfname')?.value,last_name:document.getElementById('slname')?.value,phone:document.getElementById('sphone')?.value,date_of_birth:document.getElementById('sdob')?.value,csrf_token:document.getElementById('csrfToken')?.value||''}),credentials:'same-origin'}).then(r=>r.json()).catch(()=>({success:false}));
  toast(r.success?'Profile saved!':'Could not save profile.',r.success?'ok':'err');
}
async function changePwd() {
  const nw=document.getElementById('pw_new')?.value, cnf=document.getElementById('pw_cnf')?.value;
  if(nw!==cnf){toast('New passwords do not match','err');return;}
  if(nw.length<8){toast('Password must be at least 8 characters','err');return;}
  const r=await fetch('/api/patient/change-password.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({current_password:document.getElementById('pw_cur')?.value,new_password:nw,csrf_token:document.getElementById('csrfToken')?.value||''}),credentials:'same-origin'}).then(r=>r.json()).catch(()=>({success:false}));
  if(r.success){toast('Password updated!','ok');['pw_cur','pw_new','pw_cnf'].forEach(id=>document.getElementById(id).value='');}
  else toast(r.message||'Could not update password','err');
}

/*  Emergency SOS  */
function triggerSOS() {
  const btn = document.getElementById('sosBtn');
  if (!confirm('This will dispatch an ambulance to your GPS location. Only press OK in a real emergency.')) return;
  btn.style.background='#dc2626'; btn.style.color='#fff';
  btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin" style="font-size:24px"></i><span style="font-size:.5625rem">SENDING…</span>';
  if (!navigator.geolocation) { alert('Geolocation not supported. Please call 999 directly.'); resetSOS(); return; }
  navigator.geolocation.getCurrentPosition(pos => {
    const {latitude:lat,longitude:lng} = pos.coords;
    fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`)
      .then(r=>r.json())
      .then(d => {
        const addr = d.display_name || `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
        return fetch('/api/patient/request-emergency.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({latitude:lat,longitude:lng,address_text:addr,emergency_type:'ambulance',csrf_token:document.getElementById('csrfToken')?.value||''}),credentials:'same-origin'});
      })
      .then(r=>r.json())
      .then(r=>{
        if(r.success){btn.style.background='#16a34a';btn.innerHTML='<i class="fa-solid fa-check" style="font-size:24px"></i><span style="font-size:.5625rem">SENT</span>';setTimeout(()=>{resetSOS();location.reload();},3000);}
        else{alert(r.message||'Request failed. Please call 999 directly.');resetSOS();}
      }).catch(()=>{alert('Could not send. Please call 999 or 112 directly.');resetSOS();});
  },()=>{alert('Could not get location. Please call 999 directly.');resetSOS();},{enableHighAccuracy:true,timeout:8000});
}
function resetSOS(){const btn=document.getElementById('sosBtn');if(btn){btn.style.background='#fff';btn.style.color='#dc2626';btn.innerHTML='<i class="fa-solid fa-truck-medical" style="font-size:26px"></i><span style="font-size:.625rem;font-weight:800">SOS</span>';}}
</script>

<!-- Delete Account Modal -->
<div id="deleteAccModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:900;align-items:center;justify-content:center;padding:16px">
<div style="background:#fff;border-radius:18px;padding:28px;width:100%;max-width:440px;box-shadow:0 24px 60px rgba(0,0,0,.2)">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
    <div style="width:44px;height:44px;border-radius:12px;background:#fef2f2;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="fa-solid fa-triangle-exclamation" style="color:#dc2626;font-size:18px"></i>
    </div>
    <div>
      <h3 style="font-size:1rem;font-weight:800;color:#0f172a">Delete Account</h3>
      <p style="font-size:.8125rem;color:#64748b;margin-top:1px">This action cannot be undone</p>
    </div>
  </div>
  <p style="font-size:.875rem;color:#475569;margin-bottom:16px;line-height:1.6">All your appointments, data and medical records will be permanently deleted. Enter your password to confirm.</p>
  <div id="delAccErr" style="display:none;background:#fef2f2;border:1.5px solid #fca5a5;border-radius:9px;padding:10px 13px;font-size:.8125rem;color:#991b1b;margin-bottom:13px"></div>
  <div style="margin-bottom:14px">
    <label style="display:block;font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#374151;margin-bottom:5px">Your Password</label>
    <input type="password" id="delAccPass" placeholder="Enter your password" style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.875rem;outline:none;transition:border .2s" onfocus="this.style.borderColor='#dc2626'" onblur="this.style.borderColor='#e2e8f0'">
  </div>
  <div style="display:flex;gap:10px">
    <button onclick="document.getElementById('deleteAccModal').style.display='none'" style="flex:1;padding:11px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:9px;font-family:inherit;font-size:.875rem;font-weight:600;cursor:pointer">Cancel</button>
    <button id="delAccBtn" onclick="confirmDeleteAccount()" style="flex:1;padding:11px;background:#dc2626;color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.875rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px"><i class="fa-solid fa-trash"></i> Delete Forever</button>
  </div>
</div>
</div>

<script>
async function confirmDeleteAccount(){
  const pass = document.getElementById('delAccPass').value;
  const errEl = document.getElementById('delAccErr');
  const btn = document.getElementById('delAccBtn');
  errEl.style.display='none';
  if(!pass){errEl.textContent='Please enter your password.';errEl.style.display='block';return;}
  btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Deleting…';
  try{
    const csrf=document.getElementById('_csrf_tok')?.value||'<?=Security::csrfToken()?>';
    const r=await fetch('/api/auth/delete-account.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({csrf_token:csrf,password:pass})});
    const j=await r.json();
    if(j.success){window.location.href=j.redirect||'/';}
    else{errEl.textContent=j.message||'Error deleting account.';errEl.style.display='block';btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-trash"></i> Delete Forever';}
  }catch(e){errEl.textContent='Network error. Please try again.';errEl.style.display='block';btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-trash"></i> Delete Forever';}
}
function openDeleteAccModal(){
  document.getElementById('deleteAccModal').style.display='flex';
  document.getElementById('delAccPass').value='';
  document.getElementById('delAccErr').style.display='none';
}
</script>

</body>
</html>
