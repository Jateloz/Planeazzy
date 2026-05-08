<?php
/**
 * Planeazzy — /patients/doctor-profile.php
 * Detailed standalone doctor profile page
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Database.php';
Security::startSession();

$docId = (int)($_GET['id'] ?? 0);
if (!$docId) { header('Location: /patients/search.php'); exit; }

$db  = Database::getInstance();
$doc = $db->fetchOne(
    "SELECT d.*,
            (SELECT COUNT(*) FROM doctor_availability da WHERE da.doctor_id=d.id AND da.is_available=1) avail_days,
            (SELECT GROUP_CONCAT(DISTINCT CONCAT(da.day_of_week,':',da.start_time,'-',da.end_time) ORDER BY da.day_of_week SEPARATOR '|')
             FROM doctor_availability da WHERE da.doctor_id=d.id AND da.is_available=1) schedule_info
     FROM doctors d WHERE d.id=:id AND d.is_active=1 AND d.status='active'",
    [':id' => $docId]
);
if (!$doc) { header('Location: /patients/search.php?q='); exit; }

$isVerified = ($doc['is_verified'] ?? 0) || ($doc['email_verified'] ?? 0);
$fullName = 'Dr. '.htmlspecialchars(trim($doc['first_name'].' '.$doc['last_name']));
$avatar   = $doc['avatar_path'] ?? '';
$specialty= htmlspecialchars($doc['specialty'] ?? 'General Physician');
$initials = strtoupper(($doc['first_name'][0]??'D').($doc['last_name'][0]??'R'));

// Parse schedule
$daysMap = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
$schedule = [];
if ($doc['schedule_info']) {
    foreach (explode('|', $doc['schedule_info']) as $seg) {
        $parts = explode(':', $seg, 2);
        $dow = (int)$parts[0];
        $times = $parts[1] ?? '';
        [$start,$end] = explode('-', $times.'-');
        $schedule[] = [
            'day' => $daysMap[$dow] ?? '',
            'start' => $start,
            'end' => $end,
        ];
    }
}

// Fetch reviews (simplified)
$reviews = [];
try {
    $reviews = $db->fetchAll(
        "SELECT f.rating, f.comment, f.created_at, p.first_name, p.last_name
         FROM feedback f LEFT JOIN patients p ON p.id=f.patient_id
         WHERE f.provider_id=:did AND f.rating IS NOT NULL
         ORDER BY f.created_at DESC LIMIT 10",
        [':did' => $docId]
    );
} catch(Throwable $e) {}

$isLoggedIn = !empty($_SESSION['patient_id']) && !empty($_SESSION['authenticated']);
$csrf = Security::csrfToken();
$provId = $docId + 30000;
$pageTitle = $fullName.' — Planeazzy';
$noSidebar = true;
include dirname(__DIR__).'/includes/header.php';
?>
<style>
:root{--primary:#1978e5;--primary-10:rgba(25,120,229,.1);--primary-20:rgba(25,120,229,.2);--teal:#0d9488;--green:#16a34a;--yellow:#f59e0b;--red:#ef4444;--ink:#0f172a;--muted:#475569;--faint:#64748b;--card:#fff;--bg:#f8fafc}
*{box-sizing:border-box}
.dp-wrap{max-width:1100px;margin:0 auto;padding:32px 24px}
.dp-hero{background:var(--card);border-radius:20px;border:1px solid rgba(0,0,0,.07);box-shadow:0 4px 20px rgba(0,0,0,.06);overflow:hidden;margin-bottom:24px}
.dp-hero-banner{height:160px;background:linear-gradient(135deg,#0f2460,#1e40af,#1978e5);position:relative;display:flex;align-items:flex-end;padding:0 32px 0}
.dp-av{width:120px;height:120px;border-radius:50%;border:4px solid #fff;overflow:hidden;flex-shrink:0;background:linear-gradient(135deg,#1978e5,#0d9488);display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:900;color:#fff;position:relative;top:40px;box-shadow:0 8px 32px rgba(0,0,0,.16);z-index:2}
.dp-av img{width:100%;height:100%;object-fit:cover}
.dp-hero-body{display:flex;align-items:flex-start;gap:24px;padding:56px 32px 28px}
.dp-name{font-size:1.75rem;font-weight:900;color:var(--ink);letter-spacing:-.04em;line-height:1.2}
.dp-spec{font-size:14px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.06em;margin-top:4px}
.dp-badges{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.dp-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700}
.dp-badge-verified{background:#dbeafe;color:#1e40af}
.dp-badge-exp{background:#dcfce7;color:#166534}
.dp-badge-lang{background:#f3e8ff;color:#7c3aed}
.dp-badge-fee{background:#fef9c3;color:#854d0e}
.dp-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:rgba(0,0,0,.06);border-top:1px solid rgba(0,0,0,.06)}
.dp-stat{background:var(--card);padding:20px;text-align:center}
.dp-stat-n{font-size:1.5rem;font-weight:900;color:var(--ink);letter-spacing:-.04em}
.dp-stat-l{font-size:11.5px;color:var(--faint);margin-top:3px;font-weight:500}
.dp-grid{display:grid;grid-template-columns:1fr 340px;gap:20px}
.dp-card{background:var(--card);border-radius:16px;border:1px solid rgba(0,0,0,.07);box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;margin-bottom:20px}
.dp-card-hdr{padding:16px 20px;border-bottom:1px solid rgba(0,0,0,.06);display:flex;align-items:center;gap:8px}
.dp-card-title{font-size:14px;font-weight:800;color:var(--ink)}
.dp-card-body{padding:20px}
.dp-sched-day{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:10px;background:var(--bg);margin-bottom:6px}
.dp-sched-day:last-child{margin-bottom:0}
.dp-sched-label{font-weight:700;font-size:13px;color:var(--ink)}
.dp-sched-time{font-size:12.5px;color:var(--primary);font-weight:600}
.dp-review{display:flex;gap:12px;padding:14px 0;border-bottom:1px solid rgba(0,0,0,.06)}
.dp-review:last-child{border-bottom:none}
.dp-review-av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1978e5,#0d9488);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0}
.dp-stars{color:var(--yellow);font-size:12px}
.book-card{background:var(--card);border-radius:16px;border:1px solid rgba(0,0,0,.07);box-shadow:0 4px 20px rgba(0,0,0,.08);padding:24px;position:sticky;top:20px}
.btn-book-main{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;background:linear-gradient(135deg,#1978e5,#0d9488);color:#fff;border:none;border-radius:12px;font-family:inherit;font-size:15px;font-weight:800;cursor:pointer;transition:all .2s;box-shadow:0 4px 16px rgba(25,120,229,.3)}
.btn-book-main:hover{opacity:.92;transform:translateY(-1px)}
.btn-book-main:disabled{opacity:.55;cursor:not-allowed;transform:none}
@media(max-width:900px){.dp-grid{grid-template-columns:1fr}.dp-stats{grid-template-columns:repeat(2,1fr)}.dp-hero-body{flex-direction:column;gap:12px;padding:56px 20px 24px}}
@media(max-width:600px){.dp-wrap{padding:16px}.dp-name{font-size:1.375rem}.dp-av{width:90px;height:90px;top:28px}}
</style>

<div class="dp-wrap">
  <!-- Hero -->
  <div class="dp-hero">
    <div class="dp-hero-banner">
      <div class="dp-av">
        <?php if($avatar): ?>
        <img src="<?=htmlspecialchars($avatar)?>" alt="<?=$fullName?>">
        <?php else: ?><?=htmlspecialchars($initials)?><?php endif; ?>
      </div>
    </div>
    <div class="dp-hero-body">
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <div class="dp-name"><?=$fullName?></div>
          <?php if($isVerified): ?>
          <span style="display:inline-flex;align-items:center;gap:4px;background:#dbeafe;color:#1e40af;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:800"><i class="fa-solid fa-circle-check"></i> Verified</span>
          <?php else: ?>
          <span style="display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;color:var(--faint);padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700"><i class="fa-solid fa-clock"></i> Pending Verification</span>
          <?php endif; ?>
        </div>
        <div class="dp-spec"><?=$specialty?></div>
        <?php if($doc['county']||$doc['city']): ?>
        <div style="font-size:13px;color:var(--muted);margin-top:6px;display:flex;align-items:center;gap:5px">
          <i class="fa-solid fa-location-dot" style="color:var(--primary);font-size:12px"></i>
          <?=htmlspecialchars(trim(($doc['city']??'').($doc['county']?', '.$doc['county']:'')))?>, Kenya
        </div>
        <?php endif; ?>
        <?php if($doc['kmpdc_licence']): ?>
        <div style="font-size:12px;color:var(--faint);margin-top:4px;display:flex;align-items:center;gap:5px">
          <i class="fa-solid fa-id-card" style="font-size:11px"></i>
          KMPDC: <?=htmlspecialchars($doc['kmpdc_licence'])?>
        </div>
        <?php endif; ?>
        <div class="dp-badges">
          <?php if($doc['years_exp']??0): ?>
          <span class="dp-badge dp-badge-exp"><i class="fa-solid fa-briefcase"></i> <?=(int)$doc['years_exp']?> Years Experience</span>
          <?php endif; ?>
          <?php if($doc['consult_fee']??0): ?>
          <span class="dp-badge dp-badge-fee"><i class="fa-solid fa-money-bill-wave"></i> KES <?=number_format($doc['consult_fee'],0)?> / visit</span>
          <?php endif; ?>
          <?php if($doc['languages']): ?>
          <span class="dp-badge dp-badge-lang"><i class="fa-solid fa-language"></i> <?=htmlspecialchars($doc['languages'])?></span>
          <?php endif; ?>
          <?php if($doc['accepts_tele']): ?>
          <span class="dp-badge" style="background:#dcfce7;color:#166534"><i class="fa-solid fa-video"></i> Telehealth Available</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <!-- Stats bar -->
    <div class="dp-stats">
      <div class="dp-stat">
        <div class="dp-stat-n"><?=number_format(floatval($doc['rating']??4.0),1)?></div>
        <div class="dp-stat-l"><i class="fa-solid fa-star" style="color:var(--yellow)"></i> Rating</div>
      </div>
      <div class="dp-stat">
        <div class="dp-stat-n"><?=(int)($doc['avail_days']??0)?></div>
        <div class="dp-stat-l"><i class="fa-solid fa-calendar-check" style="color:var(--green)"></i> Days/Week Available</div>
      </div>
      <div class="dp-stat">
        <div class="dp-stat-n"><?=count($reviews)?></div>
        <div class="dp-stat-l"><i class="fa-regular fa-comment" style="color:var(--primary)"></i> Patient Reviews</div>
      </div>
      <div class="dp-stat">
        <div class="dp-stat-n"><?=$doc['years_exp']??'N/A'?></div>
        <div class="dp-stat-l"><i class="fa-solid fa-stethoscope" style="color:var(--teal)"></i> Years Practice</div>
      </div>
    </div>
  </div>

  <div class="dp-grid">
    <!-- Left column -->
    <div>
      <!-- About -->
      <?php if($doc['bio']): ?>
      <div class="dp-card">
        <div class="dp-card-hdr">
          <i class="fa-solid fa-user-doctor" style="color:var(--primary);font-size:14px"></i>
          <div class="dp-card-title">About Dr. <?=htmlspecialchars($doc['first_name'])?></div>
        </div>
        <div class="dp-card-body">
          <p style="font-size:14px;line-height:1.75;color:var(--muted)"><?=nl2br(htmlspecialchars($doc['bio']))?></p>
        </div>
      </div>
      <?php endif; ?>

      <!-- Schedule -->
      <?php if(!empty($schedule)): ?>
      <div class="dp-card">
        <div class="dp-card-hdr">
          <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:14px"></i>
          <div class="dp-card-title">Availability Schedule</div>
        </div>
        <div class="dp-card-body">
          <p style="font-size:12.5px;color:var(--faint);margin-bottom:14px"><i class="fa-solid fa-circle-info" style="color:var(--primary)"></i> Appointments are available during the times below. Select your preferred slot when booking.</p>
          <?php foreach($schedule as $s): ?>
          <div class="dp-sched-day">
            <div class="dp-sched-label"><i class="fa-solid fa-calendar-day" style="color:var(--primary);margin-right:6px;font-size:12px"></i><?=$s['day']?></div>
            <div class="dp-sched-time"><?php
              $fmt=function($t){ if(!$t)return ''; $h=intval($t); $m=substr($t,3,2); $ap=$h<12?'AM':'PM'; return ($h>12?$h-12:($h?:12)).':'.$m.' '.$ap; };
              echo $fmt($s['start']).' &ndash; '.$fmt($s['end']); ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php elseif($isVerified): ?>
      <div class="dp-card">
        <div class="dp-card-hdr">
          <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:14px"></i>
          <div class="dp-card-title">Availability</div>
        </div>
        <div class="dp-card-body">
          <div style="text-align:center;padding:20px;color:var(--faint)">
            <i class="fa-solid fa-calendar-xmark" style="font-size:32px;margin-bottom:10px;display:block;opacity:.4"></i>
            <div style="font-size:13px;font-weight:600">Schedule not yet configured</div>
            <div style="font-size:12px;margin-top:4px">Contact this doctor directly to arrange an appointment.</div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Reviews -->
      <div class="dp-card">
        <div class="dp-card-hdr">
          <i class="fa-solid fa-star" style="color:var(--yellow);font-size:14px"></i>
          <div class="dp-card-title">Patient Reviews</div>
        </div>
        <div class="dp-card-body">
          <?php if(empty($reviews)): ?>
          <div style="text-align:center;padding:20px;color:var(--faint)">
            <i class="fa-regular fa-comment" style="font-size:32px;display:block;margin-bottom:10px;opacity:.4"></i>
            <div style="font-size:13px;font-weight:600">No reviews yet</div>
            <div style="font-size:12px;margin-top:4px">Be the first to review after your appointment.</div>
          </div>
          <?php else: foreach($reviews as $rev):
            $rn=htmlspecialchars(trim(($rev['first_name']??'Patient').' '.($rev['last_name']??'')));
            $ri=strtoupper($rn[0]??'P');
            $stars=intval($rev['rating']??5);
          ?>
          <div class="dp-review">
            <div class="dp-review-av"><?=$ri?></div>
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span style="font-size:13px;font-weight:700;color:var(--ink)"><?=$rn?></span>
                <div class="dp-stars"><?=str_repeat('<i class="fa-solid fa-star"></i>',$stars).str_repeat('<i class="fa-regular fa-star"></i>',5-$stars)?></div>
              </div>
              <?php if($rev['comment']): ?>
              <p style="font-size:13px;color:var(--muted);line-height:1.6;margin-top:4px"><?=htmlspecialchars($rev['comment'])?></p>
              <?php endif; ?>
              <div style="font-size:11px;color:var(--faint);margin-top:4px"><i class="fa-regular fa-clock"></i> <?=date('M j, Y',strtotime($rev['created_at']))?></div>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- Right column: booking card -->
    <div>
      <div class="book-card">
        <?php if($isVerified && !empty($schedule)): ?>
        <div style="font-size:14px;font-weight:800;color:var(--ink);margin-bottom:4px"><i class="fa-solid fa-calendar-plus" style="color:var(--primary)"></i> Book an Appointment</div>
        <p style="font-size:12.5px;color:var(--faint);line-height:1.6;margin-bottom:16px">Select a date and time from this doctor's available schedule.</p>
        <?php if($doc['consult_fee']??0): ?>
        <div style="background:var(--bg);border-radius:10px;padding:12px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:12px;color:var(--faint);font-weight:600">Consultation Fee</span>
          <span style="font-size:18px;font-weight:900;color:var(--ink)">KES <?=number_format($doc['consult_fee'],0)?></span>
        </div>
        <?php endif; ?>
        <button class="btn-book-main" onclick="triggerBooking(<?=$provId?>,'<?=addslashes($fullName)?>',false,'<?=addslashes($doc['city']??$doc['county']??'')?>',<?=$doc['consult_fee']>0?$doc['consult_fee']:'null'?>)">
          <i class="fa-solid fa-calendar-plus"></i> Book Now
        </button>
        <?php if($doc['accepts_tele']): ?>
        <div style="text-align:center;margin-top:10px;font-size:12px;color:var(--faint)"><i class="fa-solid fa-video" style="color:var(--green)"></i> Telehealth also available</div>
        <?php endif; ?>
        <?php elseif(!$isVerified): ?>
        <div style="text-align:center;padding:16px">
          <i class="fa-solid fa-clock" style="font-size:32px;color:var(--faint);display:block;margin-bottom:10px;opacity:.5"></i>
          <div style="font-size:13px;font-weight:700;color:var(--muted)">Pending Verification</div>
          <div style="font-size:12px;color:var(--faint);margin-top:6px;line-height:1.6">This doctor is currently under review. Booking will be available once verified.</div>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:16px">
          <i class="fa-solid fa-calendar-xmark" style="font-size:32px;color:var(--faint);display:block;margin-bottom:10px;opacity:.5"></i>
          <div style="font-size:13px;font-weight:700;color:var(--muted)">No Schedule Set</div>
          <div style="font-size:12px;color:var(--faint);margin-top:6px;line-height:1.6">Contact this doctor directly to arrange an appointment.</div>
          <?php if($doc['phone']): ?>
          <a href="tel:<?=htmlspecialchars($doc['phone'])?>" style="display:inline-flex;align-items:center;gap:6px;margin-top:14px;padding:10px 20px;background:var(--primary);color:#fff;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none"><i class="fa-solid fa-phone"></i> Call Doctor</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($doc['phone']||$doc['address']): ?>
        <div style="border-top:1px solid rgba(0,0,0,.06);margin-top:20px;padding-top:16px">
          <?php if($doc['phone']): ?>
          <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);margin-bottom:8px">
            <i class="fa-solid fa-phone" style="color:var(--primary);width:16px;text-align:center"></i>
            <a href="tel:<?=htmlspecialchars($doc['phone'])?>" style="color:var(--primary);text-decoration:none;font-weight:600"><?=htmlspecialchars($doc['phone'])?></a>
          </div>
          <?php endif; ?>
          <?php if($doc['address']): ?>
          <div style="display:flex;align-items:flex-start;gap:8px;font-size:13px;color:var(--muted)">
            <i class="fa-solid fa-location-dot" style="color:var(--primary);width:16px;text-align:center;margin-top:2px"></i>
            <span><?=htmlspecialchars($doc['address'])?></span>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Include booking modal -->
<input type="hidden" id="srCsrf" value="<?=htmlspecialchars($csrf)?>">
<script>
const IS_LOGGED_IN = <?=$isLoggedIn?'true':'false'?>;
function triggerBooking(id, name, isHospital, city, fee){
  if(!IS_LOGGED_IN){
    window.location.href='/patients/login.php?next='+encodeURIComponent(window.location.href);
    return;
  }
  window.location.href='/patients/book.php?provider_id='+id+'&provider='+encodeURIComponent(name)+'&type=doctor';
}
</script>
<?php include dirname(__DIR__).'/includes/footer.php'; ?>
