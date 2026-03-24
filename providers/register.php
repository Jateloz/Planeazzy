<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id'])) { header('Location: /providers/dashboard.php'); exit; }
$noSidebar = true; $pageTitle = 'Register Your Practice';
include dirname(__DIR__) . '/includes/header.php';
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:64px 20px;background:var(--bg-light)">
  <div style="width:100%;max-width:700px">
    <div style="text-align:center;margin-bottom:40px">
      <span style="display:inline-block;padding:4px 16px;border-radius:9999px;background:var(--primary-10);color:var(--primary);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px">Join Planeazzy's Provider Network</span>
      <h2 style="font-size:clamp(28px,4vw,40px);font-weight:900;color:var(--slate-900);letter-spacing:-.03em;margin-bottom:12px">Choose Your Portal</h2>
      <p style="font-size:16px;color:var(--slate-500)">Select the type of healthcare practice you want to register.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px">
      <?php foreach([
        ['doctor',  'fa-stethoscope', 'var(--teal)',  'rgba(13,148,136,.1)','rgba(13,148,136,.2)','Doctor / Specialist', 'Individual practitioners, specialists and consultants.', '/providers/doctor/register.php'],
        ['clinic',  'fa-house-medical','var(--green)','rgba(5,150,105,.1)', 'rgba(5,150,105,.2)', 'Clinic / Hospital',   'Outpatient clinics, hospitals and diagnostic centers.', '/providers/clinic/register.php'],
        ['ambulance','fa-truck-medical','var(--red)',  'rgba(220,38,38,.1)', 'rgba(220,38,38,.2)', 'Ambulance Service',   'Emergency vehicle operators and dispatch services.',   '/providers/ambulance/register.php'],
      ] as [$k,$ic,$col,$bg,$bdr,$t,$d,$link]): ?>
      <a href="<?= $link ?>" style="display:block;background:var(--white);border:2px solid var(--slate-200);border-radius:20px;padding:32px 24px;text-decoration:none;text-align:center;transition:border-color .15s,box-shadow .15s;box-shadow:var(--shadow-sm)">
        <div style="width:64px;height:64px;border-radius:16px;background:<?= $bg ?>;color:<?= $col ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:28px;border:1px solid <?= $bdr ?>">
          <i class="fa-solid <?= $ic ?>"></i>
        </div>
        <h3 style="font-size:17px;font-weight:700;color:var(--slate-900);margin-bottom:8px"><?= $t ?></h3>
        <p style="font-size:13px;color:var(--slate-500);line-height:1.6;margin-bottom:18px"><?= $d ?></p>
        <span style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:<?= $col ?>">Register <i class="fa-solid fa-arrow-right" style="font-size:11px"></i></span>
      </a>
      <?php endforeach; ?>
    </div>
    <p style="text-align:center;margin-top:28px;font-size:14px;color:var(--slate-400)">Already registered? <a href="/providers/login.php" style="color:var(--primary);font-weight:600">Sign in here</a></p>
  </div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
