<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true;
$pageTitle  = 'Planeazzy — Your Direct Path to Better Healthcare in Kenya';
include __DIR__ . '/includes/header.php';
?>
<style>
/* ══ HOMEPAGE — uniform colour system, no multi-colour body text ══ */
:root{
  --ink:#0f172a;        /* ALL headings */
  --body:#334155;       /* ALL body/paragraph text — single uniform colour */
  --faint:#64748b;      /* meta, labels, captions */
  --border:#e2e8f0;
  --bg:#f8fafc;
  --card:#ffffff;
  --primary:#1978e5;    /* Planeazzy blue — icons, buttons, accents only */
  --teal:#0d9488;
}
/* Force uniform body text — no rainbow paragraphs */
p, span, li, label, small, .pz-lead, .pz-sub, .pz-desc,
[data-en], [data-sw] { color:var(--body); }
h1,h2,h3,h4,h5,h6, .pz-h1, .pz-h2, .pz-h3 { color:var(--ink); }

/* Layout */
.pz-w  { max-width:1280px; margin:0 auto; }
.pz-wm { max-width:960px;  margin:0 auto; }
.pz-ws { max-width:740px;  margin:0 auto; }
.pz-s  { padding:48px 20px; }
.pz-sm { padding:52px 20px; }
.blob  { position:absolute; border-radius:50%; filter:blur(70px); pointer-events:none; }

/* Typography */
.pz-h1  { font-size:clamp(19px,4.2vw,44px); font-weight:900; color:var(--ink); letter-spacing:-.04em; line-height:1.08; margin-bottom:22px; }
.pz-h2  { font-size:clamp(16px,3vw,30px); font-weight:900; color:var(--ink); letter-spacing:-.04em; line-height:1.1; margin-bottom:14px; }
.pz-h3  { font-size:17px; font-weight:700; color:var(--ink); margin-bottom:10px; }
.pz-lead{ font-size:14px; line-height:1.8; color:var(--body); max-width:580px; }
.pz-sub { font-size:13px; line-height:1.75; color:var(--body); }
/* Single restrained accent — used ONLY on the hero headline word */
.pz-accent { color:var(--primary) !important; }

/* Badge */
.pz-badge { display:inline-flex; align-items:center; gap:7px; background:rgba(25,120,229,.08); color:var(--primary); padding:5px 14px; border-radius:9999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; border:1px solid rgba(25,120,229,.16); margin-bottom:18px; }

/* Cards */
.pz-card { background:var(--card); border:1.5px solid var(--border); border-radius:16px; padding:22px; box-shadow:0 2px 8px rgba(0,0,0,.05); }
.pz-card-h { transition:border-color .2s,box-shadow .2s,transform .2s; }
.pz-card-h:hover { border-color:rgba(25,120,229,.25); box-shadow:0 12px 36px rgba(25,120,229,.08); transform:translateY(-2px); }

/* Icon box */
.pz-ic { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }

/* Grids */
.g4 { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; }
.g3 { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
.g2 { display:grid; grid-template-columns:1fr 1fr; gap:40px; align-items:center; }

/* Steps */
.pz-steps { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; position:relative; }
.pz-steps::before { content:''; position:absolute; top:26px; left:calc(12.5%+18px); right:calc(12.5%+18px); height:2px; background:var(--border); }
.pz-steps > * { position:relative; z-index:1; }
.pz-sn { width:52px; height:52px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:21px; font-weight:900; color:#fff; margin-bottom:18px; }

/* Feature row */
.pz-feat { display:flex; gap:16px; align-items:flex-start; padding:20px; border-radius:14px; background:var(--bg); border:1px solid var(--border); margin-bottom:12px; }
.pz-feat-title { font-size:13.5px; font-weight:700; color:var(--ink); margin-bottom:5px; }
.pz-feat-desc  { font-size:12.5px; color:var(--body); line-height:1.7; }

/* Checklist */
.pz-chk { display:flex; align-items:flex-start; gap:10px; margin-bottom:14px; }
.pz-chk i { color:var(--primary); font-size:16px; flex-shrink:0; margin-top:2px; }
.pz-chk span { font-size:13px; color:var(--body); line-height:1.65; }

/* Specialty grid */
.pz-spec { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; }
.pz-sc { background:var(--card); border:1.5px solid var(--border); border-radius:16px; padding:20px 10px; text-align:center; cursor:pointer; font-family:inherit; display:flex; flex-direction:column; align-items:center; gap:10px; transition:border-color .15s,box-shadow .15s; }
.pz-sc:hover { border-color:rgba(25,120,229,.3); box-shadow:0 6px 20px rgba(25,120,229,.08); }
.pz-sc-lbl { font-size:11.5px; font-weight:700; color:var(--ink); line-height:1.3; }

/* Testimonial */
.pz-testi { background:var(--bg); border-radius:18px; padding:28px; border:1.5px solid var(--border); }
.pz-testi-q { font-size:13px; color:var(--body); line-height:1.85; font-style:italic; margin-bottom:20px; }

/* FAQ */
.pz-faq { background:var(--card); border-radius:14px; border:1.5px solid var(--border); overflow:hidden; margin-bottom:10px; }
.pz-fq-btn { width:100%; padding:18px 22px; display:flex; align-items:center; justify-content:space-between; background:none; border:none; cursor:pointer; font-family:inherit; text-align:left; gap:16px; }
.pz-fq-q { font-size:13.5px; font-weight:700; color:var(--ink); flex:1; }
.pz-fq-ic { color:var(--primary); flex-shrink:0; transition:transform .25s; font-size:13px; }
.pz-fq-ic.op { transform:rotate(45deg); }
.pz-fq-body { display:none; padding:0 22px 20px; }
.pz-fq-body p { font-size:13px; color:var(--body); line-height:1.8; border-top:1px solid var(--border); padding-top:14px; }

/* Floating badges */
.pz-fl { position:absolute; background:var(--card); border-radius:14px; box-shadow:0 12px 32px rgba(0,0,0,.11); padding:12px 16px; display:flex; align-items:center; gap:11px; border:1px solid var(--border); z-index:2; }
.pz-fl-lbl { font-size:11px; color:var(--faint); font-weight:600; margin-bottom:2px; }
.pz-fl-val { font-size:16px; font-weight:900; color:var(--ink); }

/* Search bar */
.pz-search { background:var(--card); padding:7px; border-radius:18px; box-shadow:0 20px 52px rgba(25,120,229,.14),0 4px 12px rgba(0,0,0,.06); border:1px solid rgba(25,120,229,.12); display:flex; flex-wrap:wrap; }
.pz-sf { flex:1; display:flex; align-items:center; gap:9px; padding:12px 18px; border-right:1px solid var(--border); min-width:130px; }
.pz-sf input, .pz-sf select { border:none; outline:none; background:transparent; font-family:inherit; font-size:14px; color:var(--ink); width:100%; }
.pz-sf select { color:var(--faint); cursor:pointer; appearance:none; -webkit-appearance:none; }
.pz-sb { background:var(--primary); color:#fff; padding:12px 30px; border-radius:13px; border:none; font-family:inherit; font-size:15px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:8px; white-space:nowrap; flex-shrink:0; box-shadow:0 6px 18px rgba(25,120,229,.35); }

/* Pills */
.pz-pill { padding:5px 14px; border-radius:9999px; font-size:12px; font-weight:600; background:var(--card); border:1.5px solid var(--border); color:var(--faint); cursor:pointer; font-family:inherit; transition:border-color .15s,color .15s; }
.pz-pill:hover { border-color:var(--primary); color:var(--primary); }

/* Social proof avatars */
.pz-avs { display:flex; }
.pz-avs img { width:38px; height:38px; border-radius:50%; border:2.5px solid #fff; object-fit:cover; box-shadow:0 2px 8px rgba(0,0,0,.12); }
.pz-avs img+img { margin-left:-10px; }

/* Stats band */
.pz-stat-n { font-size:clamp(18px,3vw,34px); font-weight:900; letter-spacing:-.05em; color:#fff; line-height:1; }

/* About section */
.pz-about-num { font-size:26px; font-weight:900; color:var(--primary); letter-spacing:-.04em; line-height:1; }
.pz-about-lbl { font-size:13px; color:var(--faint); margin-top:4px; font-weight:500; }
.pz-about-box  { padding:22px; background:var(--bg); border-radius:14px; border:1px solid var(--border); text-align:center; }

/* Mission block */
.pz-mission { background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%); border-radius:20px; padding:40px; color:#fff; }
.pz-mission h3 { font-size:22px; font-weight:900; color:#fff; letter-spacing:-.03em; margin-bottom:16px; }
.pz-mission p  { font-size:13px; color:rgba(203,213,225,.85); line-height:1.8; }
.pz-mission-ic { width:56px; height:56px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:24px; flex-shrink:0; margin-bottom:14px; }

/* Provider CTA banner */
.pz-banner { background:linear-gradient(135deg,#1462c4 0%,#1978e5 45%,#0d9488 100%); border-radius:24px; padding:52px 48px; position:relative; overflow:hidden; display:grid; grid-template-columns:1fr 1fr; gap:48px; align-items:center; }
.pz-banner-txt h2 { font-size:clamp(18px,3vw,32px); font-weight:900; color:#fff; letter-spacing:-.04em; margin-bottom:16px; line-height:1.2; }
.pz-banner-txt p  { font-size:13px; color:rgba(219,234,254,.88); line-height:1.8; margin-bottom:32px; }
.pz-btn-white { background:#fff; color:var(--primary); padding:14px 30px; border-radius:12px; font-family:inherit; font-size:14px; font-weight:800; border:none; cursor:pointer; box-shadow:0 8px 20px rgba(0,0,0,.18); }
.pz-btn-ghost { background:rgba(255,255,255,.12); border:1.5px solid rgba(255,255,255,.25); color:#fff; padding:14px 30px; border-radius:12px; font-family:inherit; font-size:14px; font-weight:700; cursor:pointer; backdrop-filter:blur(8px); }

/* Chip */
.pz-chip { font-size:11px; padding:4px 10px; border-radius:7px; background:var(--bg); color:var(--faint); font-weight:600; }

/* Responsive */
@media(max-width:1024px){
  .g4{grid-template-columns:repeat(2,1fr)}
  .g2{grid-template-columns:1fr}
  .pz-spec{grid-template-columns:repeat(4,1fr)}
  .pz-banner{grid-template-columns:1fr;padding:48px 32px}
  .pz-mission{padding:40px}
}
@media(max-width:768px){
  .pz-steps{grid-template-columns:1fr 1fr}
  .pz-steps::before{display:none}
  .pz-spec{grid-template-columns:repeat(3,1fr)}
  .pz-s{padding:64px 16px}
  .pz-sm{padding:48px 16px}
}
@media(max-width:520px){
  .g4{grid-template-columns:1fr}
  .g3{grid-template-columns:1fr}
  .pz-steps{grid-template-columns:1fr}
  .pz-spec{grid-template-columns:repeat(2,1fr)}
  .pz-banner{padding:36px 24px}
}


/* ── Responsive ─────────────────────────── */
@media(max-width:1024px){
  .g4{grid-template-columns:repeat(2,1fr)}
  .g2{grid-template-columns:1fr}
  .pz-spec{grid-template-columns:repeat(4,1fr)}
  .pz-banner{grid-template-columns:1fr;padding:40px 28px}
  .pz-banner>div:last-child{display:none}
  .pz-mission{padding:32px}
  .pz-s{padding:40px 20px}
  .pz-sm{padding:36px 20px}
}
@media(max-width:768px){
  .g2,.g2-about,.lp-grid{grid-template-columns:1fr !important;gap:20px !important}
  .g3{grid-template-columns:1fr !important}
  .g4{grid-template-columns:1fr 1fr !important}
  .pz-spec{grid-template-columns:repeat(3,1fr)}
  .pz-steps{grid-template-columns:1fr 1fr}
  .pz-steps::before{display:none}
  .pz-s{padding:32px 14px !important}
  .pz-sm{padding:28px 14px !important}
  .pz-search{flex-direction:column}
  .pz-sf{border-right:none;border-bottom:1px solid var(--border);min-width:100%}
  .pz-sf:last-of-type{border-bottom:none}
  .pz-sb{width:100%;justify-content:center;margin-top:4px}
  .pz-banner{padding:32px 16px}
  .pz-mission{padding:28px 20px;border-radius:16px}
  .pz-fl{display:none}
  .pz-hero-right,.hero-right{display:none !important}
  .pz-stat-row{grid-template-columns:1fr 1fr}
}
@media(max-width:520px){
  .g4{grid-template-columns:1fr !important}
  .pz-spec{grid-template-columns:repeat(2,1fr)}
  .pz-steps{grid-template-columns:1fr}
  .pz-stat-row{grid-template-columns:1fr 1fr}
  .pz-h1{font-size:clamp(17px,6vw,26px) !important}
  .pz-h2{font-size:clamp(14px,5vw,22px) !important}
  .pz-lead{font-size:13px !important}
  .pz-sub{font-size:12px !important}
  .pz-s{padding:28px 12px !important}
  .pz-banner{padding:28px 14px}
  .pz-mission{padding:24px 16px}
}
@media(max-width:380px){
  .pz-spec{grid-template-columns:repeat(2,1fr)}
  .pz-stat-row{grid-template-columns:1fr}
  .pz-h1{font-size:16px !important}
}
</style>

<!-- ════ 1 · HERO ════ -->
<section class="pz-s" style="background:var(--bg);position:relative;overflow:hidden;padding-bottom:104px">
  <div class="blob" style="top:-130px;right:-130px;width:460px;height:460px;background:rgba(25,120,229,.06)"></div>
  <div class="blob" style="bottom:-90px;left:-70px;width:360px;height:360px;background:rgba(13,148,136,.06)"></div>
  <div class="pz-w g2" style="gap:64px;position:relative;z-index:1">

    <!-- Left -->
    <div>
      <h1 class="pz-h1">
        <span data-en="Your Direct Path to" data-sw="Njia Yako Moja kwa Moja kwa">Your Direct Path to</span><br>
        <span class="pz-accent" data-en="Better Healthcare" data-sw="Huduma Bora ya Afya">Better Healthcare</span>
      </h1>
      <p class="pz-lead" style="margin-bottom:36px"
         data-en="Find, compare and instantly book verified Kenyan doctors, hospitals and emergency services — transparent pricing, no hidden fees, available in Swahili and English."
         data-sw="Tafuta, linganisha na weka miadi papo hapo na madaktari, hospitali na huduma za dharura zilizoidhinishwa nchini Kenya — bei wazi, bila ada zilizofichwa, kwa Kiswahili na Kiingereza.">
        Find, compare and instantly book verified Kenyan doctors, hospitals and emergency services — transparent pricing, no hidden fees, available in Swahili and English.
      </p>

      <!-- Search bar -->
      <div class="pz-search" style="margin-bottom:22px">
        <div class="pz-sf">
          <i class="fa-solid fa-location-dot" style="color:var(--primary);font-size:15px;flex-shrink:0"></i>
          <input id="sLoc" type="text" data-en-placeholder="Nairobi, Mombasa, Kisumu…" data-sw-placeholder="Nairobi, Mombasa, Kisumu…" placeholder="Nairobi, Mombasa, Kisumu…">
        </div>
        <div class="pz-sf">
          <i class="fa-solid fa-magnifying-glass" style="color:var(--faint);font-size:14px;flex-shrink:0"></i>
          <input id="sQuery" type="text" data-en-placeholder="Doctor, specialty or hospital…" data-sw-placeholder="Daktari, utaalamu au hospitali…" placeholder="Doctor, specialty or hospital…">
        </div>
        <div class="pz-sf" style="min-width:126px;border-right:none">
          <i class="fa-solid fa-calendar" style="color:var(--faint);font-size:14px;flex-shrink:0"></i>
          <select id="sType">
            <option value="in_person">In-person</option>
            <option value="telehealth">Telehealth</option>
          </select>
        </div>
        <button class="pz-sb" onclick="doSearch()"><i class="fa-solid fa-magnifying-glass"></i><span data-en="Search" data-sw="Tafuta">Search</span></button>
      </div>

      <!-- Popular pills -->
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:32px;align-items:center">
        <span style="font-size:12px;color:var(--faint);font-weight:600" data-en="Popular:" data-sw="Maarufu:">Popular:</span>
        <?php foreach([['Cardiologist','Mtaalamu wa Moyo'],['Pediatrician','Daktari wa Watoto'],['Dentist','Daktari wa Meno'],['Gynecologist','Daktari wa Wanawake'],['GP','Daktari wa Jumla']] as [$en,$sw]): ?>
        <button class="pz-pill" data-en="<?=$en?>" data-sw="<?=$sw?>" onclick="document.getElementById('sQuery').value='<?=$en?>';doSearch()"><?=$en?></button>
        <?php endforeach; ?>
      </div>

      <!-- Social proof -->
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div class="pz-avs">
          <?php foreach(['https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=80&q=70','https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=80&q=70','https://images.unsplash.com/photo-1594824476967-48c8b964273f?w=80&q=70','https://images.unsplash.com/photo-1622253694238-3b22139576c6?w=80&q=70'] as $s) echo "<img src='$s' alt='Doctor'>"; ?>
        </div>
        <div>
          <div style="display:flex;gap:2px;margin-bottom:3px"><?php for($i=0;$i<5;$i++) echo '<i class="fa-solid fa-star" style="color:#f59e0b;font-size:12px"></i>'; ?></div>
          <span class="pz-sub" style="font-size:13px" data-en="Trusted by 120,000+ patients across Kenya" data-sw="Inaaminika na wagonjwa 120,000+ nchini Kenya">Trusted by 120,000+ patients across Kenya</span>
        </div>
      </div>
    </div>

    <!-- Right: image + floating badges -->
    <div style="position:relative">
      <div class="blob" style="top:-48px;right:-48px;width:240px;height:240px;background:rgba(13,148,136,.09)"></div>
      <div class="blob" style="bottom:-48px;left:-48px;width:240px;height:240px;background:rgba(25,120,229,.09)"></div>
      <img src="assets/images/twoDoctors.png" alt="Doctor with patient" loading="eager" style="border-radius:24px;box-shadow:0 28px 60px rgba(0,0,0,.14);position:relative;z-index:1;width:100%;object-fit:cover;aspect-ratio:4/3">
      <div class="pz-fl" style="bottom:26px;left:-28px">
        <div style="width:44px;height:44px;background:rgba(22,163,74,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#16a34a;font-size:20px;flex-shrink:0"><i class="fa-solid fa-circle-check"></i></div>
        <div><div class="pz-fl-lbl" data-en="Vetted Specialists" data-sw="Wataalamu Walioidhinishwa">Vetted Specialists</div><div class="pz-fl-val" data-en="2,500+ Doctors" data-sw="Madaktari 2,500+">2,500+ Doctors</div></div>
      </div>
      <div class="pz-fl" style="top:22px;right:-20px">
        <div style="width:36px;height:36px;background:rgba(25,120,229,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:16px;flex-shrink:0"><i class="fa-solid fa-calendar-check"></i></div>
        <div><div class="pz-fl-lbl" data-en="Booked Today" data-sw="Miadi Leo">Booked Today</div><div class="pz-fl-val">1,284</div></div>
      </div>
      <div class="pz-fl" style="top:50%;right:-20px;transform:translateY(-50%)">
        <div style="width:34px;height:34px;background:rgba(220,38,38,.09);border-radius:9px;display:flex;align-items:center;justify-content:center;color:#dc2626;font-size:15px;flex-shrink:0"><i class="fa-solid fa-truck-medical"></i></div>
        <div><div class="pz-fl-lbl" data-en="Ambulance Live" data-sw="Ambulensi Inaendesha">Ambulance Live</div><div class="pz-fl-val" data-en="~4 min response" data-sw="Majibu ~dakika 4">~4 min response</div></div>
      </div>
    </div>
  </div>
</section>

<!-- ════ 2 · STATS BAND ════ -->
<section style="background:var(--ink);padding:52px 24px">
  <div class="pz-w" style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;text-align:center">
    <?php foreach([['','Verified Providers','Watoa Huduma Walioidhinishwa','fa-hospital'],['','Patients Served','Wagonjwa Waliohudumishwa','fa-users'],['','All Counties Covered','Kaunti Zote Zinafikiwa','fa-map-location-dot'],['','Best Satisfaction Rate','Kiwango cha Kuridhika Zaidi','fa-star']] as [$n,$en,$sw,$ic]): ?>
    <div>
      <div style="width:48px;height:48px;background:rgba(255,255,255,.08);border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:20px;color:rgba(255,255,255,.6)"><i class="fa-solid <?=$ic?>"></i></div>
      <div class="pz-stat-n"><?=$n?></div>
      <div style="font-size:13px;color:rgba(255,255,255,.4);font-weight:500;margin-top:5px" data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ════ 3 · ABOUT PLANEAZZY — expanded ════ -->
<section class="pz-s" style="background:#fff">
  <div class="pz-w">
    <div style="text-align:center;margin-bottom:64px">
      <h2 class="pz-h2" style="text-align:center"><span data-en="What is Planeazzy?" data-sw="Planeazzy ni Nini?">What is Planeazzy?</span></h2>
      <p class="pz-sub" style="text-align:center;max-width:680px;margin:0 auto"
         data-en="Planeazzy is Kenya's leading digital healthcare platform — a single system that connects patients with verified doctors, hospitals, clinics and ambulance services across all 47 counties, instantly and transparently."
         data-sw="Planeazzy ni jukwaa linaloongoza la afya ya kidijitali Kenya — mfumo mmoja unaounganisha wagonjwa na madaktari, hospitali, kliniki na huduma za ambulensi zilizoidhinishwa katika kaunti zote 47, kwa haraka na uwazi.">
        Planeazzy is Kenya's leading digital healthcare platform — a single system that connects patients with verified doctors, hospitals, clinics and ambulance services across all 47 counties, instantly and transparently.
      </p>
    </div>

    <!-- Story + stats -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:start;margin-bottom:64px">
      <div>
        <h3 class="pz-h3" data-en="Built to Solve a Real Problem" data-sw="Imejengwa Kutatua Tatizo Halisi">Built to Solve a Real Problem</h3>
        <p class="pz-sub" style="margin-bottom:16px"
           data-en="Before Planeazzy, booking a doctor in Kenya meant long phone calls, unclear pricing, physical queues, and no way to verify credentials. Insurance documents had to be carried to every appointment. Emergencies were handled without a reliable dispatch system."
           data-sw="Kabla ya Planeazzy, kuweka miadi na daktari Kenya kulimaanisha simu ndefu, bei zisizo wazi, foleni za kimwili, na hakuna njia ya kuthibitisha vyeti. Hati za bima zilipaswa kubebwa kwa kila miadi. Hali za dharura zilishughulikiwa bila mfumo wa kuaminika wa kutuma msaada.">
          Before Planeazzy, booking a doctor in Kenya meant long phone calls, unclear pricing, physical queues, and no way to verify credentials. Insurance had to be carried everywhere. Emergencies had no reliable dispatch system.
        </p>
        <p class="pz-sub" style="margin-bottom:28px"
           data-en="We built Planeazzy to change that. Any Kenyan can now search for verified providers near them, book in under two minutes, upload their insurance once and never carry it again, consult doctors by video from anywhere in the country, and summon an ambulance with a single tap."
           data-sw="Tulijenga Planeazzy kubadilisha hilo. Mkenya yeyote sasa anaweza kutafuta watoa huduma walioidhinishwa karibu nao, kuweka miadi kwa chini ya dakika mbili, kupakia bima mara moja na kutobeba tena, kushauriana na madaktari kwa video kutoka popote nchini, na kuomba ambulensi kwa kubonyeza mara moja.">
          We built Planeazzy to change that. Any Kenyan can now find verified providers near them, book in under two minutes, share insurance digitally, consult by video from anywhere, and summon an ambulance with a single tap — in Swahili or English.
        </p>
        <div class="pz-chk"><i class="fa-solid fa-circle-check"></i><span data-en="Every provider is verified and licensed before listing on the platform." data-sw="Kila mtoa huduma amethibitishwa na kupewa leseni kabla ya kuorodheshwa kwenye jukwaa.">Every provider is verified and licensed before listing.</span></div>
        <div class="pz-chk"><i class="fa-solid fa-circle-check"></i><span data-en="Real patient reviews collected after every appointment — no fake ratings." data-sw="Maoni ya wagonjwa halisi hukusanywa baada ya kila miadi — hakuna ukadiriaji wa bandia.">Real patient reviews after every appointment — no fake ratings.</span></div>
        <div class="pz-chk"><i class="fa-solid fa-circle-check"></i><span data-en="You see the consultation fee before confirming any booking — no surprises." data-sw="Unaona ada ya ushauri kabla ya kuthibitisha miadi yoyote — hakuna mshangao.">Transparent pricing — see the fee before you confirm.</span></div>
        <div class="pz-chk"><i class="fa-solid fa-circle-check"></i><span data-en="Health data protected under the Kenya Data Protection Act 2019." data-sw="Data ya afya inalindwa chini ya Sheria ya Ulinzi wa Data ya Kenya 2019.">Data protected under Kenya's Data Protection Act 2019.</span></div>
      </div>
      <div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px">
          <?php foreach([['2,500+','Verified Doctors','Madaktari Walioidhinishwa'],['47','Counties Covered','Kaunti Zinazohudumika'],['< 2 min','Average Booking Time','Wastani wa Muda wa Miadi'],['~4 min','Emergency Response','Majibu ya Dharura']] as [$n,$en,$sw]): ?>
          <div class="pz-about-box">
            <div class="pz-about-num"><?=$n?></div>
            <div class="pz-about-lbl" data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <img src="assets/images/femaleDoctor.png" alt="Patient using Planeazzy" loading="lazy" style="width:100%; aspect-ratio:16/10; object-fit:cover; border-radius:18px; box-shadow: 0 1px 1px rgba(0,0,0,0.05), 0 2px 2px rgba(0,0,0,0.05), 0 4px 4px rgba(0,0,0,0.05), 0 8px 8px rgba(0,0,0,0.05), 0 16px 16px rgba(0,0,0,0.05); border:none;">
      </div>
    </div>

    <!-- Mission boxes -->
    <div class="g3" style="margin-bottom:0">
      <?php foreach([
        ['fa-bullseye','#1978e5','Our Mission','Dhamira Yetu',
          'To make quality healthcare accessible to every Kenyan, regardless of location, income or language — by connecting people with trusted providers through technology.',
          'Kufanya huduma bora ya afya iwe wazi kwa kila Mkenya, bila kujali mahali, kipato au lugha — kwa kuwasiliana watu na watoa huduma wanaoaminiwa kupitia teknolojia.'],
        ['fa-eye','#0d9488','Our Vision','Maono Yetu',
          'A Kenya where no one waits hours for a doctor, no one is turned away for lack of insurance paperwork, and every emergency gets a response in under five minutes.',
          'Kenya ambapo hakuna mtu anayesubiri masaa kwa daktari, hakuna anayekataliwa kwa ukosefu wa karatasi za bima, na kila dharura inapata majibu kwa chini ya dakika tano.'],
        ['fa-handshake','#7c3aed','Our Values','Maadili Yetu',
          'Trust, transparency, and access. We verify every provider, show every price upfront, and build for all 47 counties — not just the capital.',
          'Uaminifu, uwazi, na upatikanaji. Tunathibitisha kila mtoa huduma, tunaonyesha bei zote mapema, na tunajenga kwa kaunti zote 47 — si mji mkuu peke yake.'],
      ] as [$ic,$col,$en,$sw,$desc,$descSw]): ?>
      <div class="pz-card" style="padding:28px">
        <div style="width:48px;height:48px;border-radius:13px;background:<?=$col?>15;color:<?=$col?>;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:16px"><i class="fa-solid <?=$ic?>"></i></div>
        <h3 class="pz-h3" data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></h3>
        <p class="pz-sub" style="font-size:14px" data-en="<?=htmlspecialchars($desc)?>" data-sw="<?=htmlspecialchars($descSw)?>"><?=$desc?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════ 4 · WHO USES PLANEAZZY ════ -->
<section class="pz-s" style="background:var(--bg)">
  <div class="pz-w">
    <div style="text-align:center;margin-bottom:56px">
      <h2 class="pz-h2" style="text-align:center"><span data-en="One Platform, Everyone in Healthcare" data-sw="Jukwaa Moja, Kila Mtu katika Afya">One Platform, Everyone in Healthcare</span></h2>
      <p class="pz-sub" style="text-align:center;max-width:580px;margin:0 auto" data-en="Planeazzy connects patients, doctors, hospitals, clinics and ambulance services in one seamless ecosystem." data-sw="Planeazzy inaunganisha wagonjwa, madaktari, hospitali, kliniki na huduma za ambulensi katika mfumo mmoja.">Planeazzy connects patients, doctors, hospitals, clinics and ambulance services in one seamless ecosystem.</p>
    </div>
    <div class="g4">
      <?php foreach([
        ['fa-user','#1978e5','Patients','Wagonjwa','Search verified providers near you, book instantly, upload insurance, and consult by HD video.','Tafuta watoa huduma walioidhinishwa karibu nawe, weka miadi papo hapo, pakia bima, na shauri kwa video HD.','/patients/register.php','Get Started Free','Anza Bure'],
        ['fa-stethoscope','#0d9488','Doctors','Madaktari','Manage your schedule, run secure HD video consultations, receive booking alerts by email, and grow your patient base digitally across all 47 counties.','Simamia ratiba yako, fanya mashauriano ya video salama ya HD, pokea arifa za miadi kwa barua pepe, na kukuza msingi wako wa wagonjwa kidijitali.','/providers/doctor/register.php','Register as Doctor','Jiandikishe kama Daktari'],
        ['fa-house-medical','#059669','Hospitals & Clinics','Hospitali & Kliniki','Digitise all bookings, manage your doctor roster, auto-receive patient insurance documents, track bed occupancy, and access a full analytics dashboard.','Digitisha miadi yote, simamia orodha ya madaktari, pokea hati za bima za wagonjwa kiotomatiki, fuatilia vitanda, na fikia dashibodi kamili ya takwimu.','/hospital/onboarding/join.php','Register Facility','Jiandikishe Kituo'],
        ['fa-truck-medical','#dc2626','Ambulance Services','Huduma za Ambulensi','Receive live GPS SOS alerts from patients, dispatch the nearest available unit in real time, and coordinate full emergency responses across Kenya in minutes.','Pokea arifa za SOS za GPS moja kwa moja kutoka kwa wagonjwa, tuma kitengo kilicho karibu zaidi kwa wakati halisi, na uratibu majibu ya dharura nchini Kenya.','/providers/ambulance/register.php','Register Service','Jiandikishe Huduma'],
      ] as [$ic,$col,$en,$sw,$desc,$descSw,$link,$cta,$ctaSw]): ?>
      <div class="pz-card pz-card-h" style="display:flex;flex-direction:column;gap:18px">
        <div class="pz-ic" style="background:<?=$col?>15;color:<?=$col?>;border:1.5px solid <?=$col?>25"><i class="fa-solid <?=$ic?>"></i></div>
        <div>
          <h3 class="pz-h3" data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></h3>
          <p style="font-size:13px;color:var(--body);line-height:1.75" data-en="<?=htmlspecialchars($desc)?>" data-sw="<?=htmlspecialchars($descSw)?>"><?=$desc?></p>
        </div>
        <a href="<?=$link?>" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:<?=$col?>;text-decoration:none;margin-top:auto;padding:9px 16px;border-radius:9px;background:<?=$col?>12;border:1px solid <?=$col?>22;width:fit-content">
          <span data-en="<?=$cta?>" data-sw="<?=$ctaSw?>"><?=$cta?></span><i class="fa-solid fa-arrow-right" style="font-size:11px"></i>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════ 5 · HOW IT WORKS ════ -->
<section class="pz-s" style="background:#fff">
  <div class="pz-w">
    <div style="text-align:center;margin-bottom:56px">
      <h2 class="pz-h2" style="text-align:center"><span data-en="Book Healthcare in 4 Steps" data-sw="Weka Miadi kwa Hatua 4">Book Healthcare in 4 Steps</span></h2>
      <p class="pz-sub" style="text-align:center;max-width:500px;margin:0 auto" data-en="The entire booking process takes under 2 minutes — doctor, hospital or ambulance." data-sw="Mchakato wote wa kuweka miadi huchukua chini ya dakika 2 — daktari, hospitali au ambulensi.">The entire booking process takes under 2 minutes.</p>
    </div>
    <div class="pz-steps">
      <?php foreach([
        ['1','#1978e5','fa-magnifying-glass','Search','Tafuta','Search by specialty, name, location or condition. Filter by insurance, distance and telehealth availability.','Tafuta kwa utaalamu, jina, mahali au hali. Chuja kwa bima, umbali na upatikanaji wa telemedicine.'],
        ['2','#0d9488','fa-calendar-check','Choose & Book','Chagua na Weka','Compare profiles and real reviews. Check live availability and book with one click.','Linganisha wasifu na maoni halisi. Angalia upatikanaji wa moja kwa moja na uweke kwa kubofya mara moja.'],
        ['3','#059669','fa-envelope-circle-check','Get Confirmed','Pata Uthibitisho','Receive an instant email confirmation with all details. A reminder arrives 24 hours before.','Pokea uthibitisho wa barua pepe wa papo hapo na maelezo yote. Ukumbusho unafika masaa 24 kabla.'],
        ['4','#7c3aed','fa-location-arrow','Visit or Join','Tembelea au Jiunge','Attend in person or join via secure HD video call from anywhere in Kenya.','Hudhuria ana kwa ana au jiunge kupitia simu ya video salama ya HD kutoka popote nchini Kenya.'],
      ] as [$num,$col,$ic,$en,$sw,$desc,$descSw]): ?>
      <div class="pz-card">
        <div class="pz-sn" style="background:<?=$col?>;box-shadow:0 6px 18px <?=$col?>44"><?=$num?></div>
        <div style="width:38px;height:38px;background:<?=$col?>18;color:<?=$col?>;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:16px"><i class="fa-solid <?=$ic?>"></i></div>
        <h3 class="pz-h3" data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></h3>
        <p style="font-size:13px;color:var(--body);line-height:1.75" data-en="<?=htmlspecialchars($desc)?>" data-sw="<?=htmlspecialchars($descSw)?>"><?=$desc?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════ 6 · PLATFORM FEATURES ════ -->
<section class="pz-s" style="background:var(--bg)">
  <div class="pz-w g2" style="gap:72px">
    <div style="position:relative">
      <div class="blob" style="top:-40px;left:-40px;width:220px;height:220px;background:rgba(13,148,136,.08)"></div>
      <img src="assets/images/dashboard.png" alt="Healthcare professional" loading="lazy" style="border-radius:22px;box-shadow:0 20px 44px rgba(0,0,0,.11);width:100%;object-fit:cover;aspect-ratio:4/3;position:relative;z-index:1">

    </div>
    <div>
      <h2 class="pz-h2"><span data-en="Everything You Need, One Platform" data-sw="Kila Kitu Unachohitaji, Jukwaa Moja">Everything You Need, One Platform</span></h2>
      <?php foreach([
        ['fa-shield-heart','#1978e5','Insurance Management','Usimamizi wa Bima','Upload your insurance card once — NHIF, Jubilee, AXA or any provider. It auto-shares with providers at booking so they verify your cover before you arrive.','Pakia kadi yako ya bima mara moja — NHIF, Jubilee, AXA au mtoa yeyote. Inashirikiwa kiotomatiki wakati wa miadi ili wathibitishe kinga yako mapema.'],
        ['fa-video','#0d9488','HD Telehealth Video','Telemedicine ya Video HD','Consult any doctor from home via secure HD video. Get diagnosed, receive a prescription and follow-up — without travelling.','Shauri na daktari yeyote kutoka nyumbani kwa video salama ya HD. Pata utambuzi, dawa na ufuatiliaji — bila kusafiri.'],
        ['fa-truck-medical','#dc2626','Emergency SOS Dispatch','Kutuma Dharura SOS','One tap activates emergency mode. Your GPS location is sent instantly to dispatch the nearest available ambulance — targeting 4-minute response.','Bomba moja linawasha hali ya dharura. GPS yako inatumwa mara moja kutuma ambulensi iliyo karibu zaidi — ikilenga majibu ya dakika 4.'],
        ['fa-language','#7c3aed','Full Swahili & English','Kiswahili na Kiingereza Kamili','Every label, button and message works in both languages. Switch at any time with one click — your choice is saved across all pages.','Kila lebo, kitufe na ujumbe hufanya kazi kwa lugha zote mbili. Badilisha wakati wowote kwa kubofya mara moja — chaguo lako huhifadhiwa.'],
      ] as [$ic,$col,$en,$sw,$desc,$descSw]): ?>
      <div class="pz-feat">
        <div class="pz-ic" style="background:<?=$col?>12;color:<?=$col?>;border:1px solid <?=$col?>20"><i class="fa-solid <?=$ic?>"></i></div>
        <div>
          <div class="pz-feat-title" data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></div>
          <div class="pz-feat-desc" data-en="<?=htmlspecialchars($desc)?>" data-sw="<?=htmlspecialchars($descSw)?>"><?=$desc?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════ 7 · SPECIALTIES ════ -->
<section class="pz-s" style="background:#fff">
  <div class="pz-w">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:44px;flex-wrap:wrap;gap:16px">
      <div>
        <h2 class="pz-h2" style="margin-bottom:0"><span data-en="Browse by Specialty" data-sw="Vinjari kwa Utaalamu">Browse by Specialty</span></h2>
      </div>
      <a href="/patients/search.php" style="font-size:14px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:5px;text-decoration:none"><span data-en="View All" data-sw="Ona Yote">View All</span><i class="fa-solid fa-arrow-right" style="font-size:12px"></i></a>
    </div>
    <div class="pz-spec">
      <?php foreach([
        ['fa-stethoscope','#1978e5','General Physician','Daktari wa Jumla'],['fa-tooth','#0d9488','Dentist','Daktari wa Meno'],
        ['fa-baby','#7c3aed','Pediatrics','Daktari wa Watoto'],['fa-venus','#db2777','Gynecology','Uzazi & Wanawake'],
        ['fa-heart-pulse','#dc2626','Cardiology','Moyo'],['fa-brain','#059669','Psychiatry','Afya ya Akili'],
        ['fa-eye','#d97706','Ophthalmology','Macho'],['fa-bone','#0891b2','Orthopedics','Mifupa'],
        ['fa-lungs','#2563eb','Pulmonology','Mapafu'],['fa-syringe','#9333ea','Oncology','Saratani'],
        ['fa-person-walking','#0d9488','Physiotherapy','Tiba ya Viungo'],['fa-flask','#b45309','Pathology','Patholojia'],
      ] as [$ic,$col,$en,$sw]): ?>
      <button class="pz-sc" onclick="location.href='/patients/search.php?q=<?=urlencode($en)?>'">
        <div style="width:50px;height:50px;background:<?=$col?>14;border-radius:13px;display:flex;align-items:center;justify-content:center"><i class="fa-solid <?=$ic?>" style="font-size:22px;color:<?=$col?>"></i></div>
        <span class="pz-sc-lbl" data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════ 8 · TOP HOSPITALS ════ -->
<section class="pz-s" style="background:var(--bg)">
  <div class="pz-w">
    <div style="text-align:center;margin-bottom:48px">
      <h2 class="pz-h2" style="text-align:center"><span data-en="Top-Rated Medical Centres" data-sw="Vituo vya Matibabu Vilivyo Bora">Top-Rated Medical Centres</span></h2>
    </div>
    <div class="g3">
      <?php foreach([
        ['Aga Khan University Hospital','Parklands, Nairobi','4.9',[['24/7 Emergency','Huduma ya Dharura 24/7'],['Specialized Surgery','Upasuaji Maalum'],['Neurology','Neva']],'https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=600&q=75'],
        ['The Nairobi Hospital','Argwings Kodhek Rd','4.7',[['Diagnostic Centre','Kituo cha Uchunguzi'],['Maternity','Uzazi wa Mama'],['Cardiology','Moyo']],'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=600&q=75'],
        ['MP Shah Hospital','Shivachi Rd, Nairobi','4.8',[['Critical Care','Huduma ya Dharura'],['Cancer Care','Saratani'],['Paediatrics','Watoto']],'https://images.unsplash.com/photo-1551076805-e1869033e561?w=600&q=75'],
      ] as [$name,$loc,$rating,$tags,$img]): ?>
      <div class="pz-card pz-card-h" style="padding:0;overflow:hidden">
        <div style="height:196px;position:relative;overflow:hidden">
          <img src="<?=$img?>" alt="<?=htmlspecialchars($name)?>" loading="lazy" style="width:100%;height:100%;object-fit:cover">
          <div style="position:absolute;top:12px;right:12px;background:rgba(255,255,255,.92);backdrop-filter:blur(6px);padding:4px 11px;border-radius:9999px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:4px"><i class="fa-solid fa-star" style="color:#f59e0b;font-size:11px"></i><?=$rating?></div>
        </div>
        <div style="padding:22px">
          <h3 style="font-size:17px;font-weight:800;color:var(--ink);margin-bottom:5px"><?=htmlspecialchars($name)?></h3>
          <div style="display:flex;align-items:center;gap:5px;color:var(--faint);font-size:13px;margin-bottom:14px"><i class="fa-solid fa-location-dot" style="font-size:12px;color:var(--primary)"></i><?=htmlspecialchars($loc)?></div>
          <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:18px">
            <?php foreach($tags as $t): ?><span class="pz-chip" data-en="<?=htmlspecialchars($t[0])?>" data-sw="<?=htmlspecialchars($t[1])?>"><?=htmlspecialchars($t[0])?></span><?php endforeach; ?>
          </div>
          <button onclick="location.href='/patients/search.php?q=<?=urlencode($name)?>'" style="width:100%;padding:11px;border:1.5px solid var(--primary);color:var(--primary);font-weight:700;border-radius:11px;background:transparent;cursor:pointer;font-family:inherit;font-size:13px;transition:background .15s,color .15s" onmouseover="this.style.background='var(--primary)';this.style.color='#fff'" onmouseout="this.style.background='transparent';this.style.color='var(--primary)'" data-en="View Doctors →" data-sw="Ona Madaktari →">View Doctors →</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════ 9 · PAYMENT OPTIONS ════ -->
<section class="pz-s" style="background:#fff">
  <div class="pz-wm">
    <div style="text-align:center;margin-bottom:48px">
      <h2 class="pz-h2" style="text-align:center"><span data-en="Choose How You Pay" data-sw="Chagua Jinsi ya Kulipa">Choose How You Pay</span></h2>
      <p class="pz-sub" style="text-align:center;max-width:520px;margin:0 auto" data-en="We support every payment method Kenyans use. No upfront online payments required." data-sw="Tunasaidia kila njia ya malipo inayotumiwa na Wakenya. Hakuna malipo ya awali ya mtandaoni yanayohitajika.">We support every payment method Kenyans use. No upfront online payments required.</p>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <?php foreach([
        // ITEM 1: Has 8 elements (Correct)
        ['fa-shield-heart','#1978e5','Use Your Insurance','Tumia Bima Yako','Upload your NHIF, Jubilee Health, AXA Mansard, AAR or any insurance card once. At booking, it is automatically sent to the provider for pre-authorisation — no paperwork on arrival.','Pakia kadi yako ya NHIF, Jubilee Health, AXA Mansard, AAR au nyingine yoyote mara moja. Wakati wa miadi, inatumwa kiotomatiki kwa mtoa huduma kwa idhini ya mapema — hakuna karatasi unapofika.','Learn More →','Jifunza Zaidi →'],
        
        // ITEM 2: Now has 8 elements (Added the missing Swahili description)
        ['fa-credit-card','#0d9488','Pay at Facility','Lipa Hospitalini', 'Book your appointment now — pay when you arrive using M-Pesa, Visa, Mastercard or cash', 'Weka miadi yako sasa — lipa unapofika ukitumia M-Pesa, Visa, Mastercard au pesa taslimu', 'Get Started →', 'Anza Sasa →'],
      ] as [$ic,$col,$en,$sw,$desc,$descSw,$cta,$ctaSw]): ?>
      <div class="pz-card" style="padding:36px 28px">
        <div class="pz-ic" style="background:<?=$col?>12;color:<?=$col?>;font-size:26px;border:1.5px solid <?=$col?>22;margin-bottom:20px"><i class="fa-solid <?=$ic?>"></i></div>
        <h3 class="pz-h3" data-en="<?=$en?>" data-sw="<?=$sw?>"><?=$en?></h3>
        <p style="font-size:14px;color:var(--body);line-height:1.85;margin-bottom:20px" data-en="<?=htmlspecialchars($desc)?>" data-sw="<?=htmlspecialchars($descSw)?>"><?=$desc?></p>
        <a href="/patients/register.php" style="font-size:14px;font-weight:700;color:<?=$col?>;text-decoration:none;display:inline-flex;align-items:center;gap:5px;padding:9px 18px;border-radius:9px;background:<?=$col?>10;border:1px solid <?=$col?>20">
            <span data-en="<?=$cta?>" data-sw="<?=$ctaSw?>"><?=$cta?></span>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════ 10 · TESTIMONIALS ════ -->
<section class="pz-s" style="background:var(--bg)">
  <div class="pz-w">
    <div style="text-align:center;margin-bottom:48px">
      <h2 class="pz-h2" style="text-align:center"><span data-en="Loved by Kenyans Across the Country" data-sw="Inapendwa na Wakenya Kote Nchini">Loved by Kenyans Across the Country</span></h2>
    </div>
    <div class="g3">
      <?php foreach([
        ['AW','Amara Wanjiku','Patient, Nairobi','Mgonjwa, Nairobi','"I found a cardiologist and booked within 5 minutes from my phone. The telehealth session saved me a 2-hour journey. Absolutely brilliant platform."','"Nilipata mtaalamu wa moyo na kuweka miadi ndani ya dakika 5. Kikao cha telemedicine kiliokoa safari ya masaa 2. Jukwaa zuri kabisa."','linear-gradient(135deg,#1978e5,#0d9488)'],
        ['DK','Dr. David Kamau','Cardiologist, KNH','Mtaalamu wa Moyo, KNH','"Planeazzy has completely transformed my practice. Scheduling is effortless, telehealth tools are world-class, and I reach patients in counties I could never visit in person."','"Planeazzy imebadilisha kabisa mazoezi yangu. Kupanga ratiba ni rahisi, zana za telemedicine ni za kiwango cha kimataifa, na nawafikia wagonjwa kaunti ambazo sikuweza kutembelea."','linear-gradient(135deg,#7c3aed,#1978e5)'],
        ['MO','Mercy Odhiambo','Ambulance Dispatcher, Mombasa','Mtumaji wa Ambulensi, Mombasa','"The emergency GPS dispatch is incredible. We reached a patient in Likoni within 4 minutes using the live location system. Planeazzy saves lives every single day."','"Kutuma GPS ya dharura ni cha ajabu. Tulimfikia mgonjwa Likoni ndani ya dakika 4 kwa mfumo wa moja kwa moja. Planeazzy huokoa maisha kila siku."','linear-gradient(135deg,#dc2626,#d97706)'],
      ] as [$init,$name,$role,$roleSw,$quote,$quoteSw,$grad]): ?>
      <div class="pz-testi">
        <div style="display:flex;gap:3px;margin-bottom:14px"><?php for($i=0;$i<5;$i++) echo '<i class="fa-solid fa-star" style="color:#f59e0b;font-size:13px"></i>'; ?></div>
        <p class="pz-testi-q" data-en="<?=htmlspecialchars($quote)?>" data-sw="<?=htmlspecialchars($quoteSw)?>"><?=$quote?></p>
        <div style="display:flex;align-items:center;gap:11px">
          <div style="width:42px;height:42px;border-radius:50%;background:<?=$grad?>;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;flex-shrink:0"><?=$init?></div>
          <div>
            <div style="font-size:14px;font-weight:700;color:var(--ink)"><?=$name?></div>
            <div style="font-size:12px;color:var(--faint)" data-en="<?=htmlspecialchars($role)?>" data-sw="<?=htmlspecialchars($roleSw)?>"><?=$role?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════ 11 · FAQ ════ -->
<section class="pz-s" style="background:#fff">
  <div class="pz-ws">
    <div style="text-align:center;margin-bottom:48px">
      <h2 class="pz-h2" style="text-align:center"><span data-en="Everything You Need to Know" data-sw="Kila Kitu Unachohitaji Kujua">Everything You Need to Know</span></h2>
    </div>
    <?php foreach([
      ['Is Planeazzy free for patients?','Je, Planeazzy ni bure kwa wagonjwa?','Yes — searching, browsing and booking is completely free. You pay only the consultation fee directly to the provider at your appointment. No platform fees, ever.','Ndiyo — kutafuta, kuvinjari na kuweka miadi ni bure kabisa. Unalipa tu ada ya ushauri moja kwa moja kwa mtoa huduma wakati wa miadi yako. Hakuna ada za jukwaa, kamwe.'],
      ['How do I share my insurance when booking?','Ninawezaje kushiriki bima yangu wakati wa kuweka miadi?','Upload your insurance card from Dashboard → Insurance. When booking, select that document and tick the consent box. It is emailed to the provider instantly so they can verify your cover before you arrive.','Pakia kadi yako ya bima kutoka Dashibodi → Bima. Unapoweka miadi, chagua hati hiyo na tiki kisanduku cha idhini. Inatumwa kwa barua pepe kwa mtoa huduma mara moja ili wathibitishe kinga yako kabla ya kufika.'],
      ['Can I see a doctor from outside Nairobi?','Je, ninaweza kuona daktari kutoka nje ya Nairobi?','Yes. Planeazzy has verified providers across all 47 counties. You can also use Telehealth video to consult any doctor in Kenya without travelling at all — from your phone or computer.','Ndiyo. Planeazzy ina watoa huduma walioidhinishwa katika kaunti zote 47. Unaweza pia kutumia video ya Telemedicine kushauriana na daktari yeyote nchini Kenya bila kusafiri kabisa.'],
      ['What happens when I click Hospitals Near You?','Nini kinatokea ninapobonyeza Hospitali Karibu Nawe?','Your browser will ask for permission to access your location. Once you allow it, your GPS coordinates are used to calculate the real distance to every provider and sort the results nearest first. You can then filter by type, distance, insurance and visit mode.','Kivinjari chako kitauliza ruhusa ya kufikia mahali pako. Ukikubali, kuratibu zako za GPS zinatumiwa kuhesabu umbali halisi kwa kila mtoa huduma na kupanga matokeo karibu zaidi kwanza. Unaweza kisha kuchuja kwa aina, umbali, bima na hali ya ziara.'],
      ['How do I register my hospital?','Ninawezaje kuandikisha hospitali yangu?','Click Register Your Facility, complete the form with your hospital details and MOH licence number. Your account is reviewed within 24–48 hours. Once approved you get full dashboard access.','Bofya Jiandikishe Kituo Chako, kamilisha fomu na maelezo ya hospitali yako na nambari ya leseni ya MOH. Akaunti yako inapitiwa ndani ya masaa 24–48. Baada ya kuidhinishwa unapata ufikiaji kamili wa dashibodi.'],
      ['Is my health data safe?','Je, data yangu ya afya iko salama?','Yes. All data is encrypted and protected under the Kenya Data Protection Act 2019. You control exactly what is shared with providers through your consent settings, which can be changed at any time.','Ndiyo. Data yote imesimbwa na inalindwa chini ya Sheria ya Ulinzi wa Data ya Kenya 2019. Unachagua hasa kinachoshirikiwa na watoa huduma kupitia mipangilio yako ya idhini, ambayo inaweza kubadilishwa wakati wowote.'],
    ] as $i => [$q,$qSw,$a,$aSw]): ?>
    <div class="pz-faq">
      <button class="pz-fq-btn" onclick="toggleFaq(<?=$i?>)">
        <span class="pz-fq-q" data-en="<?=htmlspecialchars($q)?>" data-sw="<?=htmlspecialchars($qSw)?>"><?=$q?></span>
        <i class="fa-solid fa-plus pz-fq-ic" id="faq-ic-<?=$i?>"></i>
      </button>
      <div class="pz-fq-body" id="faq-b-<?=$i?>">
        <p data-en="<?=htmlspecialchars($a)?>" data-sw="<?=htmlspecialchars($aSw)?>"><?=$a?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ════ 12 · PROVIDER CTA ════ 
<section class="pz-sm" style="background:var(--bg)">
  <div class="pz-w">
    <div class="pz-banner">
      <div style="position:absolute;top:-80px;right:240px;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,.05)"></div>
      <div class="pz-banner-txt" style="position:relative;z-index:1">
        <div style="display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.15);padding:5px 14px;border-radius:9999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:18px;border:1px solid rgba(255,255,255,.2);color:#fff">
          <i class="fa-solid fa-hospital"></i><span data-en="For Healthcare Providers" data-sw="Kwa Watoa Huduma za Afya">For Healthcare Providers</span>
        </div>
        <h2 data-en="Are you a healthcare provider?" data-sw="Je, wewe ni mtoa huduma za afya?">Are you a healthcare provider?</h2>
        <p data-en="Join Planeazzy to grow your practice, receive digital bookings, auto-receive insurance documents, reach thousands of new patients, and deliver better care — all from one dashboard." data-sw="Jiunge na Planeazzy kukuza mazoezi yako, pokea miadi ya kidijitali, pokea hati za bima kiotomatiki, fikia maelfu ya wagonjwa wapya, na kutoa huduma bora — yote kutoka dashibodi moja.">Join Planeazzy to grow your practice, receive digital bookings, auto-receive insurance documents, and reach thousands of new patients — all from one dashboard.</p>
        <div style="display:flex;gap:14px;flex-wrap:wrap">
          <button class="pz-btn-white" onclick="location.href='/hospital/onboarding/join.php'" data-en="Register Your Facility →" data-sw="Jiandikishe Kituo Chako →">Register Your Facility →</button>
          <button class="pz-btn-ghost" onclick="location.href='/providers/doctor/register.php'" data-en="Register as Doctor" data-sw="Jiandikishe kama Daktari">Register as Doctor</button>
        </div>
      </div>
      <div style="position:relative;z-index:1">
        <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=800&q=75" alt="Healthcare provider" loading="lazy" style="border-radius:20px;box-shadow:0 20px 44px rgba(0,0,0,.26);width:100%;object-fit:cover;aspect-ratio:4/3">
        <div style="position:absolute;inset:0;background:linear-gradient(to right,#1462c4 0%,rgba(25,120,229,.15) 50%,transparent 75%);border-radius:20px"></div>
      </div>
    </div>
  </div>
</section>-->

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
/* ── Search ── */
function doSearch() {
  const loc  = document.getElementById('sLoc')?.value.trim()   || '';
  const q    = document.getElementById('sQuery')?.value.trim() || '';
  const type = document.getElementById('sType')?.value         || 'in_person';
  window.location.href = '/patients/search.php?' + new URLSearchParams({location:loc, q, visit_type:type}).toString();
}
/* ── FAQ ── */
function toggleFaq(i) {
  const b = document.getElementById('faq-b-'+i);
  const ic= document.getElementById('faq-ic-'+i);
  const open = b.style.display === 'block';
  b.style.display = open ? 'none' : 'block';
  ic.classList.toggle('op', !open);
}
/* ── sType swap on langchange ── */
document.addEventListener('langchange', e => {
  const sel = document.getElementById('sType'); if (!sel) return;
  const opts = e.detail.lang === 'sw' ? ['Ana kwa Ana','Dawa Mtandaoni'] : ['In-person','Telehealth'];
  [...sel.options].forEach((o,i) => { if (opts[i]) o.text = opts[i]; });
});
</script>
