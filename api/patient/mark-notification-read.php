<?php
/**
 * Planeazzy — POST /api/patient/mark-notification-read.php
 */
header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
Security::startSession();
if (empty($_SESSION['patient_id'])) { echo json_encode(['success'=>false]); exit; }
$id  = (int)($_GET['id'] ?? 0);
$pid = (int)$_SESSION['patient_id'];
if ($id > 0) {
    try {
        $db = Database::getInstance();
        $db->query(
            'UPDATE notifications SET is_read=1 WHERE id=:id AND patient_id=:pid',
            [':id'=>$id,':pid'=>$pid]
        );
        echo json_encode(['success'=>true]);
    } catch(Exception $e) { echo json_encode(['success'=>false]); }
} else {
    echo json_encode(['success'=>false,'message'=>'Invalid notification ID.']);
}
