-- ============================================================
--  AttendX – Complete Database Installation
--  Version  : 1.0.0
--  Engine   : MySQL 8.0+
--  Charset  : utf8mb4 / utf8mb4_unicode_ci
--
--  Run:  mysql -u root -p attendx < install.sql
--  Or :  Use the web installer at /setup.php
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- ============================================================
--  LAYER 1 — CORE REFERENCE TABLES (no dependencies)
-- ============================================================

-- ─── Roles ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(80) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Permissions ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `permissions` (
    `id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`   VARCHAR(100) NOT NULL UNIQUE,
    `module` VARCHAR(50)  NOT NULL,
    `label`  VARCHAR(150) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Departments ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `departments` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL,
    `code`        VARCHAR(20)  NULL,
    `parent_id`   INT UNSIGNED NULL,
    `manager_id`  INT UNSIGNED NULL,   -- set FK after employees table
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `description` TEXT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Designations ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `designations` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(100) NOT NULL,
    `department_id` INT UNSIGNED NULL,
    `grade`         VARCHAR(20)  NULL,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Shifts ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `shifts` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`            VARCHAR(100) NOT NULL,
    `code`            VARCHAR(20)  NULL UNIQUE,
    `start_time`      TIME NOT NULL,
    `end_time`        TIME NOT NULL,
    `is_night_shift`  TINYINT(1) NOT NULL DEFAULT 0,
    `grace_minutes`   SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    `ot_threshold`    SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `work_minutes`    SMALLINT UNSIGNED NOT NULL DEFAULT 480,
    `half_day_minutes`SMALLINT UNSIGNED NOT NULL DEFAULT 240,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Shift Days ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `shift_days` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `shift_id`   INT UNSIGNED NOT NULL,
    `day_of_week`TINYINT UNSIGNED NOT NULL COMMENT '0=Sun,1=Mon,...,6=Sat',
    `is_working` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uq_shift_day` (`shift_id`,`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Devices ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `devices` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`           VARCHAR(100) NOT NULL,
    `serial_number`  VARCHAR(50)  NULL UNIQUE,
    `ip_address`     VARCHAR(45)  NULL,
    `port`           SMALLINT UNSIGNED NOT NULL DEFAULT 4370 COMMENT 'SDK pull port',
    `adms_port`      SMALLINT UNSIGNED NOT NULL DEFAULT 8080 COMMENT 'ADMS push port',
    `model`          VARCHAR(80)  NULL,
    `location`       VARCHAR(150) NULL,
    `status`         ENUM('online','offline','disabled') NOT NULL DEFAULT 'offline',
    `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
    `last_sync_at`   DATETIME NULL,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Leave Types ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `leave_types` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`              VARCHAR(100) NOT NULL,
    `code`              VARCHAR(20)  NOT NULL UNIQUE,
    `days_allowed`      DECIMAL(5,1) NOT NULL DEFAULT 0,
    `carry_forward`     TINYINT(1) NOT NULL DEFAULT 0,
    `requires_document` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
    `description`       TEXT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Holidays ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `holidays` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`         VARCHAR(150) NOT NULL,
    `holiday_date` DATE NOT NULL UNIQUE,
    `type`         ENUM('public','optional','company') NOT NULL DEFAULT 'public',
    `description`  TEXT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Payroll Components ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payroll_components` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL,
    `code`        VARCHAR(30)  NOT NULL UNIQUE,
    `type`        ENUM('earning','deduction','benefit') NOT NULL DEFAULT 'earning',
    `calculation` ENUM('fixed','percentage','formula')  NOT NULL DEFAULT 'fixed',
    `formula`     TEXT NULL,
    `taxable`     TINYINT(1) NOT NULL DEFAULT 1,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Access Zones ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `access_zones` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`           VARCHAR(100) NOT NULL,
    `description`    TEXT NULL,
    `zone_code`      VARCHAR(30)  NOT NULL UNIQUE,
    `security_level` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Access Schedules ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `access_schedules` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL,
    `time_from`   TIME NOT NULL,
    `time_to`     TIME NOT NULL,
    `days_of_week`SET('Mon','Tue','Wed','Thu','Fri','Sat','Sun') NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri',
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── System Settings ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`         VARCHAR(100) NOT NULL UNIQUE,
    `value`       TEXT NULL,
    `group`       VARCHAR(50)  NOT NULL DEFAULT 'app',
    `type`        ENUM('text','number','boolean','json','password') NOT NULL DEFAULT 'text',
    `label`       VARCHAR(150) NULL,
    `description` TEXT NULL,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  LAYER 2 — USER & EMPLOYEE CORE
-- ============================================================

-- ─── Users ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(100) NOT NULL,
    `email`         VARCHAR(150) NOT NULL UNIQUE,
    `password`      VARCHAR(255) NOT NULL,
    `role_id`       INT UNSIGNED NOT NULL,
    `status`        ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME NULL,
    `last_login_ip` VARCHAR(45) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_users_email`  ON `users`(`email`);
CREATE INDEX `idx_users_role`   ON `users`(`role_id`);
CREATE INDEX `idx_users_status` ON `users`(`status`);

-- ─── Role Permissions ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`,`permission_id`),
    FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Employees ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `employees` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_code`     VARCHAR(20)  NOT NULL UNIQUE,
    `full_name`         VARCHAR(150) NOT NULL,
    `email`             VARCHAR(150) NULL UNIQUE,
    `phone`             VARCHAR(20)  NULL,
    `gender`            ENUM('male','female','other') NOT NULL DEFAULT 'male',
    `date_of_birth`     DATE NULL,
    `blood_group`       ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL,
    `department_id`     INT UNSIGNED NULL,
    `designation_id`    INT UNSIGNED NULL,
    `manager_id`        INT UNSIGNED NULL,
    `join_date`         DATE NOT NULL,
    `confirm_date`      DATE NULL,
    `resign_date`       DATE NULL,
    `status`            ENUM('active','inactive','on_leave','terminated') NOT NULL DEFAULT 'active',
    `badge_number`      VARCHAR(50)  NULL UNIQUE,
    `address`           TEXT NULL,
    `emergency_contact` VARCHAR(200) NULL,
    `profile_photo`     VARCHAR(255) NULL,
    `user_id`           INT UNSIGNED NULL,
    `created_by`        INT UNSIGNED NOT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`)  REFERENCES `departments`(`id`)  ON DELETE SET NULL,
    FOREIGN KEY (`designation_id`) REFERENCES `designations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`manager_id`)     REFERENCES `employees`(`id`)    ON DELETE SET NULL,
    FOREIGN KEY (`user_id`)        REFERENCES `users`(`id`)        ON DELETE SET NULL,
    FOREIGN KEY (`created_by`)     REFERENCES `users`(`id`)        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_employees_dept`   ON `employees`(`department_id`);
CREATE INDEX `idx_employees_status` ON `employees`(`status`);
CREATE INDEX `idx_employees_badge`  ON `employees`(`badge_number`);
CREATE INDEX `idx_employees_user`   ON `employees`(`user_id`);

-- Add department manager FK now that employees exists
ALTER TABLE `departments`
    ADD CONSTRAINT `fk_dept_manager` FOREIGN KEY (`manager_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL;

-- ─── Employee Documents ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `employee_documents` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id`   INT UNSIGNED NOT NULL,
    `document_type` VARCHAR(80)  NOT NULL,
    `document_name` VARCHAR(200) NOT NULL,
    `file_path`     VARCHAR(500) NOT NULL,
    `expiry_date`   DATE NULL,
    `created_by`    INT UNSIGNED NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Employee Transfers ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `employee_transfers` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id`         INT UNSIGNED NOT NULL,
    `from_department_id`  INT UNSIGNED NULL,
    `to_department_id`    INT UNSIGNED NULL,
    `from_designation_id` INT UNSIGNED NULL,
    `to_designation_id`   INT UNSIGNED NULL,
    `effective_date`      DATE NOT NULL,
    `reason`              TEXT NULL,
    `created_by`          INT UNSIGNED NOT NULL,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`)         REFERENCES `employees`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`from_department_id`)  REFERENCES `departments`(`id`)  ON DELETE SET NULL,
    FOREIGN KEY (`to_department_id`)    REFERENCES `departments`(`id`)  ON DELETE SET NULL,
    FOREIGN KEY (`from_designation_id`) REFERENCES `designations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`to_designation_id`)   REFERENCES `designations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`)          REFERENCES `users`(`id`)        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  LAYER 3 — ROSTER & SHIFT ASSIGNMENT
-- ============================================================

-- ─── Rosters ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rosters` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL,
    `shift_id`    INT UNSIGNED NOT NULL,
    `start_date`  DATE NOT NULL,
    `end_date`    DATE NULL,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`shift_id`)   REFERENCES `shifts`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Roster Employees ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `roster_employees` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `roster_id`   INT UNSIGNED NOT NULL,
    `employee_id` INT UNSIGNED NOT NULL,
    UNIQUE KEY `uq_roster_emp` (`roster_id`,`employee_id`),
    FOREIGN KEY (`roster_id`)   REFERENCES `rosters`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Shift Assignments (individual overrides) ───────────────
CREATE TABLE IF NOT EXISTS `shift_assignments` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `shift_id`    INT UNSIGNED NOT NULL,
    `date_from`   DATE NOT NULL,
    `date_to`     DATE NULL,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`shift_id`)    REFERENCES `shifts`(`id`)    ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Roster Shift Change Requests ──────────────────────────
CREATE TABLE IF NOT EXISTS `roster_shift_changes` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id`     INT UNSIGNED NOT NULL,
    `requested_date`  DATE NOT NULL,
    `current_shift_id`INT UNSIGNED NOT NULL,
    `requested_shift_id`INT UNSIGNED NOT NULL,
    `reason`          TEXT NOT NULL,
    `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `approved_by`     INT UNSIGNED NULL,
    `approved_at`     DATETIME NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`)        REFERENCES `employees`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`current_shift_id`)   REFERENCES `shifts`(`id`)   ON DELETE RESTRICT,
    FOREIGN KEY (`requested_shift_id`) REFERENCES `shifts`(`id`)   ON DELETE RESTRICT,
    FOREIGN KEY (`approved_by`)        REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  LAYER 4 — ATTENDANCE
-- ============================================================

-- ─── Raw Punch Logs (from devices) ──────────────────────────
CREATE TABLE IF NOT EXISTS `attendance_logs` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `device_id`    INT UNSIGNED NOT NULL,
    `employee_id`  INT UNSIGNED NULL,
    `badge_number` VARCHAR(50)  NULL,
    `punch_time`   DATETIME NOT NULL,
    `punch_type`   TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=in,1=out,4=ot-in,5=ot-out',
    `verify_type`  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=fp,3=pwd,4=card',
    `source`       ENUM('adms','sdk','manual','api') NOT NULL DEFAULT 'adms',
    `note`         VARCHAR(255) NULL,
    `synced_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`device_id`)   REFERENCES `devices`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_att_logs_emp`    ON `attendance_logs`(`employee_id`);
CREATE INDEX `idx_att_logs_time`   ON `attendance_logs`(`punch_time`);
CREATE INDEX `idx_att_logs_badge`  ON `attendance_logs`(`badge_number`);
CREATE INDEX `idx_att_logs_device` ON `attendance_logs`(`device_id`);

-- ─── Processing Queue ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `processing_queue` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `device_id`  INT UNSIGNED NOT NULL,
    `queued_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed`  TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY `uq_device` (`device_id`),
    FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Processed Attendance Records ───────────────────────────
CREATE TABLE IF NOT EXISTS `attendance_records` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id`      INT UNSIGNED NOT NULL,
    `attendance_date`  DATE NOT NULL,
    `shift_id`         INT UNSIGNED NULL,
    `status`           ENUM('present','absent','late','half_day','on_leave','holiday','weekend','off') NOT NULL DEFAULT 'absent',
    `time_in`          TIME NULL,
    `time_out`         TIME NULL,
    `work_minutes`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `late_minutes`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `overtime_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `note`             VARCHAR(255) NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_emp_date` (`employee_id`,`attendance_date`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`shift_id`)    REFERENCES `shifts`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_att_records_date`   ON `attendance_records`(`attendance_date`);
CREATE INDEX `idx_att_records_status` ON `attendance_records`(`status`);

-- ─── Overtime Rules ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `overtime_rules` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`            VARCHAR(100) NOT NULL,
    `day_type`        ENUM('weekday','weekend','holiday','all') NOT NULL DEFAULT 'weekday',
    `min_minutes`     SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `max_minutes`     SMALLINT UNSIGNED NOT NULL DEFAULT 480,
    `rate_multiplier` DECIMAL(4,2) NOT NULL DEFAULT 1.50,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Overtime Applications ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `overtime_applications` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id`  INT UNSIGNED NOT NULL,
    `date`         DATE NOT NULL,
    `start_time`   TIME NOT NULL,
    `end_time`     TIME NOT NULL,
    `total_minutes`SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `reason`       TEXT NOT NULL,
    `status`       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `approved_by`  INT UNSIGNED NULL,
    `approved_at`  DATETIME NULL,
    `created_by`   INT UNSIGNED NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)     ON DELETE SET NULL,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  LAYER 5 — LEAVE
-- ============================================================

-- ─── Leave Balances ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `leave_balances` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id`     INT UNSIGNED NOT NULL,
    `leave_type_id`   INT UNSIGNED NOT NULL,
    `year`            YEAR NOT NULL,
    `allocated_days`  DECIMAL(5,1) NOT NULL DEFAULT 0,
    `carried_forward` DECIMAL(5,1) NOT NULL DEFAULT 0,
    UNIQUE KEY `uq_emp_type_year` (`employee_id`,`leave_type_id`,`year`),
    FOREIGN KEY (`employee_id`)   REFERENCES `employees`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Leave Applications ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `leave_applications` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id`   INT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `from_date`     DATE NOT NULL,
    `to_date`       DATE NOT NULL,
    `total_days`    DECIMAL(5,1) NOT NULL DEFAULT 1,
    `half_day`      TINYINT(1) NOT NULL DEFAULT 0,
    `reason`        TEXT NOT NULL,
    `document_path` VARCHAR(500) NULL,
    `status`        ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `approved_by`   INT UNSIGNED NULL,
    `approved_at`   DATETIME NULL,
    `remarks`       TEXT NULL,
    `applied_by`    INT UNSIGNED NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`)   REFERENCES `employees`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`approved_by`)   REFERENCES `users`(`id`)       ON DELETE SET NULL,
    FOREIGN KEY (`applied_by`)    REFERENCES `users`(`id`)       ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_leave_apps_emp`    ON `leave_applications`(`employee_id`);
CREATE INDEX `idx_leave_apps_status` ON `leave_applications`(`status`);
CREATE INDEX `idx_leave_apps_dates`  ON `leave_applications`(`from_date`,`to_date`);

-- ─── Remote Work Policies ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `remote_work_policies` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`            VARCHAR(100) NOT NULL,
    `max_days_week`   TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `max_days_month`  TINYINT UNSIGNED NOT NULL DEFAULT 8,
    `requires_approval`TINYINT(1) NOT NULL DEFAULT 1,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Remote Work Requests ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `remote_work_requests` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `policy_id`   INT UNSIGNED NULL,
    `work_date`   DATE NOT NULL,
    `reason`      TEXT NOT NULL,
    `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `approved_by` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`)            ON DELETE CASCADE,
    FOREIGN KEY (`policy_id`)   REFERENCES `remote_work_policies`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)                ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  LAYER 6 — PAYROLL
-- ============================================================

-- ─── Salary Structures ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `salary_structures` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Salary Structure Components ────────────────────────────
CREATE TABLE IF NOT EXISTS `salary_structure_components` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `structure_id`      INT UNSIGNED NOT NULL,
    `component_id`      INT UNSIGNED NOT NULL,
    `amount`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `percentage`        DECIMAL(5,2) NULL,
    `base_component_id` INT UNSIGNED NULL,
    UNIQUE KEY `uq_structure_component` (`structure_id`,`component_id`),
    FOREIGN KEY (`structure_id`)      REFERENCES `salary_structures`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`component_id`)      REFERENCES `payroll_components`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`base_component_id`) REFERENCES `payroll_components`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Employee Salary ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `employee_salary` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id`    INT UNSIGNED NOT NULL,
    `structure_id`   INT UNSIGNED NOT NULL,
    `effective_from` DATE NOT NULL,
    `effective_to`   DATE NULL,
    `basic_salary`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `bank_name`      VARCHAR(100) NULL,
    `bank_account`   VARCHAR(50)  NULL,
    `bank_branch`    VARCHAR(100) NULL,
    `payment_method` ENUM('bank','cash','cheque') NOT NULL DEFAULT 'bank',
    `created_by`     INT UNSIGNED NOT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`)  REFERENCES `employees`(`id`)        ON DELETE CASCADE,
    FOREIGN KEY (`structure_id`) REFERENCES `salary_structures`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`)            ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_emp_salary_emp`  ON `employee_salary`(`employee_id`);
CREATE INDEX `idx_emp_salary_date` ON `employee_salary`(`effective_from`);

-- ─── Payroll Periods ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payroll_periods` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`         VARCHAR(100) NOT NULL,
    `period_from`  DATE NOT NULL,
    `period_to`    DATE NOT NULL,
    `status`       ENUM('draft','processing','processed','approved','paid','closed') NOT NULL DEFAULT 'draft',
    `processed_by` INT UNSIGNED NULL,
    `approved_by`  INT UNSIGNED NULL,
    `processed_at` DATETIME NULL,
    `approved_at`  DATETIME NULL,
    `notes`        TEXT NULL,
    `created_by`   INT UNSIGNED NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_payroll_periods_status` ON `payroll_periods`(`status`);

-- ─── Payroll Slips ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payroll_slips` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `period_id`        INT UNSIGNED NOT NULL,
    `employee_id`      INT UNSIGNED NOT NULL,
    `salary_id`        INT UNSIGNED NOT NULL,
    `working_days`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `present_days`     DECIMAL(5,1) NOT NULL DEFAULT 0,
    `absent_days`      DECIMAL(5,1) NOT NULL DEFAULT 0,
    `late_days`        DECIMAL(5,1) NOT NULL DEFAULT 0,
    `overtime_hours`   DECIMAL(6,2) NOT NULL DEFAULT 0,
    `leave_days`       DECIMAL(5,1) NOT NULL DEFAULT 0,
    `gross_earnings`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_deductions` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_salary`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status`           ENUM('draft','approved','paid') NOT NULL DEFAULT 'draft',
    `payment_date`     DATE NULL,
    `payment_ref`      VARCHAR(100) NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_period_employee` (`period_id`,`employee_id`),
    FOREIGN KEY (`period_id`)   REFERENCES `payroll_periods`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`)        ON DELETE CASCADE,
    FOREIGN KEY (`salary_id`)   REFERENCES `employee_salary`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_payroll_slips_employee` ON `payroll_slips`(`employee_id`);
CREATE INDEX `idx_payroll_slips_status`   ON `payroll_slips`(`status`);

-- ─── Payroll Slip Components ────────────────────────────────
CREATE TABLE IF NOT EXISTS `payroll_slip_components` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slip_id`      INT UNSIGNED NOT NULL,
    `component_id` INT UNSIGNED NOT NULL,
    `amount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (`slip_id`)      REFERENCES `payroll_slips`(`id`)       ON DELETE CASCADE,
    FOREIGN KEY (`component_id`) REFERENCES `payroll_components`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Payroll Adjustments ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payroll_adjustments` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `period_id`   INT UNSIGNED NOT NULL,
    `employee_id` INT UNSIGNED NOT NULL,
    `type`        ENUM('bonus','deduction','advance','reimbursement') NOT NULL,
    `amount`      DECIMAL(12,2) NOT NULL,
    `reason`      TEXT NOT NULL,
    `approved_by` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`period_id`)   REFERENCES `payroll_periods`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`)       ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)           ON DELETE SET NULL,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)           ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  LAYER 7 — ACCESS CONTROL
-- ============================================================

-- ─── Access Doors ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `access_doors` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zone_id`    INT UNSIGNED NOT NULL,
    `device_id`  INT UNSIGNED NOT NULL,
    `name`       VARCHAR(100) NOT NULL,
    `door_index` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `direction`  ENUM('in','out','both') NOT NULL DEFAULT 'both',
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`zone_id`)   REFERENCES `access_zones`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Access Rules ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `access_rules` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zone_id`     INT UNSIGNED NOT NULL,
    `rule_type`   ENUM('employee','department','designation','all') NOT NULL DEFAULT 'employee',
    `ref_id`      INT UNSIGNED NULL,
    `schedule_id` INT UNSIGNED NULL,
    `permission`  ENUM('allow','deny') NOT NULL DEFAULT 'allow',
    `valid_from`  DATE NULL,
    `valid_to`    DATE NULL,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`zone_id`)     REFERENCES `access_zones`(`id`)     ON DELETE CASCADE,
    FOREIGN KEY (`schedule_id`) REFERENCES `access_schedules`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)            ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Access Logs ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `access_logs` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `device_id`    INT UNSIGNED NOT NULL,
    `door_id`      INT UNSIGNED NULL,
    `zone_id`      INT UNSIGNED NULL,
    `employee_id`  INT UNSIGNED NULL,
    `badge_number` VARCHAR(50)  NULL,
    `event_time`   DATETIME NOT NULL,
    `event_type`   ENUM('access_granted','access_denied','door_open','door_close','alarm','tamper') NOT NULL,
    `direction`    ENUM('in','out','unknown') NOT NULL DEFAULT 'unknown',
    `deny_reason`  VARCHAR(100) NULL,
    `raw_data`     JSON NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`device_id`)   REFERENCES `devices`(`id`)      ON DELETE CASCADE,
    FOREIGN KEY (`door_id`)     REFERENCES `access_doors`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`zone_id`)     REFERENCES `access_zones`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_access_logs_time`       ON `access_logs`(`event_time`);
CREATE INDEX `idx_access_logs_employee`   ON `access_logs`(`employee_id`);
CREATE INDEX `idx_access_logs_event_type` ON `access_logs`(`event_type`);

-- ─── Visitor Passes ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `visitor_passes` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `visitor_name`     VARCHAR(150) NOT NULL,
    `visitor_company`  VARCHAR(150) NULL,
    `visitor_phone`    VARCHAR(20)  NULL,
    `visitor_id_type`  ENUM('nid','passport','driving_license','other') NOT NULL DEFAULT 'nid',
    `visitor_id_no`    VARCHAR(50)  NOT NULL,
    `host_employee_id` INT UNSIGNED NOT NULL,
    `purpose`          TEXT NOT NULL,
    `zones_allowed`    JSON NULL,
    `valid_from`       DATETIME NOT NULL,
    `valid_to`         DATETIME NOT NULL,
    `badge_code`       VARCHAR(50)  NOT NULL UNIQUE,
    `status`           ENUM('active','expired','revoked','completed') NOT NULL DEFAULT 'active',
    `checked_in_at`    DATETIME NULL,
    `checked_out_at`   DATETIME NULL,
    `created_by`       INT UNSIGNED NOT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`host_employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`)       REFERENCES `users`(`id`)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  LAYER 8 — SYSTEM & NOTIFICATIONS
-- ============================================================

-- ─── Audit Logs ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NULL,
    `module`      VARCHAR(50)  NOT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `old_values`  JSON NULL,
    `new_values`  JSON NULL,
    `ip_address`  VARCHAR(45)  NULL,
    `user_agent`  VARCHAR(500) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_audit_logs_user`    ON `audit_logs`(`user_id`);
CREATE INDEX `idx_audit_logs_module`  ON `audit_logs`(`module`);
CREATE INDEX `idx_audit_logs_created` ON `audit_logs`(`created_at`);

-- ─── Notifications ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `type`       VARCHAR(80)  NOT NULL,
    `title`      VARCHAR(200) NOT NULL,
    `body`       TEXT NOT NULL,
    `data`       JSON NULL,
    `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
    `read_at`    DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_notifications_user`    ON `notifications`(`user_id`);
CREATE INDEX `idx_notifications_is_read` ON `notifications`(`is_read`);

-- ─── User Push Tokens ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_push_tokens` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `fcm_token`  TEXT NOT NULL,
    `platform`   ENUM('android','ios','web','unknown') NOT NULL DEFAULT 'unknown',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_platform` (`user_id`,`platform`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── JWT Refresh Tokens ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jwt_refresh_tokens` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64)  NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `revoked`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_refresh_expires` ON `jwt_refresh_tokens`(`expires_at`);

-- ============================================================
--  SEED DATA
-- ============================================================

-- ─── Roles ──────────────────────────────────────────────────
INSERT INTO `roles` (`name`, `description`) VALUES
('Super Admin',      'Full system access. Cannot be restricted.'),
('HR Manager',       'Manage employees, attendance, leave, and reports.'),
('Finance Manager',  'Payroll processing, salary management, financial reports.'),
('Department Manager','Approve leave, view team attendance and reports.'),
('Security Officer', 'Access control management and visitor handling.'),
('Employee',         'Self-service: view own attendance, apply leave, view payslips.'),
('Viewer',           'Read-only access to reports and dashboards.');

-- ─── Permissions ────────────────────────────────────────────
INSERT INTO `permissions` (`name`, `module`, `label`) VALUES
('dashboard',              'dashboard', 'View Dashboard'),
('employees',              'employees', 'View Employees'),
('employees.create',       'employees', 'Create Employees'),
('employees.edit',         'employees', 'Edit Employees'),
('employees.delete',       'employees', 'Delete Employees'),
('attendance',             'attendance','View Attendance'),
('attendance.process',     'attendance','Process Attendance'),
('attendance.edit',        'attendance','Edit Attendance Records'),
('devices',                'devices',   'View Devices'),
('devices.manage',         'devices',   'Manage Devices'),
('leave',                  'leave',     'View Leave'),
('leave.approve',          'leave',     'Approve/Reject Leave'),
('leave.manage',           'leave',     'Manage Leave Types'),
('payroll',                'payroll',   'View Payroll'),
('payroll.process',        'payroll',   'Process Payroll'),
('payroll.approve',        'payroll',   'Approve Payroll'),
('payroll.manage',         'payroll',   'Manage Payroll Settings'),
('access_control',         'access',    'View Access Control'),
('access_control.manage',  'access',    'Manage Access Rules'),
('reports',                'reports',   'View Reports'),
('reports.export',         'reports',   'Export Reports'),
('system',                 'system',    'View System Panel'),
('system.settings',        'system',    'Manage System Settings'),
('system.users',           'system',    'Manage Users'),
('system.roles',           'system',    'Manage Roles & Permissions'),
('system.audit',           'system',    'View Audit Logs'),
('system.audit.export',    'system',    'Export Audit Logs'),
('system.audit.purge',     'system',    'Purge Audit Logs'),
('system.backup',          'system',    'Manage Backups'),
('system.backup.restore',  'system',    'Restore from Backup');

-- Grant ALL permissions to Super Admin (role_id = 1)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- HR Manager permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions`
WHERE `name` IN (
    'dashboard','employees','employees.create','employees.edit',
    'attendance','attendance.process','attendance.edit',
    'devices','leave','leave.approve','leave.manage',
    'reports','reports.export'
);

-- Finance Manager permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions`
WHERE `name` IN (
    'dashboard','employees','attendance',
    'payroll','payroll.process','payroll.approve','payroll.manage',
    'reports','reports.export'
);

-- Department Manager permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions`
WHERE `name` IN ('dashboard','employees','attendance','leave','leave.approve','reports');

-- Security Officer permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, id FROM `permissions`
WHERE `name` IN ('dashboard','access_control','access_control.manage','reports');

-- Employee permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions`
WHERE `name` IN ('dashboard','attendance','leave');

-- Viewer permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 7, id FROM `permissions`
WHERE `name` IN ('dashboard','reports');

-- ─── Default Admin User ─────────────────────────────────────
-- Password: Admin@1234  (bcrypt cost=12)
INSERT INTO `users` (`name`, `email`, `password`, `role_id`, `status`) VALUES
(
    'System Administrator',
    'admin@attendx.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1,
    'active'
);

-- ─── Default Departments ────────────────────────────────────
INSERT INTO `departments` (`name`, `code`, `is_active`) VALUES
('Human Resources',  'HR',    1),
('Finance',          'FIN',   1),
('Information Technology', 'IT', 1),
('Operations',       'OPS',   1),
('Administration',   'ADMIN', 1);

-- ─── Default Designations ───────────────────────────────────
INSERT INTO `designations` (`name`, `department_id`, `is_active`) VALUES
('HR Manager',       1, 1),
('HR Officer',       1, 1),
('Finance Manager',  2, 1),
('Accountant',       2, 1),
('IT Manager',       3, 1),
('Software Engineer',3, 1),
('Operations Manager',4, 1),
('Admin Officer',    5, 1);

-- ─── Default Shift ──────────────────────────────────────────
INSERT INTO `shifts` (`name`, `code`, `start_time`, `end_time`, `grace_minutes`, `ot_threshold`, `work_minutes`) VALUES
('General (9-6)',  'GEN',   '09:00:00', '18:00:00', 15, 30, 480),
('Morning (8-5)',  'MOR',   '08:00:00', '17:00:00', 15, 30, 480),
('Night (10PM-6AM)','NIGHT','22:00:00', '06:00:00', 15, 30, 480);

-- Insert working days for General shift (Mon-Fri = days 1-5)
INSERT INTO `shift_days` (`shift_id`, `day_of_week`, `is_working`) VALUES
(1,1,1),(1,2,1),(1,3,1),(1,4,1),(1,5,1),(1,6,0),(1,0,0),
(2,1,1),(2,2,1),(2,3,1),(2,4,1),(2,5,1),(2,6,0),(2,0,0),
(3,1,1),(3,2,1),(3,3,1),(3,4,1),(3,5,1),(3,6,0),(3,0,0);

-- ─── Default Leave Types ────────────────────────────────────
INSERT INTO `leave_types` (`name`, `code`, `days_allowed`, `carry_forward`, `requires_document`) VALUES
('Annual Leave',        'AL',  18,  1, 0),
('Sick Leave',          'SL',  10,  0, 1),
('Casual Leave',        'CL',   7,  0, 0),
('Maternity Leave',     'ML', 112,  0, 1),
('Paternity Leave',     'PL',   7,  0, 0),
('Unpaid Leave',        'UL',   0,  0, 0),
('Compensatory Leave',  'CO',   0,  1, 0);

-- ─── Default Payroll Components ─────────────────────────────
INSERT INTO `payroll_components` (`name`, `code`, `type`, `calculation`, `taxable`, `sort_order`) VALUES
('Basic Salary',        'BASIC',  'earning',   'fixed',      1,  1),
('House Rent Allowance','HRA',    'earning',   'percentage', 1,  2),
('Medical Allowance',   'MED',    'earning',   'fixed',      0,  3),
('Transport Allowance', 'TRANS',  'earning',   'fixed',      1,  4),
('Overtime Pay',        'OT',     'earning',   'formula',    1,  5),
('Provident Fund',      'PF',     'deduction', 'percentage', 0, 10),
('Income Tax',          'IT',     'deduction', 'formula',    1, 11),
('Late Deduction',      'LATE',   'deduction', 'formula',    0, 12),
('Absent Deduction',    'ABSENT', 'deduction', 'formula',    0, 13);

-- ─── Default Access Zones ───────────────────────────────────
INSERT INTO `access_zones` (`name`, `zone_code`, `security_level`, `description`) VALUES
('Main Entrance',      'MAIN_ENT',   1, 'Primary building entrance'),
('Office Floor',       'OFF_FLOOR',  2, 'General office area'),
('Server Room',        'SRV_ROOM',   4, 'Data center and server room'),
('HR Department',      'HR_DEPT',    2, 'Human resources area'),
('Finance Department', 'FIN_DEPT',   3, 'Finance and accounts area');

-- ─── Default Access Schedule ────────────────────────────────
INSERT INTO `access_schedules` (`name`, `time_from`, `time_to`, `days_of_week`) VALUES
('Business Hours', '08:00:00', '19:00:00', 'Mon,Tue,Wed,Thu,Fri'),
('24/7 Access',    '00:00:00', '23:59:00', 'Mon,Tue,Wed,Thu,Fri,Sat,Sun');

-- ─── System Settings ────────────────────────────────────────
INSERT INTO `system_settings` (`key`, `value`, `group`, `type`, `label`) VALUES
('app.name',                         'AttendX',     'app',        'text',    'Application Name'),
('app.timezone',                     'Asia/Dhaka',  'app',        'text',    'Timezone'),
('app.date_format',                  'd/m/Y',       'app',        'text',    'Date Format'),
('app.time_format',                  'H:i',         'app',        'text',    'Time Format'),
('app.currency',                     '৳',          'app',        'text',    'Currency Symbol'),
('app.week_start',                   '0',           'app',        'number',  'Week Start (0=Sun)'),
('app.locale',                       'en',          'app',        'text',    'Language'),
('company.name',                     'My Company',  'company',    'text',    'Company Name'),
('company.address',                  '',            'company',    'text',    'Address'),
('company.phone',                    '',            'company',    'text',    'Phone'),
('company.email',                    '',            'company',    'text',    'Email'),
('attendance.late_grace_minutes',    '15',          'attendance', 'number',  'Late Grace (min)'),
('attendance.early_leave_grace_minutes','10',       'attendance', 'number',  'Early Leave Grace (min)'),
('attendance.ot_threshold_minutes',  '30',          'attendance', 'number',  'OT Threshold (min)'),
('attendance.min_work_hours',        '8',           'attendance', 'number',  'Min Work Hours'),
('attendance.half_day_hours',        '4',           'attendance', 'number',  'Half Day Hours'),
('attendance.auto_process_time',     '23:59',       'attendance', 'text',    'Auto Process Time'),
('attendance.auto_process',          '1',           'attendance', 'boolean', 'Auto Process Daily'),
('attendance.mark_absent_if_no_punch','1',          'attendance', 'boolean', 'Mark Absent If No Punch'),
('mail.host',                        'smtp.gmail.com','mail',     'text',    'SMTP Host'),
('mail.port',                        '587',         'mail',       'number',  'SMTP Port'),
('mail.encryption',                  'tls',         'mail',       'text',    'Encryption'),
('mail.username',                    '',            'mail',       'text',    'Username'),
('mail.password',                    '',            'mail',       'password','Password'),
('mail.from_name',                   'AttendX',     'mail',       'text',    'From Name'),
('mail.from_email',                  '',            'mail',       'text',    'From Email'),
('security.session_timeout',         '120',         'security',   'number',  'Session Timeout (min)'),
('security.max_login_attempts',      '5',           'security',   'number',  'Max Login Attempts'),
('security.lockout_duration',        '30',          'security',   'number',  'Lockout Duration (min)'),
('security.min_password_length',     '8',           'security',   'number',  'Min Password Length'),
('security.jwt_ttl',                 '86400',       'security',   'number',  'JWT TTL (sec)'),
('security.require_2fa',             '0',           'security',   'boolean', 'Require 2FA'),
('security.log_all_access',          '1',           'security',   'boolean', 'Log All Access'),
('device.adms_port',                 '8080',        'device',     'number',  'ADMS Primary Port'),
('device.adms_port_secondary',       '8081',        'device',     'number',  'ADMS Secondary Port'),
('device.sdk_port',                  '4370',        'device',     'number',  'SDK Pull Port'),
('device.sync_interval',             '300',         'device',     'number',  'Sync Interval (sec)'),
('device.auto_sync',                 '1',           'device',     'boolean', 'Auto Sync Devices')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- ─── Overtime Rules ─────────────────────────────────────────
INSERT INTO `overtime_rules` (`name`, `day_type`, `min_minutes`, `max_minutes`, `rate_multiplier`) VALUES
('Weekday OT',  'weekday', 30, 240, 1.50),
('Weekend OT',  'weekend', 60, 480, 2.00),
('Holiday OT',  'holiday', 60, 480, 2.50);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  DEFAULT CREDENTIALS
--  Email   : admin@attendx.com
--  Password: Admin@1234
--  CHANGE IMMEDIATELY AFTER FIRST LOGIN
-- ============================================================
