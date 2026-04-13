<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Security.php';
Security::requireAuth('/patients/login.php');
$noSidebar  = false;
$portalType = 'patient';
$pageTitle  = 'Telehealth Video Call';
$activeTab  = 'telehealth';
$_SESSION['patient_name'] = $_SESSION['patient_name'] ?? 'Patient';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="call-wrap">
  <div class="call-vid-col">
    <div class="call-main-feed">
      <div class="call-main-bg"></div>
      <div class="call-grad"></div>
      <div class="call-doc-lbl"><span class="call-live"></span> <i class="fa-solid fa-user-doctor" style="color:#5eead4;font-size:14px"></i>&nbsp; Dr. Sarah Jenkins · Cardiologist</div>
      <div class="call-self">
        <div class="call-self-bg"></div>
        <div class="call-self-lbl" data-en="You" data-sw="Wewe">You</div>
      </div>
      <div class="call-dur" id="callTimer">00:00</div>
    </div>
    <div class="call-controls">
      <button class="ctrl-btn" id="micBtn" title="Mute microphone"><i class="fa-solid fa-microphone"></i></button>
      <button class="ctrl-btn" id="camBtn" title="Turn off camera"><i class="fa-solid fa-video"></i></button>
      <div class="ctrl-div"></div>
      <button class="ctrl-btn" title="Share screen"><i class="fa-solid fa-display"></i></button>
      <button class="ctrl-btn" title="Reactions"><i class="fa-solid fa-face-smile"></i></button>
      <button class="ctrl-btn" title="More options"><i class="fa-solid fa-ellipsis"></i></button>
      <div class="ctrl-div"></div>
      <button class="ctrl-btn end" title="End call" onclick="if(confirm('End this consultation?'))history.back()"><i class="fa-solid fa-phone-slash"></i></button>
    </div>
  </div>
  <div class="call-sidebar">
    <!-- Session info -->
    <div class="call-panel">
      <div class="call-panel-h"><span><i class="fa-solid fa-circle-info"></i> Session Info</span><span class="call-pill">LIVE</span></div>
      <div style="padding:12px 16px;display:flex;flex-direction:column;gap:7px">
        <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,.6)"><i class="fa-solid fa-user-doctor" style="color:var(--primary);font-size:13px"></i> Dr. Sarah Jenkins — Cardiologist</div>
        <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,.6)"><i class="fa-regular fa-clock" style="color:var(--primary);font-size:13px"></i> Started: <?= date('g:i A') ?></div>
        <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,.6)"><i class="fa-solid fa-shield-halved" style="color:var(--primary);font-size:13px"></i> End-to-end encrypted</div>
        <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,.6)"><i class="fa-solid fa-wifi" style="color:var(--primary);font-size:13px"></i> Connection: Excellent</div>
      </div>
    </div>
    <!-- Chat -->
    <div class="call-panel" style="flex:1">
      <div class="call-panel-h"><span><i class="fa-solid fa-comments"></i> Chat</span></div>
      <div class="chat-msgs" id="chatMsgs">
        <div class="chat-sys">Session started — <?= date('g:i A') ?></div>
        <div class="chat-bubble them">
          <div class="bubble">Hello! I can see you clearly. How are you feeling today?</div>
          <div class="chat-time">Dr. Jenkins · just now</div>
        </div>
      </div>
      <div class="chat-inp-row">
        <input class="chat-inp" id="chatInp" placeholder="Type a message…">
        <button class="chat-send" id="chatSend"><i class="fa-solid fa-paper-plane"></i></button>
      </div>
    </div>
    <!-- Notes -->
    <div class="call-panel">
      <div class="call-panel-h"><i class="fa-solid fa-file-lines"></i> Consultation Notes</div>
      <div class="notes-area">
        <div class="notes-ind"><i class="fa-solid fa-pen-nib"></i> Doctor is taking notes…</div>
        <div class="notes-txt">Chief complaint: Chest discomfort. Duration: 2 weeks. Notes will be added to your health record after the session ends.</div>
        <textarea style="width:100%;margin-top:11px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:9px 11px;color:rgba(255,255,255,.7);font-family:'Inter',sans-serif;font-size:12px;resize:none;outline:none;height:60px" placeholder="Your own notes…"></textarea>
      </div>
    </div>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
