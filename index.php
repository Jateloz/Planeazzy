<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true;
$pageTitle  = 'Your Direct Path to Better Healthcare in Kenya';
include __DIR__ . '/includes/header.php';
?>

<!-- ── HERO ─────────────────────────────────────────── -->
<section class="hero-section reveal">
  <div class="hero-inner">
    <div>
      <span class="hero-badge">Quality care in Kenya</span>
      <h2 class="hero-title">Your Direct Path to <span class="highlight">Better Healthcare</span></h2>
      <p class="hero-desc">Find the right care effortlessly. Book appointments with top Kenyan specialists and enjoy transparent pricing without surprises.</p>
      <!-- Integrated Search Bar -->
      <div class="hero-search">
        <div class="hero-search-field">
          <i class="fa-solid fa-location-dot"></i>
          <input type="text" id="sLoc" placeholder="Nairobi, Mombasa...">
        </div>
        <div class="hero-search-field">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="sQuery" placeholder="Specialty or Doctor">
        </div>
        <div class="hero-search-field" style="min-width:150px">
          <i class="fa-solid fa-calendar"></i>
          <select id="sType">
            <option value="in_person">In-person</option>
            <option value="telehealth">Telehealth</option>
          </select>
        </div>
        <button class="hero-search-btn" onclick="doSearch()" title="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
      </div>
    </div>
    <div class="hero-visual">
      <div class="blob-teal"></div>
      <div class="blob-blue"></div>
      <img class="hero-img"
        src="https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=700&q=80"
        alt="Kenyan doctor smiling at patient" loading="lazy">
      <div class="hero-badge-card">
        <div class="hero-badge-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div>
          <div class="hero-badge-label">Vetted Specialists</div>
          <div class="hero-badge-value">2,500+ Doctors</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── PAYMENT CHOICE ─────────────────────────────── -->
<section class="payment-section reveal">
  <div class="payment-inner">
    <h3>Choose Your Payment Method</h3>
    <p>We support diverse payment options to ensure healthcare is accessible to everyone.</p>
    <div class="payment-grid">
      <div class="payment-card pc-insurance" onclick="location.href='/patients/register.php'">
        <div class="payment-card-icon"><i class="fa-solid fa-shield-heart"></i></div>
        <h4>Use Insurance</h4>
        <p>Select your insurance provider during booking to see covered benefits and co-pays instantly.</p>
        <span class="payment-card-link">Explore Providers <i class="fa-solid fa-arrow-right"></i></span>
      </div>
      <div class="payment-card pc-facility" onclick="location.href='/patients/register.php'">
        <div class="payment-card-icon"><i class="fa-solid fa-credit-card"></i></div>
        <h4>Pay at Facility</h4>
        <p>Book now and pay directly at the hospital using M-Pesa, card, or cash — no upfront costs.</p>
        <span class="payment-card-link">Transparent Pricing <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ──────────────────────────────── -->
<section class="how-section reveal">
  <div style="max-width:1280px;margin:0 auto">
    <h3>How it Works</h3>
    <div class="how-underline"></div>
    <div class="how-grid">
      <div class="how-connector"></div>
      <?php foreach([
        [true,  'fa-magnifying-glass', '1. Search',      'Find doctors by specialty, location, or name.'],
        [false, 'fa-clock',            '2. Choose Time', 'Pick a slot that fits your busy schedule.'],
        [false, 'fa-circle-check',     '3. Confirm',     'Secure your booking instantly via SMS.'],
        [false, 'fa-location-arrow',   '4. Visit',       'Go for your checkup or join via video call.'],
      ] as [$active, $icon, $title, $desc]): ?>
      <div class="how-step">
        <div class="how-step-icon <?= $active ? 'active' : 'inactive' ?>"><i class="fa-solid <?= $icon ?>"></i></div>
        <h5><?= $title ?></h5>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── BROWSE BY SPECIALTY ───────────────────────── -->
<section class="specialty-section reveal">
  <div class="specialty-inner">
    <div class="specialty-header">
      <h3>Browse by Specialty</h3>
      <a href="/patients/search.php">View All <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="specialty-grid">
      <?php foreach([
        ['fa-stethoscope','General Physician'],['fa-tooth','Dentist'],
        ['fa-baby',       'Pediatrics'],       ['fa-venus','Gynecology'],
        ['fa-heart-pulse','Cardiology'],        ['fa-brain','Psychiatry'],
      ] as [$icon, $name]): ?>
      <div class="specialty-card" onclick="location.href='/patients/search.php?q=<?= urlencode($name) ?>'">
        <i class="fa-solid <?= $icon ?>"></i>
        <span><?= $name ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── TOP HOSPITALS ────────────────────────────── -->
<section class="hospitals-section reveal">
  <div class="hospitals-inner">
    <h3>Top-Rated Medical Centers in Kenya</h3>
    <div class="hospitals-grid">
      <?php foreach([
        ['Aga Khan University Hospital','Parklands, Nairobi','4.9',['24/7 ER','Specialized Surgery'],
          'https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=600&q=70'],
        ['The Nairobi Hospital','Argwings Kodhek Rd, Nairobi','4.7',['Diagnostic Center','Maternity'],
          'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=600&q=70'],
        ['MP Shah Hospital','Shivachi Rd, Nairobi','4.8',['Critical Care','Cancer Care'],
          'https://images.unsplash.com/photo-1551076805-e1869033e561?w=600&q=70'],
      ] as [$name,$loc,$rating,$tags,$img]): ?>
      <div class="hospital-card" onclick="location.href='/patients/search.php?type=hospital'" style="cursor:pointer">
        <div class="hospital-card-img">
          <img src="<?= $img ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy">
          <div class="hospital-card-rating"><i class="fa-solid fa-star"></i><?= $rating ?></div>
        </div>
        <div class="hospital-card-body">
          <h4><?= htmlspecialchars($name) ?></h4>
          <div class="hospital-card-loc"><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($loc) ?></div>
          <div class="hospital-tags"><?php foreach($tags as $t): ?><span class="hospital-tag"><?= $t ?></span><?php endforeach; ?></div>
          <button class="btn-view-docs" onclick="event.stopPropagation();location.href='/patients/search.php?q=<?= urlencode($name) ?>'">View Doctors</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── PROVIDER CTA ──────────────────────────────── -->
<section class="provider-banner reveal">
  <div class="provider-banner-inner">
    <div class="provider-banner-content">
      <h3>Are you a healthcare provider?</h3>
      <p>Join Planeazzy to grow your practice, manage appointments seamlessly, and reach thousands of patients looking for quality care across Kenya.</p>
      <div class="provider-banner-btns">
        <button class="btn-provider-white" onclick="location.href='/providers/register.php'">List Your Practice</button>
        <button class="btn-provider-outline" onclick="location.href='/providers/register.php'">Learn More</button>
      </div>
    </div>
    <div class="provider-banner-visual">
      <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=700&q=70" alt="Doctor at computer" loading="lazy">
      <div class="provider-banner-grad"></div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
function doSearch() {
  const loc  = document.getElementById('sLoc')?.value.trim() || 'Nairobi, Kenya';
  const q    = document.getElementById('sQuery')?.value.trim() || '';
  const type = document.getElementById('sType')?.value || 'in_person';
  const rt   = (type === 'telehealth' || q.toLowerCase().includes('doctor')) ? 'doctors' : 'hospitals';
  window.location.href = '/patients/search.php?' + new URLSearchParams({ location: loc, q, visit_type: type, rt }).toString();
}
</script>
