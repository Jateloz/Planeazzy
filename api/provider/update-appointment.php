<?php
header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
require_once dirname(__DIR__, 2). '/services/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_REQUESTED_WITH']??'') !== 'XMLHttpRequest') {
    http_response_code(405); echo json_encode(['success'=>false]); exit;
}

Security::startSession();
if (empty($_SESSION['provider_id'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Unauthorized.']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!Security::verifyCsrf($body['csrf_token'] ?? '')) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Security token invalid.']); exit;
}

$apptId = (int)($body['appointment_id'] ?? 0);
$status = Security::clean($body['status'] ?? '');
$allowed = ['confirmed','completed','cancelled','in_progress'];

if (!$apptId || !in_array($status, $allowed)) {
    echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
}

$pid = (int)$_SESSION['provider_id'];
$db  = Database::getInstance();

// Verify this appointment belongs to this provider
$appt = $db->fetchOne('SELECT id FROM appointments WHERE id=:id AND provider_id=:pid',[':id'=>$apptId,':pid'=>$pid]);
if (!$appt) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Access denied.']); exit;
}

$db->query('UPDATE appointments SET status=:s,updated_at=NOW() WHERE id=:id',[':s'=>$status,':id'=>$apptId]);
echo json_encode(['success'=>true]);
