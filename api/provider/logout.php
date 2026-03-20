<?php
require_once dirname(__DIR__, 2). '/config/config.php';
require_once dirname(__DIR__, 2). '/services/Security.php';
Security::startSession();
// Destroy only provider session keys
unset($_SESSION['provider_id'], $_SESSION['provider_name'], $_SESSION['provider_email'], $_SESSION['is_provider']);
if (empty($_SESSION['patient_id'])) Security::destroySession();
header('Location: /providers/login.php');
exit;
