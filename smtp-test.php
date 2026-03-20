<?php
/**
 * Planeazzy — SMTP Diagnostic v2
 * Visit: http://localhost/smtp-test.php
 * DELETE before production!
 */
$ip=$_SERVER['REMOTE_ADDR']??'0.0.0.0';
$allowed=['127.0.0.1','::1','0.0.0.0','::ffff:127.0.0.1'];
if(php_sapi_name()!=='cli'&&!in_array($ip,$allowed)){http_response_code(403);die('Localhost only.');}
require_once __DIR__.'/config/config.php';

$testResult=null;
$sendTo=trim($_GET['to']??'');
if(isset($_GET['send'])&&filter_var($sendTo,FILTER_VALIDATE_EMAIL)){
    require_once __DIR__.'/services/Mailer.php';
    $testOtp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
    $sent=Mailer::sendOtp($sendTo,'Test User',$testOtp);
    $testResult=['sent'=>$sent,'otp'=>$testOtp,'to'=>$sendTo];
}

// DNS check
set_error_handler(function(){});
$resolved=gethostbyname(MAIL_HOST);
restore_error_handler();
$dnsOk=$resolved!==MAIL_HOST;
$resolvedIp=$dnsOk?$resolved:'';

// TCP check — try hostname then known Mailtrap IPs
$mailtrapIps=['18.156.81.133','3.68.106.62','52.29.173.68','18.184.39.246'];
$tcpOk=false;$tcpMsg='';$workingIp='';$workingPort=MAIL_PORT;
$errno=0;$errstr='';
$sock=@fsockopen(MAIL_HOST,MAIL_PORT,$errno,$errstr,6);
if($sock){$b=fgets($sock,512);fclose($sock);$tcpOk=str_starts_with(trim($b),'220');$tcpMsg='Hostname connected: '.trim($b);$workingIp=MAIL_HOST;}
if(!$tcpOk){
    foreach($mailtrapIps as $tip){
        foreach([MAIL_PORT,587,465] as $tp){
            $s=@fsockopen($tip,$tp,$e,$es,4);
            if($s){$b2=fgets($s,512);fclose($s);if(str_starts_with(trim($b2),'220')){$tcpOk=true;$workingIp=$tip;$workingPort=$tp;$tcpMsg="Direct IP $tip:$tp works! Banner: ".trim($b2);break 2;}}
        }
    }
    if(!$tcpOk)$tcpMsg='All attempts failed. Error: '.$errstr.' ('.$errno.')';
}

// Auth check
$authOk=false;$authMsg='Skipped (fix TCP first)';
if($tcpOk){
    $s2=@fsockopen($workingIp,$workingPort,$e2,$es2,8);
    if($s2){
        stream_set_timeout($s2,8);
        $rd=function($sk){$r='';while($l=fgets($sk,512)){$r.=$l;if(strlen($l)>=4&&$l[3]===' ')break;}return trim($r);};
        $rd($s2);
        fputs($s2,"EHLO planeazzy.local\r\n");$e=$rd($s2);
        if(str_starts_with($e,'250')){
            fputs($s2,"STARTTLS\r\n");$t=$rd($s2);
            if(str_starts_with($t,'220')){@stream_socket_enable_crypto($s2,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);fputs($s2,"EHLO planeazzy.local\r\n");$rd($s2);}
            fputs($s2,"AUTH LOGIN\r\n");$r1=$rd($s2);
            if(str_starts_with($r1,'334')){
                fputs($s2,base64_encode(MAIL_USER)."\r\n");$r2=$rd($s2);
                if(str_starts_with($r2,'334')){
                    fputs($s2,base64_encode(MAIL_PASS)."\r\n");$r3=$rd($s2);
                    $authOk=str_starts_with($r3,'235');
                    $authMsg=$authOk?'Authentication successful':'Failed: '.$r3;
                }else{$authMsg='Username rejected: '.$r2;}
            }else{$authMsg='AUTH LOGIN rejected: '.$r1;}
        }else{$authMsg='EHLO failed: '.$e;}
        fputs($s2,"QUIT\r\n");fclose($s2);
    }
}

$logDir=ROOT_DIR.'/logs/';
$logFile=$logDir.'mail_dev.log';
$logLines=file_exists($logFile)?implode('',array_slice(file($logFile),-15)):'(empty)';
$isWin=DIRECTORY_SEPARATOR==='\\';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Planeazzy SMTP Diagnostic</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f4ff;padding:30px 16px;color:#0f172a}
.w{max-width:860px;margin:0 auto;display:flex;flex-direction:column;gap:18px}
.card{background:#fff;border-radius:16px;padding:26px;box-shadow:0 4px 20px rgba(25,120,229,.09);border:1px solid #e2e8f0}
.card.urgent{border:2px solid #ef4444}
h1{font-size:24px;font-weight:900;letter-spacing:-.04em}h1 span{color:#1978e5}
h2{font-size:13px;font-weight:800;margin-bottom:14px;text-transform:uppercase;letter-spacing:.6px;color:#475569}
.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:99px;font-size:12px;font-weight:700}
.ok{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.wrn{background:#fefce8;color:#854d0e;border:1px solid #fde68a}
.inf{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
.row{display:grid;grid-template-columns:26px 1fr;gap:12px;padding:13px 0;border-bottom:1px solid #f8fafc;align-items:start}
.row:last-child{border-bottom:none}
.rl{font-weight:700;font-size:14px;margin-bottom:3px}
.rd{font-size:13px;color:#475569;font-family:monospace;word-break:break-all;line-height:1.55}
.tip{font-size:13px;padding:10px 14px;border-radius:10px;margin-top:8px;line-height:1.75}
code{background:#f1f5f9;padding:1px 6px;border-radius:5px;font-family:monospace;font-size:12px;color:#1978e5}
pre{background:#f1f5f9;padding:10px 14px;border-radius:8px;font-size:12px;overflow-x:auto;margin:6px 0;color:#0f172a;line-height:1.6}
form{display:flex;gap:10px;flex-wrap:wrap}
input[type=email]{flex:1;min-width:200px;height:44px;padding:0 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;outline:none}
input[type=email]:focus{border-color:#1978e5;box-shadow:0 0 0 3px rgba(25,120,229,.1)}
.btn{height:44px;padding:0 22px;background:linear-gradient(135deg,#1978e5,#0e7490);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer}
.rbox{margin-top:12px;padding:13px 16px;border-radius:10px;font-size:14px;font-weight:600}
.logbox{background:#0f172a;color:#a5f3fc;font-family:monospace;font-size:12px;padding:14px;border-radius:10px;white-space:pre-wrap;max-height:220px;overflow-y:auto;line-height:1.6}
.step{display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f1f5f9;align-items:flex-start}
.step:last-child{border-bottom:none}
.sn{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1978e5,#0e7490);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;flex-shrink:0}
.st{font-weight:800;font-size:14px;margin-bottom:5px}
.sd{font-size:13px;color:#475569;line-height:1.7}
.del{background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:10px 14px;font-size:13px;font-weight:700;color:#991b1b;margin-top:10px}
</style>
</head>
<body>
<div class="w">

<div class="card">
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:8px">
<h1>&#9993; <span>Planeazzy</span> SMTP Diagnostic</h1>
<span class="badge <?= ($tcpOk&&$authOk)?'ok':($tcpOk?'wrn':'err') ?>">
<?= ($tcpOk&&$authOk)?'&#10003; Ready':'&#10007; '.($tcpOk?'Auth Issue':'DNS Failure') ?>
</span>
</div>
<p style="font-size:14px;color:#64748b">DNS &#8594; TCP &#8594; TLS &#8594; Auth &#8594; Send pipeline test.</p>
<div class="del">&#9888; DELETE smtp-test.php before going live!</div>
</div>

<?php if($isWin&&!$dnsOk): ?>
<div class="card urgent">
<h2 style="color:#dc2626">&#128680; Root Cause: Windows DNS Cannot Resolve Mailtrap</h2>
<p style="font-size:14px;color:#475569;margin-bottom:14px">
Your XAMPP cannot look up <code>sandbox.smtp.mailtrap.io</code>. Fix it with the hosts file method below (2 minutes):
</p>
<div class="tip ok">
<strong>&#9989; Fix — Add to Windows Hosts File</strong><br>
1. Press <code>Win + S</code> &#8594; type <strong>Notepad</strong> &#8594; right-click &#8594; <strong>Run as administrator</strong><br>
2. Open: <code>C:\Windows\System32\drivers\etc\hosts</code> (change filter to "All Files")<br>
3. Add this line at the bottom and save:
<pre>18.156.81.133   sandbox.smtp.mailtrap.io</pre>
4. Open <strong>Command Prompt as Administrator</strong> and run: <code>ipconfig /flushdns</code><br>
5. Come back and click <strong>Send Test Email</strong> below.
</div>
<div class="tip wrn" style="margin-top:10px">
<strong>Alternative — Change DNS to Google</strong><br>
Control Panel &#8594; Network &#8594; Adapter &#8594; IPv4 Properties &#8594; Use custom DNS: <code>8.8.8.8</code> / <code>8.8.4.4</code>
</div>
</div>
<?php endif; ?>

<div class="card">
<h2>&#128233; Send Test Email</h2>
<form method="GET" action=""><input type="email" name="to" value="<?= htmlspecialchars($sendTo) ?>" placeholder="your@email.com" required><input type="hidden" name="send" value="1"><button class="btn" type="submit">Send Test Email</button></form>
<?php if($testResult): ?>
<div class="rbox <?= $testResult['sent']?'ok':'err' ?>">
<?php if($testResult['sent']): ?>&#10003; Sent to <strong><?= htmlspecialchars($testResult['to']) ?></strong>! Check <a href="https://mailtrap.io" target="_blank">Mailtrap inbox</a>. OTP: <code><?= $testResult['otp'] ?></code>
<?php else: ?>&#10007; Still failing. Apply the hosts file fix above, flush DNS, then retry. OTP in log: <code><?= $testResult['otp'] ?></code><?php endif; ?>
</div>
<?php endif; ?>
</div>

<div class="card">
<h2>&#128270; Pipeline Checks</h2>
<div class="row"><div><?= $dnsOk?'&#9989;':'&#10060;' ?></div><div>
<div class="rl">DNS Resolution</div>
<div class="rd"><?= $dnsOk?"Resolved: $resolvedIp &#10003;":htmlspecialchars("FAILED: Windows cannot resolve ".MAIL_HOST) ?></div>
<?php if(!$dnsOk): ?><div class="tip err">Apply the hosts file fix above. This is the root cause.</div><?php endif; ?>
</div></div>

<div class="row"><div><?= $tcpOk?'&#9989;':'&#10060;' ?></div><div>
<div class="rl">TCP Connection</div>
<div class="rd"><?= htmlspecialchars($tcpMsg) ?></div>
<?php if($tcpOk&&$workingIp!==MAIL_HOST): ?>
<div class="tip wrn">Connected via direct IP (DNS bypassed). Add hosts file entry to make permanent.</div>
<?php endif; ?>
</div></div>

<div class="row"><div><?= $authOk?'&#9989;':($tcpOk?'&#10060;':'&#9866;') ?></div><div>
<div class="rl">SMTP Authentication</div>
<div class="rd"><?= htmlspecialchars($authMsg) ?></div>
<?php if($tcpOk&&!$authOk): ?><div class="tip err">Go to <a href="https://mailtrap.io" target="_blank">mailtrap.io</a> &#8594; Inboxes &#8594; SMTP Settings &#8594; copy exact credentials into config.php.</div><?php endif; ?>
</div></div>

<div class="row"><div><?= extension_loaded('openssl')?'&#9989;':'&#9888;' ?></div><div>
<div class="rl">OpenSSL Extension</div>
<div class="rd"><?= extension_loaded('openssl')?'Loaded &#10003;':'Not loaded — edit php.ini: uncomment extension=openssl' ?></div>
</div></div>
</div>

<div class="card">
<h2>&#128220; logs/mail_dev.log</h2>
<div class="logbox"><?= htmlspecialchars($logLines) ?></div>
<div class="tip inf" style="margin-top:10px"><strong>OTPs are always logged here.</strong> Copy the OTP from this log to test your registration right now, even before email is fixed.</div>
</div>

<div class="card">
<h2>&#9881; Config Values</h2>
<table style="width:100%;border-collapse:collapse;font-size:13px">
<?php foreach(['MAIL_HOST'=>MAIL_HOST,'MAIL_PORT'=>(string)MAIL_PORT,'MAIL_USER'=>MAIL_USER?substr(MAIL_USER,0,4).'****':'(empty)','MAIL_PASS'=>MAIL_PASS?'****'.substr(MAIL_PASS,-4):'(empty)','MAIL_FROM'=>MAIL_FROM,'PHP Version'=>PHP_VERSION,'OS'=>PHP_OS,'DNS resolves?'=>$dnsOk?"YES $resolvedIp":'NO (hosts file fix needed)','ROOT_DIR'=>ROOT_DIR] as $k=>$v): ?>
<tr style="border-bottom:1px solid #f1f5f9"><td style="padding:7px 10px;font-weight:700;color:#64748b;width:160px"><?=htmlspecialchars($k)?></td><td style="padding:7px 10px;font-family:monospace;color:#1978e5"><?=htmlspecialchars($v)?></td></tr>
<?php endforeach; ?>
</table>
</div>

</div>

<?php
echo "<hr style='margin:28px 0;border-color:#e2e8f0'>";
echo "<h2 style='font-family:sans-serif;font-size:18px;font-weight:800;margin-bottom:14px'>📁 Log File Locations</h2>";

$candidates = [
    ROOT_DIR . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR,
    sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'planeazzy_logs' . DIRECTORY_SEPARATOR,
];

foreach ($candidates as $dir) {
    $logFile = $dir . 'mail_dev.log';
    $exists  = file_exists($logFile);
    $writable= is_writable($dir) || (!is_dir($dir) && @mkdir($dir, 0775, true));
    $color   = $exists ? '#059669' : ($writable ? '#d97706' : '#dc2626');
    $status  = $exists ? '✓ Found' : ($writable ? '○ Writable (no log yet)' : '✗ Not writable');
    echo "<div style='font-family:monospace;font-size:13px;padding:10px 14px;margin-bottom:8px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0'>";
    echo "<span style='color:{$color};font-weight:700'>{$status}</span> — " . htmlspecialchars($logFile);
    echo "</div>";
    if ($exists) {
        $lines = array_slice(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -5);
        echo "<div style='font-family:monospace;font-size:12px;background:#0f172a;color:#94a3b8;padding:12px;border-radius:8px;margin-bottom:10px;white-space:pre'>";
        echo htmlspecialchars(implode("\n", array_reverse($lines)));
        echo "</div>";
    }
}
echo "<p style='font-size:13px;color:#64748b;margin-top:12px'>OTP viewer: <a href='/dev-otp.php' style='color:#1978e5'>/dev-otp.php</a></p>";
?>
</body></html>
