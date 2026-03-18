<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$pageTitle = 'Welcome — Your Direct Path to Better Healthcare';
$noSidebar = true;
include __DIR__ . '/includes/header.php';
?>

<!-- ══════════════════════════════════════════════════
     SECTION 1: HERO
══════════════════════════════════════════════════ -->
<section class="hero-section">
  <div class="hero-bg"></div>
  <div class="hero-mesh"></div>
  <div class="hero-grid-lines"></div>
  <div class="hero-content">
    <!-- LEFT -->
    <div class="hero-left">
      <div class="hero-pill">
        <span class="material-symbols-outlined">verified</span>
        <span data-en="Kenya's Trusted Healthcare Platform" data-sw="Jukwaa la Afya Linaloaminiwa Kenya">Kenya's Trusted Healthcare Platform</span>
      </div>
      <h1 class="hero-title">
        <span data-en="Your Direct Path to" data-sw="Njia Yako ya Moja kwa">Your Direct Path to</span><br>
        <span class="hl" data-en="Better Healthcare" data-sw="Huduma Bora za Afya">Better Healthcare</span>
      </h1>
      <p class="hero-sub"
         data-en="Find doctors, hospitals, and clinics near you. Book appointments instantly, request ambulance services, or consult a doctor via video — all from one secure platform."
         data-sw="Pata madaktari, hospitali, na kliniki karibu nawe. Weka miadi papo hapo, omba gari la wagonjwa, au shauriana na daktari kwa video.">
        Find doctors, hospitals, and clinics near you. Book appointments instantly, request ambulance services, or consult a doctor via video — all from one secure platform.
      </p>
      <div class="hero-actions">
        <a href="/patients/register.php" class="btn btn-primary btn-lg">
          <span data-en="Get Started Free" data-sw="Anza Bure">Get Started Free</span>
          <svg class="btn-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
        <a href="#how-it-works" class="btn btn-white btn-lg">
          <span class="material-symbols-outlined" style="font-size:19px">play_circle</span>
          <span data-en="How it works" data-sw="Jinsi Inavyofanya">How it works</span>
        </a>
      </div>
      <div class="hero-trust">
        <div class="trust-item"><span class="trust-dot"></span><span data-en="HIPAA Compliant" data-sw="Inazingatia HIPAA">HIPAA Compliant</span></div>
        <div class="trust-item"><span class="trust-dot"></span><span data-en="256-bit Encrypted" data-sw="Usimbaji Biti 256">256-bit Encrypted</span></div>
        <div class="trust-item"><span class="trust-dot"></span><span data-en="24/7 Emergency" data-sw="Dharura 24/7">24/7 Emergency</span></div>
        <div class="trust-item"><span class="trust-dot"></span><span data-en="10,000+ Providers" data-sw="Watoa 10,000+">10,000+ Providers</span></div>
      </div>
    </div>

    <!-- RIGHT: Visual cards -->
    <div class="hero-right">
      <div class="hero-img-card">
        <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=700&q=85" alt="Healthcare professional" loading="eager">
      </div>
      <div class="hero-float-card fc1">
        <div class="fc-icon"><span class="material-symbols-outlined">check_circle</span></div>
        <div class="fc-txt">
          <strong data-en="Doctor Available" data-sw="Daktari Anapatikana">Doctor Available</strong>
          <span data-en="Confirmed appointment" data-sw="Miadi imethibitishwa">Confirmed appointment</span>
        </div>
      </div>
      <div class="hero-float-card fc2">
        <div class="fc-icon blue"><span class="material-symbols-outlined">local_hospital</span></div>
        <div class="fc-txt">
          <strong data-en="Nairobi Hospital" data-sw="Hospitali ya Nairobi">Nairobi Hospital</strong>
          <span data-en="3 km away · Open" data-sw="3 km · Imefunguliwa">3 km away · Open</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Scroll indicator -->
  <div style="position:absolute;bottom:30px;left:50%;transform:translateX(-50%);z-index:2;animation:float 2s ease-in-out infinite">
    <a href="#services" style="color:rgba(255,255,255,.4);display:flex;flex-direction:column;align-items:center;gap:6px;text-decoration:none;font-size:11px;font-family:var(--ff-display);font-weight:700;text-transform:uppercase;letter-spacing:.8px">
      <span data-en="Explore" data-sw="Chunguza">Explore</span>
      <span class="material-symbols-outlined" style="font-size:22px">keyboard_arrow_down</span>
    </a>
  </div>
</section>

<!-- ══════════════════════════════════════════════════
     SECTION 2: 4 SERVICES (Healthcare / Doctors / Clinics / Ambulance)
══════════════════════════════════════════════════ -->
<section class="services-section" id="services">
  <div class="section-inner">
    <div class="reveal">
      <div class="section-eyebrow"><span class="material-symbols-outlined">grid_view</span><span data-en="Our Services" data-sw="Huduma Zetu">Our Services</span></div>
      <h2 class="section-title" data-en="Everything Healthcare, in One Place" data-sw="Kila Kitu cha Afya, Mahali Pamoja">Everything Healthcare, in One Place</h2>
      <p class="section-sub"
         data-en="Whether you need to see a doctor, find a clinic, book a hospital, or call an ambulance — Planeazzy connects you instantly."
         data-sw="Iwapo unahitaji kuona daktari, kupata kliniki, kupanga hospitali, au kuita gari la wagonjwa — Planeazzy inakuunganisha papo hapo.">
        Whether you need to see a doctor, find a clinic, book a hospital, or call an ambulance — Planeazzy connects you instantly.
      </p>
    </div>

    <div class="services-grid">
      <!-- Healthcare -->
      <a href="/patients/register.php" class="service-card reveal">
        <div class="sc-icon healthcare"><span class="material-symbols-outlined">medical_information</span></div>
        <div class="sc-title" data-en="Planeazzy for Healthcare" data-sw="Planeazzy kwa Afya">Planeazzy for Healthcare</div>
        <div class="sc-desc"
             data-en="Comprehensive digital health management. Access your records, track your history, and get personalised health insights anytime."
             data-sw="Usimamizi wa afya ya kidijitali. Fikia rekodi zako, fuatilia historia yako, na upate maarifa ya afya ya kibinafsi wakati wowote.">
          Comprehensive digital health management. Access your records, track your history, and get personalised health insights anytime.
        </div>
        <div class="sc-link"><span data-en="Learn more" data-sw="Jifunze zaidi">Learn more</span><span class="material-symbols-outlined">arrow_forward</span></div>
      </a>

      <!-- Doctors -->
      <a href="/patients/register.php" class="service-card reveal">
        <div class="sc-icon doctors"><span class="material-symbols-outlined">stethoscope</span></div>
        <div class="sc-title" data-en="Planeazzy for Doctors" data-sw="Planeazzy kwa Madaktari">Planeazzy for Doctors</div>
        <div class="sc-desc"
             data-en="Find and book verified specialist doctors. See their profiles, patient reviews, and available slots — all in real-time."
             data-sw="Pata na uweke miadi na madaktari wataalamu waliothibitishwa. Ona wasifu wao, maoni ya wagonjwa, na nafasi zinazopatikana.">
          Find and book verified specialist doctors. See their profiles, patient reviews, and available slots — all in real-time.
        </div>
        <div class="sc-link"><span data-en="Find a doctor" data-sw="Pata daktari">Find a doctor</span><span class="material-symbols-outlined">arrow_forward</span></div>
      </a>

      <!-- Clinics -->
      <a href="/patients/register.php" class="service-card reveal">
        <div class="sc-icon clinics"><span class="material-symbols-outlined">local_pharmacy</span></div>
        <div class="sc-title" data-en="Planeazzy for Clinics" data-sw="Planeazzy kwa Kliniki">Planeazzy for Clinics</div>
        <div class="sc-desc"
             data-en="Browse nearby clinics and hospitals, compare services and prices, check availability, and book your visit in seconds."
             data-sw="Vinjari kliniki na hospitali zilizo karibu, linganisha huduma na bei, angalia upatikanaji, na panga ziara yako kwa sekunde.">
          Browse nearby clinics and hospitals, compare services and prices, check availability, and book your visit in seconds.
        </div>
        <div class="sc-link"><span data-en="Find clinics" data-sw="Pata kliniki">Find clinics</span><span class="material-symbols-outlined">arrow_forward</span></div>
      </a>

      <!-- Ambulance -->
      <a href="/patients/register.php" class="service-card reveal">
        <div class="sc-icon ambulance"><span class="material-symbols-outlined">emergency</span></div>
        <div class="sc-title" data-en="Planeazzy for Ambulance" data-sw="Planeazzy kwa Ambulansi">Planeazzy for Ambulance</div>
        <div class="sc-desc"
             data-en="Request emergency medical services with one tap. Real-time ambulance tracking, nearest responder dispatch, and direct hospital alerts."
             data-sw="Omba huduma za dharura kwa kubonyeza mara moja. Ufuatiliaji wa ambulansi, utumaji wa mjibu aliye karibu, na tahadhari za hospitali moja kwa moja.">
          Request emergency medical services with one tap. Real-time ambulance tracking, nearest responder dispatch, and direct hospital alerts.
        </div>
        <div class="sc-link"><span data-en="Emergency services" data-sw="Huduma za dharura">Emergency services</span><span class="material-symbols-outlined">arrow_forward</span></div>
      </a>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════
     SECTION 3: STATS BAND
══════════════════════════════════════════════════ -->
<section class="stats-section">
  <div class="stats-grid">
    <div class="stat-item reveal">
      <div class="stat-num" data-count="10000" data-suffix="+">0+</div>
      <div class="stat-lbl" data-en="Registered Providers" data-sw="Watoa Huduma Waliojisajili">Registered Providers</div>
    </div>
    <div class="stat-item reveal">
      <div class="stat-num" data-count="250000" data-suffix="+">0+</div>
      <div class="stat-lbl" data-en="Patient Appointments" data-sw="Miadi ya Wagonjwa">Patient Appointments</div>
    </div>
    <div class="stat-item reveal">
      <div class="stat-num" data-count="47" data-suffix="">0</div>
      <div class="stat-lbl" data-en="Counties Covered" data-sw="Kaunti Zilizoshughulikiwa">Counties Covered</div>
    </div>
    <div class="stat-item reveal">
      <div class="stat-num" data-count="98" data-suffix="%">0%</div>
      <div class="stat-lbl" data-en="Patient Satisfaction" data-sw="Kuridhika kwa Wagonjwa">Patient Satisfaction</div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════
     SECTION 4: HOW IT WORKS
══════════════════════════════════════════════════ -->
<section class="how-section" id="how-it-works">
  <div class="section-inner" style="max-width:1100px;margin:0 auto">
    <div class="reveal">
      <div class="section-eyebrow"><span class="material-symbols-outlined">lightbulb</span><span data-en="How It Works" data-sw="Jinsi Inavyofanya Kazi">How It Works</span></div>
      <h2 class="section-title" data-en="Get Healthcare in 3 Simple Steps" data-sw="Pata Huduma za Afya kwa Hatua 3 Rahisi">Get Healthcare in 3 Simple Steps</h2>
      <p class="section-sub"
         data-en="From signing up to seeing a doctor — the entire process takes less than 5 minutes."
         data-sw="Kuanzia kusajili hadi kuona daktari — mchakato wote huchukua chini ya dakika 5.">
        From signing up to seeing a doctor — the entire process takes less than 5 minutes.
      </p>
    </div>
    <div class="steps-grid">
      <div class="step-card reveal">
        <div class="step-num">1</div>
        <div class="step-title" data-en="Create Your Account" data-sw="Fungua Akaunti Yako">Create Your Account</div>
        <p class="step-desc"
           data-en="Sign up in under 2 minutes. Verify your email, choose your preferred healthcare service — Healthcare, Doctor, Clinic, or Ambulance — and you're ready."
           data-sw="Jisajili kwa chini ya dakika 2. Thibitisha barua pepe yako, chagua huduma unayoipenda — Afya, Daktari, Kliniki, au Ambulansi — nawe uko tayari.">
          Sign up in under 2 minutes. Verify your email, choose your preferred healthcare service — Healthcare, Doctor, Clinic, or Ambulance — and you're ready.
        </p>
      </div>
      <div class="step-card reveal">
        <div class="step-num">2</div>
        <div class="step-title" data-en="Find Your Provider" data-sw="Pata Mtoa Huduma Wako">Find Your Provider</div>
        <p class="step-desc"
           data-en="Search by specialty, location, or availability. Read verified reviews, compare prices, and choose the right doctor or clinic for your needs."
           data-sw="Tafuta kwa utaalam, eneo, au upatikanaji. Soma maoni yaliyothibitishwa, linganisha bei, na chagua daktari au kliniki inayofaa.">
          Search by specialty, location, or availability. Read verified reviews, compare prices, and choose the right doctor or clinic for your needs.
        </p>
      </div>
      <div class="step-card reveal">
        <div class="step-num">3</div>
        <div class="step-title" data-en="Get Care Instantly" data-sw="Pata Huduma Papo Hapo">Get Care Instantly</div>
        <p class="step-desc"
           data-en="Book in-person appointments, start a video consultation, order lab tests, or request emergency services — all within seconds from your dashboard."
           data-sw="Weka miadi ya ana kwa ana, anza mashauriano ya video, agiza majaribio ya maabara, au omba huduma za dharura — yote kwa sekunde kutoka kwenye dashibodi yako.">
          Book in-person appointments, start a video consultation, order lab tests, or request emergency services — all within seconds from your dashboard.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════
     SECTION 5: TESTIMONIALS
══════════════════════════════════════════════════ -->
<section class="testimonials-section">
  <div class="section-inner">
    <div class="reveal">
      <div class="section-eyebrow"><span class="material-symbols-outlined">reviews</span><span data-en="Testimonials" data-sw="Maoni">Testimonials</span></div>
      <h2 class="section-title" data-en="Trusted by Thousands of Kenyans" data-sw="Inaaminika na Wakenya Maelfu">Trusted by Thousands of Kenyans</h2>
      <p class="section-sub"
         data-en="Real stories from patients who found better healthcare with Planeazzy."
         data-sw="Hadithi za kweli kutoka kwa wagonjwa waliopata huduma bora za afya na Planeazzy.">
        Real stories from patients who found better healthcare with Planeazzy.
      </p>
    </div>
    <div class="testimonials-grid">
      <div class="testi-card reveal">
        <div class="testi-stars">
          <?php for($i=0;$i<5;$i++) echo '<span class="material-symbols-outlined">star</span>'; ?>
        </div>
        <p class="testi-quote">"I found a specialist and booked an appointment in less than 3 minutes. The process was incredibly smooth — I couldn't believe how easy it was."</p>
        <div class="testi-author">
          <div class="testi-avatar">AM</div>
          <div><div class="testi-name">Amina Mwangi</div><div class="testi-role">Nairobi · Patient since 2024</div></div>
        </div>
      </div>
      <div class="testi-card reveal">
        <div class="testi-stars">
          <?php for($i=0;$i<5;$i++) echo '<span class="material-symbols-outlined">star</span>'; ?>
        </div>
        <p class="testi-quote">"When my father had an emergency, I used the ambulance feature. An ambulance arrived in under 10 minutes. Planeazzy literally saved his life."</p>
        <div class="testi-author">
          <div class="testi-avatar">DK</div>
          <div><div class="testi-name">David Kamau</div><div class="testi-role">Mombasa · Patient since 2023</div></div>
        </div>
      </div>
      <div class="testi-card reveal">
        <div class="testi-stars">
          <?php for($i=0;$i<5;$i++) echo '<span class="material-symbols-outlined">star</span>'; ?>
        </div>
        <p class="testi-quote">"The telehealth feature is phenomenal. I consulted a doctor from my village without traveling to Nairobi. This is the future of healthcare in Kenya."</p>
        <div class="testi-author">
          <div class="testi-avatar">FO</div>
          <div><div class="testi-name">Faith Otieno</div><div class="testi-role">Kisumu · Patient since 2024</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════
     SECTION 6: FAQ
══════════════════════════════════════════════════ -->
<section class="faq-section" id="faq">
  <div class="section-inner" style="max-width:1100px;margin:0 auto">
    <div class="reveal">
      <div class="section-eyebrow"><span class="material-symbols-outlined">help</span><span data-en="FAQ" data-sw="Maswali">FAQ</span></div>
      <h2 class="section-title" data-en="Frequently Asked Questions" data-sw="Maswali Yanayoulizwa Mara Kwa Mara">Frequently Asked Questions</h2>
    </div>
    <div class="faq-grid">
      <?php
      $faqs = [
        ['Is Planeazzy free to use?','Creating a patient account on Planeazzy is completely free. Some specialist consultations or premium services may have fees set by the individual providers.'],
        ['How do I find a doctor near me?','After creating your account, use the "Find a Doctor" feature to search by your location, specialty, or doctor name. Results show availability and verified reviews.'],
        ['Is my medical data secure?','Yes. All your data is encrypted with 256-bit TLS. We are HIPAA compliant and never share your medical information with third parties without your consent.'],
        ['Can I use Planeazzy in an emergency?','Absolutely. Our ambulance feature lets you request emergency services with one tap. Real-time tracking shows the ambulance location as it heads to you.'],
        ['What is telehealth and how does it work?','Telehealth lets you consult with a verified doctor via secure video call from your phone or computer. You can receive prescriptions and referrals digitally.'],
        ['Which counties does Planeazzy cover?','Planeazzy currently covers all 47 counties in Kenya, with over 10,000 registered healthcare providers nationwide and growing every week.'],
      ];
      foreach ($faqs as $i => [$q, $a]): ?>
      <div class="faq-item reveal">
        <div class="faq-q">
          <span><?= htmlspecialchars($q) ?></span>
          <span class="material-symbols-outlined">keyboard_arrow_down</span>
        </div>
        <div class="faq-a"><?= htmlspecialchars($a) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════
     SECTION 7: CTA
══════════════════════════════════════════════════ -->
<section class="cta-section">
  <div class="cta-inner reveal">
    <h2 class="cta-title" data-en="Ready to take control of your health?" data-sw="Uko tayari kusimamia afya yako?">Ready to take control of your health?</h2>
    <p class="cta-sub"
       data-en="Join over 250,000 Kenyans who trust Planeazzy for their healthcare needs. Sign up in 2 minutes — it's completely free."
       data-sw="Jiunge na Wakenya zaidi ya 250,000 wanaomwamini Planeazzy kwa mahitaji yao ya afya. Jisajili kwa dakika 2 — ni bure kabisa.">
      Join over 250,000 Kenyans who trust Planeazzy for their healthcare needs. Sign up in 2 minutes — it's completely free.
    </p>
    <div class="cta-btns">
      <a href="/patients/register.php" class="btn btn-white btn-lg">
        <span data-en="Create Free Account" data-sw="Fungua Akaunti ya Bure">Create Free Account</span>
        <svg class="btn-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
      <a href="/partner-login.php" class="btn btn-outline btn-lg" style="border-color:rgba(255,255,255,.4);color:#fff;">
        <span data-en="For Healthcare Providers" data-sw="Kwa Watoa Huduma">For Healthcare Providers</span>
      </a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
