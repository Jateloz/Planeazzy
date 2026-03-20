<?php
$noSidebar = $noSidebar ?? false;
$csrf      = Security::csrfToken();
?>
<?php if (!$noSidebar): ?>
</div><!-- /.main-wrap -->
</div><!-- /.app-layout -->
<button class="mob-tog" id="mobToggle" onclick="toggleSidebar()">
  <span class="material-symbols-outlined">menu</span>
</button>

<!-- Location Modal -->
<div class="modal-overlay" id="locationModal">
  <div class="modal-box" style="max-width:440px">
    <div class="modal-head">
      <h2>Set Your Location</h2>
      <button class="modal-close" onclick="closeModal('locationModal')"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div class="modal-body">
      <div class="map-card" style="margin-bottom:16px">
        <div class="map-box">
          <div class="map-bg-img"></div><div class="map-overlay"></div><div class="map-hex-bg"></div>
          <div class="map-ping"><div class="map-ring1"><div class="map-ring2"><div class="map-center"></div></div></div></div>
        </div>
      </div>
      <p style="font-size:13px;color:var(--muted);margin-bottom:14px">Allow location access to find nearby healthcare services and enable emergency dispatch.</p>
      <button class="btn btn-primary btn-full mb2" onclick="requestLocation()">
        <span class="material-symbols-outlined">near_me</span> Use My Current Location
      </button>
      <div class="input-wrap">
        <span class="input-ico"><span class="material-symbols-outlined">search</span></span>
        <input type="text" id="manualLocInput" class="form-input has-ico" placeholder="Search city, area or address…">
      </div>
      <button class="btn btn-outline btn-full mt2" onclick="setManualLocation()">
        <span class="material-symbols-outlined">edit_location_alt</span> Set This Location
      </button>
    </div>
  </div>
</div>

<!-- Book Appointment Modal -->
<div class="modal-overlay" id="bookModal">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Book Appointment</h2>
      <button class="modal-close" onclick="closeModal('bookModal')"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div id="bookAlertBox" class="alert hidden"><span class="material-symbols-outlined">error</span><span id="bookAlertMsg"></span></div>
      <div class="form-group">
        <label class="form-label">Service Type</label>
        <select class="form-select" id="bookServiceType">
          <option value="doctor">See a Doctor</option>
          <option value="clinic">Clinic Visit</option>
          <option value="hospital">Hospital</option>
          <option value="telehealth">Telehealth (Video)</option>
          <option value="lab">Lab Test</option>
          <option value="pharmacy">Pharmacy</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Provider (optional)</label>
        <select class="form-select" id="bookProvider">
          <option value="">-- Any available provider --</option>
          <?php
          try {
            require_once dirname(__DIR__). '/services/Database.php';
            $db2 = Database::getInstance();
            $pvs = $db2->fetchAll('SELECT id,name,type FROM providers WHERE is_active=1 AND is_verified=1 ORDER BY rating DESC LIMIT 20');
            foreach ($pvs as $p) echo '<option value="'.htmlspecialchars($p['id']).'">'.htmlspecialchars($p['name']).' ('.ucfirst($p['type']).')</option>';
          } catch(Exception $e) {}
          ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Date</label>
          <input type="date" id="bookDate" class="form-input" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Time</label>
          <input type="time" id="bookTime" class="form-input" value="09:00">
        </div>
      </div>
      <div class="form-group mt2">
        <label class="form-label">Reason / Title</label>
        <input type="text" id="bookTitle" class="form-input" placeholder="e.g. General check-up, Follow-up…">
      </div>
      <div class="form-group">
        <label class="form-label">Notes (optional)</label>
        <textarea id="bookNotes" class="form-textarea" rows="2" placeholder="Any symptoms or additional info…"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Visit Type</label>
        <select class="form-select" id="bookLocType">
          <option value="in_person">In-Person</option>
          <option value="telehealth">Telehealth (Video Call)</option>
          <option value="home_visit">Home Visit</option>
        </select>
      </div>
      <button class="btn btn-primary btn-full btn-lg" id="bookBtn" onclick="submitBooking()">
        <span class="material-symbols-outlined">event_available</span> Confirm Booking
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($noSidebar): ?>
<footer style="padding:24px 20px;text-align:center;border-top:1px solid var(--border);background:var(--white);margin-top:auto;flex-shrink:0">
  <div style="display:flex;justify-content:center;gap:6px;flex-wrap:wrap;margin-bottom:8px">
    <a href="/patients/login.php"          style="display:inline-flex;align-items:center;gap:4px;padding:5px 11px;border-radius:99px;font-size:12px;font-weight:600;color:var(--muted);border:1px solid var(--border);background:var(--white)"><span class="material-symbols-outlined" style="font-size:14px">person</span>Patients</a>
    <a href="/providers/doctor/login.php"    style="display:inline-flex;align-items:center;gap:4px;padding:5px 11px;border-radius:99px;font-size:12px;font-weight:600;color:var(--muted);border:1px solid var(--border);background:var(--white)"><span class="material-symbols-outlined" style="font-size:14px">stethoscope</span>Doctors</a>
    <a href="/providers/clinic/login.php"    style="display:inline-flex;align-items:center;gap:4px;padding:5px 11px;border-radius:99px;font-size:12px;font-weight:600;color:var(--muted);border:1px solid var(--border);background:var(--white)"><span class="material-symbols-outlined" style="font-size:14px">local_pharmacy</span>Clinics</a>
    <a href="/providers/ambulance/login.php" style="display:inline-flex;align-items:center;gap:4px;padding:5px 11px;border-radius:99px;font-size:12px;font-weight:600;color:var(--muted);border:1px solid var(--border);background:var(--white)"><span class="material-symbols-outlined" style="font-size:14px">ambulance</span>Ambulance</a>
  </div>
  <p style="font-size:11px;color:var(--silver)">&copy; 2025 Planeazzy Healthcare Solutions. All rights reserved.</p>
</footer>
<?php endif; ?>

<input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf) ?>">
<script src="/assets/js/app.js"></script>
</body>
</html>
