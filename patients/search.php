<?php
/**
 * Planeazzy — /patients/search.php
 * Search & browse healthcare providers.
 * Geolocation requested when user arrives via "Hospitals Near You".
 * All filters (distance, insurance, visit type, type) work client-side after initial load.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Database.php';
Security::startSession();

// ── URL PARAMETERS ──────────────────────────────────────────
$q          = trim($_GET['q']          ?? '');
$location   = trim($_GET['location']   ?? '');
$visitType  = in_array($_GET['visit_type'] ?? '', ['in_person','telehealth']) ? $_GET['visit_type'] : 'in_person';
$resType    = in_array($_GET['type']   ?? $_GET['rt'] ?? '', ['doctor','hospital','clinic','ambulance','pharmacy']) ? ($_GET['type'] ?? $_GET['rt']) : 'all';
$geoRequest = !empty($_GET['geo']);   // ?geo=1 means "auto-request location"
$csrf       = Security::csrfToken();
$isLoggedIn = !empty($_SESSION['patient_id']) && !empty($_SESSION['authenticated']);

// ── DATABASE FETCH — all active verified providers ───────────
$providers  = [];
try {
    $db         = Database::getInstance();
    $conditions = ['p.is_active=1', 'p.is_verified=1'];
    $params     = [];

    if ($resType !== 'all' && in_array($resType, ['doctor','hospital','clinic','ambulance','pharmacy'])) {
        $conditions[] = 'p.type = :type';
        $params[':type'] = $resType;
    }
    if (!empty($q)) {
        $conditions[] = '(p.name LIKE :q OR p.specialty LIKE :q OR p.address LIKE :q OR p.city LIKE :q)';
        $params[':q']  = '%' . $q . '%';
    }
    if ($visitType === 'telehealth') {
        $conditions[] = "(p.services LIKE '%telehealth%')";
    }

    $where    = 'WHERE ' . implode(' AND ', $conditions);
    $sql      = "SELECT p.*, p.latitude AS lat, p.longitude AS lng
                 FROM providers p $where
                 ORDER BY p.rating DESC, p.name ASC LIMIT 60";
    $providers = $db->fetchAll($sql, $params);
} catch (Exception $e) {
    $providers = [];
}

// ── SEED FALLBACK ────────────────────────────────────────────
if (empty($providers)) {
    $providers = [
        ['id'=>1,'name'=>'Aga Khan University Hospital','type'=>'hospital','specialty'=>'Multi-Specialist','address'=>'Parklands, Nairobi','city'=>'Nairobi','rating'=>4.9,'is_available'=>1,'lat'=>-1.2645,'lng'=>36.8106,'services'=>'["emergency","surgery","lab","imaging","outpatient"]'],
        ['id'=>2,'name'=>'The Nairobi Hospital',        'type'=>'hospital','specialty'=>'General & Maternity','address'=>'Argwings Kodhek Rd, Nairobi','city'=>'Nairobi','rating'=>4.7,'is_available'=>1,'lat'=>-1.2992,'lng'=>36.8085,'services'=>'["emergency","maternity","cardiology","lab"]'],
        ['id'=>3,'name'=>'MP Shah Hospital',             'type'=>'hospital','specialty'=>'Critical Care & Cancer','address'=>'Shivachi Rd, Nairobi','city'=>'Nairobi','rating'=>4.8,'is_available'=>1,'lat'=>-1.2855,'lng'=>36.8219,'services'=>'["emergency","oncology","surgery","paediatrics"]'],
        ['id'=>4,'name'=>'Karen Hospital',               'type'=>'hospital','specialty'=>'Private Hospital','address'=>'Langata Road, Karen','city'=>'Nairobi','rating'=>4.6,'is_available'=>1,'lat'=>-1.3500,'lng'=>36.6900,'services'=>'["outpatient","surgery","maternity"]'],
        ['id'=>5,'name'=>'Mater Hospital',               'type'=>'clinic','specialty'=>'Outpatient & Surgery','address'=>'South C, Nairobi','city'=>'Nairobi','rating'=>4.5,'is_available'=>1,'lat'=>-1.3181,'lng'=>36.8338,'services'=>'["outpatient","lab","pharmacy"]'],
        ['id'=>6,'name'=>'Westlands Medical Centre',     'type'=>'clinic','specialty'=>'General Outpatient','address'=>'Westlands, Nairobi','city'=>'Nairobi','rating'=>4.3,'is_available'=>1,'lat'=>-1.2680,'lng'=>36.8075,'services'=>'["consultation","lab","pharmacy"]'],
        ['id'=>7,'name'=>'Dr. Sarah Wanjiku',            'type'=>'doctor','specialty'=>'General Practitioner','address'=>'Westlands, Nairobi','city'=>'Nairobi','rating'=>4.8,'is_available'=>1,'lat'=>-1.2676,'lng'=>36.8069,'services'=>'["consultation","telehealth","vaccination"]'],
        ['id'=>8,'name'=>'Dr. James Omondi',             'type'=>'doctor','specialty'=>'Cardiologist','address'=>'Upper Hill, Nairobi','city'=>'Nairobi','rating'=>4.9,'is_available'=>1,'lat'=>-1.2979,'lng'=>36.8150,'services'=>'["cardiology","telehealth","echocardiography"]'],
        ['id'=>9,'name'=>'Dr. Amina Hassan',             'type'=>'doctor','specialty'=>'Pediatrician','address'=>'Karen, Nairobi','city'=>'Nairobi','rating'=>4.7,'is_available'=>1,'lat'=>-1.3500,'lng'=>36.6900,'services'=>'["pediatrics","telehealth","vaccination"]'],
        ['id'=>10,'name'=>'Nairobi Ambulance Services',  'type'=>'ambulance','specialty'=>'24/7 Emergency Dispatch','address'=>'Nairobi CBD','city'=>'Nairobi','rating'=>4.4,'is_available'=>1,'lat'=>-1.2833,'lng'=>36.8167,'services'=>'["emergency","paramedic","critical_care"]'],
        ['id'=>11,'name'=>'St John Ambulance Kenya',     'type'=>'ambulance','specialty'=>'Emergency First Aid','address'=>'Haile Selassie Ave, Nairobi','city'=>'Nairobi','rating'=>4.6,'is_available'=>1,'lat'=>-1.2864,'lng'=>36.8225,'services'=>'["emergency","first_aid"]'],
        ['id'=>12,'name'=>'Goodlife Pharmacy Westlands','type'=>'pharmacy','specialty'=>'Pharmacy & Consultation','address'=>'Westlands, Nairobi','city'=>'Nairobi','rating'=>4.3,'is_available'=>1,'lat'=>-1.2680,'lng'=>36.8075,'services'=>'["prescription","otc","delivery"]'],
    ];
    // Apply search filter on seeds
    if (!empty($q)) {
        $providers = array_values(array_filter($providers, fn($p) =>
            stripos($p['name'],$q)!==false ||
            stripos($p['specialty'],$q)!==false ||
            stripos($p['address'],$q)!==false
        ));
    }
    if ($resType !== 'all') {
        $providers = array_values(array_filter($providers, fn($p) => $p['type'] === $resType));
    }
}

// Build JSON payload for client-side filtering/sorting
$providersJson = json_encode(array_values($providers), JSON_HEX_APOS | JSON_HEX_QUOT);

// Images
$hospitalImgs = ['https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=600&q=70','https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=600&q=70','https://images.unsplash.com/photo-1551076805-e1869033e561?w=600&q=70','https://images.unsplash.com/photo-1538108149393-fbbd81895907?w=600&q=70','https://images.unsplash.com/photo-1587351021759-3e566b6af7cc?w=600&q=70'];
$doctorImgs   = ['https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=400&q=70','https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=400&q=70','https://images.unsplash.com/photo-1594824476967-48c8b964273f?w=400&q=70'];
$slotTimes    = ['Tomorrow 10:00 AM','Today 4:30 PM','Wednesday 9:00 AM','Today 2:00 PM','Thursday 11:00 AM'];

$noSidebar = true;
$pageTitle  = count($providers) . ' Results' . ($q ? " for \"$q\"" : '');
include dirname(__DIR__) . '/includes/header.php';
?>

<style>
/* ── Search page styles ──────────────────────────────── */
:root{--ink:#0f172a;--muted:#475569;--faint:#64748b;--border:#e2e8f0;--bg:#f8fafc;--card:#fff;--primary:#1978e5;}
.sr-wrap{max-width:1280px;margin:0 auto;padding:28px 24px;display:flex;gap:28px}
.sr-sidebar{width:272px;flex-shrink:0}
.sr-main{flex:1;min-width:0}
/* Filter section */
.flt-title{font-size:11px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.1em;margin-bottom:14px}
.flt-section{margin-bottom:28px}
/* Radio / checkbox label */
.flt-label{display:flex;align-items:center;gap:11px;cursor:pointer;margin-bottom:11px;font-size:14px;color:var(--muted);line-height:1.4}
.flt-label input{width:17px;height:17px;border-radius:4px;accent-color:var(--primary);cursor:pointer;flex-shrink:0}
/* Type toggle */
.type-toggle{display:flex;background:rgba(100,116,139,.1);border-radius:9px;padding:4px;gap:3px}
.type-btn{flex:1;padding:7px 10px;font-size:12px;font-weight:700;border-radius:6px;border:none;cursor:pointer;font-family:inherit;background:transparent;color:var(--faint);transition:background .15s,color .15s}
.type-btn.active{background:var(--primary);color:#fff}
/* Availability select */
.flt-select{width:100%;border:1.5px solid var(--border);border-radius:9px;background:var(--card);padding:10px 12px;font-family:inherit;font-size:14px;color:var(--muted);outline:none;cursor:pointer}
.flt-select:focus{border-color:var(--primary)}
/* Location gate */
.geo-gate{background:var(--card);border:1.5px solid var(--border);border-radius:16px;padding:24px;text-align:center;margin-bottom:20px}
.geo-gate-icon{width:56px;height:56px;border-radius:50%;background:rgba(25,120,229,.08);color:var(--primary);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:24px}
/* Result card */
.rc{background:var(--card);border-radius:16px;border:1.5px solid var(--border);overflow:hidden;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);transition:box-shadow .2s,border-color .2s}
.rc:hover{box-shadow:0 8px 28px rgba(0,0,0,.09);border-color:rgba(25,120,229,.2)}
.rc-inner{display:flex}
.rc-img{width:260px;flex-shrink:0;position:relative;overflow:hidden;min-height:200px}
.rc-img img{width:100%;height:100%;object-fit:cover;display:block}
.rc-doc-img{width:260px;flex-shrink:0;background:var(--bg);display:flex;align-items:center;justify-content:center;padding:24px;min-height:200px}
.rc-doc-img img{width:136px;height:136px;border-radius:20px;object-fit:cover;border:4px solid #fff;box-shadow:0 8px 24px rgba(0,0,0,.12)}
.rc-body{flex:1;padding:28px;display:flex;flex-direction:column;justify-content:space-between;min-width:0}
.rc-name{font-size:22px;font-weight:700;color:var(--ink);margin-bottom:3px;letter-spacing:-.02em}
.rc-spec{color:var(--primary);font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
.rc-rating{display:flex;align-items:center;gap:4px;font-size:14px;color:var(--muted)}
.rc-loc{display:flex;align-items:center;gap:7px;color:var(--faint);font-size:13px;margin:14px 0}
.rc-tags{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:20px}
.rc-tag{padding:4px 11px;border-radius:7px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.rc-tag-ins{background:rgba(25,120,229,.08);color:var(--primary)}
.rc-tag-pay{background:rgba(13,148,136,.08);color:#0d9488}
.rc-tag-tel{background:rgba(5,150,105,.08);color:#059669}
.rc-footer{display:flex;align-items:center;justify-content:space-between;padding-top:20px;border-top:1px solid var(--border);flex-wrap:wrap;gap:10px}
.rc-slot{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--muted)}
.rc-slot strong{color:var(--ink)}
.rc-slot i{color:#22c55e}
.btn-select{padding:11px 28px;font-size:14px;font-weight:800;border-radius:12px;border:2px solid var(--primary);background:transparent;color:var(--primary);cursor:pointer;font-family:inherit;transition:background .15s,color .15s}
.btn-select:hover{background:var(--primary);color:#fff}
.btn-book{padding:11px 28px;font-size:14px;font-weight:800;border-radius:12px;border:none;background:var(--primary);color:#fff;cursor:pointer;font-family:inherit;box-shadow:0 6px 14px rgba(25,120,229,.25)}
/* Sort bar */
.sort-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.sort-select{border:1.5px solid var(--border);border-radius:9px;background:var(--card);padding:8px 14px;font-family:inherit;font-size:13px;color:var(--muted);outline:none;cursor:pointer}
/* Open badge */
.open-badge{position:absolute;top:10px;left:10px;background:rgba(255,255,255,.9);backdrop-filter:blur(4px);padding:3px 9px;border-radius:5px;font-size:10px;font-weight:800;color:#059669}
/* Dist badge */
.dist-badge{position:absolute;top:10px;right:10px;background:rgba(255,255,255,.9);backdrop-filter:blur(4px);padding:3px 9px;border-radius:5px;font-size:11px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:4px}
/* Empty state */
.empty-state{background:var(--card);border-radius:16px;border:1.5px solid var(--border);padding:60px 24px;text-align:center}
/* Topbar search */
.search-topbar{background:var(--card);padding:14px 0;border-bottom:1px solid var(--border);position:sticky;top:80px;z-index:40}
.search-topbar-inner{max-width:1280px;margin:0 auto;padding:0 24px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.stb-field{flex:1;display:flex;align-items:center;gap:8px;padding:9px 16px;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;min-width:130px}
.stb-field input,.stb-field select{border:none;outline:none;background:transparent;font-family:inherit;font-size:14px;color:var(--ink);width:100%}
.stb-field select{color:var(--faint);cursor:pointer;appearance:none;-webkit-appearance:none}
.stb-btn{background:var(--primary);color:#fff;padding:10px 24px;border-radius:10px;border:none;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0}
/* Location bar */
.loc-bar{background:rgba(25,120,229,.06);border:1px solid rgba(25,120,229,.15);border-radius:10px;padding:11px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap}
/* Pagination */
.pg{display:flex;justify-content:center;gap:4px;margin-top:36px}
.pg-btn{width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:8px;font-size:14px;border:1.5px solid var(--border);background:var(--card);cursor:pointer;color:var(--muted);font-family:inherit;transition:background .15s,color .15s}
.pg-btn.active{background:var(--primary);border-color:var(--primary);color:#fff;font-weight:700}
.pg-btn:not(.active):hover{border-color:var(--primary);color:var(--primary)}
/* Mobile responsive */
@media(max-width:900px){.sr-sidebar{display:none}.sr-wrap{padding:20px 16px;gap:0}.rc-inner{flex-direction:column}.rc-img,.rc-doc-img{width:100%;height:180px;min-height:unset}.rc-doc-img img{width:100px;height:100px}}
@media(max-width:600px){.rc-name{font-size:18px}.rc-footer{flex-direction:column;align-items:flex-start}.btn-select,.btn-book{width:100%}}
</style>

<!-- ── STICKY TOP SEARCH BAR ─────────────────────────────── -->
<div class="search-topbar">
  <div class="search-topbar-inner">
    <div class="stb-field" style="max-width:200px">
      <i class="fa-solid fa-location-dot" style="color:var(--primary);font-size:14px;flex-shrink:0"></i>
      <input type="text" id="sLoc" value="<?= htmlspecialchars($location) ?>"
        data-en-placeholder="Location…" data-sw-placeholder="Mahali…" placeholder="Location…">
    </div>
    <div class="stb-field" style="flex:2">
      <i class="fa-solid fa-magnifying-glass" style="color:var(--faint);font-size:13px;flex-shrink:0"></i>
      <input type="text" id="sQuery" value="<?= htmlspecialchars($q) ?>"
        data-en-placeholder="Doctor, specialty or hospital…" data-sw-placeholder="Daktari, utaalamu au hospitali…" placeholder="Doctor, specialty or hospital…">
    </div>
    <div class="stb-field" style="max-width:150px">
      <i class="fa-solid fa-calendar" style="color:var(--faint);font-size:13px;flex-shrink:0"></i>
      <select id="sType">
        <option value="in_person" <?= $visitType==='in_person'?'selected':'' ?>>In-person</option>
        <option value="telehealth" <?= $visitType==='telehealth'?'selected':'' ?>>Telehealth</option>
      </select>
    </div>
    <button class="stb-btn" onclick="doSearch()">
      <i class="fa-solid fa-magnifying-glass"></i>
      <span data-en="Update Search" data-sw="Sasisha Utafutaji">Update Search</span>
    </button>
    <button onclick="triggerGeo()" style="display:flex;align-items:center;gap:6px;background:rgba(25,120,229,.08);color:var(--primary);border:1.5px solid rgba(25,120,229,.2);padding:10px 16px;border-radius:10px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap" id="geoBtn">
      <i class="fa-solid fa-location-crosshairs"></i>
      <span data-en="Use My Location" data-sw="Tumia Mahali Pangu">Use My Location</span>
    </button>
  </div>
</div>

<!-- ── MAIN LAYOUT ────────────────────────────────────────── -->
<div class="sr-wrap">

  <!-- SIDEBAR FILTERS ─────────────────────────────────────── -->
  <aside class="sr-sidebar">

    <!-- Location status -->
    <div id="locStatusBar" style="display:none;background:rgba(25,120,229,.06);border:1px solid rgba(25,120,229,.16);border-radius:10px;padding:11px 14px;margin-bottom:20px;font-size:13px;color:var(--primary);display:flex;align-items:center;gap:7px">
      <i class="fa-solid fa-location-dot"></i>
      <span id="locStatusText" data-en="Detecting location…" data-sw="Kugunduliwa mahali…">Detecting location…</span>
    </div>

    <!-- Provider type filter -->
    <div class="flt-section">
      <div class="flt-title" data-en="Provider Type" data-sw="Aina ya Mtoa Huduma">Provider Type</div>
      <div style="display:flex;flex-direction:column;gap:8px" id="typeFilters">
        <?php foreach([['all','All Providers','Watoa Huduma Wote'],['hospital','Hospitals','Hospitali'],['clinic','Clinics','Kliniki'],['doctor','Doctors','Madaktari'],['ambulance','Ambulance','Ambulensi'],['pharmacy','Pharmacy','Duka la Dawa']] as [$v,$en,$sw]): ?>
        <label class="flt-label">
          <input type="radio" name="ptype" value="<?=$v?>" <?=$resType===$v||($resType==='all'&&$v==='all')?'checked':''?> onchange="applyFilters()">
          <span data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Distance filter -->
    <div class="flt-section">
      <div class="flt-title" data-en="Distance" data-sw="Umbali">Distance</div>
      <div id="distFilters">
        <?php foreach([['999','Any Distance','Umbali Wowote'],['10','Within 10 km','Ndani ya km 10'],['5','Within 5 km','Ndani ya km 5'],['2','Within 2 km','Ndani ya km 2']] as [$v,$en,$sw]): ?>
        <label class="flt-label">
          <input type="radio" name="dist" value="<?=$v?>" <?=$v==='999'?'checked':''?> onchange="applyFilters()">
          <span data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <div id="distNote" style="display:none;font-size:12px;color:var(--faint);margin-top:8px;padding:8px;background:rgba(25,120,229,.05);border-radius:8px" data-en="Allow location access to filter by distance" data-sw="Ruhusu upatikanaji wa mahali ili kuchuja kwa umbali">Allow location access to filter by distance</div>
    </div>

    <!-- Insurance filter -->
    <div class="flt-section">
      <div class="flt-title" data-en="Insurance Accepted" data-sw="Bima Inayokubaliwa">Insurance Accepted</div>
      <?php foreach([['nhif','NHIF Kenya'],['jubilee','Jubilee Health'],['axa','AXA Mansard'],['aar','AAR Healthcare'],['britam','Britam Health']] as [$v,$lbl]): ?>
      <label class="flt-label">
        <input type="checkbox" value="<?=$v?>" class="ins-chk" onchange="applyFilters()">
        <span><?=$lbl?></span>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- Visit type -->
    <div class="flt-section">
      <div class="flt-title" data-en="Visit Type" data-sw="Aina ya Ziara">Visit Type</div>
      <div class="type-toggle">
        <button class="type-btn <?=$visitType==='in_person'?'active':''?>" onclick="setVisitType('in_person',this)" data-en="In-Person" data-sw="Ana kwa Ana">In-Person</button>
        <button class="type-btn <?=$visitType==='telehealth'?'active':''?>" onclick="setVisitType('telehealth',this)" data-en="Telehealth" data-sw="Dawa Mtandaoni">Telehealth</button>
      </div>
    </div>

    <!-- Availability -->
    <div class="flt-section">
      <div class="flt-title" data-en="Availability" data-sw="Upatikanaji">Availability</div>
      <select class="flt-select" id="availFilter" onchange="applyFilters()">
        <option value="any" data-en="Any time" data-sw="Wakati wowote">Any time</option>
        <option value="today" data-en="Today" data-sw="Leo">Today</option>
        <option value="week" data-en="This week" data-sw="Wiki hii">This week</option>
      </select>
    </div>

    <!-- Rating -->
    <div class="flt-section">
      <div class="flt-title" data-en="Minimum Rating" data-sw="Ukadiriaji wa Chini">Minimum Rating</div>
      <?php foreach([['0','Any Rating','Ukadiriaji Wowote'],['4','4.0+','4.0+'],['4.5','4.5+','4.5+'],['4.8','4.8+ (Top Rated)','4.8+ (Bora Zaidi)']] as [$v,$en,$sw]): ?>
      <label class="flt-label">
        <input type="radio" name="rating" value="<?=$v?>" <?=$v==='0'?'checked':''?> onchange="applyFilters()">
        <span data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></span>
      </label>
      <?php endforeach; ?>
    </div>

    <button onclick="resetFilters()" style="width:100%;padding:10px;border:1.5px solid var(--border);background:var(--card);border-radius:9px;font-family:inherit;font-size:13px;font-weight:600;color:var(--muted);cursor:pointer">
      <i class="fa-solid fa-rotate-left" style="margin-right:5px"></i>
      <span data-en="Reset All Filters" data-sw="Weka Upya Vichujio Vyote">Reset All Filters</span>
    </button>
  </aside>

  <!-- MAIN RESULTS ─────────────────────────────────────────── -->
  <div class="sr-main">

    <!-- Geo gate (shown when ?geo=1 and no location yet) -->
    <div id="geoGate" style="display:<?= $geoRequest ? 'block' : 'none' ?>">
      <div class="geo-gate">
        <div class="geo-gate-icon"><i class="fa-solid fa-location-crosshairs"></i></div>
        <h3 style="font-size:17px;font-weight:700;color:var(--ink);margin-bottom:8px" data-en="Allow Location Access" data-sw="Ruhusu Upatikanaji wa Mahali">Allow Location Access</h3>
        <p style="font-size:14px;color:var(--muted);line-height:1.7;max-width:400px;margin:0 auto 18px"
           data-en="To show you hospitals and doctors nearest to you, Planeazzy needs your location. Your GPS is only used to sort results — it is never stored."
           data-sw="Ili kukuonyesha hospitali na madaktari walio karibu nawe, Planeazzy inahitaji mahali pako. GPS yako inatumika tu kupanga matokeo — haijawahi kuhifadhiwa.">
          To show you hospitals and doctors nearest to you, Planeazzy needs your location. Your GPS is only used to sort results — it is never stored.
        </p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
          <button onclick="triggerGeo()" style="background:var(--primary);color:#fff;padding:12px 26px;border-radius:10px;border:none;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 6px 14px rgba(25,120,229,.3)">
            <i class="fa-solid fa-location-dot" style="margin-right:6px"></i>
            <span data-en="Allow & Show Nearby" data-sw="Ruhusu & Onyesha Karibu">Allow & Show Nearby</span>
          </button>
          <button onclick="dismissGeoGate()" style="background:var(--bg);border:1.5px solid var(--border);color:var(--muted);padding:12px 22px;border-radius:10px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer">
            <span data-en="Skip — show all" data-sw="Ruka — onyesha yote">Skip — show all</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Location bar (shown after geo detected) -->
    <div id="locBar" style="display:none" class="loc-bar">
      <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--primary);font-weight:600">
        <i class="fa-solid fa-location-dot"></i>
        <span id="locBarText" data-en="Showing results near your location" data-sw="Onyesha matokeo karibu na mahali pako">Showing results near your location</span>
      </div>
      <button onclick="clearGeo()" style="font-size:12px;color:var(--faint);background:none;border:none;cursor:pointer;text-decoration:underline;font-family:inherit">
        <span data-en="Clear location" data-sw="Futa mahali">Clear location</span>
      </button>
    </div>

    <!-- Sort & count bar -->
    <div class="sort-bar">
      <div>
        <span style="font-size:18px;font-weight:700;color:var(--ink)" id="resultCount"><?= count($providers) ?></span>
        <span style="font-size:14px;color:var(--muted);margin-left:5px" id="resultLabel"
              data-en="providers found<?= $q ? ' for &quot;'.htmlspecialchars($q).'&quot;' : '' ?>"
              data-sw="watoa huduma walipatikana<?= $q ? ' kwa &quot;'.htmlspecialchars($q).'&quot;' : '' ?>">
          providers found<?= $q ? ' for "'.htmlspecialchars($q).'"' : '' ?>
        </span>
      </div>
      <select class="sort-select" id="sortOrder" onchange="applyFilters()">
        <option value="rating" data-en="Sort: Top Rated" data-sw="Panga: Zilizo Bora">Sort: Top Rated</option>
        <option value="distance" data-en="Sort: Nearest First" data-sw="Panga: Karibu Zaidi">Sort: Nearest First</option>
        <option value="name" data-en="Sort: A–Z" data-sw="Panga: A–Z">Sort: A–Z</option>
      </select>
    </div>

    <!-- Results container -->
    <div id="resultsContainer">
      <!-- JS renders cards here; we also have PHP-rendered initial cards as fallback -->
      <?php foreach($providers as $i => $p):
        $isDoc = ($p['type'] === 'doctor');
        $img   = $isDoc ? $doctorImgs[$i % count($doctorImgs)] : $hospitalImgs[$i % count($hospitalImgs)];
        $slot  = $slotTimes[$i % count($slotTimes)];
        $rat   = number_format(floatval($p['rating'] ?? 4.5), 1);
        $rev   = rand(30, 300);
        $hasTel = str_contains($p['services'] ?? '', 'telehealth');
      ?>
      <div class="rc" data-id="<?=$p['id']?>" data-type="<?=htmlspecialchars($p['type'])?>" data-rating="<?=floatval($p['rating'] ?? 4.5)?>" data-lat="<?=floatval($p['lat'] ?? 0)?>" data-lng="<?=floatval($p['lng'] ?? 0)?>" data-telehealth="<?=$hasTel?1:0?>">
        <div class="rc-inner">
          <?php if($isDoc): ?>
          <div class="rc-doc-img"><img src="<?=$img?>" alt="<?=htmlspecialchars($p['name'])?>"></div>
          <?php else: ?>
          <div class="rc-img">
            <img src="<?=$img?>" alt="<?=htmlspecialchars($p['name'])?>">
            <?php if($p['is_available']??0): ?><span class="open-badge" data-en="Open" data-sw="Wazi">Open</span><?php endif; ?>
            <span class="dist-badge" id="dist-<?=$p['id']?>"><i class="fa-solid fa-location-dot" style="color:var(--primary)"></i> <span data-en="— km" data-sw="— km">— km</span></span>
          </div>
          <?php endif; ?>
          <div class="rc-body">
            <div>
              <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
                <div>
                  <div class="rc-name"><?=htmlspecialchars($p['name'])?></div>
                  <?php if($isDoc): ?><div class="rc-spec"><?=htmlspecialchars($p['specialty']??'Specialist')?></div><?php endif; ?>
                  <div class="rc-rating">
                    <i class="fa-solid fa-star" style="color:#f59e0b"></i>
                    <span style="font-weight:700;color:var(--ink)"><?=$rat?></span>
                    <span>(<?=$rev?>+ reviews)</span>
                  </div>
                </div>
                <button onclick="toggleFav(this)" style="color:var(--border);background:none;border:none;cursor:pointer;font-size:20px;padding:2px;flex-shrink:0"><i class="fa-regular fa-heart"></i></button>
              </div>
              <div class="rc-loc">
                <i class="fa-solid fa-location-dot" style="color:var(--primary)"></i>
                <span><?=htmlspecialchars($p['address']??$p['city']??'Nairobi, Kenya')?></span>
                <span class="dist-text" id="disttext-<?=$p['id']?>" style="color:var(--primary);font-weight:600"></span>
              </div>
              <div class="rc-tags">
                <span class="rc-tag rc-tag-ins" data-en="Insurance Accepted" data-sw="Bima Inakubaliwa">Insurance Accepted</span>
                <span class="rc-tag rc-tag-pay" data-en="Pay at Facility" data-sw="Lipa Hospitalini">Pay at Facility</span>
                <?php if($hasTel): ?><span class="rc-tag rc-tag-tel" data-en="Telehealth" data-sw="Dawa Mtandaoni">Telehealth</span><?php endif; ?>
              </div>
            </div>
            <div class="rc-footer">
              <div class="rc-slot"><i class="fa-solid fa-calendar-check"></i><span data-en="Next slot:" data-sw="Nafasi ijayo:">Next slot:</span>&nbsp;<strong><?=$slot?></strong></div>
              <?php if($isDoc): ?>
              <button class="btn-select" onclick="<?=$isLoggedIn?"bookProvider({$p['id']})":"goLogin()"?>" data-en="Select Doctor" data-sw="Chagua Daktari">Select Doctor</button>
              <?php else: ?>
              <button class="btn-book" onclick="<?=$isLoggedIn?"bookProvider({$p['id']})":"goLogin()"?>" data-en="Book Now" data-sw="Weka Miadi">Book Now</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Empty state (hidden until JS confirms no results) -->
    <div id="emptyState" style="display:none">
      <div class="empty-state">
        <i class="fa-solid fa-magnifying-glass-location" style="font-size:48px;color:var(--border);display:block;margin-bottom:16px"></i>
        <h3 style="font-size:18px;font-weight:700;color:var(--muted);margin-bottom:8px" data-en="No providers match your filters" data-sw="Hakuna watoa huduma wanaofanana na vichujio vyako">No providers match your filters</h3>
        <p style="font-size:14px;color:var(--faint);margin-bottom:20px" data-en="Try adjusting the distance, type, or visit mode filters." data-sw="Jaribu kurekebisha umbali, aina, au vichujio vya hali ya ziara.">Try adjusting the distance, type, or visit mode filters.</p>
        <button onclick="resetFilters()" style="padding:11px 24px;border-radius:10px;background:var(--primary);color:#fff;border:none;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer" data-en="Reset Filters" data-sw="Weka Upya Vichujio">Reset Filters</button>
      </div>
    </div>

    <!-- Pagination (shown when needed) -->
    <div class="pg" id="pagination"></div>
  </div>
</div>

<!-- Book modal for logged-in users -->
<?php if($isLoggedIn): ?>
<div id="bookModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:500;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)closeModal('bookModal')">
  <div style="background:var(--card);border-radius:20px;box-shadow:0 24px 50px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto;width:100%;max-width:500px">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px 14px;border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--card);border-radius:20px 20px 0 0">
      <h2 style="font-size:17px;font-weight:700;color:var(--ink)"><i class="fa-solid fa-calendar-plus" style="color:var(--primary);margin-right:8px"></i><span data-en="Book Appointment" data-sw="Weka Miadi">Book Appointment</span></h2>
      <button onclick="closeModal('bookModal')" style="width:28px;height:28px;border-radius:50%;background:var(--bg);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--muted)"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div style="padding:18px 22px 26px">
      <div id="bookAlertBox" style="display:none;margin-bottom:12px;padding:11px 14px;border-radius:9px;font-size:13px;display:flex;align-items:center;gap:8px"></div>
      <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" id="bookProvider" value="">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div><label style="font-size:12px;font-weight:700;color:var(--ink);display:block;margin-bottom:5px" data-en="Service Type" data-sw="Aina ya Huduma">Service Type</label>
          <select id="bookServiceType" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;color:var(--ink);outline:none;cursor:pointer">
            <option value="doctor" data-en="See a Doctor" data-sw="Muone Daktari">See a Doctor</option>
            <option value="hospital" data-en="Hospital Visit" data-sw="Ziara ya Hospitali">Hospital Visit</option>
            <option value="telehealth" data-en="Telehealth Video" data-sw="Video ya Telemedicine">Telehealth Video</option>
          </select>
        </div>
        <div><label style="font-size:12px;font-weight:700;color:var(--ink);display:block;margin-bottom:5px" data-en="Visit Type" data-sw="Aina ya Ziara">Visit Type</label>
          <select id="bookLocType" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;color:var(--ink);outline:none;cursor:pointer">
            <option value="in_person" data-en="In-Person" data-sw="Ana kwa Ana">In-Person</option>
            <option value="telehealth" data-en="Telehealth" data-sw="Dawa Mtandaoni">Telehealth</option>
            <option value="home_visit" data-en="Home Visit" data-sw="Ziara ya Nyumbani">Home Visit</option>
          </select>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div><label style="font-size:12px;font-weight:700;color:var(--ink);display:block;margin-bottom:5px" data-en="Date" data-sw="Tarehe">Date</label><input type="date" id="bookDate" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;color:var(--ink);outline:none" min="<?=date('Y-m-d')?>"></div>
        <div><label style="font-size:12px;font-weight:700;color:var(--ink);display:block;margin-bottom:5px" data-en="Time" data-sw="Wakati">Time</label><input type="time" id="bookTime" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;color:var(--ink);outline:none" value="09:00"></div>
      </div>
      <div style="margin-bottom:14px"><label style="font-size:12px;font-weight:700;color:var(--ink);display:block;margin-bottom:5px" data-en="Reason for Visit" data-sw="Sababu ya Ziara">Reason for Visit</label><input type="text" id="bookTitle" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;color:var(--ink);outline:none" data-en-placeholder="e.g. General check-up, Follow-up…" data-sw-placeholder="mf. Uchunguzi wa jumla, Ufuatiliaji…" placeholder="e.g. General check-up…"></div>
      <input type="hidden" id="bookNotes" value="">
      <!-- Consent notice -->
      <div style="background:var(--bg);border-radius:9px;padding:11px 13px;margin-bottom:14px;display:flex;align-items:flex-start;gap:8px">
        <i class="fa-solid fa-user-shield" style="color:var(--primary);font-size:14px;flex-shrink:0;margin-top:1px"></i>
        <div style="font-size:12px;color:var(--faint);line-height:1.6" data-en="By confirming, you agree to share your name and contact details with the selected provider." data-sw="Kwa kuthibitisha, unakubali kushiriki jina lako na maelezo ya mawasiliano na mtoa huduma aliyechaguliwa.">By confirming, you agree to share your name and contact details with the selected provider.</div>
      </div>
      <button id="bookBtn" onclick="submitBookingSearch()" style="width:100%;height:46px;border-radius:9px;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;background:var(--primary);color:#fff;border:none;cursor:pointer;font-family:inherit">
        <i class="fa-solid fa-calendar-check"></i>
        <span data-en="Confirm Booking" data-sw="Thibitisha Miadi">Confirm Booking</span>
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
/* ── PROVIDER DATA ───────────────────────────────────────────── */
const ALL_PROVIDERS = <?= $providersJson ?>;
const HOSPITAL_IMGS = <?= json_encode($hospitalImgs) ?>;
const DOCTOR_IMGS   = <?= json_encode($doctorImgs) ?>;
const SLOT_TIMES    = <?= json_encode($slotTimes) ?>;
const IS_LOGGED_IN  = <?= $isLoggedIn ? 'true' : 'false' ?>;

/* ── STATE ──────────────────────────────────────────────────── */
let userLat  = null;
let userLng  = null;
let userCity = '';
let currentPage = 1;
const PER_PAGE  = 10;

/* ── HAVERSINE DISTANCE ─────────────────────────────────────── */
function haversine(lat1, lon1, lat2, lon2) {
  const R = 6371;
  const dL = (lat2 - lat1) * Math.PI / 180;
  const dO = (lon2 - lon1) * Math.PI / 180;
  const a  = Math.sin(dL/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dO/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

/* ── GEOLOCATION ────────────────────────────────────────────── */
function triggerGeo() {
  if (!navigator.geolocation) { alert('Geolocation is not supported by your browser.'); return; }
  const btn = document.getElementById('geoBtn');
  if (btn) { btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> <span>Locating…</span>'; btn.disabled = true; }

  navigator.geolocation.getCurrentPosition(
    pos => {
      userLat = pos.coords.latitude;
      userLng = pos.coords.longitude;
      dismissGeoGate();
      if (btn) { btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> <span data-en="Use My Location" data-sw="Tumia Mahali Pangu">Use My Location</span>'; btn.disabled = false; }
      // Show location bar
      const lb = document.getElementById('locBar');
      if (lb) lb.style.display = 'flex';
      // Enable distance sort by default
      document.getElementById('sortOrder').value = 'distance';
      // Disable distance note hint
      const dn = document.getElementById('distNote');
      if (dn) dn.style.display = 'none';
      // Reverse geocode
      fetch(`https://nominatim.openstreetmap.org/reverse?lat=${userLat}&lon=${userLng}&format=json`)
        .then(r => r.json()).then(d => {
          userCity = d.address?.suburb || d.address?.neighbourhood || d.address?.city_district || d.address?.city || 'Your Location';
          const lt = document.getElementById('locBarText');
          const ls = document.getElementById('locStatusText');
          if (lt) lt.textContent = `Showing results near ${userCity}`;
          if (ls) { ls.textContent = userCity; document.getElementById('locStatusBar').style.display = 'flex'; }
          const li = document.getElementById('sLoc');
          if (li && !li.value.trim()) li.value = userCity;
        }).catch(() => { userCity = 'Your Location'; });
      applyFilters();
    },
    err => {
      if (btn) { btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> <span>Use My Location</span>'; btn.disabled = false; }
      if (err.code === 1) {
        alert('Location access was denied. Please enable it in your browser settings to filter by distance.');
      }
      dismissGeoGate();
      // Show distance note
      const dn = document.getElementById('distNote');
      if (dn) dn.style.display = 'block';
      applyFilters();
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
  );
}

function dismissGeoGate() {
  const g = document.getElementById('geoGate');
  if (g) g.style.display = 'none';
}

function clearGeo() {
  userLat = null; userLng = null; userCity = '';
  const lb = document.getElementById('locBar');
  if (lb) lb.style.display = 'none';
  const ls = document.getElementById('locStatusBar');
  if (ls) ls.style.display = 'none';
  document.getElementById('sortOrder').value = 'rating';
  applyFilters();
}

/* ── AUTO-TRIGGER GEO if ?geo=1 ─────────────────────────────── */
(function() {
  <?php if($geoRequest): ?>
  // Check if already granted
  if (navigator.permissions) {
    navigator.permissions.query({name:'geolocation'}).then(r => {
      if (r.state === 'granted') { triggerGeo(); }
      // else: gate is visible, user must click
    }).catch(() => {});
  }
  <?php else: ?>
  // Non-geo entry: check if previously granted silently
  if (navigator.permissions) {
    navigator.permissions.query({name:'geolocation'}).then(r => {
      if (r.state === 'granted') triggerGeo();
    }).catch(() => {});
  }
  <?php endif; ?>
})();

/* ── FILTERS & SORT ─────────────────────────────────────────── */
function applyFilters() {
  currentPage = 1;
  renderResults();
}

function getFiltered() {
  const ptype   = document.querySelector('input[name="ptype"]:checked')?.value || 'all';
  const dist    = parseFloat(document.querySelector('input[name="dist"]:checked')?.value || 999);
  const minRat  = parseFloat(document.querySelector('input[name="rating"]:checked')?.value || 0);
  const sort    = document.getElementById('sortOrder')?.value || 'rating';
  const avail   = document.getElementById('availFilter')?.value || 'any';
  const insCks  = [...document.querySelectorAll('.ins-chk:checked')].map(c => c.value);
  const visitM  = document.querySelector('.type-btn.active')?.textContent?.toLowerCase().includes('tele') ? 'telehealth' : 'in_person';

  let filtered = ALL_PROVIDERS.filter(p => {
    // Type
    if (ptype !== 'all' && p.type !== ptype) return false;
    // Telehealth
    if (visitM === 'telehealth') {
      const svc = (p.services || '').toLowerCase();
      if (!svc.includes('telehealth')) return false;
    }
    // Distance (only if we have user coords AND provider coords)
    if (dist < 999 && userLat && userLng) {
      const plat = parseFloat(p.lat || p.latitude || 0);
      const plng = parseFloat(p.lng || p.longitude || 0);
      if (plat && plng) {
        const d = haversine(userLat, userLng, plat, plng);
        if (d > dist) return false;
      }
    }
    // Rating
    if (minRat > 0 && parseFloat(p.rating || 0) < minRat) return false;
    // Insurance — if any insurance filters are checked, the provider's services
    // must include at least one of the checked insurance keywords.
    // For MVP: 'nhif' maps to services containing 'nhif', etc.
    if (insCks.length > 0) {
      const svcStr = (p.services || '').toLowerCase();
      const nameStr = (p.name || '').toLowerCase();
      const hasAny = insCks.some(ins => {
        if (ins === 'nhif')    return svcStr.includes('nhif') || nameStr.includes('nhif') || p.type === 'hospital' || p.type === 'clinic';
        if (ins === 'jubilee') return svcStr.includes('jubilee') || p.type === 'hospital';
        if (ins === 'axa')     return svcStr.includes('axa') || p.type === 'hospital';
        if (ins === 'aar')     return svcStr.includes('aar') || p.type === 'clinic' || p.type === 'hospital';
        return true; // other = accepts all
      });
      if (!hasAny) return false;
    }
    return true;
  });

  // Compute & attach distances
  if (userLat && userLng) {
    filtered.forEach(p => {
      const plat = parseFloat(p.lat || p.latitude || 0);
      const plng = parseFloat(p.lng || p.longitude || 0);
      p._dist = (plat && plng) ? haversine(userLat, userLng, plat, plng) : null;
    });
  } else {
    filtered.forEach(p => { p._dist = null; });
  }

  // Sort
  filtered.sort((a, b) => {
    if (sort === 'distance') {
      if (a._dist === null && b._dist === null) return 0;
      if (a._dist === null) return 1;
      if (b._dist === null) return -1;
      return a._dist - b._dist;
    }
    if (sort === 'name') return (a.name || '').localeCompare(b.name || '');
    return parseFloat(b.rating || 0) - parseFloat(a.rating || 0); // rating desc
  });

  return filtered;
}

/* ── RENDER ─────────────────────────────────────────────────── */
function renderResults() {
  const filtered = getFiltered();
  const total    = filtered.length;
  const start    = (currentPage - 1) * PER_PAGE;
  const page     = filtered.slice(start, start + PER_PAGE);
  const isSwahili= document.documentElement.lang === 'sw';

  // Update count label
  const rc = document.getElementById('resultCount');
  const rl = document.getElementById('resultLabel');
  if (rc) rc.textContent = total;
  if (rl) rl.setAttribute('data-en', total === 1 ? 'provider found' : 'providers found');
  if (rl) rl.textContent = total === 1 ? (isSwahili ? 'mtoa huduma amepatikana' : 'provider found') : (isSwahili ? 'watoa huduma walipatikana' : 'providers found');

  const container = document.getElementById('resultsContainer');
  const empty     = document.getElementById('emptyState');

  if (page.length === 0) {
    container.innerHTML = '';
    if (empty) empty.style.display = 'block';
    document.getElementById('pagination').innerHTML = '';
    return;
  }
  if (empty) empty.style.display = 'none';

  container.innerHTML = page.map((p, i) => buildCard(p, start + i)).join('');
  renderPagination(total);
}

function buildCard(p, idx) {
  const isDoc = p.type === 'doctor';
  const img   = isDoc ? DOCTOR_IMGS[idx % DOCTOR_IMGS.length] : HOSPITAL_IMGS[idx % HOSPITAL_IMGS.length];
  const slot  = SLOT_TIMES[idx % SLOT_TIMES.length];
  const rat   = parseFloat(p.rating || 4.5).toFixed(1);
  const rev   = 30 + ((idx * 37 + 14) % 270);
  const hasTel= (p.services || '').includes('telehealth');
  const distHtml = p._dist !== null && p._dist !== undefined
    ? `<span style="color:var(--primary);font-weight:600">${p._dist.toFixed(1)} km away</span>`
    : '';
  const openBadge = p.is_available ? '<span class="open-badge" data-en="Open" data-sw="Wazi">Open</span>' : '';
  const distBadge = p._dist !== null && p._dist !== undefined
    ? `<span class="dist-badge"><i class="fa-solid fa-location-dot" style="color:var(--primary)"></i>${p._dist.toFixed(1)} km</span>`
    : '';
  const telTag = hasTel ? '<span class="rc-tag rc-tag-tel" data-en="Telehealth" data-sw="Dawa Mtandaoni">Telehealth</span>' : '';
  const bookFn = IS_LOGGED_IN ? `bookProvider(${p.id})` : 'goLogin()';

  const imgCol = isDoc
    ? `<div class="rc-doc-img"><img src="${img}" alt="${esc(p.name)}"></div>`
    : `<div class="rc-img"><img src="${img}" alt="${esc(p.name)}">${openBadge}${distBadge}</div>`;

  const footerBtn = isDoc
    ? `<button class="btn-select" onclick="${bookFn}" data-en="Select Doctor" data-sw="Chagua Daktari">Select Doctor</button>`
    : `<button class="btn-book" onclick="${bookFn}" data-en="Book Now" data-sw="Weka Miadi">Book Now</button>`;

  const specLine = isDoc ? `<div class="rc-spec">${esc(p.specialty||'Specialist')}</div>` : '';

  return `
<div class="rc" data-id="${p.id}" data-type="${esc(p.type)}" data-rating="${rat}">
  <div class="rc-inner">
    ${imgCol}
    <div class="rc-body">
      <div>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
          <div>
            <div class="rc-name">${esc(p.name)}</div>
            ${specLine}
            <div class="rc-rating"><i class="fa-solid fa-star" style="color:#f59e0b"></i><span style="font-weight:700;color:var(--ink)">${rat}</span><span>(${rev}+ reviews)</span></div>
          </div>
          <button onclick="toggleFav(this)" style="color:var(--border);background:none;border:none;cursor:pointer;font-size:20px;padding:2px;flex-shrink:0"><i class="fa-regular fa-heart"></i></button>
        </div>
        <div class="rc-loc">
          <i class="fa-solid fa-location-dot" style="color:var(--primary)"></i>
          <span>${esc(p.address||p.city||'Nairobi, Kenya')}</span>
          ${distHtml}
        </div>
        <div class="rc-tags">
          <span class="rc-tag rc-tag-ins" data-en="Insurance Accepted" data-sw="Bima Inakubaliwa">Insurance Accepted</span>
          <span class="rc-tag rc-tag-pay" data-en="Pay at Facility" data-sw="Lipa Hospitalini">Pay at Facility</span>
          ${telTag}
        </div>
      </div>
      <div class="rc-footer">
        <div class="rc-slot"><i class="fa-solid fa-calendar-check"></i><span data-en="Next slot:" data-sw="Nafasi ijayo:">Next slot:</span>&nbsp;<strong>${slot}</strong></div>
        ${footerBtn}
      </div>
    </div>
  </div>
</div>`;
}

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* ── PAGINATION ─────────────────────────────────────────────── */
function renderPagination(total) {
  const pages = Math.ceil(total / PER_PAGE);
  if (pages <= 1) { document.getElementById('pagination').innerHTML = ''; return; }
  let html = '';
  if (currentPage > 1) html += `<button class="pg-btn" onclick="goPage(${currentPage-1})"><i class="fa-solid fa-chevron-left"></i></button>`;
  for (let i = 1; i <= pages; i++) {
    if (i === 1 || i === pages || Math.abs(i - currentPage) <= 1) {
      html += `<button class="pg-btn ${i===currentPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
    } else if (Math.abs(i - currentPage) === 2) {
      html += `<button class="pg-btn" style="cursor:default;border:none;pointer-events:none">…</button>`;
    }
  }
  if (currentPage < pages) html += `<button class="pg-btn" onclick="goPage(${currentPage+1})"><i class="fa-solid fa-chevron-right"></i></button>`;
  document.getElementById('pagination').innerHTML = html;
}

function goPage(n) {
  currentPage = n;
  renderResults();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ── FILTER HELPERS ─────────────────────────────────────────── */
function setVisitType(vt, btn) {
  document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  applyFilters();
}

function resetFilters() {
  document.querySelector('input[name="ptype"][value="all"]').checked = true;
  document.querySelector('input[name="dist"][value="999"]').checked  = true;
  document.querySelector('input[name="rating"][value="0"]').checked  = true;
  document.querySelectorAll('.ins-chk').forEach(c => c.checked = false);
  document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
  document.querySelector('.type-btn:first-child').classList.add('active');
  document.getElementById('sortOrder').value  = 'rating';
  document.getElementById('availFilter').value = 'any';
  applyFilters();
}

/* ── SEARCH ─────────────────────────────────────────────────── */
function doSearch() {
  const loc  = document.getElementById('sLoc')?.value.trim()   || '';
  const q    = document.getElementById('sQuery')?.value.trim() || '';
  const type = document.getElementById('sType')?.value         || 'in_person';
  window.location.href = '/patients/search.php?' + new URLSearchParams({location:loc, q, visit_type:type}).toString();
}
['sLoc','sQuery'].forEach(id => {
  document.getElementById(id)?.addEventListener('keydown', e => { if(e.key==='Enter') doSearch(); });
});

/* ── BOOKING ────────────────────────────────────────────────── */
function bookProvider(pid) {
  document.getElementById('bookProvider').value = pid;
  document.getElementById('bookModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  const m = document.getElementById(id); if(m){m.style.display='none';document.body.style.overflow='';}
}
function goLogin() {
  location.href = '/patients/login.php?next=' + encodeURIComponent(window.location.href);
}
function toggleFav(btn) {
  const i = btn.querySelector('i');
  const isFav = i.classList.contains('fa-solid');
  i.classList.toggle('fa-solid',   !isFav);
  i.classList.toggle('fa-regular', isFav);
  i.style.color = isFav ? '' : '#ef4444';
}

async function submitBookingSearch() {
  const csrf  = document.getElementById('csrfToken')?.value || '';
  const date  = document.getElementById('bookDate')?.value;
  const time  = document.getElementById('bookTime')?.value || '09:00';
  const title = document.getElementById('bookTitle')?.value?.trim();
  const alertBox = document.getElementById('bookAlertBox');

  function showAlert(type, msg) {
    if (!alertBox) return;
    alertBox.style.display = 'flex';
    alertBox.style.background = type==='err'?'#fef2f2':'#f0fdf4';
    alertBox.style.color = type==='err'?'#991b1b':'#065f46';
    alertBox.style.border = `1px solid ${type==='err'?'rgba(220,38,38,.2)':'rgba(22,163,74,.2)'}`;
    alertBox.innerHTML = `<i class="fa-solid ${type==='err'?'fa-circle-exclamation':'fa-circle-check'}"></i><span>${msg}</span>`;
  }

  if (!date) { showAlert('err','Please select a date.'); return; }
  if (!title){ showAlert('err','Please enter a reason for the visit.'); return; }

  const btn = document.getElementById('bookBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Booking…';

  try {
    const res = await fetch('/api/patient/book-appointment.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body: JSON.stringify({
        service_type: document.getElementById('bookServiceType')?.value || 'doctor',
        provider_id:  document.getElementById('bookProvider')?.value    || null,
        appointment_at: date + ' ' + time + ':00',
        title, notes: '',
        location_type: document.getElementById('bookLocType')?.value || 'in_person',
        csrf_token: csrf,
      }),
      credentials:'same-origin',
    });
    const r = await res.json();
    if (r.requires_login) { location.href = r.redirect; return; }
    if (r.success) {
      showAlert('ok','Appointment booked! Confirmation email sent.');
      setTimeout(() => { closeModal('bookModal'); location.href='/patients/dashboard.php?tab=appointments'; }, 1400);
    } else {
      showAlert('err', r.message || 'Booking failed. Please try again.');
    }
  } catch(e) {
    showAlert('err','Network error. Please check your connection.');
  } finally {
    btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-calendar-check"></i> <span data-en="Confirm Booking" data-sw="Thibitisha Miadi">Confirm Booking</span>';
  }
}

/* ── LANGCHANGE — re-render cards with correct language labels ─ */
document.addEventListener('langchange', () => {
  // Re-apply Lang.apply to dynamically rendered elements
  document.querySelectorAll('[data-en]').forEach(el => {
    const lang = document.documentElement.lang === 'sw' ? 'sw' : 'en';
    const v = el.getAttribute('data-' + lang);
    if (v !== null) el.innerHTML = v;
  });
});

/* ── sType swap on langchange ────────────────────────────────── */
document.addEventListener('langchange', e => {
  const sel = document.getElementById('sType'); if (!sel) return;
  const opts = e.detail.lang === 'sw' ? ['Ana kwa Ana','Dawa Mtandaoni'] : ['In-person','Telehealth'];
  [...sel.options].forEach((o,i)=>{ if(opts[i]) o.text=opts[i]; });
});

/* ── INITIAL RENDER ──────────────────────────────────────────── */
// Replace PHP-rendered cards with JS-rendered (enables all filtering)
document.addEventListener('DOMContentLoaded', () => {
  // Only replace if JS is working
  renderResults();
  // Show distance note if no geo
  if (!userLat) {
    const dn = document.getElementById('distNote');
    if (dn) dn.style.display = 'block';
  }
});
</script>
