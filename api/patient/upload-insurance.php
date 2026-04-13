<?php
/**
 * Planeazzy — POST /api/patient/upload-insurance.php
 * Accepts multipart/form-data with insurance document upload.
 * Stores file in /storage/uploads/insurance/{patient_id}/
 */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit;
}

Security::startSession();
if (empty($_SESSION['patient_id']) || empty($_SESSION['authenticated'])) {
    http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
}

if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Security token invalid.']); exit;
}

$pid = (int)$_SESSION['patient_id'];

// Validate form fields
$providerName  = Security::clean($_POST['provider_name']  ?? '');
$policyNumber  = Security::clean($_POST['policy_number']  ?? '');
$memberNumber  = Security::clean($_POST['member_number']  ?? '');
$coverageType  = Security::clean($_POST['coverage_type']  ?? '');
$expiryDate    = Security::clean($_POST['expiry_date']    ?? '');
$notes         = Security::clean($_POST['notes']          ?? '');

if (empty($providerName)) {
    echo json_encode(['success'=>false,'message'=>'Insurance provider name is required.']); exit;
}

// File validation
if (empty($_FILES['insurance_doc']) || $_FILES['insurance_doc']['error'] !== UPLOAD_ERR_OK) {
    $errCodes = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was selected.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server upload folder is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
    ];
    $code = $_FILES['insurance_doc']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success'=>false,'message'=>$errCodes[$code] ?? 'Upload failed.']);
    exit;
}

$file     = $_FILES['insurance_doc'];
$maxSize  = 5 * 1024 * 1024; // 5 MB
$allowed  = ['application/pdf','image/jpeg','image/png','image/webp'];
$allowedExt = ['pdf','jpg','jpeg','png','webp'];

if ($file['size'] > $maxSize) {
    echo json_encode(['success'=>false,'message'=>'File too large. Maximum size is 5 MB.']); exit;
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $allowed)) {
    echo json_encode(['success'=>false,'message'=>'Only PDF, JPG, PNG and WebP files are accepted.']); exit;
}

$origName = basename($file['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt)) {
    echo json_encode(['success'=>false,'message'=>'Invalid file extension.']); exit;
}

// Create storage directory
$storageDir = ROOT_DIR . '/storage/uploads/insurance/' . $pid . '/';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0775, true);
}
if (!is_dir($storageDir)) {
    error_log("upload-insurance: cannot create dir $storageDir");
    echo json_encode(['success'=>false,'message'=>'Storage error. Please contact support.']); exit;
}

// Generate a unique safe filename
$safeName  = 'ins_' . $pid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath  = $storageDir . $safeName;
$relPath   = 'insurance/' . $pid . '/' . $safeName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    error_log("upload-insurance: move_uploaded_file failed to $destPath");
    echo json_encode(['success'=>false,'message'=>'Failed to save file. Please try again.']); exit;
}

// Mark any existing docs as inactive (one active doc per patient — MVP approach)
// Comment this out if you want patients to have multiple insurance docs
// $db->query('UPDATE insurance_documents SET status="expired" WHERE patient_id=:pid AND status="active"', [':pid'=>$pid]);

try {
    $db   = Database::getInstance();
    $docId = $db->insert(
        'INSERT INTO insurance_documents
         (patient_id, provider_name, policy_number, member_number, coverage_type,
          expiry_date, file_name, file_path, file_size, mime_type, notes, status, created_at)
         VALUES (:pid, :pn, :pol, :mem, :cov, :exp, :fn, :fp, :fs, :mt, :nt, "active", NOW())',
        [
            ':pid' => $pid,
            ':pn'  => $providerName,
            ':pol' => $policyNumber ?: null,
            ':mem' => $memberNumber ?: null,
            ':cov' => $coverageType ?: null,
            ':exp' => $expiryDate   ?: null,
            ':fn'  => $origName,
            ':fp'  => $relPath,
            ':fs'  => $file['size'],
            ':mt'  => $mimeType,
            ':nt'  => $notes ?: null,
        ]
    );

    // Create in-app notification
    $db->query(
        'INSERT INTO notifications (patient_id, type, title, message, icon, created_at)
         VALUES (:pid, "system", "Insurance Document Uploaded", :msg, "verified_user", NOW())',
        [
            ':pid' => $pid,
            ':msg' => "Your {$providerName} insurance document has been saved and can be shared with providers when booking.",
        ]
    );

    echo json_encode([
        'success'   => true,
        'doc_id'    => $docId,
        'file_name' => $origName,
        'message'   => 'Insurance document uploaded successfully.',
    ]);

} catch (Exception $e) {
    // Remove uploaded file if DB insert fails
    @unlink($destPath);
    error_log('upload-insurance DB: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Database error. Please try again.']);
}
