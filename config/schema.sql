-- ================================================================
-- Planeazzy Healthcare — Complete Database Schema v3.0
-- mysql -u root -p < config/schema.sql
-- ================================================================
CREATE DATABASE IF NOT EXISTS planeazzy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE planeazzy_db;

CREATE TABLE IF NOT EXISTS patients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL,
    email VARCHAR(180) NOT NULL, phone VARCHAR(30) NOT NULL,
    password_hash VARCHAR(255) NOT NULL, date_of_birth DATE DEFAULT NULL,
    gender ENUM('male','female','non_binary','prefer_not') DEFAULT NULL,
    preferred_language VARCHAR(10) DEFAULT 'en', preferred_service VARCHAR(40) DEFAULT NULL,
    onboarding_complete TINYINT(1) DEFAULT 0, is_verified TINYINT(1) DEFAULT 0,
    status ENUM('pending','active','suspended') DEFAULT 'pending',
    otp_hash VARCHAR(64) DEFAULT NULL, otp_expiry DATETIME DEFAULT NULL,
    latitude DECIMAL(10,8) DEFAULT NULL, longitude DECIMAL(11,8) DEFAULT NULL,
    h3_index VARCHAR(20) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL,
    county VARCHAR(100) DEFAULT NULL, last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email (email), INDEX idx_status (status), INDEX idx_h3 (h3_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS providers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL, email VARCHAR(180) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL, password_hash VARCHAR(255) NOT NULL,
    type ENUM('doctor','clinic','hospital','ambulance','pharmacy','lab') NOT NULL,
    specialty VARCHAR(120) DEFAULT NULL, description TEXT DEFAULT NULL,
    license_number VARCHAR(80) DEFAULT NULL, license_doc VARCHAR(255) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL,
    county VARCHAR(100) DEFAULT NULL,
    latitude DECIMAL(10,8) DEFAULT NULL, longitude DECIMAL(11,8) DEFAULT NULL,
    h3_index_r7 VARCHAR(20) DEFAULT NULL, h3_index_r9 VARCHAR(20) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL, opening_hours JSON DEFAULT NULL,
    services JSON DEFAULT NULL, image_url VARCHAR(255) DEFAULT NULL,
    rating DECIMAL(3,2) DEFAULT 0.00, review_count INT UNSIGNED DEFAULT 0,
    is_available TINYINT(1) DEFAULT 1,
    is_verified TINYINT(1) DEFAULT 0, otp_hash VARCHAR(64) DEFAULT NULL,
    otp_expiry DATETIME DEFAULT NULL, verified_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    status ENUM('pending','active','suspended','rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider_email (email),
    INDEX idx_type (type), INDEX idx_h3_r9 (h3_index_r9),
    INDEX idx_status (status), INDEX idx_available (is_available, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_realtime_location (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, provider_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10,8) NOT NULL, longitude DECIMAL(11,8) NOT NULL,
    h3_index_r9 VARCHAR(20) DEFAULT NULL, heading SMALLINT DEFAULT NULL,
    speed_kmh DECIMAL(5,2) DEFAULT NULL,
    status ENUM('available','busy','offline','en_route') DEFAULT 'available',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider (provider_id),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    INDEX idx_h3_status (h3_index_r9, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) NOT NULL, ip_address VARCHAR(45) NOT NULL,
    attempted_at INT UNSIGNED NOT NULL,
    INDEX idx_email_time (email, attempted_at), INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(255) NOT NULL, request_count INT UNSIGNED DEFAULT 1,
    expires_at INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_key (`key`), INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL, provider_id INT UNSIGNED DEFAULT NULL,
    service_type ENUM('doctor','clinic','hospital','ambulance','telehealth','pharmacy','lab') NOT NULL,
    title VARCHAR(160) DEFAULT NULL, notes TEXT DEFAULT NULL,
    appointment_at DATETIME NOT NULL, duration_min SMALLINT DEFAULT 30,
    status ENUM('scheduled','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
    location_type ENUM('in_person','telehealth','home_visit') DEFAULT 'in_person',
    meeting_url VARCHAR(255) DEFAULT NULL, meeting_token VARCHAR(64) DEFAULT NULL,
    reminder_sent TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL,
    INDEX idx_patient_appt (patient_id, appointment_at),
    INDEX idx_provider_appt (provider_id, appointment_at),
    INDEX idx_status_date (status, appointment_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS telehealth_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL, patient_id INT UNSIGNED NOT NULL,
    provider_id INT UNSIGNED NOT NULL, room_token VARCHAR(64) NOT NULL,
    status ENUM('waiting','active','ended') DEFAULT 'waiting',
    started_at DATETIME DEFAULT NULL, ended_at DATETIME DEFAULT NULL,
    duration_sec INT UNSIGNED DEFAULT NULL, chat_log JSON DEFAULT NULL,
    doctor_notes TEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    UNIQUE KEY uq_room (room_token), INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS health_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, patient_id INT UNSIGNED NOT NULL,
    record_type ENUM('diagnosis','prescription','lab_result','imaging','vaccination','allergy','vital','note') NOT NULL,
    title VARCHAR(200) NOT NULL, description TEXT DEFAULT NULL,
    provider_id INT UNSIGNED DEFAULT NULL, provider_name VARCHAR(160) DEFAULT NULL,
    record_date DATE NOT NULL, file_path VARCHAR(255) DEFAULT NULL,
    is_private TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL,
    INDEX idx_patient_records (patient_id, record_date DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patient_vitals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, patient_id INT UNSIGNED NOT NULL,
    metric_type ENUM('blood_pressure','heart_rate','temperature','weight','height','bmi','blood_glucose','oxygen_saturation','steps') NOT NULL,
    value_num DECIMAL(8,2) DEFAULT NULL, value_str VARCHAR(20) DEFAULT NULL,
    unit VARCHAR(20) DEFAULT NULL, notes VARCHAR(255) DEFAULT NULL,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_patient_vitals (patient_id, metric_type, recorded_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, patient_id INT UNSIGNED NOT NULL,
    provider_id INT UNSIGNED DEFAULT NULL, medication_name VARCHAR(160) NOT NULL,
    dosage VARCHAR(80) DEFAULT NULL, frequency VARCHAR(80) DEFAULT NULL,
    start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL,
    refills_left TINYINT DEFAULT 0,
    status ENUM('active','completed','paused','cancelled') DEFAULT 'active',
    instructions TEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL,
    INDEX idx_patient_rx (patient_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS emergency_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, patient_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10,8) NOT NULL, longitude DECIMAL(11,8) NOT NULL,
    h3_index_r9 VARCHAR(20) DEFAULT NULL, address_text VARCHAR(255) DEFAULT NULL,
    emergency_type ENUM('ambulance','cardiac','trauma','respiratory','other') DEFAULT 'ambulance',
    notes TEXT DEFAULT NULL, assigned_provider_id INT UNSIGNED DEFAULT NULL,
    status ENUM('pending','dispatched','en_route','on_scene','completed','cancelled') DEFAULT 'pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    dispatched_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_status_time (status, requested_at DESC),
    INDEX idx_h3_emergency (h3_index_r9, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, patient_id INT UNSIGNED NOT NULL,
    type ENUM('appointment','reminder','result','emergency','system','promotion') NOT NULL,
    title VARCHAR(160) NOT NULL, message TEXT NOT NULL,
    icon VARCHAR(60) DEFAULT 'notifications', link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_patient_notif (patient_id, is_read, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL, provider_id INT UNSIGNED NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pp (patient_id, provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed providers ──────────────────────────────────────────
INSERT IGNORE INTO providers (name,email,phone,password_hash,type,specialty,description,address,city,county,latitude,longitude,h3_index_r7,h3_index_r9,rating,review_count,is_available,is_verified,status,services) VALUES
('Kenyatta National Hospital','info@knh.go.ke','+254202726300','$2y$12$placeholder','hospital',NULL,'Kenya largest public referral hospital','Hospital Rd','Nairobi','Nairobi',-1.3013,36.8075,'8726e8d89ffffff','8926e8d8bffffff',4.1,2840,1,1,'active','["emergency","surgery","lab","imaging","outpatient"]'),
('Nairobi Hospital','info@nairobihospital.org','+254202845000','$2y$12$placeholder','hospital',NULL,'Premier private hospital in Nairobi','Argwings Kodhek Rd','Nairobi','Nairobi',-1.2992,36.8085,'8726e8d89ffffff','8926e8d8bffffff',4.6,1923,1,1,'active','["emergency","maternity","oncology","cardiology","lab"]'),
('Aga Khan University Hospital','info@akhospital.org','+254203662000','$2y$12$placeholder','hospital',NULL,'World-class private hospital','3rd Parklands Ave','Nairobi','Nairobi',-1.2645,36.8106,'8726e8d89ffffff','8926e8d99ffffff',4.7,3102,1,1,'active','["emergency","neurology","cardiology","lab","pediatrics"]'),
('Dr. Sarah Wanjiku','sarah.w@planeazzy.com','+254722100200','$2y$12$placeholder','doctor','General Practitioner','Experienced GP, 15 years','Westlands','Nairobi','Nairobi',-1.2676,36.8069,'8726e8d89ffffff','8926e8d99ffffff',4.8,421,1,1,'active','["consultation","vaccination","chronic_disease"]'),
('Dr. James Omondi','james.o@planeazzy.com','+254733200300','$2y$12$placeholder','doctor','Cardiologist','Senior consultant cardiologist','Upper Hill','Nairobi','Nairobi',-1.2979,36.8150,'8726e8d89ffffff','8926e8d8bffffff',4.9,287,1,1,'active','["cardiology","echocardiography","stress_test"]'),
('Dr. Amina Hassan','amina.h@planeazzy.com','+254711300400','$2y$12$placeholder','doctor','Pediatrician','Senior pediatrician','Karen','Nairobi','Nairobi',-1.3500,36.6900,'8726e8d89ffffff','8926e8c89ffffff',4.7,334,1,1,'active','["pediatrics","vaccination","child_development"]'),
('Westlands Medical Centre','info@wmc.co.ke','+254204450000','$2y$12$placeholder','clinic',NULL,'Full-service outpatient clinic','Westlands','Nairobi','Nairobi',-1.2680,36.8075,'8726e8d89ffffff','8926e8d99ffffff',4.3,765,1,1,'active','["consultation","lab","pharmacy","physiotherapy"]'),
('AAR Healthcare Westlands','info@aar.co.ke','+254202719000','$2y$12$placeholder','clinic',NULL,'Leading healthcare chain','Westlands Ring Rd','Nairobi','Nairobi',-1.2921,36.8219,'8726e8d89ffffff','8926e8d89ffffff',4.2,934,1,1,'active','["consultation","lab","pharmacy","dental"]'),
('Nairobi Ambulance Services','dispatch@nas.co.ke','+254202296000','$2y$12$placeholder','ambulance',NULL,'24/7 emergency ambulance dispatch','Nairobi CBD','Nairobi','Nairobi',-1.2833,36.8167,'8726e8d89ffffff','8926e8d89ffffff',4.4,556,1,1,'active','["emergency","paramedic","critical_care"]'),
('St John Ambulance Kenya','info@stjohnkenya.org','+254722202020','$2y$12$placeholder','ambulance',NULL,'Trusted emergency services since 1921','Haile Selassie Ave','Nairobi','Nairobi',-1.2864,36.8225,'8726e8d89ffffff','8926e8d89ffffff',4.6,789,1,1,'active','["emergency","first_aid","event_coverage"]'),
('Goodlife Pharmacy Westlands','westlands@goodlife.co.ke','+254711082000','$2y$12$placeholder','pharmacy',NULL,'Largest pharmacy chain in East Africa','Westlands','Nairobi','Nairobi',-1.2680,36.8075,'8726e8d89ffffff','8926e8d99ffffff',4.3,1102,1,1,'active','["prescription","otc","delivery","consultation"]'),
('Lancet Kenya Laboratories','info@lancet.co.ke','+254722510000','$2y$12$placeholder','lab',NULL,'ISO-certified diagnostic laboratory','Upper Hill','Nairobi','Nairobi',-1.2979,36.8150,'8726e8d89ffffff','8926e8d8bffffff',4.5,678,1,1,'active','["blood_tests","imaging","pathology","microbiology"]');
