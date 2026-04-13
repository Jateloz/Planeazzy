<?php
/**
 * Planeazzy — POST /api/patient/update-consent.php
 * Update a patient's data-sharing consent preferences.
 */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/PatientService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false]); exit;
}
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden.']); exit;
}

Security::startSession();
if (empty($_SESSION['patient_id']) || empty($_SESSION['authenticated'])) {
    http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['success'=>false]); exit; }

if (!Security::verifyCsrf($body['csrf_token'] ?? '')) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Security token invalid.']); exit;
}

$pid     = (int)$_SESSION['patient_id'];
$type    = Security::clean($body['consent_type'] ?? '');
$granted = !empty($body['granted']);

$svc    = new PatientService();
$result = $svc->updateConsent($pid, $type, $granted);

echo json_encode($result);
