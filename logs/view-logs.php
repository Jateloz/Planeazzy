<?php
/**
 * Planeazzy — Dev Log Viewer
 * Access: /logs/view-logs.php?key=YOUR_APP_SECRET
 * Shows: OTP codes, email log, SMS log, messages log, PHP errors
 */
require_once dirname(__DIR__) . '/config/config.php';

$key = $_GET['key'] ?? '';
if (!$key || !hash_equals(APP_SECRET, $key)) {
    http_response_code(403);
    die('403 Forbidden — provide ?key=APP_SECRET');
}

$logDir   = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__) . '/logs/';
$logFiles = [
    'otp_codes.txt' => 'OTP Codes (email + SMS)',
    'mail_dev.log'  => 'Email Activity',
    'sms_dev.log'   => 'SMS Activity',
    'messages.log'  => 'Appointment Messages',
    'php_errors.log'=> 'PHP Errors',
];

$activeFile = $_GET['file'] ?? 'otp_codes.txt';
if (!array_key_exists($activeFile, $logFiles)) $activeFile = 'otp_codes.txt';

$filter  = trim($_GET['filter'] ?? '');
$lines   = 200;
$content = '';

$fp = $logDir . $activeFile;
if (file_exists($fp)) {
    $all = file($fp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $all = array_reverse($all); // newest first
    if ($filter) $all = array_filter($all, fn($l) => stripos($l, $filter) !== false);
    $content = implode("\n", array_slice(array_values($all), 0, $lines));
} else {
    $content = "Log file not found: $fp\nIt will be created when the first email/SMS/OTP is sent.";
}

// Extract OTPs for quick reference
$otps = [];
if ($activeFile === 'otp_codes.txt' || $activeFile === 'mail_dev.log') {
    $raw = file_exists($fp) ? file($fp, FILE_IGNORE_NEW_LINES) : [];
    foreach (array_reverse($raw) as $line) {
        if (preg_match('/otp=(\d{6})/i', $line, $m) ||
            preg_match('/: (\d{6})\s/i', $line, $m)) {
            preg_match('/to=([^\s]+)/i', $line, $em);
            $email = $em[1] ?? '?';
            $key2  = $email . '_' . $m[1];
            if (!isset($otps[$key2])) {
                preg_match('/\[([^\]]+)\]/', $line, $dm);
                $otps[$key2] = ['otp' => $m[1], 'email' => $email, 'time' => $dm[1] ?? ''];
                if (count($otps) >= 10) break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Planeazzy — Dev Logs</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh}
    .header{background:#1e293b;border-bottom:1px solid #334155;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
    .header h1{font-size:1rem;font-weight:700;color:#e2e8f0;display:flex;align-items:center;gap:8px}
    .badge{background:#005ab4;color:#fff;padding:2px 8px;border-radius:9999px;font-size:.625rem;font-weight:700}
    .tabs{display:flex;gap:6px;flex-wrap:wrap;padding:14px 24px;border-bottom:1px solid #1e293b}
    .tab{padding:6px 14px;border-radius:8px;font-size:.75rem;font-weight:600;text-decoration:none;color:#94a3b8;border:1px solid #334155}
    .tab:hover,.tab.active{background:#005ab4;color:#fff;border-color:#005ab4}
    .main{padding:18px 24px}
    .otp-strip{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}
    .otp-card{background:#1e293b;border:1.5px solid #334155;border-radius:10px;padding:10px 14px;min-width:160px}
    .otp-card-email{font-size:.625rem;color:#94a3b8;margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .otp-card-code{font-size:1.5rem;font-weight:900;letter-spacing:.2em;color:#22c55e;font-family:monospace}
    .otp-card-time{font-size:.5625rem;color:#64748b;margin-top:2px}
    .filter-row{display:flex;gap:8px;margin-bottom:12px;align-items:center}
    .filter-inp{flex:1;max-width:320px;padding:7px 12px;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-family:inherit;font-size:.8125rem;outline:none}
    .filter-inp:focus{border-color:#005ab4}
    .log-box{background:#1e293b;border:1px solid #334155;border-radius:10px;padding:16px;overflow-x:auto}
    pre{font-family:'Courier New',monospace;font-size:.75rem;line-height:1.7;white-space:pre-wrap;word-break:break-word}
    .hi-ok{color:#34d399}.hi-fail{color:#f87171}.hi-otp{color:#fbbf24}.hi-info{color:#60a5fa}
    .refresh{padding:6px 14px;background:#334155;border:1px solid #475569;border-radius:8px;color:#e2e8f0;font-family:inherit;font-size:.75rem;font-weight:600;cursor:pointer;text-decoration:none}
    .refresh:hover{background:#475569}
  </style>
</head>
<body>
<div class="header">
  <h1>&#128203; Planeazzy Dev Logs <span class="badge">DEVELOPMENT</span></h1>
  <div style="font-size:.6875rem;color:#64748b">Auto-refresh: <a href="?key=<?=htmlspecialchars(APP_SECRET)?>&file=<?=urlencode($activeFile)?>&filter=<?=urlencode($filter)?>" style="color:#60a5fa">Reload</a></div>
</div>

<div class="tabs">
  <?php foreach($logFiles as $fn=>$label):?>
  <a href="?key=<?=htmlspecialchars(APP_SECRET)?>&file=<?=urlencode($fn)?>" class="tab <?=$fn===$activeFile?'active':''?>"><?=$label?></a>
  <?php endforeach;?>
</div>

<div class="main">
  <?php if(!empty($otps)&&in_array($activeFile,['otp_codes.txt','mail_dev.log'])): ?>
  <div style="margin-bottom:12px;font-size:.625rem;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:#64748b">Recent OTP Codes</div>
  <div class="otp-strip">
    <?php foreach($otps as $o):?>
    <div class="otp-card">
      <div class="otp-card-email"><?=htmlspecialchars($o['email'])?></div>
      <div class="otp-card-code"><?=htmlspecialchars($o['otp'])?></div>
      <div class="otp-card-time"><?=htmlspecialchars($o['time'])?></div>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>

  <div class="filter-row">
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex:1">
      <input type="hidden" name="key" value="<?=htmlspecialchars(APP_SECRET)?>">
      <input type="hidden" name="file" value="<?=htmlspecialchars($activeFile)?>">
      <input class="filter-inp" type="text" name="filter" value="<?=htmlspecialchars($filter)?>" placeholder="Filter by email, OTP, status…">
      <button class="refresh" type="submit">Filter</button>
      <?php if($filter):?><a href="?key=<?=htmlspecialchars(APP_SECRET)?>&file=<?=urlencode($activeFile)?>" class="refresh">Clear</a><?php endif;?>
    </form>
    <div style="font-size:.625rem;color:#64748b">Last <?=$lines?> entries · newest first</div>
  </div>

  <div class="log-box">
    <pre><?php
    foreach(explode("\n", $content) as $line):
      if(!trim($line)) continue;
      $cls = '';
      if (stripos($line,'YES')!==false||stripos($line,'OK')!==false||stripos($line,'sent')!==false) $cls='hi-ok';
      elseif (stripos($line,'FAIL')!==false||stripos($line,'ERROR')!==false||stripos($line,'NO')!==false) $cls='hi-fail';
      elseif (preg_match('/\d{6}/',$line)) $cls='hi-otp';
      elseif (stripos($line,'INFO')!==false||stripos($line,'WARN')!==false) $cls='hi-info';
      if($cls) echo "<span class=\"$cls\">" . htmlspecialchars($line) . "</span>\n";
      else echo htmlspecialchars($line) . "\n";
    endforeach;
    ?></pre>
  </div>
</div>

<script>
// Auto-highlight OTP codes
document.querySelectorAll('pre span').forEach(el => {
  el.innerHTML = el.innerHTML.replace(/\b(\d{6})\b/g, '<strong style="font-size:1rem;letter-spacing:.1em;background:#1a2e1a;padding:0 4px;border-radius:3px">$1</strong>');
});
</script>
</body>
</html>
