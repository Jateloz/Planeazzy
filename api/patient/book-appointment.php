<?php
/**
 * Planeazzy — POST /api/patient/book-appointment.php
 */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
require_once dirname(__DIR__, 2). '/services/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') { http_response_code(403); echo json_encode(['success'=>false]); exit; }

Security::startSession();
Security::requireAuth('/patients/login.php');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Bad request']); exit; }

if (!Security::verifyCsrf($body['csrf_token'] ?? '')) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Security token invalid.']); exit;
}

$pid         = (int)$_SESSION['patient_id'];
$serviceType = Security::clean($body['service_type'] ?? '');
$provId      = !empty($body['provider_id']) ? (int)$body['provider_id'] : null;
$apptAt      = Security::clean($body['appointment_at'] ?? '');
$title       = Security::clean($body['title'] ?? '');
$notes       = Security::clean($body['notes'] ?? '');
$locType     = Security::clean($body['location_type'] ?? 'in_person');

$allowedTypes = ['doctor','clinic','hospital','ambulance','telehealth','pharmacy','lab'];
$allowedLoc   = ['in_person','telehealth','home_visit'];

if (!in_array($serviceType, $allowedTypes)) { echo json_encode(['success'=>false,'message'=>'Invalid service type.']); exit; }
if (!in_array($locType, $allowedLoc))       { echo json_encode(['success'=>false,'message'=>'Invalid location type.']); exit; }

// Validate datetime
$dt = DateTime::createFromFormat('Y-m-d H:i:s', $apptAt);
if (!$dt || $dt < new DateTime()) {
    echo json_encode(['success'=>false,'message'=>'Please select a future date and time.']); exit;
}

try {
    $db = Database::getInstance();
    $id = $db->insert(
        'INSERT INTO appointments (patient_id,provider_id,service_type,title,notes,appointment_at,location_type,status,created_at)
         VALUES (:pid,:provid,:svc,:title,:notes,:at,:lt,"scheduled",NOW())',
        [
            ':pid'   => $pid,
            ':provid'=> $provId,
            ':svc'   => $serviceType,
            ':title' => $title ?: ucfirst($serviceType) . ' Appointment',
            ':notes' => $notes,
            ':at'    => $dt->format('Y-m-d H:i:s'),
            ':lt'    => $locType,
        ]
    );

    // Create notification
    $db->query(
        'INSERT INTO notifications (patient_id,type,title,message,icon) VALUES (:pid,"appointment",:t,:m,"event_available")',
        [
            ':pid' => $pid,
            ':t'   => 'Appointment Booked',
            ':m'   => 'Your '.ucfirst($serviceType).' appointment has been booked for '.$dt->format('M j, Y \a\t g:i A').'.',
        ]
    );

    echo json_encode(['success'=>true,'appointment_id'=>$id,'message'=>'Appointment booked successfully!']);
} catch (Exception $e) {
    error_log('Book appointment: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Failed to book appointment. Please try again.']);
}
