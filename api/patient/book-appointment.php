<?php
/**
 * Planeazzy — POST /api/patient/book-appointment.php
 *
 * Handles:
 *  1. Create appointment
 *  2. Check insurance sharing consent → attach insurance doc
 *  3. Send confirmation email to patient
 *  4. Send new-booking alert email to provider (with insurance note if consent given)
 *  5. Create in-app notification for patient
 */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
require_once dirname(__DIR__, 2) . '/services/Mailer.php';

// ── Guards ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit;
}
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden.']); exit;
}

Security::startSession();

// Allow guests to call this endpoint — but redirect them to login
if (empty($_SESSION['patient_id']) || empty($_SESSION['authenticated'])) {
    echo json_encode([
        'success'       => false,
        'requires_login'=> true,
        'redirect'      => '/patients/login.php?next=' . urlencode($_SERVER['REQUEST_URI']),
        'message'       => 'Please log in to book an appointment.',
    ]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid request body.']); exit; }

if (!Security::verifyCsrf($body['csrf_token'] ?? '')) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Security token invalid. Please refresh.']); exit;
}

// ── Inputs ────────────────────────────────────────────────────
$pid         = (int)$_SESSION['patient_id'];
$serviceType = Security::clean($body['service_type']    ?? '');
$provId      = !empty($body['provider_id']) ? (int)$body['provider_id'] : null;
$apptAt      = Security::clean($body['appointment_at']  ?? '');
$title       = Security::clean($body['title']           ?? '');
$notes       = Security::clean($body['notes']           ?? '');
$locType     = Security::clean($body['location_type']   ?? 'in_person');
$insDocId    = !empty($body['insurance_doc_id']) ? (int)$body['insurance_doc_id'] : null;
$consentGiven = !empty($body['share_insurance']);

$allowedSvc = ['doctor','clinic','hospital','ambulance','telehealth','pharmacy','lab'];
$allowedLoc = ['in_person','telehealth','home_visit'];

if (!in_array($serviceType, $allowedSvc)) {
    echo json_encode(['success'=>false,'message'=>'Invalid service type.']); exit;
}
if (!in_array($locType, $allowedLoc)) {
    echo json_encode(['success'=>false,'message'=>'Invalid location type.']); exit;
}
if (empty($title)) {
    echo json_encode(['success'=>false,'message'=>'Please provide a reason for the appointment.']); exit;
}

$dt = DateTime::createFromFormat('Y-m-d H:i:s', $apptAt);
if (!$dt || $dt < new DateTime()) {
    echo json_encode(['success'=>false,'message'=>'Please select a valid future date and time.']); exit;
}

try {
    $db = Database::getInstance();

    // Load patient info
    $pat = $db->fetchOne(
        'SELECT id, first_name, last_name, email, phone FROM patients WHERE id=:id',
        [':id' => $pid]
    );
    if (!$pat) {
        echo json_encode(['success'=>false,'message'=>'Patient account not found.']); exit;
    }
    $patName  = trim($pat['first_name'] . ' ' . $pat['last_name']);
    $patEmail = $pat['email'];

    // Load provider info (optional)
    $prov = null;
    if ($provId) {
        $prov = $db->fetchOne(
            'SELECT id, name, email, type FROM providers WHERE id=:id AND is_active=1',
            [':id' => $provId]
        );
    }

    // ── 1. Create appointment ─────────────────────────────────
    $apptId = $db->insert(
        'INSERT INTO appointments
         (patient_id, provider_id, service_type, title, notes, appointment_at,
          location_type, status, created_at)
         VALUES (:pid, :provid, :svc, :title, :notes, :at, :lt, "scheduled", NOW())',
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

    // ── 2. Insurance sharing ──────────────────────────────────
    $insuranceShared = false;
    $insDoc          = null;
    $insuranceNote   = null;

    if ($insDocId && $consentGiven) {
        // Verify the doc belongs to this patient and is active
        $insDoc = $db->fetchOne(
            'SELECT * FROM insurance_documents WHERE id=:id AND patient_id=:pid AND status="active"',
            [':id' => $insDocId, ':pid' => $pid]
        );

        if ($insDoc) {
            // Link doc to appointment
            $db->query(
                'INSERT INTO appointment_insurance (appointment_id, insurance_doc_id, shared_at)
                 VALUES (:aid, :did, NOW())',
                [':aid' => $apptId, ':did' => $insDocId]
            );
            $insuranceShared = true;
            $insuranceNote   = $insDoc['provider_name'] . ' — Policy: ' . ($insDoc['policy_number'] ?? 'N/A');

            // Update notification_sent flag after email is sent below
        }
    }

    // ── 3. Patient confirmation email ─────────────────────────
    $apptData = [
        'id'              => $apptId,
        'date'            => $dt->format('l, F j, Y'),
        'time'            => $dt->format('g:i A'),
        'provider_name'   => $prov ? $prov['name'] : 'Any available provider',
        'service_type'    => $serviceType,
        'location_type'   => $locType,
        'insurance_shared'=> $insuranceShared,
    ];
    Mailer::sendAppointmentConfirmation($patEmail, $patName, $pid, $apptData);

    // ── 4. Provider new-booking alert email ───────────────────
    if ($prov && !empty($prov['email'])) {
        $apptDataForProv = array_merge($apptData, [
            'patient_name'  => $patName,
            'patient_phone' => $pat['phone'] ?? '—',
            'patient_email' => $patEmail,
            'notes'         => $notes,
        ]);
        Mailer::sendProviderNewBooking($prov['email'], $prov['name'], $apptDataForProv, $insuranceNote);

        // If insurance was shared, also send insurance alert separately
        if ($insuranceShared && $insDoc) {
            Mailer::sendInsuranceReceivedAlert(
                $prov['email'], $prov['name'],
                $patName, $insDoc, $apptId
            );
            // Mark notification sent
            $db->query(
                'UPDATE appointment_insurance SET notification_sent=1 WHERE appointment_id=:aid',
                [':aid' => $apptId]
            );
        }
    }

    // ── 5. In-app notification ────────────────────────────────
    $notifMsg = 'Your ' . ucfirst($serviceType) . ' appointment has been booked for '
        . $dt->format('M j, Y \a\t g:i A') . '.';
    if ($insuranceShared) {
        $notifMsg .= ' Your insurance document has been shared with the provider.';
    }
    $db->query(
        'INSERT INTO notifications (patient_id, type, title, message, icon, created_at)
         VALUES (:pid, "appointment", "Appointment Booked ✓", :msg, "event_available", NOW())',
        [':pid' => $pid, ':msg' => $notifMsg]
    );

    echo json_encode([
        'success'          => true,
        'appointment_id'   => $apptId,
        'insurance_shared' => $insuranceShared,
        'message'          => 'Appointment booked! Confirmation sent to ' . $patEmail,
    ]);

} catch (Exception $e) {
    error_log('book-appointment: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Failed to book appointment. Please try again.']);
}
