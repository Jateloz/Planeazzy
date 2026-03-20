<?php
/**
 * Planeazzy — POST /api/patient/save-preferences.php
 */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
require_once dirname(__DIR__, 2). '/services/PatientService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false]); exit;
}
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403); echo json_encode(['success'=>false]); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
Security::startSession();

if (!Security::verifyCsrf($body['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Security token invalid.']); exit;
}

// Trust session patient_id over posted value if authenticated
$pid     = (int)($_SESSION['patient_id'] ?? $body['patient_id'] ?? 0);
$service = Security::clean($body['service'] ?? '');

if (!$pid) {
    // Post-registration flow before first login — pass through gracefully
    echo json_encode(['success'=>true]); exit;
}

$svc    = new PatientService();
$result = $svc->savePreferences($pid, $service);
http_response_code($result['success'] ? 200 : 422);
echo json_encode($result);
