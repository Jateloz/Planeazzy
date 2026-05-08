<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::startSession();
$noSidebar = true;
$pageTitle  = 'Account Ready — Welcome to Planeazzy!';
include dirname(__DIR__) . '/includes/header.php';
$name = htmlspecialchars($_SESSION['patient_name'] ?? 'there');
$csrf = Security::csrfToken();
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--bg-light)">
  <div style="width:100%;max-width:600px;text-align:center" class="slide-up">
    <!-- Success ring -->
    <div style="width:80px;height:80px;border-radius:50%;background:rgba(22,163,74,.1);color:var(--green);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:36px;border:3px solid rgba(22,163,74,.2)">
      <i class="fa-solid fa-circle-check"></i>
    </div>
    <h2 style="font-size:30px;font-weight:900;color:var(--slate-900);letter-spacing:-.03em;margin-bottom:12px">
      <span data-en="You're all set," data-sw="Umeweka vizuri,">You're all set,</span> <?= $name ?>! 
    </h2>
    <p style="font-size:15px;color:var(--slate-500);line-height:1.8;margin-bottom:28px;max-width:480px;margin-left:auto;margin-right:auto">
      Your Planeazzy account is verified and ready. A welcome email has been sent to your inbox.
      You can now book appointments, find doctors, and access emergency services across Kenya.
    </p>

    <!-- Feature chips -->
    <div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-bottom:32px">
      <?php foreach ([
        ['fa-stethoscope','Find Doctors'],
        ['fa-video',      'Telehealth'],
        ['fa-truck-medical','Ambulance'],
        ['fa-shield',     'Insurance'],
      ] as [$ic,$lb]): ?>
      <span style="display:flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9999px;background:var(--white);border:1.5px solid var(--slate-200);font-size:13px;font-weight:700;color:var(--slate-600)">
        <i class="fa-solid <?= $ic ?>" style="color:var(--primary)"></i><?= $lb ?>
      </span>
      <?php endforeach; ?>
    </div>

    <!--  CONSENT SECTION  -->
    <div style="background:var(--white);border:1px solid var(--slate-200);border-radius:16px;padding:28px 32px;text-align:left;margin-bottom:28px;box-shadow:0 4px 6px -1px rgba(0,0,0,.07)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
        <div style="width:36px;height:36px;background:var(--primary-10);border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:16px;flex-shrink:0">
          <i class="fa-solid fa-user-shield"></i>
        </div>
        <h3 style="font-size:16px;font-weight:700;color:var(--slate-900)">Your Privacy &amp; Data Consent</h3>
      </div>
      <p style="font-size:13px;color:var(--slate-500);line-height:1.65;margin-bottom:20px;margin-top:8px">
        Planeazzy takes your privacy seriously. Before you start, please review how your data is used.
        You can change these preferences anytime in <strong>Settings → Privacy</strong>.
      </p>
      <div id="consentAlert" class="alert hidden" style="margin-bottom:14px"></div>

      <?php
      $consentItems = [
        ['data_sharing',      true,  'fa-share-nodes',   'Share booking data with providers',
          'When you book an appointment, your name, contact info, and visit reason are shared with the provider so they can prepare for your visit. <strong>Required for bookings to work.</strong>'],
        ['insurance_sharing', true,  'fa-shield',        'Auto-share insurance documents',
          'If you upload an insurance document, it will automatically be sent to the provider when you book, so they can verify your coverage in advance.'],
        ['marketing',         false, 'fa-envelope',      'Receive health tips &amp; offers',
          'Occasional emails with health tips, platform updates and relevant offers. You can unsubscribe at any time.'],
        ['telehealth',        false, 'fa-video',         'Store telehealth session notes',
          'Allow notes from your video consultations to be saved to your health record for future reference.'],
        ['research',          false, 'fa-flask',         'Contribute to anonymised research',
          'Help improve healthcare in Kenya by sharing anonymised, aggregated data. Your personal information is <strong>never</strong> shared.'],
      ];
      foreach ($consentItems as [$key, $default, $icon, $label, $desc]):
      ?>
      <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 0;border-bottom:1px solid var(--slate-100)" id="consent-row-<?= $key ?>">
        <div style="width:38px;height:38px;background:var(--primary-10);border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:15px;flex-shrink:0;margin-top:2px">
          <i class="fa-solid <?= $icon ?>"></i>
        </div>
        <div style="flex:1">
          <div style="font-size:14px;font-weight:700;color:var(--slate-900);margin-bottom:3px"><?= $label ?></div>
          <div style="font-size:12px;color:var(--slate-500);line-height:1.6"><?= $desc ?></div>
        </div>
        <label style="flex-shrink:0;position:relative;width:44px;height:24px;cursor:pointer;display:inline-flex;margin-top:4px">
          <input type="checkbox" class="consent-toggle-ar" data-key="<?= $key ?>"
                 style="opacity:0;width:0;height:0" <?= $default?'checked':'' ?>>
          <div class="toggle-track" style="position:absolute;inset:0;border-radius:9999px;background:<?= $default?'var(--green)':'var(--slate-200)' ?>;transition:background .2s" id="tr-<?= $key ?>"></div>
          <div style="position:absolute;top:2px;left:<?= $default?'22':'2' ?>px;width:20px;height:20px;border-radius:50%;background:#fff;transition:left .2s;box-shadow:0 1px 4px rgba(0,0,0,.2)" id="th-<?= $key ?>"></div>
        </label>
      </div>
      <?php endforeach; ?>

      <p style="font-size:12px;color:var(--slate-400);margin-top:14px;line-height:1.6">
        By using Planeazzy you agree to our
        <a href="#" style="color:var(--primary)">Privacy Policy</a> and
        <a href="#" style="color:var(--primary)">Terms of Service</a>.
        Your data is protected under the <strong>Kenya Data Protection Act 2019</strong>.
      </p>
    </div>

    <!-- Action buttons -->
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="/patients/dashboard.php" class="btn btn-primary btn-lg" id="continueBtn">
        <i class="fa-solid fa-gauge"></i> Go to Dashboard
      </a>
      <a href="/patients/search.php" class="btn btn-ghost btn-lg">
        <i class="fa-solid fa-magnifying-glass"></i> Find a Doctor
      </a>
    </div>
  </div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
<script>
const csrf = document.getElementById('csrfToken')?.value || '';

// Real-time consent toggles on the onboarding page
document.querySelectorAll('.consent-toggle-ar').forEach(chk => {
  const key   = chk.dataset.key;
  const track = document.getElementById('tr-' + key);
  const thumb = document.getElementById('th-' + key);

  chk.addEventListener('change', async () => {
    const granted = chk.checked;
    // Animate toggle
    if (track) track.style.background = granted ? 'var(--green)' : 'var(--slate-200)';
    if (thumb) thumb.style.left = granted ? '22px' : '2px';

    try {
      const res = await fetch('/api/patient/update-consent.php', {
        method:  'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body:    JSON.stringify({consent_type:key,granted,csrf_token:csrf}),
        credentials: 'same-origin',
      });
      const r = await res.json();
      if (!r.success) {
        // Revert toggle on failure
        chk.checked = !granted;
        if (track) track.style.background = !granted ? 'var(--green)' : 'var(--slate-200)';
        if (thumb) thumb.style.left = !granted ? '22px' : '2px';
        console.error('Consent update failed:', r.message);
      }
    } catch(e) {
      console.error('Consent request failed', e);
    }
  });
});
</script>
