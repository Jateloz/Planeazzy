<?php
/**
 * Planeazzy — patients/telehealth.php
 * Telehealth video call room
 */
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
require_once dirname(__DIR__). '/services/Database.php';
Security::requireAuth('/patients/login.php');

$pid   = (int)$_SESSION['patient_id'];
$apptId= (int)($_GET['appt'] ?? 0);
$db    = Database::getInstance();

// Get appointment + provider info
$appt = $apptId ? $db->fetchOne(
  'SELECT a.*,p.name pname,p.specialty pspec,p.image_url pimg FROM appointments a LEFT JOIN providers p ON a.provider_id=p.id WHERE a.id=:id AND a.patient_id=:pid',
  [':id'=>$apptId,':pid'=>$pid]
) : null;

$docName = htmlspecialchars($appt['pname'] ?? 'Dr. Sarah Wanjiku');
$docSpec = htmlspecialchars($appt['pspec'] ?? 'General Practitioner');

// Don't use full sidebar for call page — custom layout
$noSidebar = true;
$pageTitle = 'Telehealth Call';
include dirname(__DIR__). '/includes/header.php';
?>
<style>
body { background: var(--navy); margin: 0; }
</style>

<div style="display:flex;flex-direction:column;min-height:calc(100vh - 64px);background:var(--navy)">
  <div class="call-layout">

    <!-- VIDEO AREA -->
    <div class="call-video-area">

      <!-- Main doctor feed -->
      <div class="call-main-feed">
        <div class="call-main-img"></div>
        <div class="call-doc-label">
          <span style="width:8px;height:8px;border-radius:50%;background:var(--green-b);flex-shrink:0"></span>
          <?= $docName ?>
        </div>
        <!-- Self preview -->
        <div class="call-self-view">
          <div class="call-self-img"></div>
          <div class="call-self-lbl">You</div>
        </div>
      </div>

      <!-- Call controls -->
      <div class="call-controls">
        <button class="ctrl-btn" id="micBtn" title="Mute/Unmute">
          <span class="material-symbols-outlined">mic</span>
        </button>
        <button class="ctrl-btn" id="camBtn" title="Camera">
          <span class="material-symbols-outlined">videocam</span>
        </button>
        <button class="ctrl-btn" title="Share screen">
          <span class="material-symbols-outlined">present_to_all</span>
        </button>
        <div class="ctrl-divider"></div>
        <button class="ctrl-btn end" title="End call" onclick="if(confirm('End this call?')) window.location.href='/patients/dashboard.php?tab=appointments'">
          <span class="material-symbols-outlined">call_end</span>
        </button>
      </div>
    </div>

    <!-- SIDEBAR -->
    <div class="call-sidebar">

      <!-- Session info -->
      <div class="call-panel">
        <div class="call-panel-head">
          <span class="material-symbols-outlined">event</span>
          Session Details
          <span class="pill-sm">In Progress</span>
        </div>
        <div style="padding:14px 16px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding:10px;background:rgba(255,255,255,.05);border-radius:var(--r)">
            <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--teal));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:15px;flex-shrink:0">DR</div>
            <div>
              <div style="font-size:13px;font-weight:800;color:rgba(255,255,255,.9)"><?= $docName ?></div>
              <div style="font-size:11px;color:rgba(255,255,255,.45)"><?= $docSpec ?></div>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;color:rgba(255,255,255,.55)">
            <div style="display:flex;align-items:center;gap:7px"><span class="material-symbols-outlined" style="font-size:15px;color:var(--blue)">schedule</span> Session: <span id="sessionTimer" style="font-family:monospace;color:rgba(255,255,255,.8)">00:00</span></div>
            <div style="display:flex;align-items:center;gap:7px"><span class="material-symbols-outlined" style="font-size:15px;color:var(--green)">security</span> <span style="color:var(--green);font-weight:700">SECURE CONNECTION</span></div>
            <?php if($appt): ?>
            <div style="display:flex;align-items:center;gap:7px"><span class="material-symbols-outlined" style="font-size:15px;color:var(--blue)">description</span> <?= htmlspecialchars($appt['title']??'Initial Consultation') ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Chat -->
      <div class="call-panel" style="flex:1;display:flex;flex-direction:column">
        <div style="border-bottom:1px solid rgba(255,255,255,.08);display:flex">
          <button style="flex:1;padding:11px;font-size:12px;font-weight:700;background:rgba(25,120,229,.15);color:var(--blue);border:none;cursor:pointer;border-bottom:2px solid var(--blue)">Chat</button>
          <button style="flex:1;padding:11px;font-size:12px;font-weight:600;color:rgba(255,255,255,.4);background:none;border:none;cursor:pointer">Shared Notes</button>
        </div>
        <div class="chat-msgs" id="chatMsgs">
          <div class="chat-bubble them">
            <div class="bubble">Hello, I've reviewed your blood work from last week. Let's discuss the results.</div>
            <div class="chat-time">10:02 AM</div>
          </div>
          <div class="chat-bubble me">
            <div class="bubble">Thank you, Doctor. I've also been feeling more fatigued lately.</div>
            <div class="chat-time">10:05 AM</div>
          </div>
          <div class="chat-system">Dr. <?= $docName ?> shared: lab_results.pdf</div>
        </div>
        <div class="chat-input-row">
          <input type="text" id="chatInput" placeholder="Type a message…">
          <button class="chat-send" id="chatSend"><span class="material-symbols-outlined">send</span></button>
        </div>
      </div>

      <!-- Doctor typing notes -->
      <div class="call-panel">
        <div class="notes-typing">
          <div class="notes-indicator">
            <span class="material-symbols-outlined">edit_note</span>
            Doctor is typing notes…
          </div>
          <div class="notes-text">"Patient reports increased fatigue over the last 48 hours. Blood glucose within range but trending higher…"</div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include dirname(__DIR__). '/includes/footer.php'; ?>
