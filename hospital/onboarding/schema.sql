-- ═══════════════════════════════════════════════════════════════
--  CLINICAL PRECISION — Complete Hospital Schema v2
--  Run this file in phpMyAdmin after config/schema.sql
-- ═══════════════════════════════════════════════════════════════

USE planeazzy_db;

-- ── Hospital providers ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hospital_providers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_name      VARCHAR(120) NOT NULL,
    admin_email     VARCHAR(180) NOT NULL UNIQUE,
    admin_phone     VARCHAR(20),
    password_hash   VARCHAR(255) NOT NULL,
    facility_type   ENUM('hospital','clinic','diagnostic','ambulance') NOT NULL DEFAULT 'hospital',
    facility_name   VARCHAR(200),
    county          VARCHAR(80),
    sub_county      VARCHAR(80),
    address         TEXT,
    latitude        DECIMAL(10,7),
    longitude       DECIMAL(10,7),
    phone           VARCHAR(20),
    website         VARCHAR(255),
    logo_path       VARCHAR(500),
    emergency_24h   TINYINT(1) NOT NULL DEFAULT 0,
    services        JSON COMMENT 'Array of service keys',
    kmpdc_number    VARCHAR(60),
    kmpdc_doc_path  VARCHAR(500),
    tax_pin         VARCHAR(30),
    tax_doc_path    VARCHAR(500),
    cr_number       VARCHAR(30),
    cr_doc_path     VARCHAR(500),
    onboarding_step TINYINT UNSIGNED NOT NULL DEFAULT 1,
    email_verified  TINYINT(1) NOT NULL DEFAULT 0,
    email_otp_hash  VARCHAR(255) COMMENT 'bcrypt hash of OTP',
    email_otp_at    DATETIME,
    status          ENUM('pending','under_review','approved','suspended','rejected') NOT NULL DEFAULT 'pending',
    is_active       TINYINT(1) NOT NULL DEFAULT 0,
    is_verified     TINYINT(1) NOT NULL DEFAULT 0,
    review_notes    TEXT,
    last_login      DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email  (admin_email),
    INDEX idx_status (status),
    INDEX idx_type   (facility_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Hospital departments ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS hospital_departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT UNSIGNED NOT NULL,
    name        VARCHAR(120) NOT NULL,
    icon        VARCHAR(60) DEFAULT 'stethoscope',
    color_hex   VARCHAR(7)  DEFAULT '#006a6a',
    head_doctor VARCHAR(120),
    capacity    SMALLINT UNSIGNED DEFAULT 0,
    notes       TEXT,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  TINYINT UNSIGNED DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospital_providers(id) ON DELETE CASCADE,
    INDEX idx_hospital (hospital_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Hospital doctors (staff linked to facility) ────────────────
CREATE TABLE IF NOT EXISTS hospital_doctors (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id   INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED,
    name          VARCHAR(120) NOT NULL,
    email         VARCHAR(180),
    phone         VARCHAR(20),
    specialty     VARCHAR(120),
    kmpdc_licence VARCHAR(60),
    rating        DECIMAL(3,1) DEFAULT 0.0,
    status        ENUM('on-duty','off-duty','on-break','suspended') DEFAULT 'off-duty',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    joined_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospital_providers(id) ON DELETE CASCADE,
    INDEX idx_hospital (hospital_id),
    INDEX idx_dept     (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Hospital appointments ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS hospital_appointments (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id   INT UNSIGNED NOT NULL,
    doctor_id     INT UNSIGNED,
    patient_name  VARCHAR(120) NOT NULL,
    patient_phone VARCHAR(20),
    patient_email VARCHAR(180),
    department    VARCHAR(120),
    visit_type    ENUM('in-person','tele-consult') DEFAULT 'in-person',
    appointment_at DATETIME NOT NULL,
    status        ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
    notes         TEXT,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospital_providers(id) ON DELETE CASCADE,
    INDEX idx_hospital (hospital_id),
    INDEX idx_status   (status),
    INDEX idx_date     (appointment_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Hospital insurance connections ─────────────────────────────
CREATE TABLE IF NOT EXISTS hospital_insurance (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id  INT UNSIGNED NOT NULL,
    provider_key VARCHAR(30) NOT NULL  COMMENT 'nhif,jubilee,aon,axa,aar,cic',
    provider_name VARCHAR(100) NOT NULL,
    status       ENUM('connected','pending','disconnected') DEFAULT 'pending',
    policy_ref   VARCHAR(100),
    connected_at DATETIME,
    notes        TEXT,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hosp_ins (hospital_id, provider_key),
    FOREIGN KEY (hospital_id) REFERENCES hospital_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Hospital billing/transactions ─────────────────────────────
CREATE TABLE IF NOT EXISTS hospital_billings (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id  INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED,
    patient_name VARCHAR(120),
    service_desc VARCHAR(200),
    amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash','mpesa','nhif','insurance','bank') DEFAULT 'cash',
    status       ENUM('paid','pending','cancelled') DEFAULT 'pending',
    reference    VARCHAR(100),
    billed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospital_providers(id) ON DELETE CASCADE,
    INDEX idx_hospital (hospital_id),
    INDEX idx_date     (billed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Hospital notifications ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS hospital_notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT UNSIGNED NOT NULL,
    type        ENUM('booking','insurance','system','alert','review') DEFAULT 'system',
    title       VARCHAR(200) NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    link        VARCHAR(500),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospital_providers(id) ON DELETE CASCADE,
    INDEX idx_hospital (hospital_id),
    INDEX idx_read     (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Hospital email log ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hospital_email_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id     INT UNSIGNED,
    recipient_email VARCHAR(180) NOT NULL,
    type            VARCHAR(60) NOT NULL,
    subject         VARCHAR(255) NOT NULL,
    status          ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    error_msg       TEXT,
    sent_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hospital (hospital_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Hospital login attempts ────────────────────────────────────
CREATE TABLE IF NOT EXISTS hospital_login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(180) NOT NULL,
    ip_address   VARCHAR(45),
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ip    (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Handle migration: rename email_otp column if exists ────────
-- Run this only if upgrading from old schema that had email_otp column
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'hospital_providers'
    AND COLUMN_NAME = 'email_otp'
);
SET @sql = IF(@col_exists > 0,
    'ALTER TABLE hospital_providers CHANGE COLUMN email_otp email_otp_hash VARCHAR(255) COMMENT ''bcrypt hash of OTP''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
