<?php
/**
 * Planeazzy — OTP Log Viewer
 * GET /logs/view-otp.php
 *
 * Displays OTP codes from the log file. Protected by a secret key.
 * Use this when email/SMS is not yet configured in production.
 *
 * Access: /logs/view-otp.php?key=YOUR_APP_SECRET
 */
define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/config/config.php';

// Protect with APP_SECRET so only the site owner can view
$key = $_GET['key'] ?? '';
if (!$key || $key !== APP_SECRET) {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;color:#dc2626;padding:40px">403 Forbidden — provide ?key=YOUR_APP_SECRET</h2>');
}

$logFile = LOG_DIR . 'otp_codes.txt';
$mailLog = LOG_DIR . 'mail_dev.log';
$smsLog  = LOG_DIR . 'sms_dev.log';

$otpLines = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $otpLines = array_reverse(array_slice($lines, -200)); // last 200, newest first
}

$filter = strtolower(trim($_GET['q'] ?? ''));
if ($filter) {
    $otpLines = array_values(array_filter($otpLines, fn($l) => stripos($l, $filter) !== false));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>OTP Log Viewer — Planeazzy</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f8fafc;color:#1e293b;padding:0}
.topbar{background:linear-gradient(135deg,#059669,#0d9488);padding:18px 28px;display:flex;align-items:center;justify-content:space-between}
.topbar h1{font-size:1.25rem;font-weight:800;color:#fff;letter-spacing:-.03em}
.topbar span{font-size:.75rem;color:rgba(255,255,255,.7);background:rgba(0,0,0,.15);padding:3px 10px;border-radius:9999px}
.content{padding:24px 28px}
.notice{background:#fef9c3;border:1.5px solid #fde047;border-radius:12px;padding:14px 16px;font-size:.875rem;color:#713f12;margin-bottom:20px;line-height:1.6}
.notice strong{color:#92400e}
.search-bar{display:flex;gap:10px;margin-bottom:20px}
.search-bar input{flex:1;padding:10px 14px;border:2px solid #e2e8f0;border-radius:10px;font-family:inherit;font-size:.875rem;outline:none;transition:border .2s}
.search-bar input:focus{border-color:#059669}
.search-bar button{padding:10px 18px;background:#059669;color:#fff;border:none;border-radius:10px;font-family:inherit;font-weight:700;cursor:pointer;font-size:.875rem}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
.stat{background:#fff;border-radius:12px;padding:14px 16px;border:1px solid #e2e8f0;text-align:center}
.stat-val{font-size:1.75rem;font-weight:800;letter-spacing:-.04em}
.stat-lbl{font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-top:3px}
.log-table{background:#fff;border-radius:14px;border:1px solid #e2e8f0;overflow:hidden}
.log-header{padding:14px 18px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between}
.log-header h3{font-size:.9375rem;font-weight:700}
.entry{padding:13px 18px;border-bottom:1px solid #f1f5f9;font-size:.8125rem;line-height:1.6}
.entry:last-child{border-bottom:none}
.entry:hover{background:#f8fafc}
.ts{font-family:monospace;color:#94a3b8;font-size:.75rem;margin-right:8px;flex-shrink:0}
.otp-badge{display:inline-block;background:#dcfce7;color:#166534;font-weight:800;font-size:.9375rem;padding:1px 8px;border-radius:6px;letter-spacing:.1em;margin:0 4px;font-family:monospace}
.sms-badge{background:#dbeafe;color:#1e40af}
.email-badge{background:#ede9fe;color:#5b21b6}
.row-wrap{display:flex;align-items:flex-start;gap:6px;flex-wrap:wrap}
.raw{color:#374151}
.empty{text-align:center;padding:48px;color:#94a3b8;font-size:.875rem}
.tabs{display:flex;gap:4px;margin-bottom:20px}
.tab{padding:7px 16px;border-radius:9999px;border:1.5px solid #e2e8f0;background:transparent;font-family:inherit;font-size:.75rem;font-weight:600;color:#64748b;cursor:pointer;text-decoration:none}
.tab.active,.tab:hover{background:#059669;color:#fff;border-color:#059669;text-decoration:none}
</style>
</head>
<body>
<div class="topbar">
  <h1> OTP Log Viewer</h1>
  <span>Planeazzy Developer Tool</span>
</div>
<div class="content">

  <div class="notice">
    <strong> Developer Tool:</strong> This page shows OTP codes from the log file. Use it when email/SMS is not yet configured.
    Once SendGrid and Africa's Talking are configured, OTPs are delivered directly to users.
    <strong>Bookmark this URL — keep it secret.</strong>
  </div>

  <form method="GET" action="" class="search-bar">
    <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
    <input type="text" name="q" placeholder="Filter by email, phone, or name…" value="<?= htmlspecialchars($filter) ?>">
    <button type="submit">Search</button>
    <?php if($filter):?><a href="?key=<?=urlencode($key)?>" class="tab" style="text-decoration:none">Clear</a><?php endif;?>
  </form>

  <?php
  // Parse OTP lines smartly
  $otps = [];
  foreach ($otpLines as $line) {
    // Extract timestamp
    preg_match('/^\[(.+?)\]/', $line, $tsm);
    $ts = $tsm[1] ?? '';
    // Detect if SMS or email
    $isSms = strpos($line, '[SMS]') !== false;
    // Extract 6-digit OTP
    preg_match('/\b(\d{6})\b/', $line, $otpm);
    $otp = $otpm[1] ?? '';
    // Extract email/phone
    preg_match('/\[([^\]@]+@[^\]]+)\]/', $line, $emailm);
    preg_match('/\[(\+?[0-9]{9,15})\]/', $line, $phonem);
    $contact = $emailm[1] ?? ($phonem[1] ?? '');
    $otps[] = compact('ts','line','otp','isSms','contact');
  }

  $otpCount   = count(array_filter($otps, fn($o) => $o['otp']));
  $smsCount   = count(array_filter($otps, fn($o) => $o['isSms']));
  $emailCount = count(array_filter($otps, fn($o) => !$o['isSms']));
  ?>

  <div class="stats">
    <div class="stat"><div class="stat-val" style="color:#059669"><?= count($otpLines) ?></div><div class="stat-lbl">Total Entries</div></div>
    <div class="stat"><div class="stat-val" style="color:#7c3aed"><?= $emailCount ?></div><div class="stat-lbl">Email OTPs</div></div>
    <div class="stat"><div class="stat-val" style="color:#1d4ed8"><?= $smsCount ?></div><div class="stat-lbl">SMS Logs</div></div>
  </div>

  <div class="log-table">
    <div class="log-header">
      <h3>OTP Codes & Delivery Log</h3>
      <span style="font-size:.75rem;color:#64748b"><?= count($otpLines) ?> entries<?= $filter ? ' (filtered)' : '' ?></span>
    </div>
    <?php if (empty($otpLines)): ?>
    <div class="empty">
      <?php if(!file_exists($logFile)): ?>
      No log file found yet. OTPs will appear here once someone registers or requests a code.<br>
      Expected path: <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px"><?= htmlspecialchars($logFile) ?></code>
      <?php else: ?>
      No entries match your search.
      <?php endif; ?>
    </div>
    <?php else: ?>
    <?php foreach ($otps as $entry): ?>
    <div class="entry">
      <div class="row-wrap">
        <span class="ts"><?= htmlspecialchars($entry['ts']) ?></span>
        <?php if ($entry['isSms']): ?>
        <span class="otp-badge sms-badge">SMS</span>
        <?php else: ?>
        <span class="otp-badge email-badge">EMAIL</span>
        <?php endif; ?>
        <?php if ($entry['otp']): ?>
        <span>OTP: <span class="otp-badge"><?= htmlspecialchars($entry['otp']) ?></span></span>
        <?php endif; ?>
        <?php if ($entry['contact']): ?>
        <span style="color:#64748b;font-size:.75rem">→ <?= htmlspecialchars($entry['contact']) ?></span>
        <?php endif; ?>
        <span class="raw"><?= htmlspecialchars(preg_replace('/^\[.+?\]\s*(\[SMS\]\s*)?/', '', $entry['line'])) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php
  // Show raw log files if they exist
  foreach ([
    'Mail Dev Log' => $mailLog,
    'SMS Dev Log'  => $smsLog,
  ] as $label => $path):
    if (!file_exists($path)) continue;
    $size = filesize($path);
    if ($size < 1) continue;
  ?>
  <div style="margin-top:16px">
    <a href="?key=<?=urlencode($key)?>&raw=<?=urlencode($path)?>" class="tab" style="font-size:.75rem">View Raw <?=htmlspecialchars($label)?> (<?=number_format($size)?>B)</a>
  </div>
  <?php endforeach; ?>

  <?php if (!empty($_GET['raw'])): ?>
  <div class="log-table" style="margin-top:16px">
    <div class="log-header"><h3><?= htmlspecialchars(basename($_GET['raw'])) ?></h3></div>
    <pre style="padding:16px;font-size:.75rem;overflow-x:auto;line-height:1.6;color:#374151;white-space:pre-wrap"><?php
    $rp = realpath($_GET['raw']);
    $logDir = realpath(LOG_DIR);
    if ($rp && $logDir && strpos($rp, $logDir) === 0 && file_exists($rp)) {
        echo htmlspecialchars(implode('', array_reverse(file($rp))));
    } else {
        echo 'Access denied.';
    }
    ?></pre>
  </div>
  <?php endif; ?>

  <div style="margin-top:24px;font-size:.75rem;color:#94a3b8;text-align:center">
    Planeazzy Developer Tools · OTP log path: <?= htmlspecialchars($logFile) ?>
  </div>
</div>
</body>
</html>
