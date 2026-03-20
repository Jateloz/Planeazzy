<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
$noSidebar = true;
$pageTitle = 'Welcome to Planeazzy!';
include dirname(__DIR__). '/includes/header.php';
?>
<main class="page-main" style="gap:28px">
  <div style="width:100%;max-width:540px">
    <div class="step-wrap">
      <div class="step-row">
        <span class="step-badge"><span class="material-symbols-outlined">celebration</span> Step 5 of 5 — Done!</span>
        <span class="pct">100% Complete</span>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:100%"></div></div>
    </div>
  </div>
  <div style="width:100%;max-width:540px;border-radius:var(--r-2xl);overflow:hidden;position:relative;height:200px;box-shadow:var(--sh-lg)">
    <img src="https://images.unsplash.com/photo-1631217868264-e5b90bb7e133?w=800&q=80" alt="Healthcare" style="width:100%;height:100%;object-fit:cover">
    <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(25,120,229,.5),transparent 50%)"></div>
    <div style="position:absolute;bottom:14px;left:50%;transform:translateX(-50%);background:rgba(255,255,255,.96);backdrop-filter:blur(10px);padding:7px 18px;border-radius:99px;display:flex;align-items:center;gap:7px;font-family:var(--ff-head);font-weight:800;font-size:13px;color:var(--navy);white-space:nowrap;box-shadow:var(--sh)">
      <span style="width:7px;height:7px;border-radius:50%;background:var(--green);flex-shrink:0;animation:blink 1.5s ease-in-out infinite"></span>
      Account Active
    </div>
  </div>
  <div class="success-wrap">
    <div class="success-ring"><span class="material-symbols-outlined">check_circle</span></div>
    <h1 class="success-title">You're all set!</h1>
    <p class="success-sub">Your Planeazzy account is active. Access doctors, clinics, hospitals, ambulance services, telehealth, and your complete health dashboard — all in one place.</p>
    <div class="chips">
      <div class="chip"><span class="material-symbols-outlined">calendar_month</span> Book Appointments</div>
      <div class="chip"><span class="material-symbols-outlined">video_chat</span> Telehealth</div>
      <div class="chip"><span class="material-symbols-outlined">emergency</span> Emergency</div>
      <div class="chip"><span class="material-symbols-outlined">folder_health</span> Health Records</div>
    </div>
    <a href="/patients/login.php" class="btn btn-primary btn-full btn-lg" style="max-width:340px;margin:0 auto;display:flex">
      <span class="material-symbols-outlined">login</span> Sign In to Dashboard
    </a>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
