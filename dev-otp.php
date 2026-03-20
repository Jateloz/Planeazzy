<?php
/**
 * Planeazzy — dev-otp.php
 * DEVELOPMENT ONLY — shows the latest OTP codes from the log file.
 * DELETE THIS FILE before deploying to production!
 * Access: http://localhost/dev-otp.php
 */
require_once __DIR__ . '/config/config.php';

// Block in production
if (APP_ENV !== 'development') {
    http_response_code(404);
    exit('Not found.');
}

// Only allow localhost
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    exit('Access denied. This page is only accessible from localhost.');
}

$logFile = ROOT_DIR . '/logs/mail_dev.log';

// Parse log entries
$entries = [];
if (file_exists($logFile)) {
    $lines = array_reverse(array_filter(explode("\n", file_get_contents($logFile))));
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        // Extract: [timestamp] TYPE OTP for email (name): CODE | SMTP:STATUS
        if (preg_match('/\[(.+?)\]\s+(.*?OTP for\s+(\S+)\s+\((.+?)\)):\s*(\d{4,8})\s*\|\s*SMTP:(\w+)/', $line, $m)) {
            $entries[] = [
                'time'   => $m[1],
                'type'   => str_contains($m[2], 'PROVIDER') ? 'Provider' : 'Patient',
                'email'  => $m[3],
                'name'   => $m[4],
                'code'   => $m[5],
                'smtp'   => $m[6],
                'raw'    => $line,
            ];
        } else {
            $entries[] = ['raw' => $line, 'code' => '', 'time' => '', 'email' => '', 'type' => ''];
        }
    }
}

// Also check for OTP in PatientService / ProviderService stored in session (backup)
$sessionOtp = $_SESSION['_dev_last_otp'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dev OTP Viewer — Planeazzy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Public Sans', sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; padding: 40px 20px; }
  .wrap { max-width: 760px; margin: 0 auto; }
  .badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(239,68,68,.18); border: 1px solid rgba(239,68,68,.4); color: #fca5a5; padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 18px; }
  h1 { font-size: 28px; font-weight: 900; color: #fff; letter-spacing: -0.04em; margin-bottom: 6px; }
  .subtitle { font-size: 13px; color: #64748b; margin-bottom: 28px; }
  .warning { background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.3); border-radius: 12px; padding: 14px 16px; font-size: 13px; color: #fbbf24; display: flex; gap: 10px; align-items: flex-start; margin-bottom: 28px; line-height: 1.6; }

  /* Log file path info */
  .info-bar { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); border-radius: 10px; padding: 13px 16px; margin-bottom: 24px; font-size: 12px; color: #94a3b8; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .info-bar .path { font-family: 'JetBrains Mono', monospace; color: #60a5fa; background: rgba(96,165,250,.1); padding: 3px 8px; border-radius: 5px; }
  .info-bar .status-ok  { color: #34d399; font-weight: 700; }
  .info-bar .status-err { color: #f87171; font-weight: 700; }

  /* OTP cards */
  .otp-card { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 20px 22px; margin-bottom: 14px; display: flex; align-items: center; gap: 20px; }
  .otp-card.latest { border-color: rgba(96,165,250,.4); background: rgba(96,165,250,.06); }
  .otp-code { font-family: 'JetBrains Mono', monospace; font-size: 38px; font-weight: 700; color: #60a5fa; letter-spacing: 8px; min-width: 180px; }
  .otp-card.latest .otp-code { color: #34d399; }
  .otp-info { flex: 1; }
  .otp-email { font-size: 14px; font-weight: 700; color: #e2e8f0; margin-bottom: 4px; }
  .otp-meta  { font-size: 12px; color: #64748b; display: flex; gap: 10px; flex-wrap: wrap; }
  .type-pill { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
  .type-patient  { background: rgba(59,130,246,.18); color: #93c5fd; }
  .type-provider { background: rgba(16,185,129,.18); color: #6ee7b7; }
  .smtp-ok  { color: #34d399; }
  .smtp-fail{ color: #f87171; }

  .copy-btn { background: rgba(96,165,250,.15); border: 1px solid rgba(96,165,250,.3); color: #93c5fd; padding: 8px 16px; border-radius: 8px; font-family: 'Public Sans', sans-serif; font-size: 12px; font-weight: 700; cursor: pointer; transition: background .2s; }
  .copy-btn:active { background: rgba(96,165,250,.3); }

  .latest-label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #34d399; margin-bottom: 6px; }

  /* Empty state */
  .empty { text-align: center; padding: 60px 20px; background: rgba(255,255,255,.03); border: 1px dashed rgba(255,255,255,.1); border-radius: 14px; }
  .empty h3 { font-size: 16px; font-weight: 700; color: #64748b; margin-bottom: 8px; }
  .empty p  { font-size: 13px; color: #475569; line-height: 1.7; }
  .empty code { font-family: 'JetBrains Mono', monospace; background: rgba(255,255,255,.06); padding: 2px 7px; border-radius: 4px; font-size: 12px; color: #93c5fd; }

  .divider { border: none; border-top: 1px solid rgba(255,255,255,.06); margin: 28px 0; }
  .raw-log { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #475569; background: rgba(0,0,0,.3); border: 1px solid rgba(255,255,255,.05); border-radius: 8px; padding: 14px; max-height: 200px; overflow-y: auto; white-space: pre; line-height: 1.7; }
  .section-title { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px; }
  .refresh-btn { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1); color: #94a3b8; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; margin-bottom: 24px; font-family: 'Public Sans', sans-serif; }
</style>
</head>
<body>
<div class="wrap">

  <div class="badge">⚠ Dev Tool — Not for Production</div>
  <h1>OTP / Verification Code Viewer</h1>
  <p class="subtitle">Shows the latest verification codes sent during development. Only accessible from localhost.</p>

  <div class="warning">
    <span style="font-size:18px;flex-shrink:0">⚠️</span>
    <span><strong>Delete this file before going live.</strong> It exposes all OTP codes sent by the system. This page is blocked in production and from non-localhost IPs automatically.</span>
  </div>

  <!-- Log file info -->
  <div class="info-bar">
    <span>Log file:</span>
    <span class="path"><?= htmlspecialchars($logFile) ?></span>
    <?php if (file_exists($logFile)): ?>
      <span class="status-ok">✓ File exists</span>
      <span>(<?= number_format(filesize($logFile)) ?> bytes, last modified <?= date('H:i:s', filemtime($logFile)) ?>)</span>
    <?php else: ?>
      <span class="status-err">✗ File not found — no codes logged yet</span>
    <?php endif; ?>
  </div>

  <a href="/dev-otp.php" class="refresh-btn">↻ Refresh</a>

  <?php
  // Show parsed OTP entries
  $validEntries = array_filter($entries, fn($e) => !empty($e['code']));
  if (empty($validEntries)): ?>

  <div class="empty">
    <h3>No verification codes found yet</h3>
    <p>
      Register or request a new code, then refresh this page.<br><br>
      If no file appears after registering, the <code>logs/</code> folder may need to be created manually:<br><br>
      Create <code><?= htmlspecialchars(ROOT_DIR . '\\logs') ?></code> and make it writable.<br><br>
      Or check the log file path above. The OTP is also shown directly on the verify page in dev mode.
    </p>
  </div>

  <?php else:
    $first = true;
    foreach ($validEntries as $entry): ?>

  <?php if ($first): ?><div class="latest-label">⚡ Latest Code</div><?php endif; ?>
  <div class="otp-card <?= $first ? 'latest' : '' ?>">
    <div class="otp-code"><?= htmlspecialchars($entry['code']) ?></div>
    <div class="otp-info">
      <div class="otp-email"><?= htmlspecialchars($entry['email']) ?> <span class="type-pill type-<?= strtolower($entry['type']) ?>"><?= htmlspecialchars($entry['type']) ?></span></div>
      <div class="otp-meta">
        <span>👤 <?= htmlspecialchars($entry['name']) ?></span>
        <span>🕒 <?= htmlspecialchars($entry['time']) ?></span>
        <span class="<?= $entry['smtp'] === 'OK' ? 'smtp-ok' : 'smtp-fail' ?>">
          SMTP: <?= htmlspecialchars($entry['smtp']) ?>
          <?= $entry['smtp'] !== 'OK' ? ' (check mail_dev.log — code still valid)' : '' ?>
        </span>
      </div>
    </div>
    <button class="copy-btn" onclick="copyCode('<?= htmlspecialchars($entry['code']) ?>', this)">Copy</button>
  </div>
  <?php $first = false; endforeach; endif; ?>

  <hr class="divider">

  <!-- Raw log -->
  <div class="section-title">Raw Log (<?= file_exists($logFile) ? 'last 50 lines' : 'no file' ?>)</div>
  <div class="raw-log"><?php
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recent = array_slice($lines, -50);
        foreach (array_reverse($recent) as $line) {
            echo htmlspecialchars($line) . "\n";
        }
    } else {
        echo "Log file not created yet.\n\nThis means either:\n1. No registration attempt has been made yet\n2. The logs/ directory is not writable\n\nManually create: " . ROOT_DIR . "\\logs\\\n\nThe OTP code is displayed directly on the verify page in dev mode.";
    }
  ?></div>

  <!-- Manual log path fix instructions -->
  <hr class="divider">
  <div class="section-title">If the log file is missing</div>
  <p style="font-size:13px;color:#64748b;line-height:1.8;margin-bottom:12px">On Windows XAMPP, create the logs folder manually:</p>
  <div class="raw-log" style="max-height:80px">mkdir <?= htmlspecialchars(ROOT_DIR) ?>\logs</div>
  <p style="font-size:13px;color:#64748b;line-height:1.8;margin-top:12px">Or check via PHP that <code style="font-family:monospace;color:#93c5fd"><?= htmlspecialchars(ROOT_DIR) ?></code> is correct. OTPs are also shown inline on the verify page during development.</p>

</div>

<script>
function copyCode(code, btn) {
  navigator.clipboard.writeText(code).then(() => {
    const orig = btn.textContent;
    btn.textContent = '✓ Copied!';
    btn.style.background = 'rgba(52,211,153,.2)';
    btn.style.borderColor = 'rgba(52,211,153,.4)';
    btn.style.color = '#34d399';
    setTimeout(() => {
      btn.textContent = orig;
      btn.style.background = '';
      btn.style.borderColor = '';
      btn.style.color = '';
    }, 2000);
  });
}
// Auto-refresh every 8 seconds
setTimeout(() => location.reload(), 8000);
</script>
</body>
</html>
