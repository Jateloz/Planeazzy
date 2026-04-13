<?php
$noSidebar = $noSidebar ?? true;
$csrf = Security::csrfToken();
?>
<?php if (!$noSidebar): ?>
</div><!-- /.main-wrap -->
</div><!-- /.sidebar layout flex -->

<!-- Mobile sidebar toggle button -->
<!-- Mobile sidebar toggle FAB -->
<button onclick="Sidebar.openMob()" id="mobSidebarToggle"
  style="display:none;position:fixed;bottom:22px;right:22px;z-index:400;width:52px;height:52px;border-radius:50%;background:var(--primary);color:#fff;border:none;box-shadow:0 8px 24px rgba(25,120,229,.45);cursor:pointer;align-items:center;justify-content:center;font-size:20px;transition:transform .15s">
  <i class="fa-solid fa-bars"></i>
</button>

<!-- Book Appointment Modal (available on all dashboard pages) -->
<div id="bookModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:500;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)closeModal('bookModal')">
  <div style="background:var(--white);border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);max-height:90vh;overflow-y:auto;width:100%;max-width:520px">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px 16px;border-bottom:1px solid var(--slate-100);position:sticky;top:0;background:var(--white);z-index:1;border-radius:20px 20px 0 0">
      <h2 style="font-size:18px;font-weight:700;color:var(--slate-900)"><i class="fa-solid fa-calendar-plus" style="color:var(--primary);margin-right:8px"></i>Book Appointment</h2>
      <button onclick="closeModal('bookModal')" style="width:30px;height:30px;border-radius:50%;background:var(--slate-100);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--slate-500)"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div style="padding:20px 24px 28px">
      <div id="bookAlertBox" class="alert hidden"></div>
      <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf) ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Service Type</label>
          <select class="form-select" id="bookServiceType">
            <option value="doctor">See a Doctor</option><option value="hospital">Hospital Visit</option>
            <option value="telehealth">Telehealth (Video)</option><option value="lab">Lab Test</option><option value="pharmacy">Pharmacy</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Visit Type</label>
          <select class="form-select" id="bookLocType">
            <option value="in_person">In-Person</option><option value="telehealth">Telehealth</option><option value="home_visit">Home Visit</option>
          </select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Provider (optional)</label>
        <select class="form-select" id="bookProvider">
          <option value="">— Any available provider —</option>
          <?php
          try {
            if (!class_exists('Database')) require_once dirname(__DIR__). '/services/Database.php';
            $db2 = Database::getInstance();
            $pvs = $db2->fetchAll('SELECT id,name,type FROM providers WHERE is_active=1 AND is_verified=1 ORDER BY rating DESC LIMIT 20');
            foreach ($pvs as $p) echo '<option value="'.htmlspecialchars($p['id']).'">'.htmlspecialchars($p['name']).' ('.ucfirst($p['type']).')</option>';
          } catch(Exception $e) {}
          ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Date</label><input type="date" id="bookDate" class="form-input" min="<?= date('Y-m-d') ?>"></div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Time</label><input type="time" id="bookTime" class="form-input" value="09:00"></div>
      </div>
      <div class="form-group"><label class="form-label">Reason / Title</label><input type="text" id="bookTitle" class="form-input" placeholder="e.g. General check-up, Follow-up…"></div>
      <div class="form-group"><label class="form-label">Notes (optional)</label><textarea id="bookNotes" class="form-textarea" rows="2" placeholder="Symptoms or additional info…"></textarea></div>
      <button class="btn-join btn" id="bookBtn" style="width:100%;margin-top:8px;height:48px;border-radius:8px;font-size:15px;font-weight:700" onclick="submitBooking()">
        <i class="fa-solid fa-calendar-check"></i> Confirm Booking
      </button>
    </div>
  </div>
</div>

<?php else: ?>
<!-- Simple public footer -->
<footer class="pub-footer" style="margin-top:auto">
  <div class="pub-footer-inner">
    <div class="footer-grid">
      <div>
        <div class="footer-logo" style="display:flex;align-items:center;gap:10px">
          <img src="/assets/images/favicon.png" alt="Planeazzy icon"
               style="width:90px;height:50px;border-radius:9px;object-fit:contain;background:transparent">
        </div>
        <p class="footer-desc">Connecting patients with the best healthcare providers in Kenya through technology and transparency.</p>
        <div class="footer-socials">
          <a class="footer-social-btn" href="#"><i class="fa-brands fa-x-twitter"></i></a>
          <a class="footer-social-btn" href="#"><i class="fa-brands fa-instagram"></i></a>
          <a class="footer-social-btn" href="#"><i class="fa-brands fa-facebook-f"></i></a>
        </div>
      </div>
      <div><div class="footer-col-title">For Patients</div><ul class="footer-links"><li><a href="/patients/search.php">Search Doctors</a></li><li><a href="/patients/search.php?type=hospital">Medical Centers</a></li><li><a href="/patients/register.php">How it Works</a></li><li><a href="#">FAQ</a></li></ul></div>
      <div><div class="footer-col-title">For Providers</div><ul class="footer-links"><li><a href="/providers/doctor/register.php">List Your Practice</a></li><li><a href="/providers/clinic/register.php">Clinic Management</a></li><li><a href="/providers/ambulance/register.php">Ambulance Services</a></li><li><a href="#">Support Center</a></li></ul></div>
      <div><div class="footer-col-title">Legal</div><ul class="footer-links"><li><a href="#">Privacy Policy</a></li><li><a href="#">Terms of Service</a></li><li><a href="#">Cookie Policy</a></li><li><a href="#">Ethics &amp; Compliance</a></li></ul></div>
    </div>
    <div class="footer-bottom">
      <p class="footer-copy">© 2025 Planeazzy Ltd. All rights reserved. Kenya's #1 Healthcare Booking Platform.</p>
      <div class="footer-contacts">
        <span class="footer-contact"><i class="fa-solid fa-phone"></i> +254 700 000 000</span>
        <span class="footer-contact"><i class="fa-solid fa-envelope"></i> info@planeazzy.com</span>
      </div>
    </div>
  </div>
</footer>
<?php endif; ?>

<input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf) ?>">
<script src="/assets/js/app.js"></script>
<script>
// Show mobile sidebar toggle on small screens
(function(){
  var btn=document.getElementById('mobSidebarToggle');
  if(btn){
    if(window.innerWidth<=768)btn.style.display='flex';
    window.addEventListener('resize',function(){btn.style.display=window.innerWidth<=768?'flex':'none';});
  }
})();
</script>
</body>
</html>
