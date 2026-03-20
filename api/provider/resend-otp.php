<?php
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
require_once dirname(__DIR__, 2). '/services/ProviderService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') { http_response_code(403); echo json_encode(['success'=>false]); exit; }

$ip = Security::ip();
if (!Security::rateLimit('prov_resend:'.$ip, 5, 600)) {
    http_response_code(429); echo json_encode(['success'=>false,'message'=>'Too many resend requests.']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
Security::startSession();
if (!Security::verifyCsrf($body['csrf_token'] ?? '')) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Security token invalid.']); exit;
}

$pid = (int)($body['provider_id'] ?? 0);
if (!$pid) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

$svc    = new ProviderService();
$result = $svc->resendOtp($pid);
http_response_code($result['success'] ? 200 : 422);
echo json_encode($result);
