<?php
/**
 * Planeazzy — POST /api/patient/request-emergency.php
 * Creates an emergency request from patient GPS location.
 */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false]); exit;
}
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403); echo json_encode(['success'=>false]); exit;
}

Security::startSession();
if (empty($_SESSION['patient_id']) || empty($_SESSION['authenticated'])) {
    http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!Security::verifyCsrf($body['csrf_token'] ?? '')) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid token.']); exit;
}

$pid  = (int)$_SESSION['patient_id'];
$lat  = isset($body['latitude'])  ? (float)$body['latitude']  : null;
$lng  = isset($body['longitude']) ? (float)$body['longitude'] : null;
$addr = Security::clean($body['address_text']   ?? '');
$type = Security::clean($body['emergency_type'] ?? 'ambulance');

$allowedTypes = ['ambulance','cardiac','trauma','respiratory','other'];
if (!in_array($type, $allowedTypes)) $type = 'ambulance';

if (!$lat || !$lng) {
    echo json_encode(['success'=>false,'message'=>'GPS coordinates are required.']); exit;
}

try {
    $db = Database::getInstance();
    $id = $db->insert(
        'INSERT INTO emergency_requests
         (patient_id, latitude, longitude, address_text, emergency_type, status, requested_at)
         VALUES (:pid, :lat, :lng, :addr, :type, "pending", NOW())',
        [':pid'=>$pid,':lat'=>$lat,':lng'=>$lng,':addr'=>$addr,':type'=>$type]
    );

    // Create in-app notification for patient
    $db->query(
        'INSERT INTO notifications (patient_id, type, title, message, icon, created_at)
         VALUES (:pid, "emergency", "Emergency Request Sent", :msg, "local_hospital", NOW())',
        [':pid'=>$pid, ':msg'=>"Your emergency request has been sent. The nearest ambulance has been dispatched to your location. Reference: #".str_pad($id,6,'0',STR_PAD_LEFT)]
    );

    echo json_encode([
        'success'   => true,
        'request_id'=> $id,
        'message'   => 'Emergency request sent. Help is on the way.',
    ]);
} catch (Exception $e) {
    error_log('request-emergency: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Failed to send request. Please call 999 directly.']);
}
