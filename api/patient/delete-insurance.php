<?php
/**
 * Planeazzy — POST /api/patient/delete-insurance.php
 */
header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') { http_response_code(403); echo json_encode(['success'=>false]); exit; }

Security::startSession();
if (empty($_SESSION['patient_id']) || empty($_SESSION['authenticated'])) {
    http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!Security::verifyCsrf($body['csrf_token'] ?? '')) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid token.']); exit;
}

$docId = (int)($body['doc_id'] ?? 0);
$pid   = (int)$_SESSION['patient_id'];

if (!$docId) { echo json_encode(['success'=>false,'message'=>'Invalid document ID.']); exit; }

try {
    $db  = Database::getInstance();
    $doc = $db->fetchOne('SELECT * FROM insurance_documents WHERE id=:id AND patient_id=:pid', [':id'=>$docId,':pid'=>$pid]);
    if (!$doc) { echo json_encode(['success'=>false,'message'=>'Document not found.']); exit; }

    // Delete physical file
    $filePath = ROOT_DIR . '/storage/uploads/' . $doc['file_path'];
    if (file_exists($filePath)) @unlink($filePath);

    // Remove from DB
    $db->query('DELETE FROM insurance_documents WHERE id=:id AND patient_id=:pid', [':id'=>$docId,':pid'=>$pid]);
    echo json_encode(['success'=>true,'message'=>'Document deleted.']);
} catch (Exception $e) {
    error_log('delete-insurance: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Delete failed. Please try again.']);
}
