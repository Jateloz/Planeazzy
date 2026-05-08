#!/usr/bin/env php
<?php
/**
 * Planeazzy — cron/send-reminders.php
 * Run via cron every hour:
 *   0 * * * * php /path/to/planeazzy/cron/send-reminders.php >> /var/log/planeazzy-reminders.log 2>&1
 *
 * Sends SMS + email reminders to:
 *  - Patients 24 hours before their appointment
 *  - Providers 24 hours before their patient's visit
 */
define('CRON_RUN', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/services/Database.php';
require_once dirname(__DIR__) . '/services/Mailer.php';
require_once dirname(__DIR__) . '/services/SmsService.php';

$db = Database::getInstance();

// Ensure reminder_sent column exists
try {
    $db->query('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS reminder_sent TINYINT(1) NOT NULL DEFAULT 0');
} catch (Exception $e) {}

// Find appointments in the next 22-26 hour window that haven't been reminded
$window_start = date('Y-m-d H:i:s', strtotime('+22 hours'));
$window_end   = date('Y-m-d H:i:s', strtotime('+26 hours'));

$appointments = $db->fetchAll(
    "SELECT a.*,
            pt.first_name, pt.last_name, pt.email pat_email, pt.phone pat_phone,
            p.name prov_name, p.email prov_email, p.phone prov_phone, p.type prov_type
     FROM appointments a
     LEFT JOIN patients  pt ON a.patient_id  = pt.id
     LEFT JOIN providers p  ON a.provider_id = p.id
     WHERE a.status = 'scheduled'
       AND a.appointment_at BETWEEN :ws AND :we
       AND a.reminder_sent  = 0",
    [':ws' => $window_start, ':we' => $window_end]
);

$sent = 0;
foreach ($appointments as $appt) {
    $patName   = trim(($appt['first_name'] ?? '') . ' ' . ($appt['last_name'] ?? ''));
    $provName  = $appt['prov_name'] ?? 'your provider';
    $dt        = new DateTime($appt['appointment_at']);
    $dateStr   = $dt->format('D, M j Y \a\t g:i A');
    $apptId    = (int)$appt['id'];
    $pid       = (int)$appt['patient_id'];

    $apptData  = [
        'id'            => $apptId,
        'date'          => $dt->format('l, F j, Y'),
        'time'          => $dt->format('g:i A'),
        'provider_name' => $provName,
        'service_type'  => $appt['service_type'] ?? 'appointment',
        'location_type' => $appt['location_type'] ?? 'in_person',
    ];

    //  Patient email reminder 
    if (!empty($appt['pat_email'])) {
        Mailer::sendAppointmentReminder($appt['pat_email'], $patName, $pid, $apptData);
    }

    //  Patient SMS reminder 
    if (!empty($appt['pat_phone'])) {
        SmsService::sendPatientReminder($appt['pat_phone'], $patName, $provName, $dateStr);
    }

    //  Provider SMS reminder 
    if (!empty($appt['prov_phone'])) {
        SmsService::sendProviderReminder($appt['prov_phone'], $provName, $patName, $dateStr, $apptId);
    }

    //  Provider email reminder 
    if (!empty($appt['prov_email'])) {
        Mailer::sendProviderNewBooking($appt['prov_email'], $provName, array_merge($apptData, [
            'patient_name'  => $patName,
            'patient_phone' => $appt['pat_phone'] ?? '—',
            'patient_email' => $appt['pat_email'] ?? '—',
            'notes'         => '[24-hour reminder] ' . ($appt['notes'] ?? ''),
        ]), null);
    }

    // Mark reminder sent
    $db->query(
        'UPDATE appointments SET reminder_sent=1 WHERE id=:id',
        [':id' => $apptId]
    );

    echo date('[Y-m-d H:i:s]') . " Reminder sent for appt #$apptId ($patName → $provName @ $dateStr)\n";
    $sent++;
}

echo date('[Y-m-d H:i:s]') . " Done. $sent reminder(s) sent.\n";
