<?php
header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();
if (empty($_SESSION['patient_id'])) { echo json_encode(['success'=>false]); exit; }
try {
    $db = Database::getInstance();
    $db->query('UPDATE notifications SET is_read=1 WHERE patient_id=:pid', [':pid'=>(int)$_SESSION['patient_id']]);
    echo json_encode(['success'=>true]);
} catch(Exception $e) { echo json_encode(['success'=>false]); }
