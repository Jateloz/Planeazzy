<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/services/Security.php';
Security::startSession();
unset($_SESSION['doctor_id'], $_SESSION['is_doctor'],
      $_SESSION['doctor_otp_id'], $_SESSION['doctor_otp_email'],
      $_SESSION['doc_reg_data'], $_SESSION['doc_reg_step']);
session_destroy();
header('Location: /doctors/onboarding/login.php');
exit;
