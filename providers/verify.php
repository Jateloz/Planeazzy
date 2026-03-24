<?php
require_once dirname(__DIR__). '/config/config.php';
require_once dirname(__DIR__). '/services/Security.php';
Security::startSession();
if(!empty($_SESSION['provider_id'])&&!empty($_SESSION['is_provider'])){header('Location: /providers/dashboard.php');exit;}
$noSidebar=true; $pageTitle='Verify Provider Account';
include dirname(__DIR__). '/includes/header.php';
$csrf=Security::csrfToken();
$devOtp='';
if(APP_ENV==='development'){
  foreach([ROOT_DIR.'/logs/mail_dev.log',sys_get_temp_dir().'/planeazzy_logs/mail_dev.log'] as $lf){
    if(file_exists($lf)){$lines=array_reverse(file($lf,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES));foreach($lines as $line){if(preg_match('/OTP for[^:]+:\s*(\d{4,8})/',$line,$m)){$devOtp=$m[1];break 2;}}}
  }
}
?>
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:48px 20px;background:var(--bg-light)">
  <div style="width:100%;max-width:480px">
    <div class="auth-card slide-up">
      <div style="text-align:center;margin-bottom:22px">
        <div style="width:56px;height:56px;border-radius:14px;background:var(--teal-10);color:var(--teal);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:24px"><i class="fa-solid fa-envelope-circle-check"></i></div>
        <h2 style="font-size:22px;font-weight:900;color:var(--slate-900);margin-bottom:6px">Verify your provider account</h2>
        <p style="font-size:14px;color:var(--slate-500)">We sent a 6-digit code to <strong id="emailShow">your email</strong>. Valid for <?=OTP_EXPIRY_MINUTES?> minutes.</p>
      </div>
      <?php if($devOtp):?>
      <div style="background:#fefce8;border:1.5px dashed #d97706;border-radius:10px;padding:14px;text-align:center;margin-bottom:16px">
        <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;margin-bottom:6px">⚡ Dev Mode OTP</div>
        <div style="font-family:monospace;font-size:34px;font-weight:900;letter-spacing:12px;color:var(--teal)"><?=htmlspecialchars($devOtp)?></div>
      </div>
      <?php endif;?>
      <div id="alertBox" class="alert hidden"><i class="fa-solid fa-circle-exclamation"></i><span id="alertMsg"></span></div>
      <div class="otp-row" id="otpGrid">
        <?php for($i=0;$i<6;$i++):?>
          <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="off" <?=$i===0?'autofocus':''>>
          <?php endfor;?>
      </div>
      <button id="verifyBtn" class="btn btn-primary btn-full btn-lg" disabled onclick="doVerify()" style="background:var(--teal)"><i class="fa-solid fa-check-circle"></i> Verify Account</button>
      <div class="resend-row" style="margin-top:14px;text-align:center">
        <button onclick="doResend()" style="background:none;border:none;cursor:pointer;color:var(--slate-500);font-family:'Inter',sans-serif;font-size:13px">Resend code — <span style="color:var(--teal);font-weight:700;text-decoration:underline">click here</span></button>
      </div>
    </div>
  </div>
</main>
<?php include dirname(__DIR__). '/includes/footer.php'; ?>
<script>
OTP.init('#otpGrid');
const pEmail=sessionStorage.getItem('pz_prov_email');
if(pEmail){const el=document.getElementById('emailShow');if(el)el.textContent=pEmail;}
async function doVerify(){
  const code=OTP.value('#otpGrid');
  const id=sessionStorage.getItem('pz_prov_id')||0;
  const r=await post('/api/provider/verify-otp.php',{csrf_token:document.getElementById('csrfToken').value,provider_id:parseInt(id),otp:code},'verifyBtn','alertBox');
  if(!r)return;
  if(r.success){UI.alert('ok','Account verified! Redirecting…','alertBox');setTimeout(()=>location.href='/providers/dashboard.php',900);}
  else UI.alert('err',r.message||'Invalid code.','alertBox');
}
async function doResend(){
  const id=sessionStorage.getItem('pz_prov_id')||0;
  const r=await post('/api/provider/resend-otp.php',{csrf_token:document.getElementById('csrfToken').value,provider_id:parseInt(id)},null,'alertBox');
  if(r?.success)UI.alert('ok','New code sent!','alertBox');
}
</script>
