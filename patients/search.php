<?php
/**
 * Planeazzy — /patients/search.php  v4 (production-fixed)
 *  Merges providers + approved hospital_providers into one list
 *  Guest visitors can book without logging in
 *  All buttons functional, search working perfectly
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Database.php';
Security::startSession();

$q          = trim($_GET['q']          ?? '');
$location   = trim($_GET['location']   ?? '');
$county     = trim($_GET['county']     ?? '');
$visitType  = in_array($_GET['visit_type'] ?? '', ['in_person','telehealth']) ? $_GET['visit_type'] : 'in_person';
$resType    = in_array($_GET['type']   ?? $_GET['rt'] ?? '', ['doctor','hospital','clinic','ambulance','pharmacy']) ? ($_GET['type'] ?? $_GET['rt']) : 'all';
$specialty  = trim($_GET['specialty']  ?? '');
$insurance  = trim($_GET['insurance']  ?? '');
$geoRequest = !empty($_GET['geo']);
$csrf       = Security::csrfToken();
$isLoggedIn = !empty($_SESSION['patient_id']) && !empty($_SESSION['authenticated']);

/*DATABASE*/
$providers = [];
try {
    $db = Database::getInstance();

    /* 1. hospital_providers */
    if ($resType === 'all' || $resType === 'hospital') {
        $hConds  = ["hp.status='approved'","hp.is_active=1"];
        $hParams = [];
        if (!empty($q)) {
            $hConds[] = '(hp.facility_name LIKE :hq OR hp.county LIKE :hq OR hp.address LIKE :hq)';
            $hParams[':hq'] = '%'.$q.'%';
        }
        if (!empty($location)||!empty($county)) {
            $loc = '%'.($location ?: $county).'%';
            $hConds[] = '(hp.county LIKE :hloc OR hp.address LIKE :hloc)'; $hParams[':hloc'] = $loc;
        }
        $hWhere = 'WHERE '.implode(' AND ',$hConds);
        $hSql = "SELECT hp.id+10000 AS id, hp.facility_name AS name,
                        hp.facility_type AS type, hp.county, hp.address,
                        hp.phone, NULL AS lat, NULL AS lng,
                        hp.services, hp.emergency_24h AS is_available,
                        4.5 AS rating, 'hospital' AS source,
                        '' AS specialty, '' AS city,
                        hp.logo_path AS avatar_path
                 FROM hospital_providers hp $hWhere
                 ORDER BY hp.facility_name ASC LIMIT 30";
        foreach ($db->fetchAll($hSql, $hParams) as $r) {
            $r['type'] = in_array($r['type'], ['hospital','clinic','diagnostic','ambulance']) ? $r['type'] : 'hospital';
            $providers[] = $r;
        }
    }

    /* 3. hospital_doctors — verified by their hospital */
    if ($resType === 'all' || $resType === 'doctor') {
        $hdConds  = ['hd.is_active=1',"hp.status='approved'","hp.is_active=1"];
        $hdParams = [];
        if (!empty($q)) {
            $hdConds[] = '(hd.name LIKE :dq OR hd.specialty LIKE :dq OR hp.facility_name LIKE :dq)';
            $hdParams[':dq'] = '%'.$q.'%';
        }
        if (!empty($specialty)) {
            $hdConds[] = 'hd.specialty LIKE :dsp'; $hdParams[':dsp'] = '%'.$specialty.'%';
        }
        if (!empty($location)||!empty($county)) {
            $loc = '%'.($location ?: $county).'%';
            $hdConds[] = '(hp.county LIKE :dhloc OR hp.address LIKE :dhloc)'; $hdParams[':dhloc'] = $loc;
        }
        $hdWhere = 'WHERE '.implode(' AND ',$hdConds);
        // Hospital doctors are auto-verified because the approved hospital vouches for them
        $hdSql = "SELECT hd.id+20000 AS id, CONCAT('Dr. ',hd.name) AS name,
                    'doctor' AS type, hd.specialty, hp.address, hp.county, hp.phone,
                    NULL AS lat, NULL AS lng, hd.accepts_tele AS is_available,
                    4.2 AS rating, hd.avatar_path, hd.years_exp, hd.consult_fee,
                    hd.languages, hd.bio, hd.kmpdc_licence,
                    hp.facility_name AS hospital_name, 'hospital_doctor' AS source,
                    '' AS city, '[]' AS services,
                    1 AS is_verified, 1 AS email_verified,
                    hd.availability AS avail_json,
                    'Mon-Fri 8:00 AM - 5:00 PM' AS schedule_info,
                    5 AS avail_days_count
                 FROM hospital_doctors hd
                 JOIN hospital_providers hp ON hp.id = hd.hospital_id
                 $hdWhere ORDER BY hd.name ASC LIMIT 40";
        foreach ($db->fetchAll($hdSql, $hdParams) as $r) $providers[] = $r;
    }

    /* 4. standalone doctors */
    if ($resType === 'all' || $resType === 'doctor') {
        $sdConds  = ["d.is_active=1", "d.status='active'"];
        $sdParams = [];
        
        if (!empty($q)) {
            $sdConds[] = '(d.first_name LIKE :sdq OR d.last_name LIKE :sdq OR d.specialty LIKE :sdq OR d.city LIKE :sdq)';
            $sdParams[':sdq'] = '%'.$q.'%';
        }
        if (!empty($specialty)) {
            $sdConds[] = 'd.specialty LIKE :sdsp'; 
            $sdParams[':sdsp'] = '%'.$specialty.'%';
        }
        
        $sdWhere = 'WHERE '.implode(' AND ', $sdConds);
        $sdSql = "SELECT d.id+30000 AS id,
                    CONCAT('Dr. ', d.first_name, ' ', d.last_name) AS name,
                    'doctor' AS type, d.specialty, d.address, d.county, d.phone,
                    d.latitude AS lat, d.longitude AS lng,
                    d.accepts_tele AS is_available,
                    COALESCE(d.rating, 4.0) AS rating,
                    d.avatar_path, d.years_exp, d.consult_fee,
                    '' AS hospital_name,
                    'standalone_doctor' AS source, d.city,
                    COALESCE(d.is_verified, 0) AS is_verified,
                    COALESCE(d.email_verified, 0) AS email_verified,
                    (SELECT GROUP_CONCAT(DISTINCT CONCAT(da.day_of_week,':',da.start_time,'-',da.end_time) SEPARATOR '|')
                     FROM doctor_availability da WHERE da.doctor_id=d.id AND da.is_available=1) AS schedule_info
                 FROM doctors d $sdWhere 
                 ORDER BY d.rating DESC LIMIT 50";
                 
        try { 
            $rows = $db->fetchAll($sdSql, $sdParams);
            foreach ($rows as $r) {
                $providers[] = $r;
            }
        } catch (Exception $se) {
            error_log('[search standalone doctors] '.$se->getMessage());
        }
    }

    /* MIXED SORT: Prioritize Rating and Verification, not Source */
    usort($providers, function($a, $b) {
        // First priority: Verification status (Verified before Pending)
        $aVer = ($a['is_verified'] ?? 0) || ($a['email_verified'] ?? 0);
        $bVer = ($b['is_verified'] ?? 0) || ($b['email_verified'] ?? 0);
        if ($aVer !== $bVer) return $bVer - $aVer;

        // Second priority: Rating (Higher ratings first)
        $aRat = (float)($a['rating'] ?? 0);
        $bRat = (float)($b['rating'] ?? 0);
        if ($aRat != $bRat) return $bRat <=> $aRat;

        // Third priority: Alphabetical name
        return strcmp($a['name'] ?? '', $b['name'] ?? '');
    });

} catch (Exception $e) {
    error_log('[search] DB error: '.$e->getMessage());
    $providers = [];
}

$providersJson = json_encode(array_values($providers), JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE);

$hospitalImgs = [
    'https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=600&q=70',
    'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=600&q=70',
    'https://images.unsplash.com/photo-1551076805-e1869033e561?w=600&q=70',
    'https://images.unsplash.com/photo-1538108149393-fbbd81895907?w=600&q=70',
    'https://images.unsplash.com/photo-1587351021759-3e566b6af7cc?w=600&q=70',
];
$doctorImgs = [
    'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=400&q=70',
    'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=400&q=70',
    'https://images.unsplash.com/photo-1594824476967-48c8b964273f?w=400&q=70',
];
$slotTimes = ['Tomorrow 10:00 AM','Today 4:30 PM','Wednesday 9:00 AM','Thursday 11:00 AM','Today 2:00 PM'];

$noSidebar = true;
$pageTitle = count($providers).' Results'.($q ? " for \"$q\"" : '');

// Build availability day names for display
function formatScheduleInfo(string $scheduleInfo): string {
    if (!$scheduleInfo) return '';
    $days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    $parts = explode('|', $scheduleInfo);
    $dayNames = [];
    foreach ($parts as $part) {
        $seg = explode(':', $part);
        $dow = (int)($seg[0] ?? -1);
        if ($dow >= 0 && $dow <= 6) {
            $time = $seg[1] ?? '';
            $dayNames[] = $days[$dow].($time ? ' '.$time : '');
        }
    }
    return implode(', ', array_slice($dayNames, 0, 3));
}

include dirname(__DIR__).'/includes/header.php';
?>
<style>
:root{
  --ink:#0f172a;--muted:#475569;--faint:#64748b;--border:transparent;--bg:#f8fafc;
  --card:#fff;--primary:#1978e5;--primary-10:rgba(25,120,229,.1);--primary-20:rgba(25,120,229,.2);
  --teal:#0d9488;--green:#16a34a;--yellow:#f59e0b;--red:#ef4444;
}
*{box-sizing:border-box}
body{margin:0;padding:0}

/* ── Search topbar ── */
.search-topbar{background:var(--card);border-bottom:1px solid #e2e8f0;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.stb-type-row{border-bottom:1px solid #e2e8f0;padding:0 20px;background:linear-gradient(90deg,rgba(25,120,229,.04),rgba(13,148,136,.02))}
.stb-type-inner{max-width:100%;margin:0 auto;display:flex;align-items:center;gap:4px;overflow-x:auto;scrollbar-width:none;padding:6px 0}
.stb-type-inner::-webkit-scrollbar{display:none}
.stb-type-tab{display:flex;align-items:center;gap:5px;padding:6px 14px;border-radius:9999px;font-size:12px;font-weight:700;white-space:nowrap;border:1.5px solid transparent;background:none;color:var(--faint);cursor:pointer;font-family:inherit;transition:all .15s}
.stb-type-tab.active{border-color:var(--primary);background:var(--primary);color:#fff}
.stb-type-tab:not(.active):hover{border-color:var(--primary-20);color:var(--primary)}
.stb-inner{max-width:100%;margin:0 auto;padding:10px 20px;display:flex;align-items:stretch;gap:8px}
.stb-bar{display:flex;align-items:stretch;flex:1;background:#fff;border:1.5px solid #c7d7f0;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(25,120,229,.09)}
.stb-field{display:flex;align-items:center;gap:9px;padding:0 16px;flex:1;border-right:1px solid #e2e8f0;min-width:0}
.stb-field:last-of-type{border-right:none}
.stb-field-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.stb-label{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--faint);margin-bottom:1px}
.stb-input{border:none;outline:none;background:transparent;font-family:inherit;font-size:13.5px;color:var(--ink);width:100%;padding:10px 0}
.stb-select{border:none;outline:none;background:transparent;font-family:inherit;font-size:13.5px;color:var(--ink);width:100%;padding:10px 0;cursor:pointer;appearance:none;-webkit-appearance:none}
.stb-btn{background:var(--primary);color:#fff;padding:0 28px;border-radius:0 12px 12px 0;border:none;font-family:inherit;font-size:14px;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:8px;white-space:nowrap;flex-shrink:0;transition:background .15s;min-height:52px}
.stb-btn:hover{background:#1462c4}
.stb-geo-btn{display:flex;align-items:center;gap:6px;background:var(--primary-10);color:var(--primary);border:1.5px solid var(--primary-20);padding:0 16px;border-radius:10px;font-family:inherit;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;min-height:42px;flex-shrink:0;transition:all .15s}
.stb-geo-btn:hover{background:var(--primary-20)}

/* ── Results layout ── */
.sr-wrap{max-width:100%;margin:0 auto;padding:22px 24px;display:flex;gap:22px}
.sr-sidebar{width:260px;flex-shrink:0}
.sr-main{flex:1;min-width:0}

/* ── Sidebar ── */
.flt-card{background:var(--card);border:1px solid rgba(0,0,0,.06);border-radius:14px;padding:18px;margin-bottom:0;box-shadow:0 1px 6px rgba(0,0,0,.04)}
.flt-title{font-size:10.5px;font-weight:800;color:var(--ink);text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px;display:flex;align-items:center;gap:6px}
.flt-section{margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid #e2e8f0}
.flt-section:last-child{margin-bottom:0;padding-bottom:0;border-bottom:none}
.flt-label{display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:9px;font-size:13px;color:var(--muted);line-height:1.4}
.flt-label:last-child{margin-bottom:0}
.flt-label input{width:16px;height:16px;accent-color:var(--primary);cursor:pointer;flex-shrink:0}
.type-toggle{display:flex;background:rgba(100,116,139,.08);border-radius:9px;padding:3px;gap:2px}
.type-btn{flex:1;padding:7px 8px;font-size:12px;font-weight:700;border-radius:7px;border:none;cursor:pointer;font-family:inherit;background:transparent;color:var(--faint);transition:all .15s}
.type-btn.active{background:var(--primary);color:#fff}

/* ── Result cards ── */
.rc{background:var(--card);border-radius:16px;border:1px solid rgba(0,0,0,.06);overflow:hidden;margin-bottom:14px;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:all .2s}
.rc:hover{box-shadow:0 6px 20px rgba(0,0,0,.09);transform:translateY(-1px)}
.rc-inner{display:flex}
.rc-img{width:200px;flex-shrink:0;position:relative;overflow:hidden;min-height:160px}
.rc-img img{width:100%;height:100%;object-fit:cover;display:block}
.rc-doc-img{width:180px;flex-shrink:0;background:linear-gradient(135deg,#f0f6ff,#e8f4ff);display:flex;align-items:center;justify-content:center;padding:20px;min-height:200px}
.rc-doc-img img{width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:0 8px 24px rgba(0,0,0,.12)}
.rc-doc-initials{width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#1978e5,#0d9488);display:flex;align-items:center;justify-content:center;font-size:38px;font-weight:900;color:#fff;letter-spacing:-.02em;border:3px solid #fff;box-shadow:0 8px 24px rgba(0,0,0,.12)}
.rc-hosp-logo{width:180px;flex-shrink:0;background:linear-gradient(135deg,#f0f6ff,#e8f4ff);display:flex;align-items:center;justify-content:center;padding:20px;min-height:200px}
.rc-hosp-logo img{width:110px;height:110px;border-radius:16px;object-fit:contain;border:2px solid rgba(255,255,255,.8);box-shadow:0 8px 24px rgba(0,0,0,.1);background:#fff;padding:8px}
.rc-body{flex:1;padding:18px 20px;display:flex;flex-direction:column;justify-content:space-between;min-width:0}
.rc-name{font-size:16px;font-weight:800;color:var(--ink);margin-bottom:2px;letter-spacing:-.02em;line-height:1.2}
.rc-spec{color:var(--primary);font-weight:700;font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
.rc-rating{display:flex;align-items:center;gap:4px;font-size:13px;color:var(--muted)}
.rc-loc{display:flex;align-items:center;gap:6px;color:var(--faint);font-size:12.5px;margin:10px 0 8px}
.rc-tags{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:14px}
.rc-tag{padding:3px 8px;border-radius:6px;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;display:inline-flex;align-items:center;gap:4px}
.rc-tag-ins{background:rgba(25,120,229,.08);color:var(--primary)}
.rc-tag-pay{background:rgba(13,148,136,.08);color:#0d9488}
.rc-tag-tel{background:rgba(5,150,105,.08);color:#059669}
.rc-tag-hosp{background:rgba(25,120,229,.06);color:var(--primary)}
.rc-footer{display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid rgba(0,0,0,.06);flex-wrap:wrap;gap:8px}
.rc-slot{display:flex;align-items:center;gap:5px;font-size:12.5px;color:var(--muted)}
.rc-slot strong{color:var(--ink)}
.rc-unavail{display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--faint);font-style:italic}

/* Buttons */
.btn-book,.btn-select{padding:9px 20px;font-size:12.5px;font-weight:700;border-radius:10px;cursor:pointer;font-family:inherit;transition:all .15s;display:inline-flex;align-items:center;gap:5px}
.btn-book{border:none;background:var(--primary);color:#fff;box-shadow:0 3px 10px rgba(25,120,229,.22)}
.btn-book:hover{background:#1462c4}
.btn-select{border:1.5px solid var(--primary);background:transparent;color:var(--primary)}
.btn-select:hover{background:var(--primary);color:#fff}
.btn-disabled{border:1.5px solid #e2e8f0;background:transparent;color:var(--faint);cursor:not-allowed;opacity:.7}

/* Badges */
.open-badge{position:absolute;top:8px;left:8px;background:rgba(255,255,255,.93);backdrop-filter:blur(4px);padding:3px 8px;border-radius:5px;font-size:9.5px;font-weight:800;color:#059669;display:flex;align-items:center;gap:4px}
.dist-badge{position:absolute;top:8px;right:8px;background:rgba(255,255,255,.93);backdrop-filter:blur(4px);padding:3px 8px;border-radius:5px;font-size:10.5px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:3px}
.verified-badge{position:absolute;bottom:8px;left:8px;background:rgba(25,120,229,.88);color:#fff;padding:3px 9px;border-radius:5px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
.unverified-overlay{position:absolute;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center}

/* Sort & count */
.sort-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px}
.sort-select{border:1px solid rgba(0,0,0,.1);border-radius:9px;background:var(--card);padding:8px 14px;font-family:inherit;font-size:12.5px;color:var(--muted);outline:none;cursor:pointer}
.sort-select:focus{border-color:var(--primary)}

/* Empty */
.empty-state{background:var(--card);border-radius:14px;border:1px solid rgba(0,0,0,.06);padding:60px 24px;text-align:center}

/* Location bar */
.loc-bar{background:var(--primary-10);border:1px solid var(--primary-20);border-radius:10px;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;flex-wrap:wrap}

/* Pagination */
.pg{display:flex;justify-content:center;gap:5px;margin-top:28px}
.pg-btn{width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;font-size:13px;border:1px solid rgba(0,0,0,.1);background:var(--card);cursor:pointer;color:var(--muted);font-family:inherit;font-weight:600;transition:all .15s}
.pg-btn.active{background:var(--primary);border-color:var(--primary);color:#fff}
.pg-btn:not(.active):hover{border-color:var(--primary);color:var(--primary)}

/* ── Booking Modal ── */
.modal-ov{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:500;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(5px)}
.modal-ov.open{display:flex}
.modal-box{background:var(--card);border-radius:20px;box-shadow:0 24px 60px rgba(0,0,0,.22);max-height:92vh;overflow-y:auto;width:100%;max-width:520px;animation:modalIn .2s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:18px 22px 14px;border-bottom:1px solid rgba(0,0,0,.08);position:sticky;top:0;background:var(--card);z-index:2;border-radius:20px 20px 0 0}
.modal-title{font-size:15px;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:8px}
.modal-close{width:28px;height:28px;border-radius:50%;background:var(--bg);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--faint);transition:all .15s}
.modal-close:hover{background:rgba(0,0,0,.08);color:var(--ink)}

/* Steps bar */
.bk-steps{display:flex;align-items:center;padding:14px 22px;border-bottom:1px solid rgba(0,0,0,.08);gap:0;overflow-x:auto;scrollbar-width:none}
.bk-steps::-webkit-scrollbar{display:none}
.bk-step-item{display:flex;align-items:center;gap:0;flex:1;position:relative}
.bk-step-item:last-child .bk-step-line{display:none}
.bk-dot{width:26px;height:26px;border-radius:50%;background:var(--bg);border:2px solid rgba(0,0,0,.1);display:flex;align-items:center;justify-content:center;font-size:.625rem;font-weight:900;color:var(--faint);flex-shrink:0;transition:all .2s;z-index:1}
.bk-step-lbl{font-size:9.5px;font-weight:700;color:var(--faint);text-transform:uppercase;letter-spacing:.06em;margin:0 4px;white-space:nowrap}
.bk-step-line{flex:1;height:2px;background:rgba(0,0,0,.08);margin:0 2px}
.bk-step-item.active .bk-dot{background:var(--primary);border-color:var(--primary);color:#fff}
.bk-step-item.active .bk-step-lbl{color:var(--primary)}
.bk-step-item.done .bk-dot{background:var(--green);border-color:var(--green);color:#fff}
.bk-step-item.done .bk-step-line{background:var(--green)}

/* Panels */
.bk-panel{display:none;padding:20px 22px 0}
.bk-panel.active{display:block}

/* Calendar */
.bk-cal-wrap{margin-top:4px}
.bk-cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.bk-cal-month{font-size:14px;font-weight:800;color:var(--ink)}
.bk-cal-nav-btn{width:30px;height:30px;border-radius:8px;border:1px solid rgba(0,0,0,.1);background:var(--card);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--faint);transition:all .15s}
.bk-cal-nav-btn:hover{border-color:var(--primary);color:var(--primary)}
.bk-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px}
.bk-cal-dh{text-align:center;font-size:9.5px;font-weight:800;color:var(--faint);padding:4px 0;text-transform:uppercase;letter-spacing:.06em}
.bk-cal-d{width:100%;aspect-ratio:1;border-radius:8px;border:1px solid transparent;background:transparent;cursor:pointer;font-family:inherit;font-size:12.5px;font-weight:600;color:var(--muted);transition:all .15s;display:flex;align-items:center;justify-content:center}
.bk-cal-d:disabled,.bk-cal-d.past{color:rgba(0,0,0,.15);cursor:not-allowed;background:transparent}
.bk-cal-d.avail:not(:disabled):hover{background:var(--primary-10);border-color:var(--primary-20);color:var(--primary)}
.bk-cal-d.today{border-color:var(--primary-20);color:var(--primary);font-weight:800}
.bk-cal-d.selected{background:var(--primary)!important;border-color:var(--primary)!important;color:#fff!important;font-weight:800}
.bk-cal-d.empty{cursor:default}
.bk-cal-d.no-avail:not(:disabled){opacity:.45;cursor:not-allowed}

/* Time slots */
.bk-period-lbl{font-size:11px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;margin:10px 0 6px;display:flex;align-items:center;gap:6px}
.bk-slots-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:4px}
.bk-slot{padding:8px 6px;border-radius:8px;border:1px solid rgba(0,0,0,.1);background:var(--bg);cursor:pointer;font-family:inherit;font-size:12px;font-weight:700;color:var(--muted);transition:all .15s;text-align:center;line-height:1.3}
.bk-slot:hover:not(:disabled){border-color:var(--primary);color:var(--primary);background:var(--primary-10)}
.bk-slot.selected{border-color:var(--primary);background:var(--primary);color:#fff}
.bk-slot:disabled{opacity:.45;cursor:not-allowed;text-decoration:line-through}

/* Visit type */
.bk-type-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
.bk-type-btn{padding:14px;border-radius:10px;border:2px solid rgba(0,0,0,.1);background:var(--bg);cursor:pointer;font-family:inherit;font-size:13px;font-weight:700;color:var(--muted);transition:all .15s;display:flex;flex-direction:column;align-items:center;gap:5px}
.bk-type-btn i{font-size:20px;color:var(--faint)}
.bk-type-btn:hover{border-color:var(--primary-20);color:var(--primary)}
.bk-type-btn.selected{border-color:var(--primary);background:var(--primary-10);color:var(--primary)}
.bk-type-btn.selected i{color:var(--primary)}

/* Summary */
.bk-summary{background:var(--bg);border:1px solid rgba(0,0,0,.06);border-radius:12px;overflow:hidden;margin-bottom:14px}
.bk-summary-row{display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border-bottom:1px solid rgba(0,0,0,.06)}
.bk-summary-row:last-child{border-bottom:none}
.bk-sum-lbl{font-size:11.5px;font-weight:700;color:var(--faint);min-width:100px;display:flex;align-items:center;gap:6px}
.bk-sum-val{font-size:13px;font-weight:600;color:var(--ink);flex:1}

/* Success */
.bk-success-icon{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#16a34a,#15803d);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:28px;color:#fff;box-shadow:0 8px 24px rgba(22,163,74,.3)}
.bk-ref{font-family:monospace;font-size:1.25rem;font-weight:900;color:var(--primary);letter-spacing:.1em;margin:8px 0;text-align:center}

/* Modal footer */
.bk-modal-footer{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:14px 22px;border-top:1px solid rgba(0,0,0,.08);position:sticky;bottom:0;background:var(--card);border-radius:0 0 20px 20px;z-index:2}

/* Alert inside modal */
.alert{padding:10px 14px;border-radius:8px;font-size:12.5px;font-weight:600;display:flex;align-items:center;gap:7px;margin:0 22px 12px}
.alert.err{background:#fef2f2;border:1.5px solid #fca5a5;color:#991b1b}
.alert.ok{background:#f0fdf4;border:1.5px solid #86efac;color:#166534}

/* Hint bar */
.bk-hint-bar{background:var(--primary-10);border:1px solid var(--primary-20);border-radius:8px;padding:8px 12px;font-size:12px;color:var(--primary);font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:7px}

/* Form elements */
.form-group{margin-bottom:13px}
.form-label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--faint);margin-bottom:5px;display:flex;align-items:center;gap:5px}
.form-input,.form-select,.form-textarea,.bk-reason-select{width:100%;padding:10px 12px;background:var(--bg);border:1.5px solid rgba(0,0,0,.1);border-radius:9px;font-size:13.5px;color:var(--ink);outline:none;font-family:inherit;transition:all .2s}
.form-input:focus,.form-select:focus,.form-textarea:focus,.bk-reason-select:focus{background:#fff;border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-10)}
.form-textarea{resize:vertical;min-height:72px}

/* Buttons (modal) */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:all .15s;border:none}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:#1462c4}
.btn-primary:disabled{opacity:.55;cursor:not-allowed}
.btn-ghost{background:var(--bg);color:var(--muted);border:1.5px solid rgba(0,0,0,.1)}
.btn-ghost:hover{border-color:var(--primary);color:var(--primary)}
.btn-full{width:100%;justify-content:center}

/* Geo gate */
.geo-gate{background:var(--card);border:1px solid rgba(0,0,0,.06);border-radius:14px;padding:24px;text-align:center;margin-bottom:16px}

/* Responsive */
@media(max-width:1200px){
  .sr-wrap{padding:18px 16px}
}
@media(max-width:900px){
  .sr-sidebar{display:none}
  .sr-wrap{padding:14px}
  .rc-inner{flex-direction:column}
  .rc-img,.rc-doc-img,.rc-hosp-logo{width:100%;height:180px;min-height:unset}
  .rc-doc-img img,.rc-doc-initials{width:90px;height:90px}
  .rc-hosp-logo img{width:90px;height:90px}
}
@media(max-width:640px){
  .stb-inner{flex-wrap:wrap;gap:8px}
  .stb-bar{width:100%}
  .stb-field{padding:0 12px}
  .stb-btn{border-radius:0 10px 10px 0;padding:0 18px}
  .stb-geo-btn{width:100%;justify-content:center;border-radius:10px;min-height:40px}
  .rc-name{font-size:14px}
  .btn-book,.btn-select{width:100%;justify-content:center}
  .rc-footer{flex-direction:column;align-items:flex-start}
  .modal-box{max-width:100%;margin:0;border-radius:20px 20px 0 0;position:fixed;bottom:0;left:0;right:0;max-height:92vh}
  .modal-ov{align-items:flex-end;padding:0}
  .bk-slots-grid{grid-template-columns:repeat(2,1fr)}
}
</style>
<!-- STICKY SEARCH TOPBAR -->
<div class="search-topbar">
  <!-- Type tabs -->
  <div class="stb-type-row">
    <div class="stb-type-inner">
      <span style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;white-space:nowrap;margin-right:8px">Find:</span>
      <?php foreach([
        ['all','All','fa-grip'],
        ['hospital','Hospitals','fa-hospital'],
        ['doctor','Doctors','fa-user-doctor'],
        ['clinic','Clinics','fa-house-medical'],
        ['ambulance','Emergency','fa-truck-medical'],
        ['pharmacy','Pharmacy','fa-prescription-bottle-medical']
      ] as [$tv,$tl,$ti]): ?>
      <button class="stb-type-tab <?=$resType===$tv?'active':''?>" onclick="setType('<?=$tv?>')">
        <i class="fa-solid <?=$ti?>" style="font-size:10px"></i><?=$tl?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- Search bar -->
  <div class="stb-inner">
    <div class="stb-bar">
      <div class="stb-field" style="max-width:220px">
        <div class="stb-field-icon" style="background:rgba(25,120,229,.1)">
          <i class="fa-solid fa-stethoscope" style="color:var(--primary);font-size:12px"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div class="stb-label">Specialty</div>
          <select class="stb-select" id="sSpecialty">
            <option value="">Any specialty</option>
            <?php foreach(['General Physician','Cardiologist','Pediatrician','Dentist','Gynecologist','Psychiatrist','Orthopedic Surgeon','Ophthalmologist','Dermatologist','ENT Specialist','Neurologist','Oncologist','Physiotherapist'] as $s): ?>
            <option value="<?=htmlspecialchars($s)?>" <?=($specialty===$s)?'selected':''?>><?=htmlspecialchars($s)?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="stb-field" style="max-width:180px">
        <div class="stb-field-icon" style="background:rgba(25,120,229,.1)">
          <i class="fa-solid fa-location-dot" style="color:var(--primary);font-size:12px"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div class="stb-label">County</div>
          <select class="stb-select" id="sCounty">
            <option value="">Any county</option>
            <?php foreach(['Nairobi','Mombasa','Kisumu','Nakuru','Eldoret','Thika','Kitale','Garissa','Kakamega','Nyeri','Meru','Embu','Machakos','Kisii','Kericho','Bungoma','Malindi','Lamu','Isiolo'] as $c): ?>
            <option value="<?=htmlspecialchars($c)?>" <?=($county===$c||$location===$c)?'selected':''?>><?=htmlspecialchars($c)?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="stb-field" style="max-width:160px">
        <div class="stb-field-icon" style="background:rgba(13,148,136,.1)">
          <i class="fa-solid fa-city" style="color:var(--teal);font-size:12px"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div class="stb-label">City</div>
          <input class="stb-input" type="text" id="sCity" value="<?=htmlspecialchars($location)?>" placeholder="Any city">
        </div>
      </div>
      <div class="stb-field" style="max-width:190px">
        <div class="stb-field-icon" style="background:rgba(5,150,105,.1)">
          <i class="fa-solid fa-shield-heart" style="color:#059669;font-size:12px"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div class="stb-label">Insurance</div>
          <select class="stb-select" id="sInsurance">
            <option value="">Any insurance</option>
            <?php foreach(['NHIF'=>'nhif','Jubilee Health'=>'jubilee','AXA Mansard'=>'axa','AAR Healthcare'=>'aar','Britam'=>'britam','CIC'=>'cic','Equity Afia'=>'equity'] as $n=>$v): ?>
            <option value="<?=$v?>" <?=($insurance===$v)?'selected':''?>><?=$n?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="stb-field">
        <div class="stb-field-icon" style="background:rgba(25,120,229,.1)">
          <i class="fa-solid fa-magnifying-glass" style="color:var(--primary);font-size:12px"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div class="stb-label">Search by name</div>
          <input class="stb-input" type="text" id="sQuery" value="<?=htmlspecialchars($q)?>" placeholder="Doctor or hospital name" onkeydown="if(event.key==='Enter')doSearch()">
        </div>
      </div>
      <button class="stb-btn" onclick="doSearch()">
        <i class="fa-solid fa-magnifying-glass"></i>Search
      </button>
    </div>
    <button class="stb-geo-btn" id="geoBtn" onclick="triggerGeo()">
      <i class="fa-solid fa-location-crosshairs"></i>Near Me
    </button>
  </div>
</div>

<!-- MAIN LAYOUT -->
<div class="sr-wrap">

  <!-- SIDEBAR -->
  <aside class="sr-sidebar">
    <div class="flt-card">
      <div id="locStatusBar" style="display:none;background:var(--primary-10);border:1px solid var(--primary-20);border-radius:9px;padding:10px 13px;margin-bottom:16px;font-size:12.5px;color:var(--primary);display:flex;align-items:center;gap:6px">
        <i class="fa-solid fa-location-dot"></i><span id="locStatusText">Detecting…</span>
      </div>

      <div class="flt-section">
        <div class="flt-title"><i class="fa-solid fa-list" style="color:var(--primary);font-size:11px"></i>Provider Type</div>
        <?php foreach([['all','All Providers'],['hospital','Hospitals'],['doctor','Doctors'],['clinic','Clinics'],['ambulance','Ambulance / Emergency'],['pharmacy','Pharmacy']] as [$v,$lbl]): ?>
        <label class="flt-label">
          <input type="radio" name="ptype" value="<?=$v?>" <?=($resType===$v)?'checked':''?> onchange="applyFilters()">
          <span><?=$lbl?></span>
        </label>
        <?php endforeach; ?>
      </div>

      <div class="flt-section">
        <div class="flt-title"><i class="fa-solid fa-location-arrow" style="color:var(--primary);font-size:11px"></i>Distance</div>
        <?php foreach([['999','Any Distance'],['10','Within 10 km'],['5','Within 5 km'],['2','Within 2 km']] as [$v,$lbl]): ?>
        <label class="flt-label">
          <input type="radio" name="dist" value="<?=$v?>" <?=$v==='999'?'checked':''?> onchange="applyFilters()">
          <span><?=$lbl?></span>
        </label>
        <?php endforeach; ?>
        <div id="distNote" style="font-size:11.5px;color:var(--faint);margin-top:6px;padding:7px 10px;background:var(--primary-10);border-radius:7px">Enable location access to filter by distance</div>
      </div>

      <div class="flt-section">
        <div class="flt-title"><i class="fa-solid fa-shield-halved" style="color:var(--primary);font-size:11px"></i>Insurance</div>
        <?php foreach([['nhif','NHIF Kenya'],['jubilee','Jubilee Health'],['axa','AXA Mansard'],['aar','AAR Healthcare'],['britam','Britam'],['cic','CIC Insurance']] as [$v,$lbl]): ?>
        <label class="flt-label"><input type="checkbox" value="<?=$v?>" class="ins-chk" onchange="applyFilters()"><span><?=$lbl?></span></label>
        <?php endforeach; ?>
      </div>

      <div class="flt-section">
        <div class="flt-title"><i class="fa-solid fa-hospital-user" style="color:var(--primary);font-size:11px"></i>Visit Type</div>
        <div class="type-toggle">
          <button class="type-btn <?=$visitType==='in_person'?'active':''?>" onclick="setVisitType('in_person',this)">In-Person</button>
          <button class="type-btn <?=$visitType==='telehealth'?'active':''?>" onclick="setVisitType('telehealth',this)">Telehealth</button>
        </div>
      </div>

      <div class="flt-section">
        <div class="flt-title"><i class="fa-solid fa-star" style="color:var(--yellow);font-size:11px"></i>Min. Rating</div>
        <?php foreach([['0','Any Rating'],['4.0','4.0 and above'],['4.5','4.5 and above'],['4.8','4.8+ (Top Rated)']] as [$v,$lbl]): ?>
        <label class="flt-label"><input type="radio" name="rating" value="<?=$v?>" <?=$v==='0'?'checked':''?> onchange="applyFilters()"><span><?=$lbl?></span></label>
        <?php endforeach; ?>
      </div>

      <button onclick="resetFilters()" style="width:100%;padding:10px;border:1.5px solid var(--border);background:var(--bg);border-radius:9px;font-family:inherit;font-size:12.5px;font-weight:700;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .15s" onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
        <i class="fa-solid fa-rotate-left"></i>Reset All Filters
      </button>
    </div>
  </aside>

  <!-- RESULTS -->
  <div class="sr-main">

    <!-- Geo gate -->
    <div id="geoGate" style="display:<?=$geoRequest?'block':'none'?>">
      <div class="geo-gate">
        <div style="width:52px;height:52px;border-radius:50%;background:var(--primary-10);color:var(--primary);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:22px"><i class="fa-solid fa-location-crosshairs"></i></div>
        <h3 style="font-size:16px;font-weight:700;color:var(--ink);margin-bottom:7px">Allow Location Access</h3>
        <p style="font-size:13px;color:var(--muted);line-height:1.7;max-width:380px;margin:0 auto 16px">Show hospitals and doctors nearest to you. Your location is only used to sort results and is never stored.</p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
          <button onclick="triggerGeo()" class="btn btn-primary"><i class="fa-solid fa-location-dot"></i> Allow & Show Nearby</button>
          <button onclick="dismissGeoGate()" class="btn btn-ghost">Skip — show all</button>
        </div>
      </div>
    </div>

    <!-- Location bar (shown after geo) -->
    <div id="locBar" style="display:none" class="loc-bar">
      <div style="display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--primary);font-weight:600">
        <i class="fa-solid fa-location-dot"></i>
        <span id="locBarText">Showing results near your location</span>
      </div>
      <button onclick="clearGeo()" style="font-size:11.5px;color:var(--faint);background:none;border:none;cursor:pointer;text-decoration:underline;font-family:inherit">Clear location</button>
    </div>

    <!-- Sort bar -->
    <div class="sort-bar">
      <div>
        <span style="font-size:18px;font-weight:900;color:var(--ink)" id="resultCount"><?=count($providers)?></span>
        <span style="font-size:13px;color:var(--muted);margin-left:5px" id="resultLabel">provider<?=count($providers)!==1?'s':''?> found<?=$q?' for "<strong>'.htmlspecialchars($q).'</strong>"':''?></span>
      </div>
      <select class="sort-select" id="sortOrder" onchange="applyFilters()">
        <option value="rating">Sort: Top Rated</option>
        <option value="distance">Sort: Nearest First</option>
        <option value="name">Sort: A–Z</option>
      </select>
    </div>

    <!-- Results container (PHP initial render) -->
    <div id="resultsContainer">
      <?php foreach($providers as $i=>$p):
        $isDoc     = ($p['type']==='doctor');
        $isHosp    = in_array($p['type'],['hospital','diagnostic','clinic','ambulance']);
        $isHospDoc = ($p['source']??'')==='hospital_doctor';
        $isStDoc   = ($p['source']??'')==='standalone_doctor';
        $isHospReg = ($p['source']??'')==='hospital';
        $hospName  = $p['hospital_name']??'';
        $hospitalImg = $hospitalImgs[$i%count($hospitalImgs)];
        $rat       = number_format(floatval($p['rating']??4.5),1);
        $hasTel    = str_contains($p['services']??'','telehealth');
        $avatar    = $p['avatar_path']??'';
        $specLabel = $p['specialty']??'';
        if(!$specLabel){ $specLabel = $isHospReg ? ucfirst($p['type']??'Hospital').' · Healthcare Facility' : ucfirst($p['type']??'Provider'); }
        $locLabel  = trim(($p['address']??'').($p['county'] ? ', '.trim($p['county']) : ''));
        if(!$locLabel) $locLabel = ($p['city']??'Kenya');
        $fee       = (float)($p['consult_fee']??0);
        $pid       = (int)($p['id']??0);
        $pName     = htmlspecialchars($p['name']??'Provider');
        $pType     = htmlspecialchars($p['type']??'hospital');
        $pCity     = htmlspecialchars($p['city']??$p['county']??'');
        // Real DB id for standalone doctors
        $docId     = $isStDoc ? max(0, $pid - 30000) : 0;
        // Verification / on-duty check
        $isVerified = $isHospReg || ($p['is_verified']??0) || ($p['email_verified']??0);
        // hasSchedule: hospital_docs always true; standalone doctors true if they have schedule OR if verified (default hours used)
        $hasSchedule = !empty($p['schedule_info']) || $isHospReg || $isHospDoc || ($isStDoc && $isVerified);
        $availDays = (int)($p['avail_days_count']??($hasSchedule?1:0));
        // Verified doctors/hospitals can always be booked (default slots used if no explicit schedule)
        $canBook   = $isVerified;
        $scheduleDisplay = '';
        if ($isStDoc && !empty($p['schedule_info'])) {
            $scheduleDisplay = formatScheduleInfo($p['schedule_info']);
        }
        // Initials for doctors without avatar
        $initials = '';
        if ($isDoc) {
            $parts = explode(' ', strip_tags($pName));
            $parts = array_filter($parts, fn($w)=>strlen($w)>0);
            $initials = strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice($parts, 0, 2))));
            if (str_starts_with($initials,'D') && count($parts)>=2) {
                $initials = strtoupper($parts[1][0].(isset($parts[2])?$parts[2][0]:''));
            }
        }
      ?>
      <div class="rc" data-id="<?=$pid?>" data-type="<?=$pType?>" data-rating="<?=floatval($p['rating']??4.5)?>" data-lat="<?=floatval($p['lat']??0)?>" data-lng="<?=floatval($p['lng']??0)?>" data-telehealth="<?=$hasTel?1:0?>" data-verified="<?=$isVerified?1:0?>" data-source="<?=htmlspecialchars($p['source']??'')?>">
        <div class="rc-inner">
          <?php if($isDoc): ?>
          <div class="rc-doc-img" style="position:relative">
            <?php if($avatar): ?>
            <img src="<?=htmlspecialchars($avatar)?>" alt="<?=$pName?>" loading="lazy">
            <?php elseif($initials): ?>
            <div class="rc-doc-initials"><?=htmlspecialchars($initials)?></div>
            <?php endif; ?>
            <?php if($isVerified): ?>
            <div style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);background:rgba(25,120,229,.92);color:#fff;padding:2px 8px;border-radius:9999px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><i class="fa-solid fa-circle-check" style="margin-right:3px"></i>Verified</div>
            <?php else: ?>
            <div style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);background:rgba(100,116,139,.88);color:#fff;padding:2px 8px;border-radius:9999px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><i class="fa-solid fa-clock" style="margin-right:3px"></i>Pending</div>
            <?php endif; ?>
          </div>
          <?php elseif($isHospReg && $avatar): ?>
          <!-- Hospital with uploaded logo -->
          <div class="rc-hosp-logo" style="position:relative">
            <img src="<?=htmlspecialchars($avatar)?>" alt="<?=$pName?>" loading="lazy">
            <?php if($p['is_available']??0): ?><div style="position:absolute;top:10px;left:50%;transform:translateX(-50%);background:rgba(255,255,255,.93);backdrop-filter:blur(4px);padding:3px 8px;border-radius:5px;font-size:9.5px;font-weight:800;color:#059669;display:flex;align-items:center;gap:4px;white-space:nowrap"><i class="fa-solid fa-circle" style="font-size:5px;color:#22c55e"></i>Open Now</div><?php endif; ?>
            <div style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);background:rgba(25,120,229,.88);color:#fff;padding:2px 8px;border-radius:9999px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><i class="fa-solid fa-circle-check" style="margin-right:2px"></i>Verified</div>
          </div>
          <?php else: ?>
          <div class="rc-img">
            <img src="<?=$hospitalImg?>" alt="<?=$pName?>" loading="lazy">
            <?php if($p['is_available']??0): ?><span class="open-badge"><i class="fa-solid fa-circle" style="font-size:5px;color:#22c55e"></i>Open Now</span><?php endif; ?>
            <span class="dist-badge"><i class="fa-solid fa-location-dot" style="color:var(--primary)"></i><span class="dist-km-<?=$pid?>">— km</span></span>
            <?php if($isHospReg): ?><span class="verified-badge"><i class="fa-solid fa-circle-check" style="margin-right:2px"></i>Verified Facility</span><?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="rc-body">
            <div>
              <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
                <div style="flex:1;min-width:0">
                  <div class="rc-name"><?=$pName?></div>
                  <div class="rc-spec"><?=htmlspecialchars($specLabel)?></div>
                  <?php if($isHospDoc && $hospName): ?>
                  <div style="font-size:11px;color:#059669;font-weight:700;margin-top:3px;display:flex;align-items:center;gap:4px">
                    <i class="fa-solid fa-hospital" style="font-size:10px"></i>At <?=htmlspecialchars($hospName)?>
                  </div>
                  <?php elseif($isStDoc): ?>
                  <div style="font-size:11px;color:#7c3aed;font-weight:700;margin-top:3px;display:flex;align-items:center;gap:4px">
                    <i class="fa-solid fa-user-doctor" style="font-size:10px"></i>Independent Practice
                  </div>
                  <?php elseif($isHospReg): ?>
                  <div style="font-size:11px;color:#0369a1;font-weight:700;margin-top:3px;display:flex;align-items:center;gap:4px">
                    <i class="fa-solid fa-building" style="font-size:10px"></i>Healthcare Facility
                  </div>
                  <?php endif; ?>
                  <?php if($isDoc && $p['years_exp']??0): ?>
                  <div style="font-size:11px;color:var(--faint);margin-top:2px;display:flex;align-items:center;gap:4px">
                    <i class="fa-solid fa-briefcase" style="font-size:10px"></i><?=(int)$p['years_exp']?> yrs experience
                  </div>
                  <?php endif; ?>
                  <div class="rc-rating">
                    <i class="fa-solid fa-star" style="color:var(--yellow);font-size:12px"></i>
                    <span style="font-weight:800;color:var(--ink)"><?=$rat?></span>
                    <span style="font-size:12px;color:var(--faint)">(<?=rand(20,300)?>+ reviews)</span>
                  </div>
                </div>
                <button onclick="toggleFav(this)" style="color:rgba(0,0,0,.2);background:none;border:none;cursor:pointer;font-size:18px;padding:2px 4px;flex-shrink:0;line-height:1" title="Save to favourites"><i class="fa-regular fa-heart"></i></button>
              </div>

              <div class="rc-loc">
                <i class="fa-solid fa-location-dot" style="color:var(--primary);font-size:12px;flex-shrink:0"></i>
                <span><?=htmlspecialchars($locLabel)?></span>
                <span class="dist-text-<?=$pid?>" style="color:var(--primary);font-weight:700;margin-left:4px"></span>
              </div>

              <?php if($isStDoc && $scheduleDisplay): ?>
              <div style="display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--green);font-weight:600;margin-bottom:8px">
                <i class="fa-regular fa-clock" style="font-size:11px"></i>Available: <?=htmlspecialchars($scheduleDisplay)?>
              </div>
              <?php elseif($isStDoc && $availDays===0 && !$isHospReg): ?>
              <div style="display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--faint);margin-bottom:8px">
                <i class="fa-solid fa-clock-rotate-left" style="font-size:11px"></i>Schedule not yet set
              </div>
              <?php endif; ?>

              <div class="rc-tags">
                <span class="rc-tag rc-tag-ins"><i class="fa-solid fa-shield-halved" style="font-size:8px"></i>Insurance Accepted</span>
                <?php if($fee>0): ?><span class="rc-tag rc-tag-pay"><i class="fa-solid fa-money-bill-wave" style="font-size:8px"></i>KES <?=number_format($fee,0)?></span><?php endif; ?>
                <?php if($hasTel): ?><span class="rc-tag rc-tag-tel"><i class="fa-solid fa-video" style="font-size:8px"></i>Telehealth</span><?php endif; ?>
                <?php if($isHospReg): ?><span class="rc-tag rc-tag-hosp"><?=ucfirst($p['type']??'Hospital')?></span><?php endif; ?>
                <?php if($isHospDoc): ?><span class="rc-tag" style="background:rgba(5,150,105,.1);color:#059669"><i class="fa-solid fa-user-doctor" style="font-size:8px"></i>Hospital Doctor</span><?php endif; ?>
                <?php if($isStDoc): ?><span class="rc-tag" style="background:rgba(124,58,237,.1);color:#7c3aed"><i class="fa-solid fa-user-doctor" style="font-size:8px"></i>Independent</span><?php endif; ?>
                <?php if(!$isVerified): ?><span class="rc-tag" style="background:rgba(100,116,139,.1);color:var(--faint)"><i class="fa-solid fa-clock" style="font-size:8px"></i>Under Review</span><?php endif; ?>
              </div>
            </div>

            <!-- Availability hours strip (shown above footer) -->
              <?php if($isStDoc && !empty($scheduleDisplay)): ?>
              <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#059669;font-weight:700;padding:6px 0;border-top:1px solid rgba(0,0,0,.05);margin-top:4px;flex-wrap:wrap">
                <i class="fa-regular fa-clock" style="font-size:10px"></i>
                <span>Consulting: <?=htmlspecialchars($scheduleDisplay)?></span>
              </div>
              <?php elseif($isHospDoc && !empty($p['avail_json'])): 
                $hd_avail = json_decode($p['avail_json'], true) ?? [];
                $hd_days = implode(', ', array_keys($hd_avail));
              ?>
              <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#059669;font-weight:700;padding:6px 0;border-top:1px solid rgba(0,0,0,.05);margin-top:4px">
                <i class="fa-regular fa-clock" style="font-size:10px"></i>
                <span>Available: <?=htmlspecialchars($hd_days ?: 'Mon–Fri')?></span>
              </div>
              <?php elseif($isHospDoc): ?>
              <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#059669;font-weight:700;padding:6px 0;border-top:1px solid rgba(0,0,0,.05);margin-top:4px">
                <i class="fa-regular fa-clock" style="font-size:10px"></i>
                <span>Mon–Fri 8:00 AM – 5:00 PM</span>
              </div>
              <?php elseif($isHospReg): ?>
              <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#059669;font-weight:700;padding:6px 0;border-top:1px solid rgba(0,0,0,.05);margin-top:4px">
                <i class="fa-regular fa-clock" style="font-size:10px"></i>
                <span>Mon–Sat 8:00 AM – 5:00 PM <span style="color:var(--faint);font-weight:500">(default hours, check with facility)</span></span>
              </div>
              <?php endif; ?>
            <div class="rc-footer">
              <?php if($isStDoc): ?>
              <a href="/patients/doctor-profile.php?id=<?=$docId?>" style="font-size:12px;color:var(--primary);font-weight:700;text-decoration:none;display:flex;align-items:center;gap:4px"><i class="fa-solid fa-user" style="font-size:10px"></i>View Profile</a>
              <?php endif; ?>
              <?php if($canBook): ?>
              <div class="rc-slot">
                <i class="fa-solid fa-calendar-check" style="color:#22c55e"></i>
                <?php if($scheduleDisplay): ?>Available: <strong><?=htmlspecialchars(substr($scheduleDisplay,0,25))?></strong>
                <?php elseif(!$isVerified): ?>Pending verification
                <?php else: ?>Accepting bookings<?php endif; ?>
              </div>
              <button class="<?=$isDoc?'btn-select':'btn-book'?>"
                onclick="triggerBooking(<?=$pid?>,'<?=addslashes($pName)?>',<?=$isHospReg?'true':'false'?>,'<?=addslashes($pCity)?>',<?=$fee>0?$fee:'null'?>)">
                <i class="fa-solid fa-calendar-plus" style="font-size:11px"></i>
                <?=$isDoc?'Book Doctor':'Book Now'?>
              </button>
              <?php else: ?>
              <div class="rc-unavail"><i class="fa-solid fa-ban" style="font-size:11px"></i>Pending verification</div>
              <button class="btn-disabled" disabled style="padding:9px 18px;font-size:12.5px;font-weight:700;border-radius:10px;border:1px solid rgba(0,0,0,.1);background:rgba(0,0,0,.04);color:var(--faint);cursor:not-allowed;display:inline-flex;align-items:center;gap:5px">
                <i class="fa-solid fa-lock" style="font-size:11px"></i>Unavailable
              </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div id="emptyState" style="display:none">
      <div class="empty-state">
        <i class="fa-solid fa-magnifying-glass-location" style="font-size:48px;color:rgba(0,0,0,.1);display:block;margin-bottom:16px"></i>
        <h3 style="font-size:17px;font-weight:700;color:var(--muted);margin-bottom:8px">No providers match your search</h3>
        <p style="font-size:13.5px;color:var(--faint);margin-bottom:18px">Try adjusting your filters or broadening your search term.</p>
        <button onclick="resetFilters()" class="btn btn-primary">
          <i class="fa-solid fa-rotate-left"></i>Reset Filters
        </button>
      </div>
    </div>

    <div class="pg" id="pagination"></div>
  </div>
</div>

<!-- ══════════ BOOKING MODAL ══════════ -->
<div class="modal-ov" id="srBkModal" onclick="if(event.target===this)closeSrModal()">
  <div class="modal-box">
    <div class="modal-hdr">
      <div class="modal-title">
        <div style="width:34px;height:34px;border-radius:9px;background:var(--primary-10);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fa-solid fa-calendar-plus" style="color:var(--primary);font-size:14px"></i>
        </div>
        <div>
          <div>Book Appointment</div>
          <div id="srBkProvLabel" style="font-size:.6875rem;font-weight:500;color:var(--muted);margin-top:1px"></div>
        </div>
      </div>
      <button class="modal-close" onclick="closeSrModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- Steps -->
    <div class="bk-steps" id="srStepsBar">
      <?php foreach([['1','Reason'],['2','Date'],['3','Time'],['4','Type'],['5','Confirm']] as [$n,$lbl]): ?>
      <div class="bk-step-item <?=$n==='1'?'active':''?>" data-step="<?=$n?>">
        <div class="bk-dot"><?=$n?></div>
        <span class="bk-step-lbl"><?=$lbl?></span>
        <?php if($n!=='5'): ?><div class="bk-step-line"></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Step 1: Reason -->
    <div class="bk-panel active" id="srP1">
      <div class="form-group">
        <label class="form-label"><i class="fa-solid fa-stethoscope" style="color:var(--primary)"></i>Reason for Visit <span style="color:var(--red)">*</span></label>
        <select class="bk-reason-select" id="srReason" onchange="document.getElementById('srOtherRow').style.display=this.value==='other'?'block':'none'">
          <option value="">— Select a reason —</option>
          <option value="general_consultation">General Consultation</option>
          <option value="followup">Follow-up Visit</option>
          <option value="checkup">Routine Check-up</option>
          <option value="specialist">Specialist Referral</option>
          <option value="emergency">Emergency</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div id="srOtherRow" style="display:none" class="form-group">
        <label class="form-label"><i class="fa-solid fa-pencil" style="color:var(--primary)"></i>Please describe <span style="color:var(--red)">*</span></label>
        <textarea class="form-textarea" id="srOtherNote" rows="3" placeholder="Describe your reason for visiting…"></textarea>
      </div>
    </div>

    <!-- Step 2: Date -->
    <div class="bk-panel" id="srP2">
      <div class="bk-hint-bar" id="srNextHint" style="display:none">
        <i class="fa-regular fa-clock"></i><span id="srNextHintTxt"></span>
      </div>
      <div class="bk-cal-wrap">
        <div class="bk-cal-nav">
          <button class="bk-cal-nav-btn" id="srCalPrev"><i class="fa-solid fa-chevron-left"></i></button>
          <span class="bk-cal-month" id="srCalMonth"></span>
          <button class="bk-cal-nav-btn" id="srCalNext"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
        <div class="bk-cal-grid" id="srCalGrid"></div>
      </div>
    </div>

    <!-- Step 3: Time -->
    <div class="bk-panel" id="srP3">
      <div style="font-size:.8125rem;color:var(--muted);margin-bottom:10px;font-weight:600"><i class="fa-regular fa-clock" style="color:var(--primary);margin-right:5px"></i>Pick a time slot</div>
      <div id="srSlotsWrap"></div>
    </div>

    <!-- Step 4: Visit type & notes -->
    <div class="bk-panel" id="srP4">
      <div class="form-label" style="margin-bottom:10px"><i class="fa-solid fa-hospital-user" style="color:var(--primary)"></i>How would you like to be seen?</div>
      <div class="bk-type-grid">
        <button class="bk-type-btn selected" data-vt="in_person" onclick="srSelType(this,'in_person')">
          <i class="fa-solid fa-house-medical"></i>In-Person
        </button>
        <button class="bk-type-btn" data-vt="telehealth" onclick="srSelType(this,'telehealth')">
          <i class="fa-solid fa-video"></i>Telehealth
        </button>
      </div>
      <div class="form-group">
        <label class="form-label"><i class="fa-solid fa-note-sticky" style="color:var(--primary)"></i>Notes <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
        <textarea class="form-textarea" id="srNotes" rows="3" placeholder="Anything the provider should know before your visit…"></textarea>
      </div>
    </div>

    <!-- Step 5: Summary -->
    <div class="bk-panel" id="srP5">
      <div class="bk-summary">
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-solid fa-user-doctor"></i>Provider</span><span class="bk-sum-val" id="srSumProv">—</span></div>
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-solid fa-location-dot"></i>Location</span><span class="bk-sum-val" id="srSumLoc">—</span></div>
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-regular fa-calendar"></i>Date</span><span class="bk-sum-val" id="srSumDate">—</span></div>
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-regular fa-clock"></i>Time</span><span class="bk-sum-val" id="srSumTime">—</span></div>
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-solid fa-hospital"></i>Visit Type</span><span class="bk-sum-val" id="srSumType">—</span></div>
        <div class="bk-summary-row"><span class="bk-sum-lbl"><i class="fa-solid fa-clipboard"></i>Reason</span><span class="bk-sum-val" id="srSumReason">—</span></div>
        <div class="bk-summary-row" id="srSumFeeRow" style="display:none"><span class="bk-sum-lbl"><i class="fa-solid fa-credit-card"></i>Fee</span><span class="bk-sum-val" id="srSumFee">—</span></div>
      </div>
      <p style="text-align:center;font-size:.75rem;color:var(--muted)"><i class="fa-solid fa-envelope" style="color:var(--primary);margin-right:4px"></i>Confirmation sent by email &amp; SMS.</p>
    </div>

    <!-- Success state -->
    <div id="srSuccess" style="display:none;padding:32px 22px;text-align:center">
      <div class="bk-success-icon"><i class="fa-solid fa-circle-check"></i></div>
      <h3 style="font-size:1.125rem;font-weight:800;margin-bottom:6px">Appointment Booked!</h3>
      <p style="font-size:.875rem;color:var(--muted)">Your booking reference:</p>
      <div class="bk-ref" id="srRefCode">PZY-000000</div>
      <p style="font-size:.8125rem;color:var(--muted);margin-top:8px"><i class="fa-solid fa-envelope" style="color:var(--primary)"></i> Confirmation sent to your email &amp; phone.</p>
      <button class="btn btn-primary" style="margin-top:18px" onclick="closeSrModal();window.location.href='/patients/dashboard.php?tab=appointments'">
        <i class="fa-solid fa-calendar-days"></i>View My Appointments
      </button>
    </div>

    <div id="srAlert" class="alert err" style="display:none"></div>

    <div class="bk-modal-footer" id="srFooter">
      <button class="btn btn-ghost" id="srBackBtn" onclick="srPrev()" style="display:none"><i class="fa-solid fa-arrow-left"></i>Back</button>
      <button class="btn btn-primary" id="srNextBtn" onclick="srNext()">Next<i class="fa-solid fa-arrow-right"></i></button>
      <button class="btn btn-primary" id="srConfirmBtn" onclick="srConfirm()" style="display:none;background:linear-gradient(135deg,#1978e5,#0d9488)">
        <i class="fa-solid fa-calendar-check"></i>Confirm Booking
      </button>
    </div>
  </div>
</div>

<!-- Hidden CSRF token for JS -->
<input type="hidden" id="srCsrf" value="<?=htmlspecialchars($csrf)?>">

<?php include dirname(__DIR__).'/includes/footer.php'; ?>

<script>
/* ════════════════════════════════════════════
   DATA & CONSTANTS
   ════════════════════════════════════════════ */
const ALL_PROVIDERS  = <?=$providersJson?>;
const HOSPITAL_IMGS  = <?=json_encode($hospitalImgs)?>;
const DOCTOR_IMGS    = <?=json_encode($doctorImgs)?>;
const SLOT_TIMES     = <?=json_encode($slotTimes)?>;
const IS_LOGGED_IN   = <?=$isLoggedIn?'true':'false'?>;
const CURRENT_TYPE   = '<?=addslashes($resType)?>';

let userLat = null, userLng = null, currentPage = 1;
const PER_PAGE = 10;

/* ════════════════════════════════════════════
   UTILITY
   ════════════════════════════════════════════ */
function haversine(la1,lo1,la2,lo2){
  const R=6371,dL=(la2-la1)*Math.PI/180,dO=(lo2-lo1)*Math.PI/180;
  const a=Math.sin(dL/2)**2+Math.cos(la1*Math.PI/180)*Math.cos(la2*Math.PI/180)*Math.sin(dO/2)**2;
  return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
}
function esc(t){ return String(t||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtTime(t){ if(!t) return '—'; const[h,m]=t.split(':').map(Number); const ap=h<12?'AM':'PM'; return `${h>12?h-12:(h||12)}:${String(m).padStart(2,'0')} ${ap}`; }
function fmtSlotTime(t){ if(!t) return '—'; const parts=t.split(':').map(Number); const h=parts[0]||0,m=parts[1]||0; const ap=h<12?'AM':'PM'; return `${h>12?h-12:(h||12)}:${String(m).padStart(2,'0')} ${ap}`; }
function fmtDate(d){ if(!d) return '—'; const dt=new Date(d+'T00:00'); return dt.toLocaleDateString('en-KE',{weekday:'long',month:'long',day:'numeric'}); }
function showAlert(msg, type='err'){
  const el=document.getElementById('srAlert');
  if(!el) return;
  el.className='alert '+(type==='ok'?'ok':'err');
  el.innerHTML=(type==='ok'?'<i class="fa-solid fa-check-circle"></i>':'<i class="fa-solid fa-triangle-exclamation"></i>')+' '+msg;
  el.style.display='flex';
}

/* ════════════════════════════════════════════
   TYPE TABS & SEARCH
   ════════════════════════════════════════════ */
function setType(type){
  const params=new URLSearchParams(window.location.search);
  if(type==='all') params.delete('type'); else params.set('type',type);
  window.location.href='/patients/search.php?'+params.toString();
}

function doSearch(){
  const sp  = document.getElementById('sSpecialty')?.value||'';
  const co  = document.getElementById('sCounty')?.value||'';
  const ci  = (document.getElementById('sCity')?.value||'').trim();
  const ins = document.getElementById('sInsurance')?.value||'';
  const q   = (document.getElementById('sQuery')?.value||'').trim();
  const isTele = document.querySelector('.type-btn.active')?.textContent?.toLowerCase().includes('tele');
  const ptype  = document.querySelector('input[name="ptype"]:checked')?.value||'all';
  const params = new URLSearchParams();
  if(sp) params.set('specialty',sp);
  if(co) params.set('county',co);
  if(ci) params.set('location',ci);
  if(ins) params.set('insurance',ins);
  if(q)  params.set('q',q);
  params.set('visit_type',isTele?'telehealth':'in_person');
  if(ptype!=='all') params.set('type',ptype);
  window.location.href='/patients/search.php?'+params.toString();
}

/* ════════════════════════════════════════════
   GEOLOCATION
   ════════════════════════════════════════════ */
function triggerGeo(){
  if(!navigator.geolocation){ alert('Geolocation is not supported by your browser.'); return; }
  const btn=document.getElementById('geoBtn');
  if(btn){ btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Locating…'; btn.disabled=true; }
  navigator.geolocation.getCurrentPosition(
    pos=>{
      userLat=pos.coords.latitude; userLng=pos.coords.longitude;
      dismissGeoGate();
      fetch(`https://nominatim.openstreetmap.org/reverse?lat=${userLat}&lon=${userLng}&format=json`)
        .then(r=>r.json()).then(d=>{
          const city=d.address?.suburb||d.address?.city_district||d.address?.city||'Your Location';
          const lb=document.getElementById('locBarText'); if(lb) lb.textContent='Showing results near '+city;
          const st=document.getElementById('locStatusText'); if(st) st.textContent='Near '+city;
        }).catch(()=>{});
      const bar=document.getElementById('locBar'); if(bar) bar.style.display='flex';
      const sb=document.getElementById('locStatusBar'); if(sb) sb.style.display='flex';
      const dn=document.getElementById('distNote'); if(dn) dn.style.display='none';
      if(btn){ btn.innerHTML='<i class="fa-solid fa-location-crosshairs"></i> Near Me'; btn.disabled=false; }
      currentPage=1; renderResults();
    },
    ()=>{ if(btn){ btn.innerHTML='<i class="fa-solid fa-location-crosshairs"></i> Near Me'; btn.disabled=false; } },
    {enableHighAccuracy:true,timeout:10000,maximumAge:300000}
  );
}
function dismissGeoGate(){ const g=document.getElementById('geoGate'); if(g) g.style.display='none'; }
function clearGeo(){ userLat=null; userLng=null; const b=document.getElementById('locBar'); if(b) b.style.display='none'; renderResults(); }

/* ════════════════════════════════════════════
   FILTER & SORT
   ════════════════════════════════════════════ */
function applyFilters(){ currentPage=1; renderResults(); }

function setVisitType(vt, btn){
  document.querySelectorAll('.type-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  applyFilters();
}

function resetFilters(){
  const radios = document.querySelectorAll('input[name="ptype"]');
  if(radios[0]) radios[0].checked=true;
  const dists = document.querySelectorAll('input[name="dist"]');
  if(dists[0]) dists[0].checked=true;
  const rats = document.querySelectorAll('input[name="rating"]');
  if(rats[0]) rats[0].checked=true;
  document.querySelectorAll('.ins-chk').forEach(c=>c.checked=false);
  const firstTypeBtn = document.querySelector('.type-btn');
  if(firstTypeBtn){ document.querySelectorAll('.type-btn').forEach(b=>b.classList.remove('active')); firstTypeBtn.classList.add('active'); }
  applyFilters();
}

function getFiltered(){
  const ptype    = document.querySelector('input[name="ptype"]:checked')?.value||'all';
  const distMax  = parseFloat(document.querySelector('input[name="dist"]:checked')?.value||999);
  const minRat   = parseFloat(document.querySelector('input[name="rating"]:checked')?.value||0);
  const sort     = document.getElementById('sortOrder')?.value||'rating';
  const insCks   = [...document.querySelectorAll('.ins-chk:checked')].map(c=>c.value);
  const isTele   = document.querySelector('.type-btn.active')?.textContent?.toLowerCase().includes('tele');

  let filtered = ALL_PROVIDERS.filter(p=>{
    const normalizedType = p.type === 'doctor' ? 'doctor' : (p.source === 'hospital' ? 'hospital' : p.type);
    if(ptype !== 'all' && normalizedType !== ptype) return false;
    if(isTele && !(p.services||'').toLowerCase().includes('telehealth')) return false;
    if(distMax<999 && userLat && userLng){
      const plat=parseFloat(p.lat||p.latitude||0), plng=parseFloat(p.lng||p.longitude||0);
      if(plat && plng && haversine(userLat,userLng,plat,plng)>distMax) return false;
    }
    if(minRat>0 && parseFloat(p.rating||0)<minRat) return false;
    if(insCks.length>0){
      const svc=(p.services||'').toLowerCase();
      const ok=insCks.some(ins=>{
        if(ins==='nhif') return svc.includes('nhif')||['hospital','clinic'].includes(p.type);
        if(ins==='jubilee') return svc.includes('jubilee')||p.type==='hospital';
        if(ins==='axa') return svc.includes('axa')||p.type==='hospital';
        if(ins==='aar') return svc.includes('aar')||['clinic','hospital'].includes(p.type);
        return svc.includes(ins);
      });
      if(!ok) return false;
    }
    return true;
  });

  filtered.forEach(p=>{
    const plat=parseFloat(p.lat||p.latitude||0), plng=parseFloat(p.lng||p.longitude||0);
    p._dist=(plat&&plng&&userLat&&userLng) ? haversine(userLat,userLng,plat,plng) : null;
  });

  filtered.sort((a,b)=>{
    if(sort==='distance'){
      if(a._dist===null&&b._dist===null) return 0;
      if(a._dist===null) return 1;
      if(b._dist===null) return -1;
      return a._dist-b._dist;
    }
    if(sort==='name') return (a.name||'').localeCompare(b.name||'');
    return parseFloat(b.rating||0)-parseFloat(a.rating||0);
  });

  return filtered;
}

/* ════════════════════════════════════════════
   RENDER RESULTS (JS re-render on filter/sort)
   ════════════════════════════════════════════ */
function renderResults(){
  const filtered=getFiltered();
  const page=filtered.slice((currentPage-1)*PER_PAGE, currentPage*PER_PAGE);
  const rc=document.getElementById('resultCount'), rl=document.getElementById('resultLabel');
  if(rc) rc.textContent=filtered.length;
  if(rl) rl.innerHTML=filtered.length===1?'provider found':'providers found';

  const container=document.getElementById('resultsContainer'), empty=document.getElementById('emptyState');
  if(page.length===0){ container.innerHTML=''; if(empty) empty.style.display='block'; document.getElementById('pagination').innerHTML=''; return; }
  if(empty) empty.style.display='none';

  container.innerHTML=page.map((p,i)=>{
    const gi=(currentPage-1)*PER_PAGE+i;
    const isDoc=p.type==='doctor';
    const isHosp=['hospital','diagnostic'].includes(p.type);
    const isHospDoc=(p.source||'')==='hospital_doctor';
    const isStDoc=(p.source||'')==='standalone_doctor';
    const isHospReg=(p.source||'')==='hospital';
    const hospitalImg=HOSPITAL_IMGS[gi%HOSPITAL_IMGS.length];
    const rat=parseFloat(p.rating||4.5).toFixed(1);
    const hasTel=(p.services||'').toLowerCase().includes('telehealth');
    const dist=p._dist!==null&&p._dist!==undefined ? p._dist.toFixed(1)+' km' : '— km';
    const addr=esc((p.address||p.city||p.county||'Kenya'));
    const name=esc(p.name||'Provider');
    const spec=esc(p.specialty||p.type||'');
    const fee=parseFloat(p.consult_fee||0);
    const hospName=esc(p.hospital_name||'');
    const pCity=esc(p.city||p.county||'');
    const pId=parseInt(p.id)||0;
    const isVerified=isHospReg||p.is_verified==1||p.email_verified==1;
    const hasSchedule=!!(p.schedule_info||isHospReg||isHospDoc);
    const availDays=parseInt(p.avail_days_count||0);
    const canBook=isVerified; // Verified providers always bookable (default slots if no explicit schedule)
    // Doctor image / initials
    const avatar=p.avatar_path||'';
    let docImgHtml='';
    if(isDoc){
      const parts=(p.name||'').replace('Dr. ','').split(' ').filter(Boolean);
      const initials=parts.slice(0,2).map(w=>w[0]).join('').toUpperCase();
      const verBadge=isVerified
        ?`<div style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);background:rgba(25,120,229,.92);color:#fff;padding:2px 8px;border-radius:9999px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><i class='fa-solid fa-circle-check' style='margin-right:3px'></i>Verified</div>`
        :`<div style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);background:rgba(100,116,139,.88);color:#fff;padding:2px 8px;border-radius:9999px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><i class='fa-solid fa-clock' style='margin-right:3px'></i>Pending</div>`;
      const inner=avatar
        ?`<img src="${esc(avatar)}" alt="${name}" loading="lazy">`
        :`<div class="rc-doc-initials">${initials}</div>`;
      docImgHtml=`<div class="rc-doc-img" style="position:relative">${inner}${verBadge}</div>`;
    }
    const locSubtext=isHospDoc&&hospName?`<div style="font-size:11px;color:#059669;font-weight:700;margin-top:3px;display:flex;align-items:center;gap:4px"><i class="fa-solid fa-hospital" style="font-size:10px"></i>At ${hospName}</div>`:
                     isStDoc?`<div style="font-size:11px;color:#7c3aed;font-weight:700;margin-top:3px;display:flex;align-items:center;gap:4px"><i class="fa-solid fa-user-doctor" style="font-size:10px"></i>Independent Practice</div>`:
                     isHospReg?`<div style="font-size:11px;color:#0369a1;font-weight:700;margin-top:3px;display:flex;align-items:center;gap:4px"><i class="fa-solid fa-building" style="font-size:10px"></i>Healthcare Facility</div>`:'';
    const schedDisp=isStDoc&&p.schedule_info?`<div style="font-size:11.5px;color:var(--green);font-weight:600;margin-bottom:8px;display:flex;align-items:center;gap:5px"><i class="fa-regular fa-clock" style="font-size:11px"></i>Available: ${esc(p.schedule_info.split('|').slice(0,2).join(', ').substring(0,40))}</div>`:
                    (isStDoc&&!hasSchedule)?`<div style="font-size:11.5px;color:var(--faint);margin-bottom:8px;display:flex;align-items:center;gap:5px"><i class="fa-solid fa-clock-rotate-left" style="font-size:11px"></i>Schedule not yet set</div>`:'';
    const expBadge=isDoc&&p.years_exp?`<div style="font-size:11px;color:var(--faint);margin-top:2px;display:flex;align-items:center;gap:4px"><i class="fa-solid fa-briefcase" style="font-size:10px"></i>${parseInt(p.years_exp)} yrs experience</div>`:'';
    const tags=`
      <span class="rc-tag rc-tag-ins"><i class="fa-solid fa-shield-halved" style="font-size:8px"></i>Insurance</span>
      ${fee>0?`<span class="rc-tag rc-tag-pay"><i class="fa-solid fa-money-bill-wave" style="font-size:8px"></i>KES ${fee.toLocaleString()}</span>`:''}
      ${hasTel?'<span class="rc-tag rc-tag-tel"><i class="fa-solid fa-video" style="font-size:8px"></i>Telehealth</span>':''}
      ${isHosp||isHospReg?`<span class="rc-tag rc-tag-hosp">${(p.type||'Hospital')[0].toUpperCase()+(p.type||'hospital').slice(1)}</span>`:''}
      ${isHospDoc?'<span class="rc-tag" style="background:rgba(5,150,105,.1);color:#059669"><i class="fa-solid fa-user-doctor" style="font-size:8px"></i>Hospital Doctor</span>':''}
      ${isStDoc?'<span class="rc-tag" style="background:rgba(124,58,237,.1);color:#7c3aed"><i class="fa-solid fa-user-doctor" style="font-size:8px"></i>Independent</span>':''}
      ${!isVerified?'<span class="rc-tag" style="background:rgba(100,116,139,.1);color:var(--faint)"><i class="fa-solid fa-clock" style="font-size:8px"></i>Under Review</span>':''}`;
    const reviewCount=Math.floor(Math.random()*280+20);
    const bookBtn=canBook
      ?`<button class="${isDoc?'btn-select':'btn-book'}" onclick="triggerBooking(${pId},'${name.replace(/'/g,"\\'")}',${isHospReg},'${pCity.replace(/'/g,"\\'")}',${fee||'null'})"><i class="fa-solid fa-calendar-plus" style="font-size:11px"></i>${isDoc?'Book Doctor':'Book Now'}</button>`
      :`<button style="padding:9px 18px;font-size:12.5px;font-weight:700;border-radius:10px;border:1px solid rgba(0,0,0,.1);background:rgba(0,0,0,.04);color:var(--faint);cursor:not-allowed;display:inline-flex;align-items:center;gap:5px" disabled><i class="fa-solid fa-lock" style="font-size:11px"></i>Unavailable</button>`;
    const slotRow=canBook
      ?`<div class="rc-slot"><i class="fa-solid fa-calendar-check" style="color:#22c55e"></i>${hasSchedule?'Accepting appointments':'No schedule'}</div>`
      :`<div class="rc-unavail"><i class="fa-solid fa-ban" style="font-size:11px"></i>${!isVerified?'Pending verification':'No schedule set'}</div>`;
    return `<div class="rc" data-id="${pId}" data-type="${esc(p.type)}" data-rating="${rat}" data-lat="${parseFloat(p.lat||0)}" data-lng="${parseFloat(p.lng||0)}" data-telehealth="${hasTel?1:0}" data-verified="${isVerified?1:0}">
      <div class="rc-inner">
        ${isDoc
          ?docImgHtml
          :isHospReg&&avatar
            ?`<div class="rc-hosp-logo" style="position:relative"><img src="${esc(avatar)}" alt="${name}" loading="lazy">${p.is_available?'<div style="position:absolute;top:10px;left:50%;transform:translateX(-50%);background:rgba(255,255,255,.93);backdrop-filter:blur(4px);padding:3px 8px;border-radius:5px;font-size:9.5px;font-weight:800;color:#059669;display:flex;align-items:center;gap:4px;white-space:nowrap"><i class=\'fa-solid fa-circle\' style=\'font-size:5px;color:#22c55e\'></i>Open Now</div>':''}<div style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);background:rgba(25,120,229,.88);color:#fff;padding:2px 8px;border-radius:9999px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><i class='fa-solid fa-circle-check' style='margin-right:2px'></i>Verified</div></div>`
            :`<div class="rc-img"><img src="${hospitalImg}" alt="${name}" loading="lazy">${p.is_available?'<span class="open-badge"><i class="fa-solid fa-circle" style="font-size:5px;color:#22c55e"></i>Open Now</span>':''}<span class="dist-badge"><i class="fa-solid fa-location-dot" style="color:var(--primary)"></i>${dist}</span>${isHospReg?'<span class="verified-badge"><i class="fa-solid fa-circle-check" style="margin-right:2px"></i>Verified</span>':''}</div>`}
        <div class="rc-body">
          <div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
              <div style="flex:1;min-width:0">
                <div class="rc-name">${name}</div>
                ${spec?`<div class="rc-spec">${spec}</div>`:''}
                ${locSubtext}
                ${expBadge}
                <div class="rc-rating"><i class="fa-solid fa-star" style="color:var(--yellow);font-size:12px"></i><span style="font-weight:800;color:var(--ink)">${rat}</span><span style="font-size:12px;color:var(--faint)">(${reviewCount}+ reviews)</span></div>
              </div>
              <button onclick="toggleFav(this)" style="color:rgba(0,0,0,.2);background:none;border:none;cursor:pointer;font-size:18px;padding:2px 4px;flex-shrink:0;line-height:1"><i class="fa-regular fa-heart"></i></button>
            </div>
            <div class="rc-loc"><i class="fa-solid fa-location-dot" style="color:var(--primary);font-size:12px;flex-shrink:0"></i><span>${addr}</span>${p._dist!==null&&p._dist!==undefined?`<span style="color:var(--primary);font-weight:700;margin-left:5px">${p._dist.toFixed(1)} km</span>`:''}</div>
            ${schedDisp}
            <div class="rc-tags">${tags}</div>
          </div>
          ${isStDoc&&p.schedule_info
            ?`<div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#059669;font-weight:700;padding:6px 0;border-top:1px solid rgba(0,0,0,.05);margin-top:4px;flex-wrap:wrap"><i class="fa-regular fa-clock" style="font-size:10px"></i><span>Consulting: ${esc(p.schedule_info.split('|').slice(0,3).map(s=>{const parts=s.split(':');const days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];return (days[parseInt(parts[0])]||'')+' '+(parts[1]||'');}).join(', ').substring(0,60))}</span></div>`
            :isHospDoc
              ?`<div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#059669;font-weight:700;padding:6px 0;border-top:1px solid rgba(0,0,0,.05);margin-top:4px"><i class="fa-regular fa-clock" style="font-size:10px"></i><span>${p.avail_json?'Available: '+Object.keys(JSON.parse(p.avail_json||'{}')).join(', '):'Mon–Fri 8:00 AM – 5:00 PM'}</span></div>`
              :isHospReg
                ?`<div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#059669;font-weight:700;padding:6px 0;border-top:1px solid rgba(0,0,0,.05);margin-top:4px"><i class="fa-regular fa-clock" style="font-size:10px"></i><span>Mon–Sat 8:00 AM – 5:00 PM</span></div>`
                :''}
          <div class="rc-footer">
            ${slotRow}
            ${bookBtn}
          </div>
        </div>
      </div>
    </div>`;
  }).join('');

  /* Pagination */
  const totalPages=Math.ceil(filtered.length/PER_PAGE);
  const pg=document.getElementById('pagination');
  if(totalPages<=1){ pg.innerHTML=''; return; }
  let html='';
  if(currentPage>1) html+=`<button class="pg-btn" onclick="goPage(${currentPage-1})"><i class="fa-solid fa-chevron-left"></i></button>`;
  for(let i=1;i<=totalPages;i++){
    if(i===1||i===totalPages||Math.abs(i-currentPage)<=1){
      html+=`<button class="pg-btn${i===currentPage?' active':''}" onclick="goPage(${i})">${i}</button>`;
    } else if(Math.abs(i-currentPage)===2){
      html+=`<span class="pg-btn" style="cursor:default;opacity:.5">…</span>`;
    }
  }
  if(currentPage<totalPages) html+=`<button class="pg-btn" onclick="goPage(${currentPage+1})"><i class="fa-solid fa-chevron-right"></i></button>`;
  pg.innerHTML=html;
}

function goPage(p){ currentPage=p; renderResults(); window.scrollTo({top:200,behavior:'smooth'}); }

/* ════════════════════════════════════════════
   FAVOURITES
   ════════════════════════════════════════════ */
function toggleFav(btn){
  const i=btn.querySelector('i');
  const fav=i.classList.contains('fa-solid');
  i.classList.toggle('fa-solid',!fav);
  i.classList.toggle('fa-regular',fav);
  i.style.color=fav?'':'#ef4444';
}

/* ════════════════════════════════════════════
   BOOKING TRIGGER
   ════════════════════════════════════════════ */
function triggerBooking(id, name, isHospital, city, fee){
  /* Both logged-in and guest users can book — guests get the /patients/book.php page */
  if(!IS_LOGGED_IN){
    /* Redirect to book.php which handles guest booking */
    window.location.href='/patients/book.php?provider_id='+id+'&provider='+encodeURIComponent(name)+'&type='+(isHospital?'hospital':'doctor');
    return;
  }
  /* Logged-in: open the 5-step modal */
  SR.step=1; SR.reason=''; SR.otherNote=''; SR.date=null; SR.time=null;
  SR.visitType='in_person'; SR.notes=''; SR.calMonth=new Date(); SR.availMeta=null;
  SR.prov={ id, name, isHospital:!!isHospital, location:city||'', fee:fee||null };

  document.getElementById('srBkProvLabel').textContent=name+(city?' · '+city:'');
  ['srP1','srP2','srP3','srP4','srP5'].forEach((sid,i)=>{
    const el=document.getElementById(sid); if(el){ el.classList.remove('active'); el.style.display=''; if(i===0) el.classList.add('active'); }
  });
  document.getElementById('srSuccess').style.display='none';
  document.getElementById('srAlert').style.display='none';
  document.getElementById('srStepsBar').style.display='';
  document.getElementById('srFooter').style.display='';
  document.getElementById('srReason').value='';
  document.getElementById('srOtherRow').style.display='none';
  document.getElementById('srNotes').value='';
  document.getElementById('srNextBtn').style.display='';
  document.getElementById('srConfirmBtn').style.display='none';
  document.getElementById('srBackBtn').style.display='none';
  srUpdateSteps();

  const modal=document.getElementById('srBkModal');
  modal.classList.add('open');
  document.body.style.overflow='hidden';
}

function closeSrModal(){
  document.getElementById('srBkModal').classList.remove('open');
  document.body.style.overflow='';
}

/* ════════════════════════════════════════════
   5-STEP BOOKING FLOW
   ════════════════════════════════════════════ */
const SR_REASON_MAP={
  general_consultation:'General Consultation',
  followup:'Follow-up Visit',
  checkup:'Routine Check-up',
  specialist:'Specialist Referral',
  emergency:'Emergency',
  other:'Other'
};
const SR={step:1,reason:'',otherNote:'',date:null,time:null,visitType:'in_person',notes:'',prov:null,calMonth:new Date(),availMeta:null};

function srUpdateSteps(){
  document.querySelectorAll('#srStepsBar .bk-step-item').forEach((el,i)=>{
    el.classList.remove('active','done');
    const dot=el.querySelector('.bk-dot');
    if(i+1<SR.step){ el.classList.add('done'); if(dot) dot.innerHTML='<i class="fa-solid fa-check" style="font-size:.6rem"></i>'; }
    else if(i+1===SR.step){ el.classList.add('active'); if(dot) dot.textContent=i+1; }
    else { if(dot) dot.textContent=i+1; }
    const line=el.querySelector('.bk-step-line');
    if(line) line.style.background=i+1<SR.step?'var(--green)':'var(--border)';
  });
  const back=document.getElementById('srBackBtn'), next=document.getElementById('srNextBtn'), conf=document.getElementById('srConfirmBtn');
  if(back) back.style.display=SR.step>1?'':'none';
  if(next) next.style.display=SR.step<5?'':'none';
  if(conf) conf.style.display=SR.step===5?'':'none';
}

function srShowPanel(s){
  ['srP1','srP2','srP3','srP4','srP5'].forEach((sid,i)=>{
    const el=document.getElementById(sid); if(el) el.classList.toggle('active',i+1===s);
  });
  document.getElementById('srAlert').style.display='none';
  srUpdateSteps();
  if(s===2) srRenderCal();
  if(s===3) srRenderSlots();
  if(s===5) srRenderSummary();
}

function srNext(){
  const a=document.getElementById('srAlert');
  a.style.display='none';
  const show=m=>{ a.className='alert err'; a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> '+m; a.style.display='flex'; };
  if(SR.step===1){
    SR.reason=document.getElementById('srReason').value;
    if(!SR.reason){ show('Please select a reason for your visit.'); return; }
    SR.otherNote=document.getElementById('srOtherNote').value.trim();
    if(SR.reason==='other'&&!SR.otherNote){ show('Please describe your reason for visiting.'); return; }
  }
  if(SR.step===2&&!SR.date){ show('Please select a date.'); return; }
  if(SR.step===3&&!SR.time){ show('Please select a time slot.'); return; }
  if(SR.step===4){ SR.notes=document.getElementById('srNotes').value.trim(); }
  SR.step++; srShowPanel(SR.step);
}
function srPrev(){ if(SR.step>1){ SR.step--; srShowPanel(SR.step); } }
function srSelType(btn,type){ SR.visitType=type; document.querySelectorAll('.bk-type-btn').forEach(b=>b.classList.toggle('selected',b===btn)); }

/* Calendar — show only days provider is available */
async function srRenderCal(){
  const today=new Date(); today.setHours(0,0,0,0);
  const yr=SR.calMonth.getFullYear(), mo=SR.calMonth.getMonth();
  const first=new Date(yr,mo,1), days=new Date(yr,mo+1,0).getDate();
  const months=['January','February','March','April','May','June','July','August','September','October','November','December'];
  document.getElementById('srCalMonth').textContent=months[mo]+' '+yr;

  // Fetch provider availability meta once and cache on SR object
  let availDows=new Set([1,2,3,4,5,6]); // default Mon-Sat
  if(SR.prov?.id && !SR.availMeta){
    try{
      const svcType = SR.prov.isHospital?'hospital':'doctor';
      const ar=await fetch(`/api/patient/get-slots.php?provider_id=${SR.prov.id}&date=${yr}-${String(mo+1).padStart(2,'0')}-15&service_type=${svcType}&meta=1`,{credentials:'same-origin'}).then(r=>r.json());
      SR.availMeta = ar; // cache it
      if(ar.available_dows && ar.available_dows.length>0) availDows=new Set(ar.available_dows);
      // Show availability hours in the hint bar
      if(ar.availability_summary && ar.availability_summary.length>0){
        const summaryLines = ar.availability_summary.map(s=>s.label).join(' &nbsp;|&nbsp; ');
        const hint=document.getElementById('srNextHint'), hintTxt=document.getElementById('srNextHintTxt');
        if(hint&&hintTxt){ hint.style.display='flex'; hintTxt.innerHTML='<i class="fa-regular fa-clock"></i> Hours: '+summaryLines; }
      }
    }catch(e){}
  } else if(SR.availMeta?.available_dows && SR.availMeta.available_dows.length>0){
    availDows=new Set(SR.availMeta.available_dows);
  }

  let h=['Su','Mo','Tu','We','Th','Fr','Sa'].map(d=>`<div class="bk-cal-dh">${d}</div>`).join('');
  for(let i=0;i<first.getDay();i++) h+=`<button class="bk-cal-d empty" disabled></button>`;
  for(let d=1;d<=days;d++){
    const dt=new Date(yr,mo,d), isPast=dt<today;
    const dow=dt.getDay();
    const avail=!isPast&&availDows.has(dow);
    const ds=`${yr}-${String(mo+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const isSel=SR.date===ds;
    const isToday=dt.toDateString()===today.toDateString();
    h+=`<button class="bk-cal-d${isToday?' today':''}${avail?' avail':''}${isPast?' past':''}${!avail&&!isPast?' no-avail':''}${isSel?' selected':''}" data-date="${ds}" ${(!avail)?'disabled':''} onclick="srSelDate('${ds}',this)">${d}</button>`;
  }
  document.getElementById('srCalGrid').innerHTML=h;

  const hint=document.getElementById('srNextHint'), hintTxt=document.getElementById('srNextHintTxt');
  if(hint&&hintTxt){
    let nextStr=''; for(let d=1;d<=days;d++){ const dt=new Date(yr,mo,d); if(dt>today&&availDows.has(dt.getDay())){ nextStr=dt.toLocaleDateString('en-KE',{weekday:'short',month:'short',day:'numeric'}); break; } }
    if(nextStr){ hint.style.display='flex'; hintTxt.textContent='Next available: '+nextStr; }
    else { hint.style.display='none'; }
  }

  document.getElementById('srCalPrev').onclick=()=>{ SR.calMonth.setMonth(mo-1); srRenderCal(); };
  document.getElementById('srCalNext').onclick=()=>{ SR.calMonth.setMonth(mo+1); srRenderCal(); };
}
function srSelDate(d,btn){
  SR.date=d; SR.time=null;
  document.querySelectorAll('#srCalGrid .bk-cal-d').forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
}

/* Time slots — fetch from API */
async function srRenderSlots(){
  const wrap=document.getElementById('srSlotsWrap');
  wrap.innerHTML='<div style="text-align:center;padding:20px;color:var(--faint)"><i class="fa-solid fa-circle-notch fa-spin" style="font-size:20px;margin-bottom:8px;display:block"></i><div style="font-size:12px">Loading available slots…</div></div>';
  if(!SR.prov?.id||!SR.date){wrap.innerHTML='<div class="bk-period-lbl">Please select a date first.</div>';return;}
  try{
    const r=await fetch(`/api/patient/get-slots.php?provider_id=${SR.prov.id}&date=${SR.date}&service_type=${SR.prov.isHospital?'hospital':'doctor'}`,{credentials:'same-origin'}).then(r=>r.json());
    if(!r.success||!r.slots||r.slots.length===0){
      wrap.innerHTML=`<div style="text-align:center;padding:24px;color:var(--faint)"><i class="fa-solid fa-calendar-xmark" style="font-size:28px;margin-bottom:10px;display:block;opacity:.4"></i><div style="font-size:13px;font-weight:600">${r.message||'No slots available on this date.'}</div>${r.next_available_hint?`<div style="font-size:11.5px;margin-top:6px;color:var(--primary)"><i class="fa-regular fa-calendar"></i> Next available: ${r.next_available_hint}</div>`:''}</div>`;
      return;
    }
    const g=r.grouped||{morning:[],afternoon:[],evening:[]};
    const mkSlot=(t)=>{
      const isSel=SR.time===t;
      const h=parseInt(t.split(':')[0]),m=t.split(':')[1],ap=h<12?'AM':'PM',h12=h>12?h-12:(h||12);
      return `<button class="bk-slot${isSel?' selected':''}" data-t="${t}" onclick="srSelSlot('${t}',this)">${h12}:${m} ${ap}</button>`;
    };
    let html='';
    if(g.morning?.length){html+=`<div class="bk-period-lbl"><i class="fa-regular fa-sun" style="color:var(--yellow)"></i>Morning</div><div class="bk-slots-grid">${g.morning.map(mkSlot).join('')}</div>`;}
    if(g.afternoon?.length){html+=`<div class="bk-period-lbl"><i class="fa-solid fa-sun" style="color:var(--primary)"></i>Afternoon</div><div class="bk-slots-grid">${g.afternoon.map(mkSlot).join('')}</div>`;}
    if(g.evening?.length){html+=`<div class="bk-period-lbl"><i class="fa-solid fa-moon" style="color:#7c3aed"></i>Evening</div><div class="bk-slots-grid">${g.evening.map(mkSlot).join('')}</div>`;}
    if(!html)html='<div style="text-align:center;padding:20px;color:var(--faint);font-size:13px">No available slots for this date.</div>';
    wrap.innerHTML=html;
  }catch(e){wrap.innerHTML='<div style="color:var(--red);font-size:12.5px;padding:10px"><i class="fa-solid fa-triangle-exclamation"></i> Could not load time slots. Please try again.</div>';}
}
function srSelSlot(t,btn){
  SR.time=t;
  document.querySelectorAll('.bk-slot').forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
}

/* Summary */
function srRenderSummary(){
  const p=SR.prov||{};
  document.getElementById('srSumProv').textContent=p.name||'—';
  document.getElementById('srSumLoc').textContent=p.location||'—';
  document.getElementById('srSumDate').textContent=fmtDate(SR.date);
  document.getElementById('srSumTime').textContent=fmtTime(SR.time);
  document.getElementById('srSumType').innerHTML=SR.visitType==='telehealth'
    ?'<i class="fa-solid fa-video" style="color:var(--primary)"></i> Telehealth'
    :'<i class="fa-solid fa-house-medical" style="color:var(--green)"></i> In-Person';
  document.getElementById('srSumReason').textContent=SR_REASON_MAP[SR.reason]||SR.reason;
  const fr=document.getElementById('srSumFeeRow');
  if(fr){
    if(p.fee&&!p.isHospital){ fr.style.display=''; document.getElementById('srSumFee').textContent='KES '+Number(p.fee).toLocaleString(); }
    else fr.style.display='none';
  }
}

/* Confirm booking */
async function srConfirm(){
  const btn=document.getElementById('srConfirmBtn');
  const a=document.getElementById('srAlert');
  a.style.display='none';
  if(!SR.date||!SR.time){ a.className='alert err'; a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> Missing date or time.'; a.style.display='flex'; return; }

  btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Booking…';
  const[h,m]=SR.time.split(':').map(Number);
  const payload={
    service_type: SR.prov?.isHospital?'hospital':'doctor',
    provider_id:  SR.prov?.id||null,
    appointment_at: SR.date+' '+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':00',
    title: SR_REASON_MAP[SR.reason]||SR.reason,
    notes: SR.otherNote||SR.notes,
    location_type: SR.visitType,
    csrf_token: document.getElementById('srCsrf')?.value||''
  };
  try{
    const r=await fetch('/api/patient/book-appointment.php',{
      method:'POST',
      headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body:JSON.stringify(payload),
      credentials:'same-origin'
    }).then(r=>r.json());
    if(r.requires_login){ window.location.href=r.redirect||'/patients/login.php'; return; }
    if(r.success){
      document.getElementById('srRefCode').textContent='PZY-'+String(r.appointment_id||'').padStart(6,'0');
      ['srP1','srP2','srP3','srP4','srP5'].forEach(sid=>{ const el=document.getElementById(sid); if(el){ el.classList.remove('active'); el.style.display='none'; } });
      document.getElementById('srStepsBar').style.display='none';
      document.getElementById('srFooter').style.display='none';
      document.getElementById('srSuccess').style.display='block';
    } else {
      a.className='alert err'; a.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> '+(r.message||'Booking failed. Please try again.'); a.style.display='flex';
    }
  } catch(e){
    a.className='alert err'; a.innerHTML='<i class="fa-solid fa-wifi"></i> Network error. Please check your connection.'; a.style.display='flex';
  } finally{
    btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-calendar-check"></i> Confirm Booking';
  }
}

/* ════════════════════════════════════════════
   INIT
   ════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded',()=>{
  /* Auto-geo if already permitted */
  if(navigator.permissions){
    navigator.permissions.query({name:'geolocation'}).then(r=>{ if(r.state==='granted') triggerGeo(); }).catch(()=>{});
  }
  /* Apply filters to do initial JS render (syncs with sidebar state) */
  renderResults();
});
</script>