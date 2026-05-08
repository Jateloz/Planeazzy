<?php
/**
 * Planeazzy — /patients/book.php
 * Book an appointment: logged-in patients get browse+modal flow, guests get a form.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Database.php';
Security::startSession();

$csrf       = Security::csrfToken();
$isLoggedIn = !empty($_SESSION['patient_id']) && !empty($_SESSION['authenticated']);
$pat        = [];

if ($isLoggedIn) {
    try {
        $db2 = Database::getInstance();
        $pat = $db2->fetchOne('SELECT * FROM patients WHERE id=:id', [':id' => (int)$_SESSION['patient_id']]) ?? [];
    } catch(Throwable $e) {}
}

/* URL params */
$preProvider   = htmlspecialchars(trim($_GET['provider'] ?? ''));
$preType       = in_array($_GET['type'] ?? '', ['doctor','hospital','clinic','ambulance','telehealth','lab','pharmacy']) ? $_GET['type'] : '';
$preProviderId = (int)($_GET['provider_id'] ?? 0);

/* Load all providers for display */
$allProviders = [];
try {
    $db2 = $db2 ?? Database::getInstance();
    $hospitals = $db2->fetchAll(
        "SELECT hp.id+10000 AS id, hp.facility_name AS name, 'hospital' AS type,
                hp.county AS city, hp.facility_type AS specialty, '' AS fee,
                hp.logo_path AS avatar_path, hp.phone, hp.emergency_24h AS is_available,
                4.5 AS rating, 'hospital_provider' AS source, '' AS hospital_name
         FROM hospital_providers hp
         WHERE hp.is_active=1 AND hp.status='approved'
         ORDER BY hp.facility_name ASC LIMIT 50"
    ) ?? [];

    $standaloneDoctors = $db2->fetchAll(
        "SELECT d.id+30000 AS id, CONCAT('Dr. ',d.first_name,' ',d.last_name) AS name,
                'doctor' AS type, d.specialty, d.city, d.county,
                d.consult_fee AS fee, d.avatar_path, d.phone,
                d.accepts_tele AS is_available,
                COALESCE(d.rating,4.0) AS rating,
                'standalone_doctor' AS source, '' AS hospital_name,
                d.years_exp, d.kmpdc_licence
         FROM doctors d
         WHERE d.is_active=1 AND d.status='active' AND (d.is_verified=1 OR d.email_verified=1)
         ORDER BY d.first_name ASC LIMIT 80"
    ) ?? [];

    $hospitalDoctors = $db2->fetchAll(
        "SELECT hd.id+20000 AS id, CONCAT('Dr. ',hd.name) AS name,
                'doctor' AS type, hd.specialty, hp.county AS city, hp.county,
                hd.consult_fee AS fee, hd.avatar_path, hd.phone,
                hd.accepts_tele AS is_available, 4.2 AS rating,
                'hospital_doctor' AS source, hp.facility_name AS hospital_name,
                hd.years_exp, hd.kmpdc_licence
         FROM hospital_doctors hd
         JOIN hospital_providers hp ON hp.id=hd.hospital_id
         WHERE hd.is_active=1 AND hp.status='approved' AND hp.is_active=1
           AND hd.status != 'suspended'
         ORDER BY hd.name ASC LIMIT 80"
    ) ?? [];

    $allProviders = array_merge($hospitals, $standaloneDoctors, $hospitalDoctors);
} catch(Throwable $e) { $allProviders = []; }

/* Helpers */
$patName  = trim(($pat['first_name'] ?? '') . ' ' . ($pat['last_name'] ?? ''));
$patEmail = $pat['email'] ?? '';
$patPhone = $pat['phone'] ?? '';
$_patInitials = $patName ? strtoupper(substr($patName,0,1).(strpos($patName,' ')!==false?substr($patName,strrpos($patName,' ')+1,1):'')) : 'P';

$insuranceList = ['NHIF','Jubilee Health','AXA Mansard','AAR Healthcare','CIC Insurance','Britam','Other'];

$providersJson = json_encode(array_values($allProviders), JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE);
$noSidebar = true;
$pageTitle = 'Book an Appointment';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Book an Appointment — Planeazzy</title>
<link rel="icon" type="image/png" href="/assets/images/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="/assets/css/clinical.css">
<link rel="stylesheet" href="/assets/css/app.css">
<style>
:root{--primary:#1978e5;--primary-dark:#1251a3;--primary-10:rgba(25,120,229,.1);--primary-20:rgba(25,120,229,.2);--teal:#0d9488;--green:#16a34a;--yellow:#f59e0b;--red:#ef4444;--ink:#0f172a;--muted:#475569;--faint:#64748b;--border:#e2e8f0;--bg:#f8fafc;--card:#fff;--shadow-sm:0 1px 4px rgba(0,0,0,.05);--shadow-md:0 4px 16px rgba(0,0,0,.08)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;background:var(--bg);color:var(--ink)}

/* Page layout */
.bk-page{min-height:100vh;display:flex;flex-direction:column}
.bk-nav{background:var(--card);border-bottom:1px solid var(--border);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.bk-logo{font-size:1.125rem;font-weight:900;color:var(--primary);letter-spacing:-.03em;text-decoration:none;display:flex;align-items:center;gap:8px}
.bk-nav-right{display:flex;align-items:center;gap:10px}
.bk-nav-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 15px;border-radius:8px;font-family:inherit;font-size:.8125rem;font-weight:600;cursor:pointer;transition:all .15s;text-decoration:none}
.bk-nav-btn.ghost{border:1.5px solid var(--border);color:var(--muted);background:var(--card)}
.bk-nav-btn.ghost:hover{border-color:var(--primary);color:var(--primary)}
.bk-nav-btn.fill{background:var(--primary);color:#fff;border:none}
.bk-nav-btn.fill:hover{background:var(--primary-dark)}

/* Hero */
.bk-hero{background:linear-gradient(135deg,var(--primary),var(--primary-dark));padding:28px 24px;color:#fff;text-align:center}
.bk-hero h1{font-size:1.5rem;font-weight:900;letter-spacing:-.03em;margin-bottom:4px}
.bk-hero p{font-size:.875rem;opacity:.88}

/* Filter bar */
.bk-filter{background:var(--card);border-bottom:1px solid var(--border);padding:12px 24px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.bk-filter-tabs{display:flex;gap:6px;flex-wrap:wrap}
.bk-ftab{padding:6px 14px;border-radius:9999px;font-size:.75rem;font-weight:700;border:1.5px solid var(--border);background:var(--card);color:var(--muted);cursor:pointer;font-family:inherit;transition:all .15s;display:flex;align-items:center;gap:4px}
.bk-ftab.active{background:var(--primary);border-color:var(--primary);color:#fff}
.bk-ftab:not(.active):hover{border-color:var(--primary);color:var(--primary)}
.bk-search-bar{display:flex;align-items:center;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:0 12px;flex:1;min-width:200px;max-width:320px;gap:7px;transition:border .15s}
.bk-search-bar:focus-within{border-color:var(--primary)}
.bk-search-bar input{border:none;background:transparent;font-family:inherit;font-size:.875rem;color:var(--ink);padding:9px 0;outline:none;width:100%}

/* Provider grid */
.bk-body{padding:20px 24px 60px;max-width:1400px;width:100%;margin:0 auto}
.bk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.bk-card{background:var(--card);border-radius:14px;border:1px solid var(--border);overflow:hidden;transition:all .2s;cursor:pointer;box-shadow:var(--shadow-sm)}
.bk-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.09);transform:translateY(-1px);border-color:var(--primary-20)}
.bk-card-img{height:140px;overflow:hidden;position:relative;background:linear-gradient(135deg,#e8f0fe,#dbeafe)}
.bk-card-img img{width:100%;height:100%;object-fit:cover}
.bk-card-initials{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:900;color:#fff;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.15)}
.bk-card-hosp-logo{width:80px;height:80px;border-radius:12px;object-fit:contain;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:8px;border:2px solid rgba(255,255,255,.8);box-shadow:0 4px 12px rgba(0,0,0,.12)}
.bk-card-body{padding:14px 16px}
.bk-card-badge{position:absolute;top:8px;right:8px;background:rgba(255,255,255,.92);backdrop-filter:blur(4px);padding:2px 8px;border-radius:5px;font-size:.625rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.bk-card-badge.verified{color:var(--primary)}
.bk-card-badge.hosp-doc{color:#059669}
.bk-card-name{font-size:.9375rem;font-weight:800;color:var(--ink);margin-bottom:2px;line-height:1.2}
.bk-card-spec{font-size:.75rem;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px}
.bk-card-meta{display:flex;flex-direction:column;gap:3px;margin-bottom:10px}
.bk-card-meta-row{display:flex;align-items:center;gap:5px;font-size:.75rem;color:var(--faint)}
.bk-card-footer{display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid rgba(0,0,0,.06)}
.bk-card-fee{font-size:.75rem;font-weight:700;color:var(--teal)}
.btn-book-card{padding:7px 16px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-family:inherit;font-size:.75rem;font-weight:700;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:4px}
.btn-book-card:hover{background:var(--primary-dark)}
.bk-avail-strip{display:flex;align-items:center;gap:4px;font-size:.6875rem;color:#059669;font-weight:600;margin-bottom:8px}

/* Empty state */
.bk-empty{text-align:center;padding:60px 20px;color:var(--faint)}

/* ══ BOOKING MODAL ══ */
.bk-modal-ov{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:600;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(5px)}
.bk-modal-ov.open{display:flex}
.bk-modal-box{background:var(--card);border-radius:20px;box-shadow:0 24px 60px rgba(0,0,0,.22);max-height:92vh;overflow-y:auto;width:100%;max-width:520px;animation:bkIn .2s ease}
@keyframes bkIn{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:scale(1) translateY(0)}}
.bk-modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:18px 22px 14px;border-bottom:1px solid rgba(0,0,0,.08);position:sticky;top:0;background:var(--card);z-index:2;border-radius:20px 20px 0 0}
.bk-modal-title{font-size:.9375rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:8px}
.bk-modal-close{width:28px;height:28px;border-radius:50%;background:var(--bg);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--faint);transition:all .15s}
.bk-modal-close:hover{background:rgba(0,0,0,.08)}

/* Steps */
.bk-steps-bar{display:flex;align-items:center;padding:12px 22px;border-bottom:1px solid rgba(0,0,0,.08);gap:0;overflow-x:auto;scrollbar-width:none}
.bk-steps-bar::-webkit-scrollbar{display:none}
.bk-step{display:flex;align-items:center;gap:0;flex:1}
.bk-step-dot{width:24px;height:24px;border-radius:50%;background:var(--bg);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.5625rem;font-weight:900;color:var(--faint);flex-shrink:0;transition:all .2s;z-index:1}
.bk-step-lbl{font-size:.5625rem;font-weight:700;color:var(--faint);text-transform:uppercase;letter-spacing:.06em;margin:0 3px;white-space:nowrap}
.bk-step-line{flex:1;height:2px;background:var(--border);margin:0 2px}
.bk-step.active .bk-step-dot{background:var(--primary);border-color:var(--primary);color:#fff}
.bk-step.active .bk-step-lbl{color:var(--primary)}
.bk-step.done .bk-step-dot{background:var(--green);border-color:var(--green);color:#fff}
.bk-step.done .bk-step-line{background:var(--green)}

/* Panels */
.bk-panel{display:none;padding:18px 22px 0}
.bk-panel.active{display:block}

/* Calendar */
.bk-cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.bk-cal-month{font-size:.875rem;font-weight:800;color:var(--ink)}
.bk-cal-navbtn{width:28px;height:28px;border-radius:7px;border:1px solid var(--border);background:var(--card);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--faint);transition:all .15s}
.bk-cal-navbtn:hover{border-color:var(--primary);color:var(--primary)}
.bk-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px}
.bk-cal-dh{text-align:center;font-size:.5625rem;font-weight:800;color:var(--faint);padding:4px 0;text-transform:uppercase;letter-spacing:.06em}
.bk-cal-d{width:100%;aspect-ratio:1;border-radius:7px;border:1px solid transparent;background:transparent;cursor:pointer;font-family:inherit;font-size:.75rem;font-weight:600;color:var(--muted);transition:all .15s;display:flex;align-items:center;justify-content:center}
.bk-cal-d:disabled,.bk-cal-d.past{color:rgba(0,0,0,.15);cursor:not-allowed}
.bk-cal-d.avail:not(:disabled):hover{background:var(--primary-10);border-color:var(--primary-20);color:var(--primary)}
.bk-cal-d.today{border-color:var(--primary-20);color:var(--primary);font-weight:800}
.bk-cal-d.selected{background:var(--primary)!important;border-color:var(--primary)!important;color:#fff!important;font-weight:800}
.bk-cal-d.empty{cursor:default}
.bk-cal-d.no-avail:not(:disabled){opacity:.4;cursor:not-allowed}

/* Slots */
.bk-slot-period{font-size:.6875rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;margin:10px 0 6px;display:flex;align-items:center;gap:5px}
.bk-slots-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:5px;margin-bottom:4px}
.bk-slot{padding:8px 4px;border-radius:8px;border:1px solid var(--border);background:var(--bg);cursor:pointer;font-family:inherit;font-size:.75rem;font-weight:700;color:var(--muted);transition:all .15s;text-align:center}
.bk-slot:hover:not(:disabled){border-color:var(--primary);color:var(--primary);background:var(--primary-10)}
.bk-slot.selected{border-color:var(--primary);background:var(--primary);color:#fff}

/* Visit type */
.bk-vt-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px}
.bk-vt-btn{padding:12px;border-radius:10px;border:2px solid var(--border);background:var(--bg);cursor:pointer;font-family:inherit;font-size:.8125rem;font-weight:700;color:var(--muted);transition:all .15s;display:flex;flex-direction:column;align-items:center;gap:4px}
.bk-vt-btn i{font-size:18px;color:var(--faint)}
.bk-vt-btn.sel{border-color:var(--primary);background:var(--primary-10);color:var(--primary)}
.bk-vt-btn.sel i{color:var(--primary)}

/* Summary */
.bk-summary{background:var(--bg);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:12px}
.bk-sum-row{display:flex;align-items:flex-start;gap:10px;padding:9px 14px;border-bottom:1px solid rgba(0,0,0,.06)}
.bk-sum-row:last-child{border-bottom:none}
.bk-sum-lbl{font-size:.6875rem;font-weight:700;color:var(--faint);min-width:90px;display:flex;align-items:center;gap:5px}
.bk-sum-val{font-size:.8125rem;font-weight:600;color:var(--ink);flex:1}

/* Success */
.bk-success-icon{width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,var(--green),#15803d);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:26px;color:#fff;box-shadow:0 8px 24px rgba(22,163,74,.3)}
.bk-ref{font-family:monospace;font-size:1.25rem;font-weight:900;color:var(--primary);letter-spacing:.1em;text-align:center;margin:8px 0}

/* Modal footer */
.bk-modal-footer{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:14px 22px;border-top:1px solid rgba(0,0,0,.08);position:sticky;bottom:0;background:var(--card);border-radius:0 0 20px 20px;z-index:2}

/* Modal form elements */
.bk-label{display:block;font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--faint);margin-bottom:5px}
.bk-input,.bk-select,.bk-textarea{width:100%;padding:9px 12px;background:var(--bg);border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.875rem;color:var(--ink);outline:none;transition:all .2s}
.bk-input:focus,.bk-select:focus,.bk-textarea:focus{background:var(--card);border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-10)}
.bk-textarea{resize:vertical;min-height:70px}
.bk-g2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.bk-fg{margin-bottom:12px}
.bk-fg:last-child{margin-bottom:0}
.bk-avail-info{background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:7px 11px;font-size:.6875rem;color:#166534;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.bk-hint{background:var(--primary-10);border:1px solid var(--primary-20);border-radius:7px;padding:7px 11px;font-size:.6875rem;color:var(--primary);font-weight:600;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.bk-modal-alert{padding:9px 13px;border-radius:8px;font-size:.75rem;font-weight:600;display:none;align-items:center;gap:7px;margin:0 22px 10px}
.bk-modal-alert.err{background:#fef2f2;border:1.5px solid #fca5a5;color:#991b1b}
.bk-modal-alert.ok{background:#f0fdf4;border:1.5px solid #86efac;color:#166534}
.btn{display:inline-flex;align-items:center;gap:5px;padding:9px 16px;border-radius:9px;font-family:inherit;font-size:.8125rem;font-weight:700;cursor:pointer;transition:all .15s;border:none}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-dark)}
.btn-primary:disabled{opacity:.55;cursor:not-allowed}
.btn-ghost{background:var(--bg);color:var(--muted);border:1.5px solid var(--border)}
.btn-ghost:hover{border-color:var(--primary);color:var(--primary)}

/* Guest form */
.gu-page{background:var(--bg);min-height:100vh}
.gu-nav{background:var(--card);border-bottom:1px solid var(--border);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100}
.gu-logo{font-size:1.125rem;font-weight:900;color:var(--primary);text-decoration:none;display:flex;align-items:center;gap:8px}
.gu-nav-links{display:flex;gap:8px}
.gu-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:8px;font-family:inherit;font-size:.8125rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .15s}
.gu-btn.out{border:1.5px solid var(--border);color:var(--muted);background:var(--card)}
.gu-btn.out:hover{border-color:var(--primary);color:var(--primary)}
.gu-btn.fill{background:var(--primary);color:#fff;border:none}
.gu-btn.fill:hover{background:var(--primary-dark)}
.gu-hero{background:linear-gradient(135deg,var(--primary),var(--primary-dark));padding:36px 24px;text-align:center;color:#fff}
.gu-hero h1{font-size:1.625rem;font-weight:900;letter-spacing:-.03em;margin-bottom:6px}
.gu-hero p{font-size:.875rem;opacity:.88;max-width:420px;margin:0 auto}
.gu-body{max-width:960px;margin:0 auto;padding:24px 24px 60px}
.gu-signin{background:linear-gradient(135deg,rgba(25,120,229,.06),rgba(25,120,229,.02));border:1.5px solid var(--primary-20);border-radius:14px;padding:16px 18px;margin-bottom:20px;display:flex;align-items:center;gap:14px}
.gu-signin-ic{width:40px;height:40px;border-radius:10px;background:var(--primary-10);display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--primary);flex-shrink:0}
.gu-signin-txt{flex:1}
.gu-signin-txt h3{font-size:.875rem;font-weight:700;color:var(--ink);margin-bottom:1px}
.gu-signin-txt p{font-size:.75rem;color:var(--faint)}
.gu-card{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden;margin-bottom:16px}
.gu-card-head{padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:9px}
.gu-card-ic{width:30px;height:30px;border-radius:8px;background:var(--primary-10);display:flex;align-items:center;justify-content:center}
.gu-card-ic i{color:var(--primary);font-size:12px}
.gu-card-title{font-size:.9375rem;font-weight:700;color:var(--ink)}
.gu-card-body{padding:16px 18px}
.gf{margin-bottom:12px}
.gf:last-child{margin-bottom:0}
.gl{display:block;font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--faint);margin-bottom:4px}
.gi{width:100%;padding:9px 12px;background:var(--bg);border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.9375rem;color:var(--ink);outline:none;transition:all .2s}
.gi:focus{background:var(--card);border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-10)}
textarea.gi{resize:vertical;min-height:75px}
.gg2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.gu-submit{width:100%;padding:13px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;border:none;border-radius:11px;font-family:inherit;font-size:1rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:opacity .2s;box-shadow:0 4px 14px rgba(25,120,229,.3)}
.gu-submit:hover{opacity:.92}
.gu-submit:disabled{opacity:.55;cursor:not-allowed}
.gu-success{display:none;background:var(--card);border-radius:16px;border:1px solid var(--border);box-shadow:var(--shadow-md);padding:48px 24px;text-align:center;margin-bottom:20px}
.gu-success-icon{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--green),#15803d);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:30px;color:#fff;box-shadow:0 8px 24px rgba(22,163,74,.3)}
.gu-ref{font-family:monospace;font-size:1.375rem;font-weight:900;color:var(--primary);letter-spacing:.12em;margin:8px 0;background:var(--primary-10);display:inline-block;padding:7px 18px;border-radius:8px}
.gu-alert{border-radius:10px;padding:10px 13px;font-size:.875rem;font-weight:500;margin-bottom:14px;display:none;align-items:center;gap:7px}
.gu-alert.err{background:#fef2f2;border:1.5px solid #fca5a5;color:#991b1b}
.gu-alert.ok{background:#f0fdf4;border:1.5px solid #86efac;color:#166534}

@media(max-width:768px){
  .bk-grid{grid-template-columns:repeat(auto-fill,minmax(240px,1fr))}
  .bk-body{padding:14px 14px 40px}
  .bk-filter{padding:10px 14px}
  .bk-modal-box{max-width:100%;border-radius:20px 20px 0 0;position:fixed;bottom:0;left:0;right:0;max-height:92vh}
  .bk-modal-ov{align-items:flex-end;padding:0}
  .bk-slots-grid{grid-template-columns:repeat(2,1fr)}
  .gu-body{padding:14px 14px 40px}
  .gg2,.bk-g2{grid-template-columns:1fr}
}

/* Make disabled days look locked and prevent clicks */
.bk-cal-d:disabled {
    background: #f1f5f9 !important;
    color: #cbd5e1 !important;
    cursor: not-allowed !important;
    pointer-events: none !important; /* This stops the click from reaching the function */
    opacity: 0.6;
    border: none !important;
}

/* Style for days that are NOT the doctor's working days */
.bk-cal-d.no-avail {
    background: #f8fafc;
    color: #94a3b8;
}
</style>
</head>
<body>

<?php if($isLoggedIn): ?>
<!-- ═══ LOGGED-IN: BROWSE + MODAL FLOW ═══ -->
<div class="bk-page">
  <nav class="bk-nav">
    <a href="/" class="bk-logo">
      <img src="/assets/images/favicon1.png" alt="Planeazzy" style="height:28px;width:auto">
    </a>
    <div class="bk-nav-right">
      <span style="font-size:.8125rem;font-weight:600;color:var(--muted)">
        <i class="fa-solid fa-circle-check" style="color:var(--green);margin-right:4px"></i>
        <?=htmlspecialchars($patName)?>
      </span>
      <a href="/patients/dashboard.php" class="bk-nav-btn ghost">
        <i class="fa-solid fa-arrow-left" style="font-size:10px"></i>Dashboard
      </a>
    </div>
  </nav>

  <div class="bk-hero">
    <h1><i class="fa-solid fa-calendar-plus" style="margin-right:8px;opacity:.9"></i>Book an Appointment</h1>
    <p>Browse doctors and hospitals, then book directly with real-time availability</p>
  </div>

  <!-- Filter bar -->
  <div class="bk-filter">
    <div class="bk-filter-tabs" id="bkTabs">
      <button class="bk-ftab active" data-type="all" onclick="setBkType('all',this)"><i class="fa-solid fa-grip" style="font-size:10px"></i>All</button>
      <button class="bk-ftab" data-type="hospital" onclick="setBkType('hospital',this)"><i class="fa-solid fa-hospital" style="font-size:10px"></i>Hospitals</button>
      <button class="bk-ftab" data-type="doctor" onclick="setBkType('doctor',this)"><i class="fa-solid fa-user-doctor" style="font-size:10px"></i>Doctors</button>
    </div>
    <div class="bk-search-bar">
      <i class="fa-solid fa-magnifying-glass" style="color:var(--faint);font-size:12px"></i>
      <input type="text" id="bkSearch" placeholder="Search by name, specialty…" oninput="renderBkGrid()">
    </div>
    <div id="bkCount" style="font-size:.75rem;color:var(--faint);white-space:nowrap"></div>
  </div>

  <!-- Provider grid -->
  <div class="bk-body">
    <div class="bk-grid" id="bkGrid"></div>
    <div class="bk-empty" id="bkEmpty" style="display:none">
      <i class="fa-solid fa-magnifying-glass-location" style="font-size:40px;display:block;margin-bottom:14px;opacity:.3"></i>
      <div style="font-size:.9375rem;font-weight:600;color:var(--muted)">No providers match your search</div>
      <div style="font-size:.8125rem;margin-top:6px">Try a different name or specialty</div>
    </div>
  </div>
</div>

<!-- Booking Modal -->
<input type="hidden" id="bkCsrf" value="<?=htmlspecialchars($csrf)?>">

<div class="bk-modal-ov" id="bkModal" onclick="if(event.target===this)closeBkModal()">
  <div class="bk-modal-box">
    <div class="bk-modal-hdr">
      <div class="bk-modal-title">
        <div style="width:32px;height:32px;border-radius:8px;background:var(--primary-10);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fa-solid fa-calendar-plus" style="color:var(--primary);font-size:13px"></i>
        </div>
        <div>
          <div>Book Appointment</div>
          <div id="bkModalProv" style="font-size:.625rem;font-weight:500;color:var(--muted);margin-top:1px"></div>
        </div>
      </div>
      <button class="bk-modal-close" onclick="closeBkModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="bk-steps-bar" id="bkStepsBar">
      <?php foreach([['1','Reason'],['2','Date'],['3','Time'],['4','Type'],['5','Confirm']] as [$n,$l]): ?>
      <div class="bk-step <?=$n==='1'?'active':''?>" data-step="<?=$n?>">
        <div class="bk-step-dot"><?=$n?></div>
        <span class="bk-step-lbl"><?=$l?></span>
        <?php if($n!=='5'): ?><div class="bk-step-line"></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Step 1: Reason -->
    <div class="bk-panel active" id="bkP1">
      <div class="bk-fg">
        <label class="bk-label"><i class="fa-solid fa-stethoscope" style="color:var(--primary)"></i> Reason for Visit <span style="color:var(--red)">*</span></label>
        <select class="bk-select" id="bkReason" onchange="document.getElementById('bkOtherRow').style.display=this.value==='other'?'block':'none'">
          <option value="">— Select a reason —</option>
          <option value="general_consultation">General Consultation</option>
          <option value="followup">Follow-up Visit</option>
          <option value="checkup">Routine Check-up</option>
          <option value="specialist">Specialist Referral</option>
          <option value="emergency">Emergency</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div id="bkOtherRow" style="display:none" class="bk-fg">
        <label class="bk-label">Describe your reason <span style="color:var(--red)">*</span></label>
        <textarea class="bk-textarea" id="bkOtherNote" placeholder="Describe your reason for visiting…"></textarea>
      </div>
    </div>

    <!-- Step 2: Date -->
    <div class="bk-panel" id="bkP2">
      <div class="bk-hint" id="bkHint" style="display:none">
        <i class="fa-regular fa-clock"></i><span id="bkHintTxt"></span>
      </div>
      <div class="bk-cal-nav">
        <button class="bk-cal-navbtn" id="bkCalPrev"><i class="fa-solid fa-chevron-left"></i></button>
        <span class="bk-cal-month" id="bkCalMonth"></span>
        <button class="bk-cal-navbtn" id="bkCalNext"><i class="fa-solid fa-chevron-right"></i></button>
      </div>
      <div class="bk-cal-grid" id="bkCalGrid"></div>
    </div>

    <!-- Step 3: Time slots -->
    <div class="bk-panel" id="bkP3">
      <div id="bkSlotsWrap"></div>
    </div>

    <!-- Step 4: Visit type & notes -->
    <div class="bk-panel" id="bkP4">
      <div class="bk-label" style="margin-bottom:9px">How would you like to be seen?</div>
      <div class="bk-vt-grid">
        <button class="bk-vt-btn sel" data-vt="in_person" onclick="bkSelType(this,'in_person')">
          <i class="fa-solid fa-house-medical"></i>In-Person
        </button>
        <button class="bk-vt-btn" data-vt="telehealth" onclick="bkSelType(this,'telehealth')">
          <i class="fa-solid fa-video"></i>Telehealth
        </button>
      </div>
      <div class="bk-fg">
        <label class="bk-label">Insurance</label>
        <select class="bk-select" id="bkIns">
          <option value="">None / Self-pay</option>
          <?php foreach($insuranceList as $ins): ?>
          <option value="<?=$ins?>"><?=$ins?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="bk-fg">
        <label class="bk-label">Notes <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
        <textarea class="bk-textarea" id="bkNotes" placeholder="Anything the provider should know…"></textarea>
      </div>
    </div>

    <!-- Step 5: Summary -->
    <div class="bk-panel" id="bkP5">
      <div class="bk-summary">
        <div class="bk-sum-row"><span class="bk-sum-lbl"><i class="fa-solid fa-user-doctor"></i>Provider</span><span class="bk-sum-val" id="bkSumProv">—</span></div>
        <div class="bk-sum-row"><span class="bk-sum-lbl"><i class="fa-regular fa-calendar"></i>Date</span><span class="bk-sum-val" id="bkSumDate">—</span></div>
        <div class="bk-sum-row"><span class="bk-sum-lbl"><i class="fa-regular fa-clock"></i>Time</span><span class="bk-sum-val" id="bkSumTime">—</span></div>
        <div class="bk-sum-row"><span class="bk-sum-lbl"><i class="fa-solid fa-hospital"></i>Visit</span><span class="bk-sum-val" id="bkSumType">—</span></div>
        <div class="bk-sum-row"><span class="bk-sum-lbl"><i class="fa-solid fa-clipboard"></i>Reason</span><span class="bk-sum-val" id="bkSumReason">—</span></div>
        <div class="bk-sum-row" id="bkSumFeeRow" style="display:none"><span class="bk-sum-lbl"><i class="fa-solid fa-credit-card"></i>Fee</span><span class="bk-sum-val" id="bkSumFee">—</span></div>
      </div>
      <p style="text-align:center;font-size:.6875rem;color:var(--muted)"><i class="fa-solid fa-envelope" style="color:var(--primary);margin-right:4px"></i>Confirmation sent by email &amp; SMS</p>
    </div>

    <!-- Success -->
    <div id="bkSuccess" style="display:none;padding:32px 22px;text-align:center">
      <div class="bk-success-icon"><i class="fa-solid fa-circle-check"></i></div>
      <h3 style="font-size:1.0625rem;font-weight:800;margin-bottom:6px">Appointment Booked!</h3>
      <p style="font-size:.8125rem;color:var(--muted)">Booking reference:</p>
      <div class="bk-ref" id="bkRef">PZY-000000</div>
      <p style="font-size:.75rem;color:var(--muted);margin-top:6px"><i class="fa-solid fa-envelope" style="color:var(--primary)"></i> Confirmation sent to your email &amp; phone.</p>
      <button class="btn btn-primary" style="margin-top:16px" onclick="window.location.href='/patients/dashboard.php?tab=appointments'">
        <i class="fa-solid fa-calendar-days"></i>View My Appointments
      </button>
    </div>

    <div class="bk-modal-alert" id="bkAlert"></div>

    <div class="bk-modal-footer" id="bkFooter">
      <button class="btn btn-ghost" id="bkBackBtn" onclick="bkPrev()" style="display:none"><i class="fa-solid fa-arrow-left"></i>Back</button>
      <button class="btn btn-primary" id="bkNextBtn" onclick="bkNext()">Next<i class="fa-solid fa-arrow-right"></i></button>
      <button class="btn btn-primary" id="bkConfirmBtn" onclick="bkConfirm()" style="display:none;background:linear-gradient(135deg,var(--primary),var(--teal))">
        <i class="fa-solid fa-calendar-check"></i>Confirm Booking
      </button>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ═══ GUEST BOOKING FORM ═══ -->
<div class="gu-page">
  <nav class="gu-nav">
    <a href="/" class="gu-logo">
      <img src="/assets/images/favicon1.png" alt="Planeazzy" style="height:28px;width:auto">
    </a>
    <div class="gu-nav-links">
      <a href="/patients/login.php" class="gu-btn out"><i class="fa-solid fa-right-to-bracket" style="font-size:10px"></i>Sign In</a>
      <a href="/patients/register.php" class="gu-btn fill"><i class="fa-solid fa-user-plus" style="font-size:10px"></i>Register</a>
    </div>
  </nav>
  <div class="gu-hero">
    <h1><i class="fa-solid fa-calendar-plus" style="margin-right:8px;opacity:.9"></i>Book an Appointment</h1>
    <p>No account needed — we'll reach out to confirm your booking within 2 hours</p>
  </div>
  <div class="gu-body">
    <div class="gu-signin">
      <div class="gu-signin-ic"><i class="fa-solid fa-bolt"></i></div>
      <div class="gu-signin-txt">
        <h3>Have an account? Book faster &amp; track your appointments</h3>
        <p>Your details auto-fill and you can view, cancel or reschedule anytime</p>
      </div>
      <a href="/patients/login.php" class="gu-btn fill" style="flex-shrink:0"><i class="fa-solid fa-right-to-bracket" style="font-size:10px"></i>Sign In</a>
    </div>

    <div class="gu-alert" id="gAlert"></div>

    <div class="gu-success" id="gSuccess">
      <div class="gu-success-icon"><i class="fa-solid fa-check"></i></div>
      <h2 style="font-size:1.25rem;font-weight:900;color:var(--ink);margin-bottom:8px">Booking Received!</h2>
      <p style="color:var(--faint);font-size:.875rem;margin-bottom:6px">Your reference number:</p>
      <div class="gu-ref" id="gRef"></div>
      <p style="color:var(--muted);font-size:.8125rem;margin-top:10px;line-height:1.7">A coordinator will contact you within 2 hours to confirm.<br>Check your email and SMS.</p>
      <div style="display:flex;gap:10px;justify-content:center;margin-top:18px;flex-wrap:wrap">
        <a href="/patients/register.php" class="gu-btn fill"><i class="fa-solid fa-user-plus"></i>Create Account</a>
        <a href="/" class="gu-btn out"><i class="fa-solid fa-house"></i>Homepage</a>
      </div>
    </div>

    <div id="gForm">
      <input type="hidden" id="gCsrf" value="<?=htmlspecialchars($csrf)?>">

      <div class="gu-card">
        <div class="gu-card-head">
          <div class="gu-card-ic"><i class="fa-solid fa-hospital"></i></div>
          <div class="gu-card-title">Select a Provider <span style="font-size:.6875rem;font-weight:400;color:var(--faint)">(optional)</span></div>
        </div>
        <div class="gu-card-body">
          <div class="gf">
            <label class="gl">Service Type</label>
            <select class="gi" id="gSvc" onchange="filterGuestProviders()">
              <option value="">All Types</option>
              <option value="hospital" <?=$preType==='hospital'?'selected':''?>>Hospital / Clinic</option>
              <option value="doctor" <?=$preType==='doctor'?'selected':''?>>Doctor / Physician</option>
              <option value="telehealth">Telehealth</option>
            </select>
          </div>
          <div class="gf">
            <label class="gl">Specific Facility or Doctor</label>
            <select class="gi" id="gProvider">
              <option value="">— Any available —</option>
              <?php foreach($allProviders as $p):
                $label = htmlspecialchars($p['name']??'');
                if(!empty($p['hospital_name'])) $label .= ' · '.htmlspecialchars($p['hospital_name']);
                elseif(!empty($p['city'])) $label .= ' · '.htmlspecialchars($p['city']);
              ?>
              <option value="<?=(int)($p['id']??0)?>"
                data-name="<?=htmlspecialchars($p['name']??'')?>"
                data-type="<?=htmlspecialchars($p['type']??'')?>"
                data-source="<?=htmlspecialchars($p['source']??'')?>"
                <?=(int)($p['id']??0)===$preProviderId?'selected':''?>>
                <?=$label?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="gu-card">
        <div class="gu-card-head">
          <div class="gu-card-ic"><i class="fa-solid fa-user"></i></div>
          <div class="gu-card-title">Your Contact Information <span style="font-size:.6875rem;font-weight:700;color:var(--red)">Required</span></div>
        </div>
        <div class="gu-card-body">
          <div class="gf"><label class="gl">Full Name <span style="color:var(--red)">*</span></label><input class="gi" type="text" id="gName" placeholder="Your full name" autocomplete="name"></div>
          <div class="gg2">
            <div class="gf"><label class="gl">Phone <span style="color:var(--red)">*</span></label><input class="gi" type="tel" id="gPhone" placeholder="+254 700 000 000" autocomplete="tel"></div>
            <div class="gf"><label class="gl">Email <span style="color:var(--red)">*</span></label><input class="gi" type="email" id="gEmail" placeholder="you@example.com" autocomplete="email"></div>
          </div>
        </div>
      </div>

      <div class="gu-card">
        <div class="gu-card-head">
          <div class="gu-card-ic"><i class="fa-solid fa-calendar-days"></i></div>
          <div class="gu-card-title">Visit Details</div>
        </div>
        <div class="gu-card-body">
          <div class="gg2">
            <div class="gf"><label class="gl">Date</label><input class="gi" type="date" id="gDate" min="<?=date('Y-m-d')?>"></div>
            <div class="gf"><label class="gl">Time</label><input class="gi" type="time" id="gTime" value="09:00"></div>
          </div>
          <div class="gg2">
            <div class="gf"><label class="gl">Visit Type</label>
              <select class="gi" id="gLoc"><option value="in_person">In-Person</option><option value="telehealth">Telehealth</option><option value="home_visit">Home Visit</option></select>
            </div>
            <div class="gf"><label class="gl">Insurance</label>
              <select class="gi" id="gIns"><option value="">None / Self-pay</option><?php foreach($insuranceList as $ins): ?><option value="<?=$ins?>"><?=$ins?></option><?php endforeach; ?></select>
            </div>
          </div>
          <div class="gf"><label class="gl">Reason for Visit <span style="color:var(--red)">*</span></label><textarea class="gi" id="gReason" rows="3" placeholder="Describe your symptoms or reason…"></textarea></div>
        </div>
      </div>

      <button class="gu-submit" id="gBtn" onclick="submitGuestBooking()">
        <i class="fa-solid fa-paper-plane"></i>Send Booking Request
      </button>
      <p style="text-align:center;font-size:.6875rem;color:var(--faint);line-height:1.7;margin-top:8px">
        By submitting you agree to our <a href="/terms" style="color:var(--primary);text-decoration:none">Terms</a> and <a href="/privacy" style="color:var(--primary);text-decoration:none">Privacy Policy</a>.
      </p>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const ALL_PROVIDERS = <?=$providersJson?>;
const IS_LOGGED_IN  = <?=$isLoggedIn?'true':'false'?>;

/* ═══ LOGGED-IN: GRID RENDER ═══ */
let bkCurrentType = 'all';
const TYPE_COLORS = {hospital:'#1978e5',doctor:'#0d9488'};

function setBkType(type, btn) {
  bkCurrentType = type;
  document.querySelectorAll('.bk-ftab').forEach(b=>b.classList.remove('active'));
  if(btn) btn.classList.add('active');
  renderBkGrid();
}

function renderBkGrid() {
  const q = (document.getElementById('bkSearch')?.value||'').toLowerCase().trim();
  const grid = document.getElementById('bkGrid');
  const empty = document.getElementById('bkEmpty');
  if(!grid) return;

  let filtered = ALL_PROVIDERS.filter(p => {
    if(bkCurrentType !== 'all') {
      if(bkCurrentType === 'hospital' && p.type !== 'hospital') return false;
      if(bkCurrentType === 'doctor' && p.type !== 'doctor') return false;
    }
    if(q) {
      const hay = ((p.name||'') + ' ' + (p.specialty||'') + ' ' + (p.city||'') + ' ' + (p.hospital_name||'')).toLowerCase();
      if(!hay.includes(q)) return false;
    }
    return true;
  });

  const cnt = document.getElementById('bkCount');
  if(cnt) cnt.textContent = filtered.length + ' provider' + (filtered.length !== 1?'s':'') + ' found';

  if(!filtered.length) {
    grid.innerHTML = '';
    if(empty) empty.style.display = 'block';
    return;
  }
  if(empty) empty.style.display = 'none';

  grid.innerHTML = filtered.map(p => {
    const isDoc = p.type === 'doctor';
    const isHospDoc = (p.source||'') === 'hospital_doctor';
    const isStDoc = (p.source||'') === 'standalone_doctor';
    const isHosp = (p.source||'') === 'hospital_provider';
    const name = esc(p.name||'');
    const spec = esc(p.specialty||'');
    const city = esc(p.city||p.county||'');
    const fee = parseFloat(p.fee||0);
    const parts = (p.name||'').replace('Dr. ','').split(' ').filter(Boolean);
    const initials = parts.slice(0,2).map(w=>w[0]||'').join('').toUpperCase();

    let imgHtml = '';
    if(p.avatar_path) {
      imgHtml = `<div class="bk-card-img"><img src="${esc(p.avatar_path)}" alt="${name}" loading="lazy"></div>`;
    } else if(isHosp) {
      imgHtml = `<div class="bk-card-img" style="background:linear-gradient(135deg,#dbeafe,#e0f2fe)"><i class="fa-solid fa-hospital" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:48px;color:rgba(25,120,229,.25)"></i></div>`;
    } else {
      imgHtml = `<div class="bk-card-img"><div class="bk-card-initials">${initials}</div></div>`;
    }

    const badge = isHospDoc
      ? '<span class="bk-card-badge hosp-doc"><i class="fa-solid fa-hospital" style="font-size:7px;margin-right:2px"></i>Hospital Doctor</span>'
      : isStDoc
        ? '<span class="bk-card-badge verified" style="color:#7c3aed"><i class="fa-solid fa-user-doctor" style="font-size:7px;margin-right:2px"></i>Independent</span>'
        : '<span class="bk-card-badge verified"><i class="fa-solid fa-circle-check" style="font-size:7px;margin-right:2px"></i>Verified</span>';

    const hospLine = isHospDoc && p.hospital_name
      ? `<div class="bk-card-meta-row"><i class="fa-solid fa-hospital" style="color:#059669;font-size:9px"></i><span style="color:#059669;font-weight:600">${esc(p.hospital_name)}</span></div>`
      : isHosp
        ? `<div class="bk-card-meta-row"><i class="fa-solid fa-building" style="font-size:9px"></i>Healthcare Facility</div>`
        : '';

    const expLine = p.years_exp
      ? `<div class="bk-card-meta-row"><i class="fa-solid fa-briefcase" style="font-size:9px"></i>${parseInt(p.years_exp)} yrs experience</div>`
      : '';

    const locLine = city
      ? `<div class="bk-card-meta-row"><i class="fa-solid fa-location-dot" style="color:var(--primary);font-size:9px"></i>${city}</div>`
      : '';

    const feeStr = fee > 0 ? `<span style="font-size:.6875rem;font-weight:700;color:var(--teal)">KES ${fee.toLocaleString()}</span>` : `<span style="font-size:.6875rem;color:var(--faint)">Free / Varies</span>`;

    return `<div class="bk-card" onclick="openBkModal(${p.id},'${name.replace(/'/g,"\\'")}',${isHosp?'true':'false'},'${city.replace(/'/g,"\\'")}',${fee||'null'})">
      <div style="position:relative">${imgHtml}${badge}</div>
      <div class="bk-card-body">
        <div class="bk-card-name">${name}</div>
        ${spec?`<div class="bk-card-spec">${spec}</div>`:''}
        <div class="bk-card-meta">${hospLine}${expLine}${locLine}</div>
        <div class="bk-card-footer">
          ${feeStr}
          <button class="btn-book-card" onclick="event.stopPropagation();openBkModal(${p.id},'${name.replace(/'/g,"\\'")}',${isHosp?'true':'false'},'${city.replace(/'/g,"\\'")}',${fee||'null'})">
            <i class="fa-solid fa-calendar-plus" style="font-size:10px"></i>Book
          </button>
        </div>
      </div>
    </div>`;
  }).join('');
}

function esc(t){ return String(t||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* ═══ BOOKING MODAL STATE ═══ */
const BK = {step:1,reason:'',otherNote:'',date:null,time:null,visitType:'in_person',notes:'',ins:'',prov:null,calMonth:new Date(),availMeta:null};
const REASON_LABELS = {general_consultation:'General Consultation',followup:'Follow-up Visit',checkup:'Routine Check-up',specialist:'Specialist Referral',emergency:'Emergency',other:'Other'};

function openBkModal(id, name, isHospital, city, fee) {
  // Reset all state
  BK.step = 1; BK.reason = ''; BK.otherNote = ''; BK.date = null; BK.time = null;
  BK.visitType = 'in_person'; BK.notes = ''; BK.ins = ''; 
  BK.availMeta = null; // IMPORTANT: Clear previous data
  BK.prov = {id, name, isHospital: !!isHospital, city: city || '', fee: fee || null};

  // UI Resets
  document.getElementById('bkModalProv').textContent = name + (city ? ' · ' + city : '');
  ['bkP1','bkP2','bkP3','bkP4','bkP5'].forEach((sid, i) => {
    const el = document.getElementById(sid); 
    if (el) {
      el.classList.remove('active'); 
      if (i === 0) el.classList.add('active');
    }
  });

  document.getElementById('bkSuccess').style.display = 'none';
  document.getElementById('bkAlert').style.display = 'none';
  document.getElementById('bkNextBtn').style.display = '';
  document.getElementById('bkConfirmBtn').style.display = 'none';
  document.getElementById('bkBackBtn').style.display = 'none';

  // FETCH SCHEDULE IMMEDIATELY
  const svc = isHospital ? 'hospital' : 'doctor';
  fetch(`/api/patient/get-slots.php?provider_id=${id}&service_type=${svc}&date=${new Date().toISOString().split('T')[0]}&meta=1`)
    .then(r => r.json())
    .then(data => {
      BK.availMeta = data;
      if (BK.step === 2) bkRenderCal(); // Refresh if user is on Step 2
    });

  bkUpdateSteps();
  document.getElementById('bkModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeBkModal() {
  document.getElementById('bkModal').classList.remove('open');
  document.body.style.overflow='';
}

function bkUpdateSteps() {
  document.querySelectorAll('#bkStepsBar .bk-step').forEach((el,i) => {
    el.classList.remove('active','done');
    const dot = el.querySelector('.bk-step-dot');
    if(i+1 < BK.step){ el.classList.add('done'); if(dot) dot.innerHTML='<i class="fa-solid fa-check" style="font-size:.5rem"></i>'; }
    else if(i+1 === BK.step){ el.classList.add('active'); if(dot) dot.textContent=i+1; }
    else { if(dot) dot.textContent=i+1; }
    const line = el.querySelector('.bk-step-line');
    if(line) line.style.background = i+1<BK.step?'var(--green)':'var(--border)';
  });
  const back=document.getElementById('bkBackBtn'), next=document.getElementById('bkNextBtn'), conf=document.getElementById('bkConfirmBtn');
  if(back) back.style.display=BK.step>1?'':'none';
  if(next) next.style.display=BK.step<5?'':'none';
  if(conf) conf.style.display=BK.step===5?'':'none';
}

function bkShowPanel(s) {
  ['bkP1','bkP2','bkP3','bkP4','bkP5'].forEach((sid,i)=>{
    const el=document.getElementById(sid); if(el) el.classList.toggle('active',i+1===s);
  });
  document.getElementById('bkAlert').style.display='none';
  bkUpdateSteps();
  if(s===2) bkRenderCal();
  if(s===3) bkRenderSlots();
  if(s===5) bkRenderSummary();
}

function bkNext() {
  const a = document.getElementById('bkAlert');
  a.style.display='none';
  const err = m => { a.className='bk-modal-alert err'; a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> '+m; a.style.display='flex'; };
  if(BK.step===1) {
    BK.reason = document.getElementById('bkReason').value;
    if(!BK.reason){ err('Please select a reason for your visit.'); return; }
    BK.otherNote = document.getElementById('bkOtherNote').value.trim();
    if(BK.reason==='other'&&!BK.otherNote){ err('Please describe your reason.'); return; }
  }
  if(BK.step===2&&!BK.date){ err('Please select a date.'); return; }
  if(BK.step===3&&!BK.time){ err('Please select a time slot.'); return; }
  if(BK.step===4){ BK.notes=document.getElementById('bkNotes').value.trim(); BK.ins=document.getElementById('bkIns').value; }
  BK.step++; bkShowPanel(BK.step);
}
function bkPrev() { if(BK.step>1){ BK.step--; bkShowPanel(BK.step); } }
function bkSelType(btn,vt){ BK.visitType=vt; document.querySelectorAll('.bk-vt-btn').forEach(b=>b.classList.toggle('sel',b===btn)); }

async function bkRenderCal() {
  const today = new Date(); today.setHours(0,0,0,0);
  const yr = BK.calMonth.getFullYear(), mo = BK.calMonth.getMonth();
  const days = new Date(yr, mo + 1, 0).getDate(), first = new Date(yr, mo, 1);
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  document.getElementById('bkCalMonth').textContent = months[mo] + ' ' + yr;

  // 1. Get working days from metadata
  let availDows = null; 
  if (BK.availMeta && BK.availMeta.available_dows) {
      availDows = new Set(BK.availMeta.available_dows.map(Number));
  }

  // 2. Setup Header
  let h = ['Su','Mo','Tu','We','Th','Fr','Sa'].map(d => `<div class="bk-cal-dh">${d}</div>`).join('');
  
  // 3. Padding for start of month
  for (let i = 0; i < first.getDay(); i++) h += `<button class="bk-cal-d empty" disabled></button>`;

  // 4. Generate Days
  for (let d = 1; d <= days; d++) {
    const dt = new Date(yr, mo, d);
    const isPast = dt < today;
    const dow = dt.getDay(); 
    
    // STRICT LOGIC: 
    // If we have availDows, check it. 
    // If we DON'T have data yet, default to false (wait for load)
    const isWorkingDay = availDows ? availDows.has(dow) : false; 
    
    const avail = !isPast && isWorkingDay;

    const ds = `${yr}-${String(mo+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const isSel = BK.date === ds;
    const isToday = dt.toDateString() === today.toDateString();

    // The 'disabled' attribute here is what stops the user from picking wrong dates
    h += `<button class="bk-cal-d${isToday?' today':''}${avail?' avail':''}${isPast?' past':''}${!isWorkingDay&&!isPast?' no-avail':''}${isSel?' selected':''}" 
            ${!avail ? 'disabled' : ''} 
            onclick="bkSelDate('${ds}',this)">
            ${d}
          </button>`;
  }

  document.getElementById('bkCalGrid').innerHTML = h;
  
  // Update working hours hint
  const hintEl = document.getElementById('bkHint'), hintTxt = document.getElementById('bkHintTxt');
  if (BK.availMeta?.availability_summary?.length) {
    hintEl.style.display = 'flex';
    hintTxt.textContent = 'Hours: ' + BK.availMeta.availability_summary.map(s=>s.label).join(' · ');
  } else {
    hintEl.style.display = 'none';
  }

  document.getElementById('bkCalPrev').onclick = () => { BK.calMonth.setMonth(mo-1); bkRenderCal(); };
  document.getElementById('bkCalNext').onclick = () => { BK.calMonth.setMonth(mo+1); bkRenderCal(); };
}
function bkSelDate(d,btn){ BK.date=d; BK.time=null; document.querySelectorAll('#bkCalGrid .bk-cal-d').forEach(b=>b.classList.remove('selected')); btn.classList.add('selected'); }

/* Time slots */
async function bkRenderSlots() {
  const wrap=document.getElementById('bkSlotsWrap');
  wrap.innerHTML='<div style="text-align:center;padding:20px;color:var(--faint)"><i class="fa-solid fa-circle-notch fa-spin" style="font-size:20px;margin-bottom:8px;display:block"></i><div style="font-size:.75rem">Loading available slots…</div></div>';
  if(!BK.prov?.id||!BK.date){wrap.innerHTML='<div style="padding:14px;font-size:.8125rem;color:var(--faint)">Please select a date first.</div>';return;}
  try{
    const svc=BK.prov.isHospital?'hospital':'doctor';
    const r=await fetch(`/api/patient/get-slots.php?provider_id=${BK.prov.id}&date=${BK.date}&service_type=${svc}`,{credentials:'same-origin'}).then(r=>r.json());
    if(!r.success||!r.slots?.length){
      const hint=r.next_available_hint?`<div style="font-size:.75rem;color:var(--primary);margin-top:6px"><i class="fa-regular fa-calendar"></i> Next available: <strong>${r.next_available_hint}</strong></div>`:'';
      const avail=r.availability_summary?.length?`<div style="font-size:.75rem;color:var(--faint);margin-top:4px">Working hours: ${r.availability_summary.map(s=>s.label).join(' · ')}</div>`:'';
      wrap.innerHTML=`<div style="text-align:center;padding:22px;color:var(--faint)"><i class="fa-solid fa-calendar-xmark" style="font-size:26px;margin-bottom:10px;display:block;opacity:.4"></i><div style="font-size:.8125rem;font-weight:600">${r.message||'No slots available on this date.'}</div></div>${hint}${avail}`;
      return;
    }
    if(r.availability_summary?.length){
      wrap.innerHTML=`<div style="font-size:.6875rem;color:var(--primary);font-weight:600;margin-bottom:10px;display:flex;align-items:center;gap:5px"><i class="fa-regular fa-clock"></i>Working hours: ${r.availability_summary.map(s=>s.label).join(' · ')}</div>`;
    } else { wrap.innerHTML=''; }
    const mkSlot=t=>{const[h,m]=t.split(':').map(Number),ap=h<12?'AM':'PM',h12=h>12?h-12:(h||12);const sel=BK.time===t;return`<button class="bk-slot${sel?' selected':''}" onclick="bkSelSlot('${t}',this)">${h12}:${String(m).padStart(2,'0')} ${ap}</button>`;};
    const g=r.grouped||{morning:[],afternoon:[],evening:[]};
    let html='';
    if(g.morning?.length) html+=`<div class="bk-slot-period"><i class="fa-regular fa-sun" style="color:var(--yellow)"></i>Morning</div><div class="bk-slots-grid">${g.morning.map(mkSlot).join('')}</div>`;
    if(g.afternoon?.length) html+=`<div class="bk-slot-period"><i class="fa-solid fa-sun" style="color:var(--primary)"></i>Afternoon</div><div class="bk-slots-grid">${g.afternoon.map(mkSlot).join('')}</div>`;
    if(g.evening?.length) html+=`<div class="bk-slot-period"><i class="fa-solid fa-moon" style="color:#7c3aed"></i>Evening</div><div class="bk-slots-grid">${g.evening.map(mkSlot).join('')}</div>`;
    wrap.innerHTML += html || '<div style="padding:14px;font-size:.8125rem;color:var(--faint);text-align:center">No available slots.</div>';
  }catch(e){wrap.innerHTML='<div style="color:var(--red);font-size:.8125rem;padding:12px"><i class="fa-solid fa-triangle-exclamation"></i> Could not load slots. Please try again.</div>';}
}
function bkSelSlot(t,btn){ BK.time=t; document.querySelectorAll('.bk-slot').forEach(b=>b.classList.remove('selected')); btn.classList.add('selected'); }

/* Summary */
function bkRenderSummary() {
  const p=BK.prov||{};
  document.getElementById('bkSumProv').textContent=p.name||'—';
  const dt=BK.date?new Date(BK.date+'T00:00'):'';
  document.getElementById('bkSumDate').textContent=dt?dt.toLocaleDateString('en-KE',{weekday:'long',month:'long',day:'numeric'}):'—';
  const[h,m]=(BK.time||'').split(':').map(Number);
  document.getElementById('bkSumTime').textContent=BK.time?`${h>12?h-12:(h||12)}:${String(m).padStart(2,'0')} ${h<12?'AM':'PM'}`:'—';
  document.getElementById('bkSumType').innerHTML=BK.visitType==='telehealth'?'<i class="fa-solid fa-video" style="color:var(--primary)"></i> Telehealth':'<i class="fa-solid fa-house-medical" style="color:var(--green)"></i> In-Person';
  document.getElementById('bkSumReason').textContent=REASON_LABELS[BK.reason]||BK.reason;
  const fr=document.getElementById('bkSumFeeRow');
  if(fr){ if(p.fee&&!p.isHospital){ fr.style.display=''; document.getElementById('bkSumFee').textContent='KES '+Number(p.fee).toLocaleString(); } else fr.style.display='none'; }
}

/* Confirm booking */
async function bkConfirm() {
  const btn=document.getElementById('bkConfirmBtn');
  const a=document.getElementById('bkAlert'); a.style.display='none';
  if(!BK.date||!BK.time){ a.className='bk-modal-alert err'; a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> Missing date or time.'; a.style.display='flex'; return; }
  btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Booking…';
  const[h,m]=BK.time.split(':').map(Number);
  try{
    const r=await fetch('/api/patient/book-appointment.php',{
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body:JSON.stringify({
        csrf_token:document.getElementById('bkCsrf').value,
        service_type:BK.prov?.isHospital?'hospital':'doctor',
        provider_id:BK.prov?.id||null,
        appointment_at:BK.date+' '+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':00',
        title:REASON_LABELS[BK.reason]||BK.reason,
        notes:BK.otherNote||BK.notes,
        location_type:BK.visitType,
        insurance:BK.ins
      })
    }).then(r=>r.json());
    if(r.requires_login){ window.location.href=r.redirect||'/patients/login.php'; return; }
    if(r.success){
      document.getElementById('bkRef').textContent=r.booking_ref||('PZY-'+String(r.appointment_id||'').padStart(6,'0'));
      ['bkP1','bkP2','bkP3','bkP4','bkP5'].forEach(sid=>{const el=document.getElementById(sid);if(el){el.classList.remove('active');el.style.display='none';}});
      document.getElementById('bkStepsBar').style.display='none';
      document.getElementById('bkFooter').style.display='none';
      document.getElementById('bkSuccess').style.display='block';
    } else {
      a.className='bk-modal-alert err'; a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> '+(r.message||'Booking failed.'); a.style.display='flex';
    }
  }catch(e){
    a.className='bk-modal-alert err'; a.innerHTML='<i class="fa-solid fa-wifi"></i> Network error.'; a.style.display='flex';
  }finally{
    btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-calendar-check"></i> Confirm Booking';
  }
}

/* ═══ GUEST BOOKING ═══ */
function filterGuestProviders() {
  const svc = document.getElementById('gSvc')?.value||'';
  document.querySelectorAll('#gProvider option').forEach(o=>{
    if(!o.value) return;
    const t=(o.getAttribute('data-type')||'').toLowerCase();
    const s=(o.getAttribute('data-source')||'').toLowerCase();
    const show = !svc || (svc==='hospital'&&(t==='hospital'||s.includes('hospital'))) || (svc==='doctor'&&t==='doctor') || svc==='telehealth';
    o.style.display=show?'':'none';
  });
  const gp=document.getElementById('gProvider'); if(gp) gp.value='';
}

async function submitGuestBooking(){
  const btn=document.getElementById('gBtn');
  const name=(document.getElementById('gName')?.value||'').trim();
  const phone=(document.getElementById('gPhone')?.value||'').trim();
  const email=(document.getElementById('gEmail')?.value||'').trim();
  const reason=(document.getElementById('gReason')?.value||'').trim();
  if(!name||!phone||!email||!reason){showGuestAlert('err','Please fill all required fields.');return;}
  btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Sending…';
  const provSel=document.getElementById('gProvider');
  const provOpt=provSel?.options[provSel.selectedIndex];
  try{
    const r=await fetch('/api/patient/guest-book.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        csrf_token:document.getElementById('gCsrf').value,
        guest_name:name,guest_email:email,guest_phone:phone,
        service_type:document.getElementById('gSvc')?.value||'hospital',
        provider_id:provSel?.value||null,
        provider_name:provOpt?.getAttribute('data-name')||'',
        appointment_at:(document.getElementById('gDate')?.value||'')+' '+(document.getElementById('gTime')?.value||'09:00')+':00',
        location_type:document.getElementById('gLoc')?.value||'in_person',
        insurance:document.getElementById('gIns')?.value||'',
        reason:reason
      })
    }).then(r=>r.json());
    if(r.success){
      document.getElementById('gForm').style.display='none';
      document.getElementById('gSuccess').style.display='block';
      document.getElementById('gRef').textContent=r.booking_ref||'PZY-XXXXXX';
      window.scrollTo({top:0,behavior:'smooth'});
    } else { showGuestAlert('err',r.message||'Could not submit request.'); }
  }catch(e){ showGuestAlert('err','Network error. Please check your connection.'); }
  finally{ btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-paper-plane"></i>Send Booking Request'; }
}

function showGuestAlert(type,msg){
  const el=document.getElementById('gAlert'); if(!el)return;
  el.className='gu-alert '+type;
  el.innerHTML=(type==='err'?'<i class="fa-solid fa-circle-exclamation"></i> ':'<i class="fa-solid fa-check-circle"></i> ')+msg;
  el.style.display='flex';
}

/* ═══ INIT ═══ */
document.addEventListener('DOMContentLoaded',()=>{
  if(IS_LOGGED_IN){ renderBkGrid(); setBkType('all', document.querySelector('.bk-ftab[data-type="all"]')); }
  else { filterGuestProviders(); }
});
</script>
</body>
</html>
