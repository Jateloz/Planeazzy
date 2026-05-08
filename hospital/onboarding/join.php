<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
Security::startSession();
if (!empty($_SESSION['hospital_id']) && !empty($_SESSION['hospital_auth'])) {
    header('Location: /hospital/onboarding/dashboard.php'); exit;
}
$cpStep = 1; $cpTitle = 'Join Clinical Precision';
include __DIR__ . '/_head.php';
?>
<style>
.lp-grid{display:grid;grid-template-columns:7fr 5fr;gap:56px;align-items:center;max-width:1280px;margin:0 auto;padding:88px 48px 120px}
.lp-bento-grid{display:grid;grid-template-columns:1fr 2fr;gap:20px}
.lp-bento-box{background:var(--cp-surface-container-lowest);padding:40px;border-radius:28px;border:1px solid rgba(193,198,213,.1)}
.lp-bento-wide{grid-column:span 2;display:flex;gap:32px;flex-wrap:wrap}
.lp-cta-vault{background:var(--cp-surface-container-low);border:2px dashed rgba(193,198,213,.35);border-radius:48px;padding:80px 40px;display:flex;flex-direction:column;align-items:center;text-align:center;max-width:900px;margin:0 auto}
.lp-stat-bar{display:flex;gap:40px;padding-top:36px;border-top:1px solid rgba(193,198,213,.2);margin-top:40px;flex-wrap:wrap}
@media(max-width:1024px){.lp-grid{grid-template-columns:1fr;padding:56px 24px 80px;gap:40px}}
@media(max-width:768px){.lp-bento-grid{grid-template-columns:1fr}.lp-bento-wide{grid-column:span 1}.lp-cta-vault{padding:48px 24px;border-radius:28px;section{padding:56px 24px !important}}}
</style>

<header class="cp-topnav">
  <div style="display:flex;align-items:center;gap:24px">
    <a href="/hospital/onboarding/join.php" class="cp-topnav-brand" data-en="Clinical Precision" data-sw="Usahihi wa Kliniki">Clinical Precision</a>
    <nav class="cp-topnav-nav">
      <a href="#overview" class="cp-topnav-link active" data-en="Overview" data-sw="Muhtasari">Overview</a>
      <a href="#compliance" class="cp-topnav-link" data-en="Compliance" data-sw="Utiifu">Compliance</a>
      <a href="#cta" class="cp-topnav-link" data-en="Pricing" data-sw="Bei">Pricing</a>
    </nav>
  </div>
  <div class="cp-topnav-actions">
    <button class="cp-lang-btn" id="langToggle"><span class="material-symbols-outlined" style="font-size:15px">language</span><span id="langLabel">SW</span></button>
    <a href="/hospital/onboarding/login.php" class="cp-btn cp-btn-ghost cp-btn-sm" data-en="Sign In" data-sw="Ingia">Sign In</a>
    <a href="/hospital/onboarding/signup.php" class="cp-btn cp-btn-primary cp-btn-sm" data-en="Get Started" data-sw="Anza">Get Started</a>
  </div>
</header>


<div style="max-width:960px;margin:12px auto;padding:0 24px"><a href="/" style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;color:#64748b;text-decoration:none;transition:color .15s" onmouseover="this.style.color='#1978e5'" onmouseout="this.style.color='#64748b'"><i class="fa-solid fa-arrow-left" style="font-size:11px"></i> Back to Homepage</a></div>
<main id="overview">
<section style="background:linear-gradient(180deg,#f7f9fb 0%,#f2f4f6 100%);position:relative;overflow:hidden">
  <div style="position:absolute;top:-100px;left:-100px;width:400px;height:400px;background:rgba(0,90,180,.05);border-radius:50%;filter:blur(100px);pointer-events:none"></div>
  <div style="position:absolute;bottom:-100px;right:-100px;width:500px;height:500px;background:rgba(0,106,106,.05);border-radius:50%;filter:blur(120px);pointer-events:none"></div>
  <div class="lp-grid">
    <div>
      <div class="cp-badge cp-badge-secondary" style="margin-bottom:28px">
        <span class="material-symbols-outlined msf" style="font-size:13px">verified</span>
        <span data-en="KMPDC &amp; KDPA COMPLIANT" data-sw="INAZINGATIA KMPDC &amp; KDPA">KMPDC &amp; KDPA COMPLIANT</span>
      </div>
      <h1 class="cp-display" style="margin-bottom:24px">
        <span data-en="Reach more patients." data-sw="Fikia wagonjwa zaidi.">Reach more patients.</span><br>
        <span style="color:var(--cp-primary)" data-en="Manage bookings easily." data-sw="Simamia miadi kwa urahisi.">Manage bookings easily.</span>
      </h1>
      <p class="cp-body-lg" style="margin-bottom:40px;max-width:520px"
         data-en="The Digital Sanctuary for Kenyan Healthcare. Be visible when it matters most with Clinical Precision's surgical efficiency."
         data-sw="Hifadhi ya Kidijitali kwa Afya ya Kenya. Onekana wakati unaohusika zaidi kwa ufanisi wa kisayansi wa Clinical Precision.">
        The Digital Sanctuary for Kenyan Healthcare. Be visible when it matters most with Clinical Precision's surgical efficiency.
      </p>
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:48px">
        <a href="/hospital/onboarding/signup.php" class="cp-btn cp-btn-primary cp-btn-lg" data-en="Get Started" data-sw="Anza">Get Started</a>
        <button class="cp-btn cp-btn-ghost cp-btn-lg" data-en="View Platform Demo" data-sw="Ona Demo ya Jukwaa">View Platform Demo</button>
      </div>
      <div class="lp-stat-bar">
        <?php foreach([['+124%','Avg. booking increase','Ongezeko la wastani la miadi'],['500+','Verified Facilities','Vituo Vilivyothibitishwa'],['47','Counties Active','Kaunti Zinazohudumika']] as [$n,$en,$sw]): ?>
        <div>
          <div style="font-size:1.625rem;font-weight:900;color:var(--cp-primary);letter-spacing:-.04em"><?=$n?></div>
          <div style="font-size:.75rem;color:var(--cp-on-surface-var);font-weight:600;margin-top:2px" data-en="<?=htmlspecialchars($en)?>" data-sw="<?=htmlspecialchars($sw)?>"><?=$en?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="position:relative">
      <div style="border-radius:3rem;overflow:hidden;transform:rotate(3deg);box-shadow:var(--cp-shadow-xl);aspect-ratio:1/1">
        <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=700&q=80" alt="Modern medical facility" style="width:100%;height:100%;object-fit:cover;opacity:.9">
      </div>
      <div class="cp-glass" style="position:absolute;bottom:-24px;left:-24px;padding:22px 24px;border-radius:22px;border:1px solid rgba(255,255,255,.5);box-shadow:var(--cp-shadow-lg);max-width:210px">
        <span class="material-symbols-outlined" style="color:var(--cp-primary);font-size:34px;display:block;margin-bottom:8px">calendar_today</span>
        <div style="font-size:1.5rem;font-weight:900;color:var(--cp-on-surface)">+124%</div>
        <p style="font-size:.8125rem;color:var(--cp-on-surface-var);line-height:1.5;margin-top:4px" data-en="Average booking increase for new facilities" data-sw="Ongezeko la wastani la miadi kwa vituo vipya">Average booking increase for new facilities</p>
      </div>
    </div>
  </div>
</section>

<section id="compliance" style="background:var(--cp-surface-container-low);padding:80px 48px">
  <div style="max-width:1280px;margin:0 auto">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:56px;flex-wrap:wrap;gap:24px">
      <div style="max-width:600px">
        <h2 class="cp-h2" style="margin-bottom:12px" data-en="A Legacy of Trust" data-sw="Urithi wa Uaminifu">A Legacy of Trust</h2>
        <p class="cp-body-lg" data-en="We provide an authoritative yet breathable interface that guides providers through complex workflows while maintaining absolute Kenyan regulatory compliance." data-sw="Tunatoa kiolesura cha mamlaka lakini kinachopumua kinachoongoza watoa huduma kupitia mtiririko wa kazi mgumu huku ukidumisha utiifu kamili wa udhibiti wa Kenya.">We provide an authoritative yet breathable interface that guides providers through complex workflows while maintaining absolute Kenyan regulatory compliance.</p>
      </div>
      <div style="display:flex;gap:12px">
        <?php foreach(['KMPDC','KDPA','KEPDA'] as $b): ?>
        <div style="height:56px;min-width:88px;background:var(--cp-surface-container-lowest);border-radius:12px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(193,198,213,.15);font-size:.75rem;font-weight:800;color:var(--cp-outline);letter-spacing:.14em"><?=$b?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="lp-bento-grid">
      <div class="lp-bento-box">
        <span class="material-symbols-outlined msf" style="color:var(--cp-secondary);font-size:36px;display:block;margin-bottom:18px">security</span>
        <h3 class="cp-h3" style="margin-bottom:12px" data-en="The Security Vault" data-sw="Vault ya Usalama">The Security Vault</h3>
        <p class="cp-body" data-en="KEPDA compliant data storage. Patient records protected by military-grade encryption and regional data sovereignty protocols." data-sw="Uhifadhi wa data unaozingatia KEPDA. Rekodi za wagonjwa zinalindwa na usimbuaji wa kiwango cha kijeshi.">KEPDA compliant data storage. Patient records protected by military-grade encryption and regional data sovereignty protocols.</p>
      </div>
      <div class="lp-bento-box" style="position:relative;overflow:hidden">
        <div style="position:relative;z-index:1">
          <span class="material-symbols-outlined" style="color:var(--cp-primary);font-size:36px;display:block;margin-bottom:18px">visibility</span>
          <h3 class="cp-h3" style="margin-bottom:12px" data-en="Hyper-Visibility Engine" data-sw="Injini ya Mwonekano wa Juu">Hyper-Visibility Engine</h3>
          <p class="cp-body" style="max-width:380px" data-en="Our algorithm ensures your facility appears at the top of local searches precisely when patients are seeking your specific medical expertise." data-sw="Algorithm yetu inahakikisha kituo chako kinaonekana juu ya utafutaji wa ndani.">Our algorithm ensures your facility appears at the top of local searches precisely when patients are seeking your specific medical expertise.</p>
        </div>
        <div style="position:absolute;top:0;right:0;height:100%;width:40%;background:linear-gradient(to left,rgba(144,239,239,.2),transparent);display:flex;align-items:center;justify-content:center">
          <span class="material-symbols-outlined" style="font-size:110px;color:var(--cp-secondary);opacity:.18">map</span>
        </div>
      </div>
      <div class="lp-bento-box lp-bento-wide">
        <div style="flex:1;min-width:220px">
          <span class="material-symbols-outlined" style="color:var(--cp-primary);font-size:36px;display:block;margin-bottom:18px">surgical</span>
          <h3 class="cp-h3" style="margin-bottom:12px" data-en="Clinical Precision UI" data-sw="Kiolesura cha Usahihi wa Kliniki">Clinical Precision UI</h3>
          <p class="cp-body" data-en="No more cluttered spreadsheets. Manage your entire facility from a single, editorial-style dashboard designed for surgical efficiency." data-sw="Hakuna tena lahajedwali zilizosongwa. Simamia kituo chako chote kutoka dashibodi moja ya mtindo wa uhariri.">No more cluttered spreadsheets. Manage your entire facility from a single, editorial-style dashboard designed for surgical efficiency.</p>
        </div>
        <div style="flex:1;min-width:220px;background:var(--cp-surface-container-low);border-radius:18px;padding:22px">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
            <div style="width:48px;height:3px;background:var(--cp-primary);border-radius:9999px"></div>
            <span style="font-size:.625rem;font-weight:800;color:var(--cp-primary);text-transform:uppercase;letter-spacing:.14em" data-en="STEP 01: FACILITY VERIFICATION" data-sw="HATUA 01: UTHIBITISHO WA KITUO">STEP 01: FACILITY VERIFICATION</span>
          </div>
          <div style="display:flex;flex-direction:column;gap:10px">
            <div style="height:10px;background:var(--cp-surface-container-highest);border-radius:9999px"></div>
            <div style="height:10px;background:var(--cp-surface-container-highest);border-radius:9999px;width:75%"></div>
            <div style="height:10px;background:var(--cp-surface-container-highest);border-radius:9999px;width:85%"></div>
          </div>
        </div>
      </div>
      <div style="background:linear-gradient(135deg,#005ab4,#0873df);border-radius:28px;padding:36px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:#fff">
        <div style="font-size:2.25rem;font-weight:900;margin-bottom:4px">24/7</div>
        <div style="font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.14em;opacity:.8;margin-bottom:20px" data-en="Support Access" data-sw="Ufikiaji wa Msaada">Support Access</div>
        <button style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);color:#fff;padding:10px 22px;border-radius:12px;font-family:inherit;font-size:.875rem;font-weight:700;cursor:pointer" data-en="Contact Support" data-sw="Wasiliana na Msaada">Contact Support</button>
      </div>
    </div>
  </div>
</section>

<section id="cta" style="padding:100px 48px">
  <div class="lp-cta-vault">
    <span class="material-symbols-outlined msf" style="font-size:64px;color:var(--cp-primary);margin-bottom:24px">add_business</span>
    <h2 class="cp-h2" style="margin-bottom:16px" data-en="Ready to modernize your practice?" data-sw="Uko tayari kuboresha mazoezi yako?">Ready to modernize your practice?</h2>
    <p class="cp-body-lg" style="margin-bottom:48px;max-width:480px" data-en="Join the network of elite Kenyan healthcare providers leveraging Clinical Precision today." data-sw="Jiunge na mtandao wa watoa huduma wa afya bora wa Kenya wanaotumia Clinical Precision leo.">Join the network of elite Kenyan healthcare providers leveraging Clinical Precision today.</p>
    <a href="/hospital/onboarding/signup.php" class="cp-btn cp-btn-primary cp-btn-xl cp-btn-round" data-en="Register Your Facility" data-sw="Sajili Kituo Chako">Register Your Facility</a>
    <div style="margin-top:20px;display:flex;align-items:center;gap:7px;font-size:.75rem;color:var(--cp-on-surface-var)">
      <span class="material-symbols-outlined msf" style="font-size:15px;color:var(--cp-secondary)">lock</span>
      <span data-en="Enterprise-grade security guaranteed" data-sw="Usalama wa kiwango cha biashara unahakikishiwa">Enterprise-grade security guaranteed</span>
    </div>
  </div>
</section>
</main>

<footer class="cp-footer">
  <span data-en="© 2025 Clinical Precision — Planeazzy. KEPDA Compliant." data-sw="© 2025 Usahihi wa Kliniki — Planeazzy. Inazingatia KEPDA.">© 2025 Clinical Precision — Planeazzy. KEPDA Compliant.</span>
  <div class="cp-footer-links">
    <a href="#" data-en="Privacy Policy" data-sw="Sera ya Faragha">Privacy Policy</a>
    <a href="#" data-en="Terms of Service" data-sw="Masharti ya Huduma">Terms of Service</a>
    <a href="#" data-en="Security Vault" data-sw="Vault ya Usalama">Security Vault</a>
  </div>
</footer>
<script src="/assets/js/app.js"></script>
<script>document.addEventListener('DOMContentLoaded',()=>{if(typeof Lang!=='undefined')Lang.init();document.getElementById('langToggle')?.addEventListener('click',()=>Lang.toggle());});</script>
</body></html>
