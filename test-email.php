<?php
/**
 * Planeazzy — Email Diagnostic Tool
 * Place in your htdocs/planeazzy_v5/ folder
 * Visit: http://localhost/planeazzy_v5/test-email.php
 * DELETE THIS FILE before going live on Hostinger
 */
require_once __DIR__ . '/config/config.php';

$result  = '';
$error   = '';
$sending = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to   = trim($_POST['to'] ?? '');
    $sending = true;

    // ── 1. Check cURL is available ─────────────────────────
    if (!function_exists('curl_init')) {
        $error = '❌ cURL is NOT enabled in PHP. Open php.ini and enable extension=curl then restart Apache.';
    }
    // ── 2. Check API key looks real ────────────────────────
    elseif (empty(SENDGRID_API_KEY) || SENDGRID_API_KEY === 'YOUR_SENDGRID_API_KEY_HERE' || strlen(SENDGRID_API_KEY) < 40) {
        $error = '❌ SENDGRID_API_KEY is not set properly in config/config.php. It should start with SG. and be about 70 characters long.';
    }
    // ── 3. Check from email looks real ────────────────────
    elseif (empty(SENDGRID_FROM_EMAIL) || !filter_var(SENDGRID_FROM_EMAIL, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ SENDGRID_FROM_EMAIL is not a valid email address in config/config.php.';
    }
    // ── 4. Check destination email ─────────────────────────
    elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ Please enter a valid destination email address.';
    }
    // ── 5. Try sending via SendGrid ───────────────────────
    else {
        $payload = [
            'personalizations' => [[
                'to'      => [['email' => $to]],
                'subject' => 'Planeazzy Email Test — ' . date('H:i:s'),
            ]],
            'from'    => [
                'email' => SENDGRID_FROM_EMAIL,
                'name'  => SENDGRID_FROM_NAME,
            ],
            'content' => [[
                'type'  => 'text/html',
                'value' => '
                    <div style="font-family:Inter,sans-serif;max-width:500px;margin:40px auto;background:#f0f9ff;border-radius:16px;overflow:hidden">
                        <div style="background:linear-gradient(135deg,#005ab4,#0873df);padding:28px 32px;text-align:center">
                            <h1 style="margin:0;color:#fff;font-size:22px;font-weight:900">Planeazzy</h1>
                            <p style="margin:4px 0 0;color:rgba(255,255,255,.7);font-size:12px;text-transform:uppercase;letter-spacing:1px">Clinical Precision</p>
                        </div>
                        <div style="padding:32px">
                            <h2 style="color:#0f172a;font-size:20px;margin-bottom:12px">✅ Email is working!</h2>
                            <p style="color:#475569;font-size:15px;line-height:1.7">
                                SendGrid is correctly configured and sending emails from <strong>' . htmlspecialchars(SENDGRID_FROM_EMAIL) . '</strong>.<br><br>
                                Sent at: <strong>' . date('Y-m-d H:i:s') . '</strong>
                            </p>
                        </div>
                        <div style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0">
                            <p style="margin:0;font-size:12px;color:#94a3b8">© ' . date('Y') . ' Planeazzy · Nairobi, Kenya</p>
                        </div>
                    </div>
                ',
            ]],
        ];

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . SENDGRID_API_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $result = '✅ Email sent successfully to <strong>' . htmlspecialchars($to) . '</strong>! Check your inbox (and spam folder).';
        } elseif ($httpCode === 401) {
            $error = '❌ HTTP 401 — API key is invalid or revoked. Go to SendGrid → Settings → API Keys and create a new one.';
        } elseif ($httpCode === 403) {
            $error = '❌ HTTP 403 — Sender email <strong>' . htmlspecialchars(SENDGRID_FROM_EMAIL) . '</strong> is not verified in SendGrid. Go to SendGrid → Settings → Sender Authentication and verify this email.';
        } elseif ($httpCode === 0 && strpos($curlErr, 'SSL') !== false) {
            $error = '❌ SSL Certificate error on localhost. See fix below.';
            $curlErrDisplay = $curlErr;
        } elseif ($httpCode === 0) {
            $error = '❌ Cannot reach api.sendgrid.com — check your internet connection. cURL error: ' . $curlErr;
        } else {
            $resp  = json_decode($body, true);
            $msgs  = implode(', ', array_column($resp['errors'] ?? [], 'message'));
            $error = '❌ HTTP ' . $httpCode . ' — ' . ($msgs ?: $body);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Planeazzy — Email Diagnostic</title>
  <style>
    body { font-family: Inter, system-ui, sans-serif; background: #f0f4ff; margin: 0; padding: 32px 16px; color: #1e293b; font-size: 14px; }
    .card { background: #fff; border-radius: 20px; padding: 36px; max-width: 560px; margin: 0 auto; box-shadow: 0 12px 40px rgba(0,0,0,.08); }
    h1 { font-size: 22px; font-weight: 900; color: #005ab4; letter-spacing: -.03em; margin: 0 0 6px; }
    p.sub { color: #64748b; margin: 0 0 28px; font-size: 13px; }
    label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #64748b; margin-bottom: 6px; }
    input[type=email] { width: 100%; box-sizing: border-box; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: 15px; outline: none; transition: border-color .2s; }
    input[type=email]:focus { border-color: #005ab4; box-shadow: 0 0 0 4px rgba(0,90,180,.1); }
    button { width: 100%; margin-top: 14px; padding: 13px; background: linear-gradient(135deg,#005ab4,#0873df); color: #fff; border: none; border-radius: 10px; font-family: inherit; font-size: 15px; font-weight: 700; cursor: pointer; }
    button:hover { opacity: .92; }
    .ok  { background: rgba(22,163,74,.08); border: 1px solid rgba(22,163,74,.2); color: #14532d; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; font-weight: 500; }
    .err { background: rgba(186,26,26,.08); border: 1px solid rgba(186,26,26,.2); color: #7f1d1d; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; }
    .info { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-top: 24px; font-size: 13px; color: #475569; }
    .info h3 { margin: 0 0 8px; font-size: 13px; color: #1e293b; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
    .warn { background: #fefce8; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 16px; margin-top: 16px; font-size: 13px; color: #92400e; }
  </style>
</head>
<body>
<div class="card">
  <h1>📧 Planeazzy Email Diagnostic</h1>
  <p class="sub">Tests your SendGrid connection. Delete this file before going live.</p>

  <?php if ($result): ?>
  <div class="ok"><?= $result ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="err"><?= $error ?></div>
  <?php endif; ?>

  <!-- Current config status -->
  <div style="background:#f8fafc;border-radius:10px;padding:14px 16px;margin-bottom:24px;font-size:13px">
    <strong>Current config.php values:</strong><br><br>
    <span style="color:<?= (strlen(SENDGRID_API_KEY) > 40 && SENDGRID_API_KEY !== 'YOUR_SENDGRID_API_KEY_HERE') ? '#16a34a' : '#dc2626' ?>">
      <?= (strlen(SENDGRID_API_KEY) > 40 && SENDGRID_API_KEY !== 'YOUR_SENDGRID_API_KEY_HERE') ? '✅' : '❌' ?>
      API Key: <?= strlen(SENDGRID_API_KEY) > 10 ? substr(SENDGRID_API_KEY, 0, 8) . '...' . substr(SENDGRID_API_KEY, -6) : 'NOT SET' ?>
    </span><br>
    <span style="color:<?= filter_var(SENDGRID_FROM_EMAIL, FILTER_VALIDATE_EMAIL) ? '#16a34a' : '#dc2626' ?>">
      <?= filter_var(SENDGRID_FROM_EMAIL, FILTER_VALIDATE_EMAIL) ? '✅' : '❌' ?>
      From Email: <?= htmlspecialchars(SENDGRID_FROM_EMAIL) ?>
    </span><br>
    <span style="color:#16a34a">
      <?= function_exists('curl_init') ? '✅' : '❌' ?>
      cURL: <?= function_exists('curl_init') ? 'Enabled' : 'NOT enabled — fix php.ini' ?>
    </span>
  </div>

  <form method="POST">
    <label>Send test email to</label>
    <input type="email" name="to" placeholder="your-real-email@gmail.com"
           value="<?= htmlspecialchars($_POST['to'] ?? '') ?>" required>
    <button type="submit">Send Test Email</button>
  </form>

  <!-- SSL fix for localhost -->
  <div class="info">
    <h3>🔧 If you get an SSL error on localhost</h3>
    1. Download <a href="https://curl.se/ca/cacert.pem" target="_blank">cacert.pem</a> and save it to <code>C:\xampp\php\extras\ssl\cacert.pem</code><br><br>
    2. Open <code>C:\xampp\php\php.ini</code> and find or add:<br>
    <code>curl.cainfo = "C:\xampp\php\extras\ssl\cacert.pem"</code><br><br>
    3. Restart Apache in XAMPP Control Panel
  </div>

  <!-- OTP in logs note -->
  <div class="warn">
    ⚡ <strong>Dev mode tip:</strong> While on localhost the OTP code is also written to
    <code>planeazzy_v5/logs/otp_codes.txt</code> — check that file if email does not arrive.
  </div>
</div>
</body>
</html>
