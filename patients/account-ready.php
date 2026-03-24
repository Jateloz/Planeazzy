<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
$noSidebar = true; $pageTitle = 'Account Ready!';
include dirname(__DIR__). '/includes/header.php';
$name = htmlspecialchars($_SESSION['patient_name'] ?? 'there');
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--bg-light)">
  <div style="width:100%;max-width:520px;text-align:center" class="slide-up">
    <div style="width:80px;height:80px;border-radius:50%;background:rgba(22,163,74,.1);color:var(--green);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:36px;border:3px solid rgba(22,163,74,.2)">
      <i class="fa-solid fa-circle-check"></i>
    </div>
    <h2 style="font-size:30px;font-weight:900;color:var(--slate-900);letter-spacing:-.03em;margin-bottom:12px">You're all set, <?= $name ?>! 🎉</h2>
    <p style="font-size:15px;color:var(--slate-500);line-height:1.8;margin-bottom:28px">Your Planeazzy account is ready. Start booking appointments with top Kenyan specialists or explore hospitals near you.</p>
    <div style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-bottom:28px">
      <?php foreach([['fa-stethoscope','Find a Doctor'],['fa-video','Telehealth'],['fa-truck-medical','Ambulance'],['fa-location-dot','Nearby Care']] as[$ic,$lb]):?>
      <span style="display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9999px;background:var(--white);border:1.5px solid var(--slate-200);font-size:13px;font-weight:700;color:var(--slate-600)">
        <i class="fa-solid <?=$ic?>" style="color:var(--primary)"></i><?=$lb?>
      </span>
      <?php endforeach;?>
    </div>
    <a href="/patients/dashboard.php" class="btn btn-primary btn-lg" style="margin-right:10px"><i class="fa-solid fa-gauge"></i> Go to Dashboard</a>
    <a href="/patients/search.php" class="btn btn-ghost btn-lg"><i class="fa-solid fa-magnifying-glass"></i> Find a Doctor</a>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
