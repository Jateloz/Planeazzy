<?php
/**
 * Planeazzy — POST /api/auth/login.php
 */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
require_once dirname(__DIR__, 2). '/services/PatientService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit;
}
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden.']); exit;
}

$ip = Security::ip();
if (!Security::rateLimit('login:'.$ip, 20, 300)) {
    http_response_code(429);
    echo json_encode(['success'=>false,'message'=>'Too many login attempts. Please wait 5 minutes.']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
}

Security::startSession();
if (!Security::verifyCsrf($body['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Security token invalid. Refresh the page.']); exit;
}

$svc    = new PatientService();
$result = $svc->login($body['email'] ?? '', $body['password'] ?? '', $ip);
http_response_code($result['success'] ? 200 : 401);
echo json_encode($result);
