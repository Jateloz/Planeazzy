<?php
header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
require_once dirname(__DIR__, 2). '/services/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_REQUESTED_WITH']??'') !== 'XMLHttpRequest') {
    http_response_code(405); echo json_encode(['success'=>false]); exit;
}

Security::startSession();
if (empty($_SESSION['provider_id'])) { http_response_code(403); echo json_encode(['success'=>false]); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!Security::verifyCsrf($body['csrf_token'] ?? '')) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Security token invalid.']); exit;
}

$pid = (int)$_SESSION['provider_id'];
$val = !empty($body['available']) ? 1 : 0;
Database::getInstance()->query('UPDATE providers SET is_available=:v WHERE id=:id',[':v'=>$val,':id'=>$pid]);
echo json_encode(['success'=>true]);
