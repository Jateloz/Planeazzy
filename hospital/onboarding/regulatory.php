<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
require_once dirname(__DIR__, 2) . '/services/HospitalMailer.php';
Security::startSession();
if (empty($_SESSION['hospital_id'])) { header('Location: /hospital/onboarding/signup.php'); exit; }
$hid  = (int)$_SESSION['hospital_id'];
$csrf = Security::csrfToken(); $error = '';
$db   = Database::getInstance();
$hosp = $db->fetchOne('SELECT * FROM hospital_providers WHERE id=:id',[':id'=>$hid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok  = trim($_POST['csrf_token'] ?? '');
    $kn   = Security::clean($_POST['kmpdc_number'] ?? '');
    $tp   = Security::clean($_POST['tax_pin'] ?? '');
    $cr   = Security::clean($_POST['cr_number'] ?? '');
    $decl = !empty($_POST['declaration']);
    if (!Security::verifyCsrf($tok))  $error = 'Security error.';
    elseif (!$kn)                      $error = 'KMPDC licence number is required.';
    elseif (!$tp)                      $error = 'KRA PIN number is required.';
    elseif (!$decl)                    $error = 'You must sign the declaration.';
    else {
        $uploadDir = UPLOAD_DIR . 'regulatory/' . $hid . '/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0750, true);
        $uploads = [];
        foreach (['kmpdc_doc'=>'kmpdc_doc_path','tax_doc'=>'tax_doc_path','cr_doc'=>'cr_doc_path'] as $field=>$col) {
            if (!empty($_FILES[$field]['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES[$field]['name'],PATHINFO_EXTENSION));
                if (!in_array($ext,['pdf','jpg','jpeg','png'])) { $error = 'Only PDF, JPG, PNG files are allowed.'; break; }
                if ($_FILES[$field]['size'] > 10*1024*1024) { $error = 'Max file size is 10 MB.'; break; }
                $fname = $field.'_'.time().'.'.$ext;
                move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir.$fname);
                $uploads[$col] = 'regulatory/'.$hid.'/'.$fname;
            }
        }
        if (!$error) {
            $sets = 'kmpdc_number=:kn,tax_pin=:tp,cr_number=:cr,onboarding_step=7,status="under_review"';
            $params = [':kn'=>$kn,':tp'=>$tp,':cr'=>$cr,':id'=>$hid];
            foreach ($uploads as $col=>$path) { $sets.=",$col=:$col"; $params[":$col"]=$path; }
            $db->query("UPDATE hospital_providers SET $sets WHERE id=:id",$params);
            HospitalMailer::sendUnderReview($hosp['admin_email'],$hosp['admin_name'],$hosp['facility_name']??'Your Facility',$hid);
            header('Location: /hospital/onboarding/pending.php'); exit;
        }
    }
}
$cpStep = 7; $cpTitle = 'Regulatory Verification';
include __DIR__ . '/_head.php';
?>
<style>
.reg-layout{max-width:1100px;margin:0 auto;padding:40px 40px 60px;display:grid;grid-template-columns:1fr 340px;gap:40px;align-items:start}
.reg-doc-section{background:var(--cp-surface-container-lowest);border-radius:var(--cp-r-xl);padding:26px;border:1px solid rgba(193,198,213,.15);margin-bottom:16px}
@media(max-width:960px){.reg-layout{grid-template-columns:1fr;padding:24px 20px}}
</style>

<!-- Progress rail -->
<div class="cp-progress-rail"><div class="cp-progress-fill" style="width:77%"></div></div>

<header class="cp-topnav">
  <a href="\hospital\onboarding\profile.php" class="cp-topnav-brand" data-en="Clinical Precision" data-sw="Usahihi wa Kliniki">Clinical Precision</a>
  <div class="cp-topnav-actions">
    <button class="cp-lang-btn" onclick="history.back()" style="cursor:pointer">
  <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>
  <span>Back</span>
</button>
  </div>
</header>

<div class="reg-layout">
  <div>
    <h1 class="cp-h2" style="margin-bottom:8px" data-en="Regulatory Verification" data-sw="Uthibitisho wa Udhibiti">Regulatory Verification</h1>
    <p class="cp-body" style="margin-bottom:32px;max-width:580px"
       data-en="Submit your regulatory documents for KEPDA compliance review. All documents are encrypted and stored securely."
       data-sw="Wasilisha hati zako za udhibiti kwa mapitio ya utiifu wa KEPDA. Hati zote zimesimbwa na kuhifadhiwa kwa usalama.">
      Submit your regulatory documents for KEPDA compliance review. All documents are encrypted and stored securely.
    </p>

    <?php if ($error): ?>
    <div class="cp-alert cp-alert-error" style="margin-bottom:20px"><span class="material-symbols-outlined" style="font-size:18px;flex-shrink:0">error</span><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="regForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

      <!-- KMPDC -->
      <div class="reg-doc-section">
        <div style="display:flex;align-items:flex-start;gap:16px">
          <div style="width:46px;height:46px;border-radius:12px;background:var(--cp-primary-fixed);color:var(--cp-primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <span class="material-symbols-outlined">gavel</span>
          </div>
          <div style="flex:1">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
              <div>
                <h3 class="cp-h4" data-en="KMPDC Medical Licence" data-sw="Leseni ya Matibabu ya KMPDC">KMPDC Medical Licence</h3>
                <p style="font-size:.8125rem;color:var(--cp-on-surface-var)" data-en="Kenya Medical Practitioners and Dentists Council" data-sw="Baraza la Madaktari na Madaktari wa Meno Kenya">Kenya Medical Practitioners and Dentists Council</p>
              </div>
              <span class="cp-badge cp-badge-primary" data-en="Required" data-sw="Inahitajika">Required</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:end">
              <div class="cp-field" style="margin-bottom:0">
                <label class="cp-label-text" data-en="Licence Number" data-sw="Nambari ya Leseni">Licence Number</label>
                <input class="cp-input" type="text" name="kmpdc_number" placeholder="KMPDC/0000/0000" required value="<?= htmlspecialchars($_POST['kmpdc_number'] ?? $hosp['kmpdc_number'] ?? '') ?>">
              </div>
              <div>
                <label class="cp-label-text" data-en="Upload Certificate (PDF/JPG)" data-sw="Pakia Cheti (PDF/JPG)">Upload Certificate (PDF/JPG)</label>
                <div class="cp-file-drop" onclick="document.getElementById('kmpdc_file').click()">
                  <input type="file" id="kmpdc_file" name="kmpdc_doc" accept=".pdf,.jpg,.jpeg,.png" onchange="showFile(this,'kmpdc_lbl')">
                  <span class="material-symbols-outlined" style="font-size:24px;color:var(--cp-outline);display:block;margin-bottom:4px">upload_file</span>
                  <p id="kmpdc_lbl" style="font-size:.8125rem;color:var(--cp-outline)" data-en="Click to upload" data-sw="Bonyeza kupakia">Click to upload</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- KRA PIN -->
      <div class="reg-doc-section">
        <div style="display:flex;align-items:flex-start;gap:16px">
          <div style="width:46px;height:46px;border-radius:12px;background:rgba(0,106,106,.12);color:var(--cp-secondary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <span class="material-symbols-outlined">receipt_long</span>
          </div>
          <div style="flex:1">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
              <div>
                <h3 class="cp-h4" data-en="KRA PIN Certificate" data-sw="Cheti cha KRA PIN">KRA PIN Certificate</h3>
                <p style="font-size:.8125rem;color:var(--cp-on-surface-var)" data-en="Kenya Revenue Authority Tax PIN" data-sw="KRA PIN ya Mamlaka ya Mapato Kenya">Kenya Revenue Authority Tax PIN</p>
              </div>
              <span class="cp-badge cp-badge-primary" data-en="Required" data-sw="Inahitajika">Required</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:end">
              <div class="cp-field" style="margin-bottom:0">
                <label class="cp-label-text" data-en="KRA PIN Number" data-sw="Nambari ya KRA PIN">KRA PIN Number</label>
                <input class="cp-input" type="text" name="tax_pin" placeholder="P0000000000X" required value="<?= htmlspecialchars($_POST['tax_pin'] ?? $hosp['tax_pin'] ?? '') ?>">
              </div>
              <div>
                <label class="cp-label-text" data-en="Upload Certificate" data-sw="Pakia Cheti">Upload Certificate</label>
                <div class="cp-file-drop" onclick="document.getElementById('tax_file').click()">
                  <input type="file" id="tax_file" name="tax_doc" accept=".pdf,.jpg,.jpeg,.png" onchange="showFile(this,'tax_lbl')">
                  <span class="material-symbols-outlined" style="font-size:24px;color:var(--cp-outline);display:block;margin-bottom:4px">upload_file</span>
                  <p id="tax_lbl" style="font-size:.8125rem;color:var(--cp-outline)" data-en="Click to upload" data-sw="Bonyeza kupakia">Click to upload</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- CR12 -->
      <div class="reg-doc-section">
        <div style="display:flex;align-items:flex-start;gap:16px">
          <div style="width:46px;height:46px;border-radius:12px;background:rgba(124,58,237,.1);color:#7c3aed;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <span class="material-symbols-outlined">verified</span>
          </div>
          <div style="flex:1">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
              <div>
                <h3 class="cp-h4" data-en="Certificate of Registration (CR12)" data-sw="Cheti cha Usajili (CR12)">Certificate of Registration (CR12)</h3>
                <p style="font-size:.8125rem;color:var(--cp-on-surface-var)" data-en="Business Registration Certificate" data-sw="Cheti cha Usajili wa Biashara">Business Registration Certificate</p>
              </div>
              <span class="cp-badge" style="background:rgba(124,58,237,.1);color:#7c3aed" data-en="Optional" data-sw="Si Lazima">Optional</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:end">
              <div class="cp-field" style="margin-bottom:0">
                <label class="cp-label-text" data-en="CR Number" data-sw="Nambari ya CR">CR Number</label>
                <input class="cp-input" type="text" name="cr_number" placeholder="CPR/2020/000000" value="<?= htmlspecialchars($_POST['cr_number'] ?? $hosp['cr_number'] ?? '') ?>">
              </div>
              <div>
                <label class="cp-label-text" data-en="Upload Certificate" data-sw="Pakia Cheti">Upload Certificate</label>
                <div class="cp-file-drop" onclick="document.getElementById('cr_file').click()">
                  <input type="file" id="cr_file" name="cr_doc" accept=".pdf,.jpg,.jpeg,.png" onchange="showFile(this,'cr_lbl')">
                  <span class="material-symbols-outlined" style="font-size:24px;color:var(--cp-outline);display:block;margin-bottom:4px">upload_file</span>
                  <p id="cr_lbl" style="font-size:.8125rem;color:var(--cp-outline)" data-en="Click to upload" data-sw="Bonyeza kupakia">Click to upload</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Declaration -->
      <div class="cp-card" style="padding:20px;margin-bottom:24px">
        <label class="cp-check-row" id="declRow">
          <input type="checkbox" name="declaration" id="declChk" style="width:17px;height:17px;accent-color:var(--cp-primary)">
          <span style="font-size:.875rem;color:var(--cp-on-surface-var)"
                data-en="I declare that all documents submitted are genuine and accurate. I understand that providing false information is a criminal offence."
                data-sw="Natangaza kwamba hati zote zilizowasilishwa ni za kweli na sahihi. Naelewa kwamba kutoa taarifa za uongo ni kosa la jinai.">
            I declare that all documents submitted are genuine and accurate. I understand that providing false information is a criminal offence.
          </span>
        </label>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:12px;justify-content:flex-end">
        <a href="/hospital/onboarding/departments.php" class="cp-btn cp-btn-ghost" data-en="← Back" data-sw="← Rudi">← Back</a>
        <button type="submit" class="cp-btn cp-btn-primary cp-btn-lg">
          <span class="material-symbols-outlined">send</span>
          <span data-en="Submit for Verification" data-sw="Wasilisha kwa Uthibitisho">Submit for Verification</span>
        </button>
      </div>
    </form>
  </div>

  <!-- Right sidebar: What happens next -->
  <aside style="position:sticky;top:84px;display:flex;flex-direction:column;gap:16px">
    <div class="cp-card" style="padding:26px">
      <h3 class="cp-h4" style="margin-bottom:16px" data-en="What happens next?" data-sw="Kinachofuata ni nini?">What happens next?</h3>
      <?php foreach([
        ['1','hourglass_top','Document Review','Mapitio ya Hati','24–48 hrs','Masaa 24–48'],
        ['2','gavel','KMPDC Cross-check','Uhakiki wa KMPDC','1–2 days','Siku 1–2'],
        ['3','verified','KEPDA Approval','Idhini ya KEPDA','24 hrs','Masaa 24'],
        ['4','celebration','Go Live!','Anza Kutumika!','Instant','Papo hapo'],
      ] as [$n,$ic,$en,$sw,$t,$tSw]): ?>
      <div style="display:flex;gap:12px;margin-bottom:14px;align-items:flex-start">
        <div style="width:26px;height:26px;border-radius:50%;background:var(--cp-primary-fixed);color:var(--cp-primary);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;flex-shrink:0"><?=$n?></div>
        <div>
          <p style="font-weight:600;font-size:.875rem;margin-bottom:1px" data-en="<?=htmlspecialchars($en)?>" data-sw="<?=htmlspecialchars($sw)?>"><?=$en?></p>
          <p style="font-size:.75rem;color:var(--cp-on-surface-var)" data-en="~<?=$t?>" data-sw="~<?=$tSw?>">~<?=$t?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="cp-card" style="padding:24px;background:var(--cp-primary-fixed);border-color:rgba(0,90,180,.15)">
      <span class="material-symbols-outlined msf" style="color:var(--cp-primary);display:block;margin-bottom:10px">lock</span>
      <p style="font-weight:700;margin-bottom:6px" data-en="Secure Document Vault" data-sw="Hifadhi Salama ya Hati">Secure Document Vault</p>
      <p style="font-size:.8125rem;color:var(--cp-on-surface-var)" data-en="All uploaded documents are encrypted with AES-256 and stored in compliance with KEPDA data sovereignty requirements." data-sw="Hati zote zilizopakiwa zimesimbwa kwa AES-256 na kuhifadhiwa kwa kufuata mahitaji ya umiliki wa data ya KEPDA.">All uploaded documents are encrypted with AES-256 and stored in compliance with KEPDA data sovereignty requirements.</p>
    </div>
  </aside>
</div>

<footer class="cp-footer">
  <span data-en="© 2025 Clinical Precision Framework. KEPDA Compliant." data-sw="© 2025 Mfumo wa Usahihi wa Kliniki. Inazingatia KEPDA.">© 2025 Clinical Precision Framework. KEPDA Compliant.</span>
  <div class="cp-footer-links"><a href="#" data-en="Privacy Policy" data-sw="Sera ya Faragha">Privacy Policy</a><a href="#" data-en="Terms of Service" data-sw="Masharti ya Huduma">Terms of Service</a></div>
</footer>

<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  if(typeof Lang!=='undefined')Lang.init();
  document.getElementById('langToggle')?.addEventListener('click',()=>Lang.toggle());
  document.getElementById('declChk')?.addEventListener('change',function(){document.getElementById('declRow')?.classList.toggle('checked',this.checked);});
});
function showFile(inp, lblId) {
  const f = inp.files[0]; const lbl = document.getElementById(lblId);
  if (lbl && f) { lbl.textContent = f.name + ' (' + Math.round(f.size/1024) + ' KB)'; }
  inp.closest('.cp-file-drop').style.borderColor = 'var(--cp-primary)';
  inp.closest('.cp-file-drop').style.background = 'rgba(0,90,180,.05)';
}
</script>
</body></html>
