<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['provider_id'])) { header('Location: /providers/dashboard.php'); exit; }
$noSidebar = true; $pageTitle = 'Register Your Practice';
include dirname(__DIR__) . '/includes/header.php';
?>
<style>
.auth-wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:64px 20px;background:var(--slate-50)}
.reg-card{background:#fff;border-radius:20px;padding:48px 40px;box-shadow:0 12px 40px rgba(0,0,0,.08);width:100%;max-width:720px}
.ptype-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:24px}
.ptype-option{display:block;background:#fff;border:2px solid var(--slate-200);border-radius:18px;padding:28px 20px;text-decoration:none;text-align:center;transition:all .15s;box-shadow:var(--shadow-sm)}
.ptype-option:hover{border-color:var(--primary);box-shadow:0 8px 24px rgba(25,120,229,.1);transform:translateY(-2px)}
.ptype-option-icon{width:60px;height:60px;border-radius:15px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:26px}
@media(max-width:640px){.ptype-grid{grid-template-columns:1fr}.reg-card{padding:32px 20px}}
</style>
<main class="auth-wrap">
  <div class="reg-card">
    <div style="text-align:center;margin-bottom:8px">
      <span style="display:inline-block;padding:4px 16px;border-radius:9999px;background:var(--primary-10);color:var(--primary);font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px"
            data-en="Join Planeazzy's Provider Network" data-sw="Jiunge na Mtandao wa Watoa Huduma wa Planeazzy">Join Planeazzy's Provider Network</span>
      <h2 style="font-size:clamp(1.5rem,4vw,2rem);font-weight:900;color:var(--slate-900);letter-spacing:-.03em;margin-bottom:10px"
          data-en="Choose Your Portal" data-sw="Chagua Lango Lako">Choose Your Portal</h2>
      <p style="font-size:1rem;color:var(--slate-500)"
         data-en="Select the type of healthcare practice you want to register."
         data-sw="Chagua aina ya mazoezi ya afya unayotaka kusajili.">
        Select the type of healthcare practice you want to register.
      </p>
    </div>

    <div class="ptype-grid">
      <?php foreach([
        ['doctor',  'fa-stethoscope', 'var(--teal)',  'rgba(13,148,136,.1)', 'rgba(13,148,136,.2)',
         'Doctor / Specialist',  'Daktari / Mtaalamu',
         'Individual practitioners, specialists and consultants.',
         'Madaktari binafsi, wataalamu na washauri.',
         '/providers/doctor/register.php', 'Register as Doctor','Jisajili kama Daktari'],
        ['clinic',  'fa-house-medical','var(--green)','rgba(5,150,105,.1)',  'rgba(5,150,105,.2)',
         'Clinic / Hospital',   'Kliniki / Hospitali',
         'Outpatient clinics, hospitals and diagnostic centers.',
         'Kliniki za wagonjwa wa nje, hospitali na vituo vya uchunguzi.',
         '/providers/clinic/register.php', 'Register Clinic','Jisajili Kliniki'],
        ['ambulance','fa-truck-medical','var(--red)',  'rgba(220,38,38,.1)',  'rgba(220,38,38,.2)',
         'Ambulance Service',   'Huduma ya Ambulensi',
         'Emergency vehicle operators and dispatch services.',
         'Waendeshaji wa magari ya dharura na huduma za kutuma.',
         '/providers/ambulance/register.php','Register Service','Jisajili Huduma'],
      ] as [$k,$ic,$col,$bg,$bdr,$en,$sw,$desc,$descSw,$link,$cta,$ctaSw]): ?>
      <a href="<?=$link?>" class="ptype-option">
        <div class="ptype-option-icon" style="background:<?=$bg?>;color:<?=$col?>;border:1px solid <?=$bdr?>">
          <i class="fa-solid <?=$ic?>"></i>
        </div>
        <h3 style="font-size:1rem;font-weight:700;color:var(--slate-900);margin-bottom:8px"
            data-en="<?=htmlspecialchars($en)?>" data-sw="<?=htmlspecialchars($sw)?>"><?=$en?></h3>
        <p style="font-size:.8125rem;color:var(--slate-500);line-height:1.6;margin-bottom:16px"
           data-en="<?=htmlspecialchars($desc)?>" data-sw="<?=htmlspecialchars($descSw)?>"><?=$desc?></p>
        <span style="display:inline-flex;align-items:center;gap:5px;font-size:.8125rem;font-weight:700;color:<?=$col?>"
              data-en="<?=htmlspecialchars($cta)?> →" data-sw="<?=htmlspecialchars($ctaSw)?> →"><?=$cta?> →</span>
      </a>
      <?php endforeach; ?>
    </div>

    <p style="text-align:center;margin-top:24px;font-size:.875rem;color:var(--slate-400)">
      <span data-en="Already registered?" data-sw="Umesajiliwa tayari?">Already registered?</span>
      <a href="/providers/login.php" style="color:var(--primary);font-weight:600"
         data-en="Sign in here →" data-sw="Ingia hapa →">Sign in here →</a>
    </p>
  </div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
