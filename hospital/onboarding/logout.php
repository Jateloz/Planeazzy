<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
Security::startSession();
$keys = ['hospital_id','hospital_auth','hospital_name','hospital_email','hospital_type','hospital_step'];
foreach ($keys as $k) unset($_SESSION[$k]);
header('Location: /hospital/onboarding/login.php'); exit;
