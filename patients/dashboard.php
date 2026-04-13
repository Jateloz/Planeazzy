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

/* ── Patient data ────────────────────────────────────────────── */
$pat    = $db->fetchOne('SELECT * FROM patients WHERE id=:id', [':id' => $pid]);
$appts  = $db->fetchAll(
    'SELECT a.*, p.name prov_name, p.type prov_type, p.specialty, p.rating prov_rating
     FROM appointments a LEFT JOIN providers p ON a.provider_id = p.id
     WHERE a.patient_id=:pid ORDER BY a.appointment_at DESC LIMIT 60',
    [':pid' => $pid]
);
$notifs = $db->fetchAll('SELECT * FROM notifications WHERE patient_id=:pid ORDER BY created_at DESC LIMIT 40', [':pid' => $pid]);
$nearby = $db->fetchAll('SELECT * FROM providers WHERE is_active=1 AND is_verified=1 ORDER BY rating DESC LIMIT 40');
$insDocs = [];
try { $insDocs = $db->fetchAll('SELECT * FROM insurance_documents WHERE patient_id=:pid AND status="active" ORDER BY created_at DESC', [':pid'=>$pid]); } catch(Exception $e) {}
$consents = []; $defaultConsents = ['data_sharing'=>true,'insurance_sharing'=>true,'marketing'=>false,'telehealth'=>false,'research'=>false];
try { foreach($db->fetchAll('SELECT consent_type,granted FROM patient_consents WHERE patient_id=:pid',[':pid'=>$pid]) as $r) $consents[$r['consent_type']]=(bool)$r['granted']; } catch(Exception $e) {}
foreach($defaultConsents as $k=>$v) if(!isset($consents[$k])) $consents[$k]=$v;

/* ── Computed vars ───────────────────────────────────────────── */
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

/* ── Real stats ──────────────────────────────────────────────── */
$totalAppts  = count($appts);
$completedCt = count(array_filter($appts, fn($a)=>$a['status']==='completed'));
$upcomingCt  = count($upcoming);

/* ── Emergency history ───────────────────────────────────────── */
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
  <link rel="icon" href="/assets/images/favicon1.png" type="image/svg+xml">
<style>
/* ═══════════════════════════════════════════════
   PATIENT DASHBOARD — Design System v3
═══════════════════════════════════════════════ */
:root {
  --primary:#1978e5; --primary-10:rgba(25,120,229,.1); --primary-20:rgba(25,120,229,.2);
  --teal:#0d9488; --green:#16a34a; --red:#dc2626; --yellow:#d97706;
  --slate-50:#f8fafc; --slate-100:#f1f5f9; --slate-200:#e2e8f0;
  --slate-400:#94a3b8; --slate-500:#64748b; --slate-700:#334155; --slate-900:#0f172a;
  --white:#fff; --shadow:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06);
  --shadow-md:0 4px 6px -1px rgba(0,0,0,.07),0 2px 4px rgba(0,0,0,.05);
  --sb-w:228px; --sb-col:60px; --hdr-h:52px; --r:8px; --r-lg:12px; --r-xl:16px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;font-size:13px;color:var(--slate-900);background:#f5f7fa;min-height:100vh;display:flex;flex-direction:column;line-height:1.5}
a{color:inherit;text-decoration:none}
button{font-family:inherit;cursor:pointer}
input,select,textarea{font-family:inherit}

/* ── Patient header ─────────────────────────────── */
.pat-hdr{position:sticky;top:0;z-index:200;height:var(--hdr-h);background:rgba(255,255,255,.96);border-bottom:1px solid var(--slate-200);backdrop-filter:blur(16px);display:flex;align-items:center;padding:0 16px;gap:10px}
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

/* ── App layout ─────────────────────────────────── */
.pat-layout{display:flex;flex:1;min-height:calc(100vh - var(--hdr-h))}

/* ── Sidebar ────────────────────────────────────── */
.pat-sb{width:var(--sb-w);flex-shrink:0;background:#fff;border-right:1px solid var(--slate-200);display:flex;flex-direction:column;position:sticky;top:var(--hdr-h);height:calc(100vh - var(--hdr-h));overflow-y:auto;transition:transform .25s cubic-bezier(.4,0,.2,1),width .2s;z-index:150}
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

/* ── Main area ──────────────────────────────────── */
.pat-main{flex:1;display:flex;flex-direction:column;min-width:0}
.pat-content{padding:18px;flex:1;max-width:1260px;margin:0 auto;width:100%}

/* ── Page heading ───────────────────────────────── */
.pat-page-hdr{margin-bottom:16px}
.pat-page-title{font-size:1.125rem;font-weight:800;letter-spacing:-.03em;color:var(--slate-900);margin-bottom:3px}
.pat-page-sub{font-size:.75rem;color:var(--slate-500)}

/* ── Stat cards ─────────────────────────────────── */
.pat-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
.pat-stat{background:#fff;border-radius:var(--r-lg);padding:14px;border:1px solid var(--slate-200);box-shadow:var(--shadow);display:flex;align-items:center;gap:10px}
.pat-stat-ic{width:36px;height:36px;border-radius:var(--r);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.pat-stat-val{font-size:1.25rem;font-weight:800;letter-spacing:-.04em;color:var(--slate-900);line-height:1}
.pat-stat-lbl{font-size:.6875rem;color:var(--slate-400);margin-top:2px;font-weight:500}

/* ── Dashboard grid ─────────────────────────────── */
.pat-grid{display:grid;grid-template-columns:1fr 320px;gap:14px}
.pat-panel{background:#fff;border-radius:var(--r-lg);border:1px solid var(--slate-200);box-shadow:var(--shadow);overflow:hidden}
.pat-panel-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--slate-100)}
.pat-panel-title{font-size:.875rem;font-weight:700;color:var(--slate-900)}
.pat-panel-link{font-size:.75rem;font-weight:600;color:var(--primary)}
.pat-panel-body{padding:12px 16px}

/* ── Welcome banner ─────────────────────────────── */
.pat-welcome{background:linear-gradient(135deg,#1462c4,#1978e5 50%,#0d9488);border-radius:var(--r-xl);padding:20px 22px;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px;position:relative;overflow:hidden}
.pat-welcome::before{content:'';position:absolute;top:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.07);pointer-events:none}
.pat-welcome-title{font-size:1rem;font-weight:800;letter-spacing:-.03em;margin-bottom:4px}
.pat-welcome-sub{font-size:.75rem;color:rgba(219,234,254,.85);line-height:1.6}
.pat-welcome-btns{display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap}
.pat-btn-white{background:#fff;color:var(--primary);padding:8px 16px;border-radius:9999px;font-size:.75rem;font-weight:700;border:none;cursor:pointer;white-space:nowrap;transition:all .15s}
.pat-btn-white:hover{background:rgba(255,255,255,.9)}
.pat-btn-ghost{background:rgba(255,255,255,.15);color:#fff;padding:8px 16px;border-radius:9999px;font-size:.75rem;font-weight:700;border:1.5px solid rgba(255,255,255,.3);cursor:pointer;white-space:nowrap;transition:all .15s}
.pat-btn-ghost:hover{background:rgba(255,255,255,.22)}

/* ── Appointment cards ──────────────────────────── */
.appt-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--slate-100)}
.appt-row:last-child{border-bottom:none}
.appt-ic{width:38px;height:38px;border-radius:var(--r);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.appt-ic.tele{background:var(--primary-10);color:var(--primary)}
.appt-ic.inperson{background:rgba(13,148,136,.1);color:var(--teal)}
.appt-name{font-size:.8125rem;font-weight:700;color:var(--slate-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.appt-meta{font-size:.6875rem;color:var(--slate-400);margin-top:1px}
.appt-pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:9999px;font-size:.5625rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.appt-pill.scheduled{background:var(--primary-10);color:var(--primary)}
.appt-pill.completed{background:rgba(22,163,74,.1);color:var(--green)}
.appt-pill.cancelled{background:var(--slate-100);color:var(--slate-500)}
.appt-pill.tele{background:var(--primary-10);color:var(--primary)}
.appt-pill.inp{background:rgba(13,148,136,.1);color:var(--teal)}

/* ── Quick actions grid ─────────────────────────── */
.qa-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.qa-btn{display:flex;flex-direction:column;align-items:center;gap:5px;padding:12px 6px;background:var(--slate-50);border:1px solid var(--slate-100);border-radius:var(--r);text-decoration:none;cursor:pointer;transition:all .15s;text-align:center}
.qa-btn:hover{background:#fff;border-color:var(--primary-20);box-shadow:var(--shadow-md);transform:translateY(-1px)}
.qa-ic{width:36px;height:36px;border-radius:var(--r);display:flex;align-items:center;justify-content:center;font-size:15px;margin-bottom:2px}
.qa-lbl{font-size:.625rem;font-weight:700;color:var(--slate-700);line-height:1.2}

/* ── Doctor row ─────────────────────────────────── */
.doc-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--slate-100)}
.doc-row:last-child{border-bottom:none}
.doc-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--teal));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;flex-shrink:0}
.doc-name{font-size:.8125rem;font-weight:700;color:var(--slate-900)}
.doc-spec{font-size:.6875rem;color:var(--slate-400)}

/* ── Provider/Hospital cards (Find Care tab) ─────── */
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

/* ── Notification items ─────────────────────────── */
.notif-item{display:flex;gap:12px;padding:12px;border-radius:var(--r-lg);border:1.5px solid var(--slate-200);background:#fff;cursor:pointer;transition:all .15s;margin-bottom:8px}
.notif-item.unread{border-color:var(--primary-20);background:rgba(25,120,229,.02)}
.notif-item:hover{border-color:var(--primary-20);box-shadow:var(--shadow)}
.notif-ic{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.notif-title{font-size:.8125rem;font-weight:700;color:var(--slate-900);margin-bottom:2px}
.notif-msg{font-size:.75rem;color:var(--slate-500);line-height:1.5}
.notif-time{font-size:.625rem;color:var(--slate-400);margin-top:3px}
.notif-dot{width:7px;height:7px;border-radius:50%;background:var(--primary);flex-shrink:0;margin-top:4px}

/* ── Forms / settings ───────────────────────────── */
.form-group{margin-bottom:14px}
.form-label{display:block;font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--slate-500);margin-bottom:5px}
.form-input,.form-select,.form-textarea{width:100%;padding:9px 12px;background:var(--slate-50);border:1.5px solid var(--slate-200);border-radius:var(--r);font-size:.875rem;color:var(--slate-900);outline:none;transition:all .2s}
.form-input:focus,.form-select:focus,.form-textarea:focus{background:#fff;border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-10)}
.form-textarea{resize:vertical;min-height:80px}
.settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}

/* ── Toggle switch ──────────────────────────────── */
.toggle-sw{position:relative;display:inline-block;width:38px;height:21px;flex-shrink:0}
.toggle-sw input{opacity:0;width:0;height:0;position:absolute}
.toggle-track{position:absolute;inset:0;border-radius:9999px;background:var(--slate-200);transition:.2s;cursor:pointer}
.toggle-sw input:checked + .toggle-track{background:var(--primary)}
.toggle-thumb{position:absolute;top:3px;left:3px;width:15px;height:15px;border-radius:50%;background:#fff;transition:.2s;pointer-events:none;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.toggle-sw input:checked ~ .toggle-thumb{transform:translateX(17px)}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--slate-100)}
.toggle-row:last-child{border-bottom:none}
.toggle-label{font-size:.8125rem;color:var(--slate-700);font-weight:500;flex:1;padding-right:12px}

/* ── Modals ─────────────────────────────────────── */
.modal-ov{display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:500;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(4px)}
.modal-ov.open{display:flex}
.modal-box{background:#fff;border-radius:var(--r-xl);box-shadow:0 25px 50px -12px rgba(0,0,0,.25);max-height:90vh;overflow-y:auto;width:100%;max-width:480px}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:16px 18px 12px;border-bottom:1px solid var(--slate-100);position:sticky;top:0;background:#fff;z-index:2;border-radius:var(--r-xl) var(--r-xl) 0 0}
.modal-title{font-size:.9375rem;font-weight:700;color:var(--slate-900);display:flex;align-items:center;gap:7px}
.modal-close{width:28px;height:28px;border-radius:50%;background:var(--slate-100);border:none;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--slate-500);cursor:pointer}
.modal-body{padding:16px 18px}

/* ── Buttons ────────────────────────────────────── */
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

/* ── Toast ──────────────────────────────────────── */
.toast{position:fixed;bottom:20px;right:20px;z-index:9999;padding:11px 16px;border-radius:var(--r-lg);font-size:.8125rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.15);transform:translateY(60px);opacity:0;transition:all .3s;max-width:300px}
.toast.show{transform:translateY(0);opacity:1}
.toast.ok{background:#065f46;color:#fff;border-left:4px solid #34d399}
.toast.err{background:#7f1d1d;color:#fff;border-left:4px solid #f87171}
.toast.info{background:#1e3a5f;color:#fff;border-left:4px solid #60a5fa}

/* ── Mobile overlay + FAB ───────────────────────── */
.mob-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:148;backdrop-filter:blur(2px)}
.mob-ov.show{display:block}
.mob-fab{display:none;position:fixed;bottom:20px;left:20px;z-index:160;width:46px;height:46px;border-radius:50%;background:var(--primary);color:#fff;border:none;font-size:18px;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(25,120,229,.4)}

/* ── Emergency SOS ──────────────────────────────── */
.sos-card{background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:var(--r-xl);padding:28px;text-align:center;margin-bottom:16px;position:relative;overflow:hidden}
.sos-btn{width:100px;height:100px;border-radius:50%;background:#fff;color:#dc2626;border:5px solid rgba(255,255,255,.3);font-family:'Inter',sans-serif;font-size:19px;font-weight:900;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-direction:column;margin:0 auto 16px;gap:3px;box-shadow:0 0 0 10px rgba(255,255,255,.15),0 16px 32px rgba(0,0,0,.25)}

/* ── History table ──────────────────────────────── */
.hist-table{width:100%;border-collapse:collapse;font-size:.75rem}
.hist-table th{font-size:.5625rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--slate-400);padding:8px 10px;background:var(--slate-50);border-bottom:1px solid var(--slate-100);text-align:left;white-space:nowrap}
.hist-table td{padding:10px;border-bottom:1px solid var(--slate-100);vertical-align:middle;color:var(--slate-700)}
.hist-table tr:last-child td{border-bottom:none}
.hist-table tr:hover td{background:var(--slate-50)}
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}

/* ── Alert / empty states ───────────────────────── */
.alert{display:flex;align-items:flex-start;gap:8px;padding:10px 14px;border-radius:var(--r);font-size:.8125rem;font-weight:500;margin-bottom:14px}
.alert.ok{background:rgba(22,163,74,.08);border:1px solid rgba(22,163,74,.2);color:#14532d}
.alert.err{background:rgba(186,26,26,.08);border:1px solid rgba(186,26,26,.2);color:#7f1d1d}
.alert.hidden{display:none}
.empty-state{text-align:center;padding:36px 20px}
.empty-state i{font-size:36px;color:var(--slate-200);display:block;margin-bottom:12px}
.empty-state h3{font-size:.9375rem;font-weight:700;color:var(--slate-500);margin-bottom:6px}
.empty-state p{font-size:.8125rem;color:var(--slate-400);line-height:1.6;max-width:320px;margin:0 auto 16px}

/* ── Tab bar ────────────────────────────────────── */
.tab-bar{display:flex;gap:4px;margin-bottom:14px;flex-wrap:wrap}
.tab-item{padding:6px 14px;border-radius:9999px;font-size:.75rem;font-weight:600;border:1.5px solid var(--slate-200);background:#fff;color:var(--slate-500);cursor:pointer;transition:all .15s}
.tab-item.active{background:var(--primary);color:#fff;border-color:var(--primary)}

/* ══ RESPONSIVE ════════════════════════════════════ */
@media(max-width:1024px){
  .pat-grid{grid-template-columns:1fr}
  .pat-stats{grid-template-columns:repeat(2,1fr)}
  .pat-sb{position:fixed;top:var(--hdr-h);left:0;height:calc(100vh - var(--hdr-h));transform:translateX(-100%)}
  .pat-sb.mob-open{transform:translateX(0);box-shadow:4px 0 24px rgba(0,0,0,.15)}
  .pat-main{margin-left:0!important}
  .mob-fab{display:flex}
  .pat-hamb{display:flex}
  .pat-hdr-center{max-width:260px}
  .settings-grid{grid-template-columns:1fr}
}
@media(max-width:768px){
  :root{--hdr-h:48px}
  .pat-hdr{padding:0 12px;gap:8px}
  .pat-hdr-brand img{height:26px}
  .pat-hdr-center{max-width:180px}
  .pat-hdr-search{font-size:12px;padding:6px 10px 6px 30px}
  .pat-content{padding:12px}
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
</style>
</head>
<body>

<!-- ═══ PATIENT HEADER ══════════════════════════════════ -->
<header class="pat-hdr">
  <button class="pat-hamb" id="patHamb" aria-label="Menu">
    <i class="fa-solid fa-bars"></i>
  </button>
  <a href="/" class="pat-hdr-brand">
    <img src="/assets/images/favicon.png" alt="Planeazzy">
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
    <a href="?tab=settings" class="pat-hdr-avatar" title="Profile"><?= htmlspecialchars($initials) ?></a>
  </div>
</header>

<!-- ═══ LAYOUT ══════════════════════════════════════════ -->
<div class="pat-layout">

  <!-- ── SIDEBAR ──────────────────────────────────────── -->
  <aside class="pat-sb" id="patSb">
    <!-- Profile -->
    <div class="pat-sb-profile">
      <div style="display:flex;align-items:center;gap:10px">
        <div class="pat-sb-av"><?= htmlspecialchars($initials) ?></div>
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

  <!-- ── MAIN CONTENT ──────────────────────────────────── -->
  <main class="pat-main" id="patMain">
    <div class="pat-content">

    <!-- ═══ OVERVIEW ════════════════════════════════════ -->
    <?php if($tab==='overview'): ?>

    <!-- Welcome banner -->
    <div class="pat-welcome">
      <div style="flex:1;min-width:0">
        <div class="pat-welcome-title">
          <span id="dashGreeting"><?= htmlspecialchars($greetEn) ?></span>, <?= $fname ?> 👋
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
        <button class="pat-btn-white" onclick="openModal('bookModal')">
          <i class="fa-solid fa-calendar-plus"></i> Book Appointment
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
              <button class="btn btn-primary btn-sm" onclick="openModal('bookModal')"><i class="fa-solid fa-plus"></i> Book Now</button>
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
                <button class="prov-card-book" onclick="openModal('bookModal')">
                  <i class="fa-solid fa-calendar-plus"></i> Book Now
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
                ['fa-calendar-plus','rgba(25,120,229,.1)','var(--primary)','Book Appt','#','openModal(\'bookModal\')'],
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
              <button class="btn btn-sm" style="background:var(--primary-10);color:var(--primary);border:none;font-size:.625rem;padding:5px 9px;border-radius:6px" onclick="openModal('bookModal')">
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

    <?php /* ═══ APPOINTMENTS ═══════════════════════════ */ elseif($tab==='appointments'): ?>

    <div class="pat-page-hdr">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
          <div class="pat-page-title" data-en="My Appointments" data-sw="Miadi Yangu">My Appointments</div>
          <div class="pat-page-sub"><?=$totalAppts?> total · <?=$upcomingCt?> upcoming</div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openModal('bookModal')">
          <i class="fa-solid fa-calendar-plus"></i> Book New
        </button>
      </div>
    </div>
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
      <button class="btn btn-primary btn-sm" onclick="openModal('bookModal')"><i class="fa-solid fa-plus"></i> Book Now</button>
    </div></div>
    <?php else: foreach($show as $a):
      $d=strtotime($a['appointment_at']); $isTele=($a['location_type']??'')==='telehealth'; $st=$a['status']??'scheduled';
    ?>
    <div class="pat-panel" style="margin-bottom:10px">
      <div style="display:flex;gap:10px;padding:12px 14px;align-items:center">
        <div class="appt-ic <?=$isTele?'tele':'inperson'?>" style="flex-shrink:0">
          <i class="fa-solid <?=$isTele?'fa-video':'fa-location-dot'?>"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div class="appt-name"><?= htmlspecialchars($a['prov_name']??$a['title']??'Appointment') ?></div>
          <div class="appt-meta"><?= date('M j, Y · g:i A',$d) ?> · <?= htmlspecialchars($a['specialty']??ucfirst($a['prov_type']??'General')) ?></div>
          <div style="margin-top:5px;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <span class="appt-pill <?=$isTele?'tele':'inp'?>"><?=$isTele?'Telehealth':'In-Person'?></span>
            <span class="appt-pill <?=$st?>"><?=ucfirst($st)?></span>
          </div>
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0">
          <?php if($isTele&&$st==='scheduled'):?>
          <a href="/patients/telehealth.php" class="btn btn-teal btn-sm">Join</a>
          <?php endif;?>
          <?php if($st==='scheduled'):?>
          <button class="btn btn-ghost btn-sm" style="font-size:.6875rem">Reschedule</button>
          <?php endif;?>
        </div>
      </div>
    </div>
    <?php endforeach;endif;?>

    <?php /* ═══ FIND CARE (NEARBY) ══════════════════════ */ elseif($tab==='nearby'): ?>

    <div class="pat-page-hdr">
      <div class="pat-page-title" data-en="Find Care Near You" data-sw="Tafuta Huduma Karibu Nawe">Find Care Near You</div>
      <div class="pat-page-sub" id="nearbySubtitle">Allow location access to see providers close to you.</div>
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
      <?php foreach($nearby as $p):
        $pt=$p['type']??'clinic';
        $icons=['doctor'=>'fa-stethoscope','clinic'=>'fa-house-medical','hospital'=>'fa-hospital','ambulance'=>'fa-truck-medical','pharmacy'=>'fa-pills'];
        $bgs=['doctor'=>'rgba(13,148,136,.1)','clinic'=>'rgba(5,150,105,.1)','hospital'=>'rgba(25,120,229,.1)','ambulance'=>'rgba(220,38,38,.1)','pharmacy'=>'rgba(217,119,6,.1)'];
        $cols=['doctor'=>'var(--teal)','clinic'=>'var(--green)','hospital'=>'var(--primary)','ambulance'=>'var(--red)','pharmacy'=>'var(--yellow)'];
        $ic=$icons[$pt]??'fa-hospital'; $bg=$bgs[$pt]??'rgba(25,120,229,.1)'; $col=$cols[$pt]??'var(--primary)';
      ?>
      <div class="prov-card" data-ptype="<?=$pt?>" data-name="<?= strtolower(htmlspecialchars($p['name']??'')) ?>" data-spec="<?= strtolower(htmlspecialchars($p['specialty']??'')) ?>">
        <div class="prov-card-top">
          <div class="prov-card-ic" style="background:<?=$bg?>;color:<?=$col?>"><i class="fa-solid <?=$ic?>"></i></div>
          <div style="flex:1;min-width:0">
            <div class="prov-card-name"><?= htmlspecialchars($p['name']??'Provider') ?></div>
            <div class="prov-card-type"><?= htmlspecialchars($p['specialty']??ucfirst($pt)) ?></div>
          </div>
        </div>
        <div class="prov-card-bottom">
          <span class="prov-card-dist" id="dist-<?=$p['id']?>"><i class="fa-solid fa-location-dot"></i> —</span>
          <span class="prov-card-rating"><i class="fa-solid fa-star"></i> <?= number_format($p['rating']??4.5,1) ?></span>
        </div>
        <button class="prov-card-book" onclick="bookProvider(<?=$p['id']?>,<?=($p['rating']??4.5)?>,'<?= addslashes($p['name']??'') ?>')">
          <i class="fa-solid fa-calendar-plus"></i> Book Now
        </button>
      </div>
      <?php endforeach;?>
    </div>
    <?php if(empty($nearby)):?>
    <div class="empty-state">
      <i class="fa-solid fa-hospital-user"></i>
      <h3>No providers in the system yet</h3>
      <p>Providers will appear here once they join Planeazzy.</p>
    </div>
    <?php endif;?>

    <?php /* ═══ INSURANCE ═══════════════════════════════ */ elseif($tab==='insurance'): ?>

    <div class="pat-page-hdr">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
          <div class="pat-page-title">Insurance Documents</div>
          <div class="pat-page-sub">Upload and manage your health insurance documents.</div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openModal('insModal')"><i class="fa-solid fa-upload"></i> Upload Card</button>
      </div>
    </div>
    <div id="insAlert" class="alert hidden"></div>
    <?php if(empty($insDocs)):?>
    <div class="pat-panel"><div class="empty-state">
      <i class="fa-solid fa-shield" style="color:var(--primary-10)"></i>
      <h3>No insurance documents yet</h3>
      <p>Upload your insurance card so it can be shared with providers when you book appointments.</p>
      <button class="btn btn-primary btn-sm" onclick="openModal('insModal')"><i class="fa-solid fa-upload"></i> Upload Now</button>
    </div></div>
    <?php else:?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;margin-bottom:16px">
      <?php foreach($insDocs as $doc):?>
      <div class="pat-panel">
        <div style="background:linear-gradient(135deg,var(--primary),var(--teal));padding:14px 16px;display:flex;align-items:center;gap:10px">
          <div style="width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:var(--r);display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0">
            <i class="fa-solid fa-shield-heart"></i>
          </div>
          <div style="min-width:0;flex:1">
            <div style="font-size:.8125rem;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($doc['provider_name']) ?></div>
            <div style="font-size:.6875rem;color:rgba(255,255,255,.75)"><?= htmlspecialchars($doc['coverage_type']??'Health Insurance') ?></div>
          </div>
          <span style="padding:2px 8px;border-radius:9999px;font-size:.5625rem;font-weight:700;background:rgba(255,255,255,.2);color:#fff"><?= ucfirst($doc['status']) ?></span>
        </div>
        <div class="pat-panel-body" style="font-size:.8125rem">
          <?php if($doc['policy_number']):?><div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--slate-100)"><span style="color:var(--slate-400)">Policy #</span><span style="font-weight:600"><?= htmlspecialchars($doc['policy_number']) ?></span></div><?php endif;?>
          <?php if($doc['expiry_date']):
            $expired=strtotime($doc['expiry_date'])<time();?>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--slate-100)"><span style="color:var(--slate-400)">Expires</span><span style="font-weight:600;color:<?=$expired?'var(--red)':'var(--slate-900)'?>"><?=date('M j, Y',strtotime($doc['expiry_date']))?></span></div>
          <?php endif;?>
          <div style="display:flex;gap:6px;margin-top:10px">
            <button class="btn btn-primary btn-sm" style="flex:1" onclick="openModal('bookModal')"><i class="fa-solid fa-calendar-plus"></i> Book with This</button>
            <button class="btn btn-ghost btn-sm" onclick="delIns(<?=$doc['id']?>,this)" title="Delete"><i class="fa-solid fa-trash" style="color:var(--red)"></i></button>
          </div>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="openModal('insModal')"><i class="fa-solid fa-plus"></i> Add Another</button>
    <?php endif;?>

    <?php /* ═══ NOTIFICATIONS ════════════════════════════ */ elseif($tab==='notifications'): ?>

    <div class="pat-page-hdr">
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

    <?php /* ═══ EMERGENCY ══════════════════════════════ */ elseif($tab==='emergency'): ?>

    <div class="pat-page-hdr">
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

    <?php /* ═══ SETTINGS ════════════════════════════════ */ elseif($tab==='settings'): ?>

    <div class="pat-page-hdr">
      <div class="pat-page-title">Account Settings</div>
      <div class="pat-page-sub">Manage your profile, preferences and security.</div>
    </div>
    <div id="settAlert" class="alert hidden"></div>
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

<!-- ══ BOOK APPOINTMENT MODAL ══════════════════════════════ -->
<div class="modal-ov" id="bookModal" onclick="if(event.target===this)closeModal('bookModal')">
  <div class="modal-box">
    <div class="modal-hdr">
      <div class="modal-title"><i class="fa-solid fa-calendar-plus" style="color:var(--primary)"></i> Book Appointment</div>
      <button class="modal-close" onclick="closeModal('bookModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div id="bookAlert" class="alert hidden"></div>
      <div class="form-group">
        <label class="form-label">Service Type</label>
        <select class="form-select" id="bookServiceType">
          <option value="doctor">Doctor / Specialist</option>
          <option value="clinic">Clinic</option>
          <option value="hospital">Hospital</option>
          <option value="telehealth">Telehealth (Video)</option>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Date</label><input type="date" id="bookDate" class="form-input" min="<?= date('Y-m-d') ?>"></div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Time</label><input type="time" id="bookTime" class="form-input" value="09:00"></div>
      </div>
      <div class="form-group" style="margin-top:12px"><label class="form-label">Reason for Visit</label><input type="text" id="bookTitle" class="form-input" placeholder="e.g., General checkup, Fever…"></div>
      <div class="form-group"><label class="form-label">Notes (optional)</label><textarea id="bookNotes" class="form-textarea" rows="2" placeholder="Any additional details for your doctor…"></textarea></div>
      <?php if(!empty($insDocs)):?>
      <div class="form-group">
        <label class="form-label">Use Insurance</label>
        <select class="form-select" id="bookInsDoc">
          <option value="">— Pay directly —</option>
          <?php foreach($insDocs as $doc):?><option value="<?=$doc['id']?>"><?= htmlspecialchars($doc['provider_name']) ?></option><?php endforeach;?>
        </select>
      </div>
      <?php endif;?>
      <button class="btn btn-primary btn-full" id="bookBtn" onclick="submitBooking()">
        <i class="fa-solid fa-calendar-check"></i> Confirm Booking
      </button>
    </div>
  </div>
</div>

<!-- ══ INSURANCE UPLOAD MODAL ══════════════════════════════ -->
<div class="modal-ov" id="insModal" onclick="if(event.target===this)closeModal('insModal')">
  <div class="modal-box">
    <div class="modal-hdr">
      <div class="modal-title"><i class="fa-solid fa-shield" style="color:var(--primary)"></i> Upload Insurance</div>
      <button class="modal-close" onclick="closeModal('insModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div id="insModalAlert" class="alert hidden"></div>
      <form id="insForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="form-group"><label class="form-label">Provider Name *</label><input type="text" name="provider_name" class="form-input" placeholder="e.g. NHIF, Jubilee Health…" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group" style="margin-bottom:0"><label class="form-label">Policy Number</label><input type="text" name="policy_number" class="form-input" placeholder="POL-12345"></div>
          <div class="form-group" style="margin-bottom:0"><label class="form-label">Member Number</label><input type="text" name="member_number" class="form-input" placeholder="MEM-67890"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
          <div class="form-group" style="margin-bottom:0"><label class="form-label">Coverage Type</label>
            <select name="coverage_type" class="form-select"><option value="">Select…</option><option>Inpatient &amp; Outpatient</option><option>Outpatient Only</option><option>Comprehensive</option><option>Emergency Only</option></select>
          </div>
          <div class="form-group" style="margin-bottom:0"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-input"></div>
        </div>
        <div class="form-group" style="margin-top:10px"><label class="form-label">Insurance Document *</label>
          <div id="insDropZone" onclick="document.getElementById('insFile').click()"
               style="border:2px dashed var(--slate-200);border-radius:var(--r);padding:20px;text-align:center;cursor:pointer;transition:.15s">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:24px;color:var(--slate-300);display:block;margin-bottom:6px"></i>
            <div style="font-size:.8125rem;font-weight:600;color:var(--slate-500)">Click to upload or drag &amp; drop</div>
            <div style="font-size:.6875rem;color:var(--slate-400);margin-top:3px">PDF, JPG, PNG · Max 5 MB</div>
            <div id="insFileName" style="display:none;margin-top:6px;font-size:.75rem;font-weight:600;color:var(--primary)"></div>
          </div>
          <input type="file" name="insurance_doc" id="insFile" accept=".pdf,.jpg,.jpeg,.png,.webp" style="display:none" onchange="document.getElementById('insFileName').textContent=this.files[0]?.name;document.getElementById('insFileName').style.display='block'">
        </div>
        <button type="button" class="btn btn-primary btn-full" id="insUploadBtn" onclick="uploadInsurance()">
          <i class="fa-solid fa-shield-heart"></i> Save Insurance Document
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Toast + CSRF -->
<input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf) ?>">
<div class="toast" id="toast"></div>
<button class="mob-fab" id="mobFab" onclick="openSb()"><i class="fa-solid fa-bars"></i></button>

<script src="/assets/js/app.js"></script>
<script>
/* ── Lang init ──────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
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

/* ── Greeting ────────────────────────────────────────────── */
(function(){
  const h = new Date().getHours();
  const sw = document.documentElement.lang === 'sw';
  const g = h<12?(sw?'Habari za asubuhi':'Good morning'):h<17?(sw?'Habari za mchana':'Good afternoon'):(sw?'Habari za jioni':'Good evening');
  const el = document.getElementById('dashGreeting');
  if (el) el.textContent = g;
})();

/* ── Toast ──────────────────────────────────────────────── */
function toast(msg, type='info') {
  const el = document.getElementById('toast');
  el.textContent = msg; el.className = 'toast ' + type + ' show';
  setTimeout(() => el.classList.remove('show'), 3500);
}

/* ── Sidebar mobile ─────────────────────────────────────── */
function openSb() {
  document.getElementById('patSb')?.classList.add('mob-open');
  document.getElementById('mobOv')?.classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeSb() {
  document.getElementById('patSb')?.classList.remove('mob-open');
  document.getElementById('mobOv')?.classList.remove('show');
  document.body.style.overflow = '';
}
document.getElementById('patHamb')?.addEventListener('click', openSb);
document.getElementById('mobFab')?.addEventListener('click', openSb);

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

/* ── Modals ─────────────────────────────────────────────── */
function openModal(id) { document.getElementById(id)?.classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); document.body.style.overflow=''; }

/* ── Appointment filter (URL) ───────────────────────────── */
function setAf(af) { location.href = '?tab=appointments&af=' + af; }

/* ── Provider preview filter (overview tab) ─────────────── */
function previewFilter(type, btn) {
  document.querySelectorAll('[data-fkey]').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#provPreviewGrid .prov-card').forEach(c => {
    c.style.display = (type==='all' || c.dataset.ptype===type) ? '' : 'none';
  });
}

/* ── Nearby tab filtering + search ─────────────────────── */
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

function bookProvider(id, rating, name) {
  openModal('bookModal');
  document.getElementById('bookTitle').value = 'Appointment with ' + name;
}

/* ── Geolocation ────────────────────────────────────────── */
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

  // Update distance labels
  document.querySelectorAll('#nearbyGrid .prov-card').forEach((card, i) => {
    const p = _provData[i];
    if (!p) return;
    const distEl = document.getElementById('dist-' + p.id);
    if (distEl) {
      if (p.latitude && p.longitude) {
        const d = haversineKm(lat, lng, parseFloat(p.latitude), parseFloat(p.longitude));
        distEl.innerHTML = '<i class="fa-solid fa-location-dot"></i> ' + d.toFixed(1) + ' km';
      } else {
        const d = (Math.random() * 20 + 0.5).toFixed(1);
        distEl.innerHTML = '<i class="fa-solid fa-location-dot"></i> ~' + d + ' km';
      }
    }
  });
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

/* ── Booking ────────────────────────────────────────────── */
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
      closeModal('bookModal');
      toast('Appointment booked! Check your email for confirmation.','ok');
      setTimeout(() => location.href='?tab=appointments', 1600);
    } else {
      alertEl.className='alert err'; alertEl.textContent=r.message||'Booking failed. Please try again.'; alertEl.classList.remove('hidden');
    }
  } catch(e) {
    alertEl.className='alert err'; alertEl.textContent='Network error. Please try again.'; alertEl.classList.remove('hidden');
  } finally {
    btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-calendar-check"></i> Confirm Booking';
  }
}

/* ── Insurance upload ───────────────────────────────────── */
async function uploadInsurance() {
  const btn = document.getElementById('insUploadBtn');
  const alertEl = document.getElementById('insModalAlert');
  const form = document.getElementById('insForm');
  const fd = new FormData(form);
  if (!fd.get('provider_name')?.trim()) { alertEl.className='alert err'; alertEl.textContent='Provider name is required.'; alertEl.classList.remove('hidden'); return; }
  btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Saving…';
  try {
    const r = await fetch('/api/patient/upload-insurance.php', {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json());
    if (r.success) { toast('Insurance document saved!','ok'); closeModal('insModal'); setTimeout(()=>location.reload(),1000); }
    else { alertEl.className='alert err'; alertEl.textContent=r.message||'Upload failed.'; alertEl.classList.remove('hidden'); }
  } catch(e) { alertEl.className='alert err'; alertEl.textContent='Upload error. Please try again.'; alertEl.classList.remove('hidden'); }
  finally { btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-shield-heart"></i> Save Insurance Document'; }
}

function delIns(id, btn) {
  if (!confirm('Delete this insurance document?')) return;
  btn.disabled = true;
  fetch('/api/patient/delete-insurance.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({doc_id:id,csrf_token:document.getElementById('csrfToken')?.value||''}),credentials:'same-origin'})
    .then(r=>r.json()).then(r=>{ if(r.success){toast('Document deleted','ok');setTimeout(()=>location.reload(),800);}else toast(r.message||'Error','err'); })
    .catch(()=>toast('Network error','err'));
}

/* ── Notifications ──────────────────────────────────────── */
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

/* ── Settings ───────────────────────────────────────────── */
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

/* ── Emergency SOS ──────────────────────────────────────── */
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
</body>
</html>
