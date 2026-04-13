<?php
/**
 * Clinical Precision — Hospital Dashboard API
 * Handles all AJAX actions from the hospital dashboard
 */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
require_once dirname(__DIR__, 2) . '/services/Database.php';
require_once dirname(__DIR__, 2) . '/services/HospitalMailer.php';
Security::startSession();

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'msg' => 'Method not allowed'], 405);
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_POST['action'] ?? '');
$hid    = (int)($_SESSION['hospital_id'] ?? 0);

/* ── Auth check (skip for lang/public actions) ─────────────── */
$publicActions = ['set_lang'];
if (!in_array($action, $publicActions)) {
    if (!$hid || empty($_SESSION['hospital_auth'])) {
        json_out(['ok' => false, 'msg' => 'Not authenticated'], 401);
    }
}

/* ── CSRF check for state-changing actions ─────────────────── */
$csrfRequired = ['add_doctor','update_appointment','toggle_service','save_settings',
                  'connect_insurance','disconnect_insurance','add_department',
                  'delete_department','toggle_department'];
if (in_array($action, $csrfRequired)) {
    $tok = $body['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    if (!Security::verifyCsrf($tok)) {
        json_out(['ok' => false, 'msg' => 'CSRF token invalid'], 403);
    }
}

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    json_out(['ok' => false, 'msg' => 'Database unavailable'], 503);
}

switch ($action) {

    /* ── Language ─────────────────────────────────────────── */
    case 'set_lang':
        $lang = in_array($body['lang'] ?? '', ['en','sw']) ? $body['lang'] : 'en';
        $_SESSION['cp_lang'] = $lang;
        json_out(['ok' => true, 'lang' => $lang]);

    /* ── Notifications ─────────────────────────────────────── */
    case 'mark_notif_read':
        $nid = (int)($body['notif_id'] ?? 0);
        $db->query('UPDATE hospital_notifications SET is_read=1 WHERE id=:n AND hospital_id=:h',
                   [':n' => $nid, ':h' => $hid]);
        json_out(['ok' => true]);

    case 'mark_all_read':
        $db->query('UPDATE hospital_notifications SET is_read=1 WHERE hospital_id=:h', [':h' => $hid]);
        json_out(['ok' => true]);

    /* ── Appointments ──────────────────────────────────────── */
    case 'update_appointment':
        $apptId = (int)($body['appointment_id'] ?? 0);
        $status = $body['status'] ?? '';
        $allowed = ['confirmed','cancelled','completed'];
        if (!$apptId || !in_array($status, $allowed)) {
            json_out(['ok' => false, 'msg' => 'Invalid request']);
        }
        // Verify appointment belongs to this hospital
        $appt = $db->fetchOne('SELECT * FROM hospital_appointments WHERE id=:id AND hospital_id=:h',
                              [':id' => $apptId, ':h' => $hid]);
        if (!$appt) json_out(['ok' => false, 'msg' => 'Appointment not found']);

        $db->query('UPDATE hospital_appointments SET status=:s WHERE id=:id',
                   [':s' => $status, ':id' => $apptId]);

        // Notify patient by email if confirmed
        if ($status === 'confirmed' && !empty($appt['patient_email'])) {
            // Send confirmation email (non-blocking, ignore failure)
            @HospitalMailer::sendNewBooking(
                $appt['patient_email'],
                $db->fetchOne('SELECT facility_name FROM hospital_providers WHERE id=:h', [':h' => $hid])['facility_name'] ?? 'Hospital',
                (array)$appt,
                $hid
            );
        }
        json_out(['ok' => true, 'status' => $status]);

    case 'get_appointments':
        $filter = $body['filter'] ?? 'all';
        $date   = $body['date'] ?? '';
        $sql = 'SELECT * FROM hospital_appointments WHERE hospital_id=:h';
        $params = [':h' => $hid];
        if ($filter !== 'all' && in_array($filter, ['pending','confirmed','cancelled','completed'])) {
            $sql .= ' AND status=:s'; $params[':s'] = $filter;
        }
        if ($date) { $sql .= ' AND DATE(appointment_at)=:d'; $params[':d'] = $date; }
        $sql .= ' ORDER BY appointment_at DESC LIMIT 100';
        $appts = $db->fetchAll($sql, $params);
        json_out(['ok' => true, 'appointments' => $appts]);

    /* ── Doctors ───────────────────────────────────────────── */
    case 'add_doctor':
        $dname   = trim(strip_tags($body['name'] ?? ''));
        $demail  = filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $dphone  = preg_replace('/[^0-9+\-\s]/', '', $body['phone'] ?? '');
        $dspec   = trim(strip_tags($body['specialty'] ?? ''));
        $dlic    = trim(strip_tags($body['licence'] ?? ''));
        $dept_id = (int)($body['department_id'] ?? 0);

        if (!$dname) json_out(['ok' => false, 'msg' => 'Doctor name is required']);

        // Check duplicate licence
        if ($dlic) {
            $dup = $db->fetchOne('SELECT id FROM hospital_doctors WHERE kmpdc_licence=:l AND hospital_id=:h',
                                 [':l' => $dlic, ':h' => $hid]);
            if ($dup) json_out(['ok' => false, 'msg' => 'A doctor with this KMPDC licence already exists']);
        }

        $did = $db->insert(
            'INSERT INTO hospital_doctors (hospital_id, department_id, name, email, phone, specialty, kmpdc_licence, status)
             VALUES (:h, :d, :n, :e, :p, :s, :l, "off-duty")',
            [':h'=>$hid, ':d'=>$dept_id?:null, ':n'=>$dname, ':e'=>$demail?:null,
             ':p'=>$dphone?:null, ':s'=>$dspec?:null, ':l'=>$dlic?:null]
        );
        // Create welcome notification
        $db->query(
            'INSERT INTO hospital_notifications (hospital_id,type,title,message) VALUES (:h,"system",:t,:m)',
            [':h'=>$hid, ':t'=>'Doctor Added', ':m'=>"$dname has been added to your medical staff."]
        );
        json_out(['ok' => true, 'doctor_id' => $did, 'msg' => 'Doctor added successfully']);

    case 'update_doctor_status':
        $did    = (int)($body['doctor_id'] ?? 0);
        $status = $body['status'] ?? '';
        if (!$did || !in_array($status, ['on-duty','off-duty','on-break','suspended'])) {
            json_out(['ok' => false, 'msg' => 'Invalid request']);
        }
        $db->query('UPDATE hospital_doctors SET status=:s WHERE id=:id AND hospital_id=:h',
                   [':s' => $status, ':id' => $did, ':h' => $hid]);
        json_out(['ok' => true]);

    case 'get_doctors':
        $doctors = $db->fetchAll(
            'SELECT d.*, dep.name dept_name FROM hospital_doctors d
             LEFT JOIN hospital_departments dep ON dep.id = d.department_id
             WHERE d.hospital_id=:h AND d.is_active=1 ORDER BY d.name',
            [':h' => $hid]
        );
        json_out(['ok' => true, 'doctors' => $doctors]);

    /* ── Departments ───────────────────────────────────────── */
    case 'add_department':
        $dname = trim(strip_tags($body['name'] ?? ''));
        $icon  = preg_replace('/[^a-z_]/', '', $body['icon'] ?? 'stethoscope');
        if (!$dname) json_out(['ok' => false, 'msg' => 'Department name is required']);
        $did = $db->insert(
            'INSERT INTO hospital_departments (hospital_id, name, icon) VALUES (:h,:n,:i)',
            [':h' => $hid, ':n' => $dname, ':i' => $icon]
        );
        json_out(['ok' => true, 'department_id' => $did]);

    case 'toggle_department':
        $did    = (int)($body['department_id'] ?? 0);
        $active = (int)((bool)($body['active'] ?? false));
        $db->query('UPDATE hospital_departments SET is_active=:a WHERE id=:id AND hospital_id=:h',
                   [':a' => $active, ':id' => $did, ':h' => $hid]);
        json_out(['ok' => true, 'active' => $active]);

    case 'delete_department':
        $did = (int)($body['department_id'] ?? 0);
        // Check no doctors assigned
        $docCount = $db->fetchOne('SELECT COUNT(*) c FROM hospital_doctors WHERE department_id=:d AND hospital_id=:h AND is_active=1',
                                  [':d' => $did, ':h' => $hid]);
        if (($docCount['c'] ?? 0) > 0) {
            json_out(['ok' => false, 'msg' => 'Remove all doctors from this department first']);
        }
        $db->query('DELETE FROM hospital_departments WHERE id=:id AND hospital_id=:h',
                   [':id' => $did, ':h' => $hid]);
        json_out(['ok' => true]);

    /* ── Services ──────────────────────────────────────────── */
    case 'toggle_service':
        $key     = preg_replace('/[^a-z_]/', '', $body['service_key'] ?? '');
        $enabled = (bool)($body['enabled'] ?? false);
        if (!$key) json_out(['ok' => false, 'msg' => 'Invalid service key']);

        $hosp    = $db->fetchOne('SELECT services FROM hospital_providers WHERE id=:id', [':id' => $hid]);
        $services = json_decode($hosp['services'] ?? '[]', true) ?? [];
        if ($enabled) {
            if (!in_array($key, $services)) $services[] = $key;
        } else {
            $services = array_values(array_filter($services, fn($s) => $s !== $key));
        }
        $db->query('UPDATE hospital_providers SET services=:s WHERE id=:id',
                   [':s' => json_encode($services), ':id' => $hid]);
        json_out(['ok' => true, 'services' => $services]);

    /* ── Insurance ─────────────────────────────────────────── */
    case 'connect_insurance':
        $pkey  = preg_replace('/[^a-z]/', '', strtolower($body['provider_key'] ?? ''));
        $pname = trim(strip_tags($body['provider_name'] ?? ''));
        $ref   = trim(strip_tags($body['policy_ref'] ?? ''));
        if (!$pkey || !$pname) json_out(['ok' => false, 'msg' => 'Provider key and name are required']);

        $db->query(
            'INSERT INTO hospital_insurance (hospital_id, provider_key, provider_name, status, policy_ref, connected_at)
             VALUES (:h, :pk, :pn, "connected", :ref, NOW())
             ON DUPLICATE KEY UPDATE status="connected", policy_ref=:ref, connected_at=NOW()',
            [':h' => $hid, ':pk' => $pkey, ':pn' => $pname, ':ref' => $ref ?: null]
        );
        $db->query(
            'INSERT INTO hospital_notifications (hospital_id,type,title,message) VALUES (:h,"insurance",:t,:m)',
            [':h'=>$hid, ':t'=>"$pname Connected", ':m'=>"Insurance provider $pname has been successfully connected."]
        );
        json_out(['ok' => true, 'msg' => "$pname connected successfully"]);

    case 'disconnect_insurance':
        $pkey = preg_replace('/[^a-z]/', '', strtolower($body['provider_key'] ?? ''));
        $db->query('UPDATE hospital_insurance SET status="disconnected" WHERE provider_key=:pk AND hospital_id=:h',
                   [':pk' => $pkey, ':h' => $hid]);
        json_out(['ok' => true]);

    case 'get_insurance':
        $ins = $db->fetchAll('SELECT * FROM hospital_insurance WHERE hospital_id=:h ORDER BY provider_name', [':h' => $hid]);
        json_out(['ok' => true, 'insurance' => $ins]);

    /* ── Settings ──────────────────────────────────────────── */
    case 'save_settings':
        $fname  = trim(strip_tags($body['facility_name'] ?? ''));
        $phone  = preg_replace('/[^0-9+\-\s]/', '', $body['phone'] ?? '');
        $county = trim(strip_tags($body['county'] ?? ''));
        $addr   = trim(strip_tags($body['address'] ?? ''));
        $web    = filter_var($body['website'] ?? '', FILTER_SANITIZE_URL);
        $emerg  = (int)((bool)($body['emergency_24h'] ?? false));

        if (!$fname) json_out(['ok' => false, 'msg' => 'Facility name is required']);

        $db->query(
            'UPDATE hospital_providers SET facility_name=:fn, phone=:ph, county=:co, address=:ad, website=:wb, emergency_24h=:em WHERE id=:id',
            [':fn'=>$fname, ':ph'=>$phone, ':co'=>$county, ':ad'=>$addr, ':wb'=>$web?:null, ':em'=>$emerg, ':id'=>$hid]
        );
        $_SESSION['hospital_name'] = $fname;
        json_out(['ok' => true, 'msg' => 'Facility information saved successfully']);

    case 'change_password':
        $current = $body['current_password'] ?? '';
        $new     = $body['new_password']     ?? '';
        if (strlen($new) < 8) json_out(['ok' => false, 'msg' => 'New password must be at least 8 characters']);

        $hosp = $db->fetchOne('SELECT password_hash FROM hospital_providers WHERE id=:id', [':id' => $hid]);
        if (!password_verify($current, $hosp['password_hash'])) {
            json_out(['ok' => false, 'msg' => 'Current password is incorrect']);
        }
        $newHash = password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $db->query('UPDATE hospital_providers SET password_hash=:h WHERE id=:id', [':h' => $newHash, ':id' => $hid]);
        json_out(['ok' => true, 'msg' => 'Password updated successfully']);

    /* ── Analytics / Stats ─────────────────────────────────── */
    case 'get_stats':
        $today_count = $db->fetchOne(
            'SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND DATE(appointment_at)=CURDATE()',
            [':h' => $hid]
        )['c'] ?? 0;
        $pending_count = $db->fetchOne(
            'SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND status="pending"',
            [':h' => $hid]
        )['c'] ?? 0;
        $total_patients = $db->fetchOne(
            'SELECT COUNT(DISTINCT patient_email) c FROM hospital_appointments WHERE hospital_id=:h AND patient_email IS NOT NULL',
            [':h' => $hid]
        )['c'] ?? 0;
        $monthly_revenue = $db->fetchOne(
            'SELECT COALESCE(SUM(amount),0) total FROM hospital_billings WHERE hospital_id=:h AND MONTH(billed_at)=MONTH(CURDATE()) AND YEAR(billed_at)=YEAR(CURDATE()) AND status="paid"',
            [':h' => $hid]
        )['total'] ?? 0;
        $docs_on = $db->fetchOne(
            'SELECT COUNT(*) c FROM hospital_doctors WHERE hospital_id=:h AND status="on-duty" AND is_active=1',
            [':h' => $hid]
        )['c'] ?? 0;
        json_out(['ok' => true, 'stats' => [
            'today'          => (int)$today_count,
            'pending'        => (int)$pending_count,
            'patients'       => (int)$total_patients,
            'revenue'        => (float)$monthly_revenue,
            'docs_on'        => (int)$docs_on,
        ]]);

    /* ── Billings ──────────────────────────────────────────── */
    case 'get_billings':
        $bills = $db->fetchAll(
            'SELECT * FROM hospital_billings WHERE hospital_id=:h ORDER BY billed_at DESC LIMIT 50',
            [':h' => $hid]
        );
        json_out(['ok' => true, 'billings' => $bills]);

    case 'add_billing':
        $patName = trim(strip_tags($body['patient_name'] ?? ''));
        $svcDesc = trim(strip_tags($body['service_desc'] ?? ''));
        $amount  = (float)($body['amount'] ?? 0);
        $method  = $body['payment_method'] ?? 'cash';
        $status  = $body['status'] ?? 'pending';
        $ref     = trim(strip_tags($body['reference'] ?? ''));
        if (!$patName || $amount <= 0) json_out(['ok' => false, 'msg' => 'Patient name and amount are required']);
        if (!in_array($method, ['cash','mpesa','nhif','insurance','bank'])) $method = 'cash';
        if (!in_array($status, ['paid','pending','cancelled'])) $status = 'pending';
        $bid = $db->insert(
            'INSERT INTO hospital_billings (hospital_id, patient_name, service_desc, amount, payment_method, status, reference)
             VALUES (:h,:pn,:sd,:am,:pm,:st,:ref)',
            [':h'=>$hid,':pn'=>$patName,':sd'=>$svcDesc,':am'=>$amount,':pm'=>$method,':st'=>$status,':ref'=>$ref?:null]
        );
        json_out(['ok' => true, 'billing_id' => $bid]);


    case 'add_appointment':
        $pname = trim(strip_tags($body['patient_name'] ?? ''));
        $pphone= preg_replace('/[^0-9+\-\s]/', '', $body['patient_phone'] ?? '');
        $pemail= filter_var($body['patient_email'] ?? '', FILTER_VALIDATE_EMAIL);
        $dt    = $body['appointment_at'] ?? '';
        $vtype = in_array($body['visit_type']??'', ['in-person','tele-consult']) ? $body['visit_type'] : 'in-person';
        $dept  = trim(strip_tags($body['department'] ?? ''));
        if (!$pname || !$dt) json_out(['ok'=>false,'msg'=>'Patient name and date/time are required']);
        $ts = strtotime($dt);
        if (!$ts || $ts < time() - 3600) json_out(['ok'=>false,'msg'=>'Please enter a valid future date and time']);
        $aid = $db->insert(
            'INSERT INTO hospital_appointments (hospital_id,patient_name,patient_phone,patient_email,appointment_at,visit_type,department,status)
             VALUES (:h,:pn,:pp,:pe,:at,:vt,:dept,"pending")',
            [':h'=>$hid,':pn'=>$pname,':pp'=>$pphone?:null,':pe'=>$pemail?:null,
             ':at'=>date('Y-m-d H:i:s',$ts),':vt'=>$vtype,':dept'=>$dept?:null]
        );
        $db->query('INSERT INTO hospital_notifications (hospital_id,type,title,message) VALUES (:h,"booking",:t,:m)',
                   [':h'=>$hid,':t'=>'New Appointment','m'=>"Appointment added for $pname on ".date('M d, Y g:i A',$ts)]);
        json_out(['ok'=>true,'appointment_id'=>$aid]);

    case 'delete_doctor':
        $did = (int)($body['doctor_id'] ?? 0);
        if (!$did) json_out(['ok'=>false,'msg'=>'Invalid doctor ID']);
        $db->query('UPDATE hospital_doctors SET is_active=0 WHERE id=:id AND hospital_id=:h',
                   [':id'=>$did,':h'=>$hid]);
        json_out(['ok'=>true]);

    default:
        json_out(['ok' => false, 'msg' => 'Unknown action'], 400);
}
