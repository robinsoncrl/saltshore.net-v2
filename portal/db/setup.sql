-- ============================================================
-- Saltshore Owner Portal — Database Schema
-- Run once against your MySQL/MariaDB instance
-- ============================================================

CREATE DATABASE IF NOT EXISTS saltshore_portal
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE saltshore_portal;

-- ── Users ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username      VARCHAR(60)     NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username)
) ENGINE=InnoDB;

-- Seed owner account (PIN: 1988)
INSERT IGNORE INTO users (username, password_hash)
VALUES ('Admin', '$2y$10$YvH0wikmdkeRVeqbAiZiluzSnBJFxGTq.bYEd23QvWHRi3Z9n3RCi');

-- ── Portal Settings ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS portal_settings (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    setting_key   VARCHAR(60)     NOT NULL,
    setting_value TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_key (user_id, setting_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── CalGen: Time Blocks ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS time_blocks (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED    NOT NULL,
    employee_id      INT UNSIGNED    DEFAULT NULL,
    start_time       DATETIME        NOT NULL,
    end_time         DATETIME,
    duration_seconds INT UNSIGNED    NOT NULL DEFAULT 0,
    category         VARCHAR(100)    NOT NULL DEFAULT 'General',
    is_billable      TINYINT(1)      NOT NULL DEFAULT 1,
    submitted_for_approval TINYINT(1) NOT NULL DEFAULT 0,
    is_approved      TINYINT(1)      NOT NULL DEFAULT 0,
    approved_at      DATETIME        DEFAULT NULL,
    approved_by      VARCHAR(80)     DEFAULT NULL,
    notes            TEXT,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_month (user_id, start_time),
    KEY idx_time_blocks_employee (employee_id, start_time),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── FinPro: Invoices ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invoices (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    client_name   VARCHAR(150)    NOT NULL DEFAULT 'Client',
    description   TEXT,
    hours         DECIMAL(8,2)    NOT NULL DEFAULT 0,
    rate          DECIMAL(10,2)   NOT NULL DEFAULT 0,
    amount        DECIMAL(10,2)   NOT NULL DEFAULT 0,
    status        ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at       DATETIME,
    PRIMARY KEY (id),
    KEY idx_user_status (user_id, status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── LedgerPro: Transactions ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transactions (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    date          DATE            NOT NULL,
    description   VARCHAR(255)    NOT NULL DEFAULT '',
    category      VARCHAR(100)    NOT NULL DEFAULT 'Uncategorized',
    amount        DECIMAL(12,2)   NOT NULL DEFAULT 0,
    reconciled    TINYINT(1)      NOT NULL DEFAULT 0,
    source        VARCHAR(20)     NOT NULL DEFAULT 'manual',  -- 'manual', 'csv', 'finpro'
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_date (user_id, date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Management: Employees ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS employees (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    full_name     VARCHAR(120)    NOT NULL,
    employee_code VARCHAR(40)     DEFAULT NULL,
    email         VARCHAR(150)    DEFAULT NULL,
    phone         VARCHAR(40)     DEFAULT NULL,
    address_line1 VARCHAR(255)    DEFAULT NULL,
    city_state_zip VARCHAR(255)   DEFAULT NULL,
    emergency_contact VARCHAR(255) DEFAULT NULL,
    role_title    VARCHAR(100)    DEFAULT NULL,
    start_date    DATE            DEFAULT NULL,
    hourly_rate   DECIMAL(10,2)   DEFAULT NULL,
    photo_url     VARCHAR(255)    DEFAULT NULL,
    pin_hash      VARCHAR(255)    DEFAULT NULL,
    pin_active    TINYINT(1)      NOT NULL DEFAULT 0,
    login_locked  TINYINT(1)      NOT NULL DEFAULT 0,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employee_code (employee_code),
    KEY idx_emp_user_status (user_id, status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── FinPro: Employee Expense Requests ────────────────────────────────────
CREATE TABLE IF NOT EXISTS expense_requests (
    id                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED    NOT NULL,
    employee_id       INT UNSIGNED    NOT NULL,
    request_date      DATE            NOT NULL,
    expense_type      VARCHAR(100)    NOT NULL,
    amount            DECIMAL(10,2)   NOT NULL,
    receipt_reference VARCHAR(255)    DEFAULT NULL,
    notes             VARCHAR(255)    DEFAULT NULL,
    status            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by       VARCHAR(80)     DEFAULT NULL,
    reviewed_at       DATETIME        DEFAULT NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_expense_user_status (user_id, status),
    KEY idx_expense_employee (employee_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── LedgerPro: Generated Paystubs ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS paystubs (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED    NOT NULL,
    employee_id      INT UNSIGNED    NOT NULL,
    period_start     DATE            NOT NULL,
    period_end       DATE            NOT NULL,
    approved_hours   DECIMAL(10,2)   NOT NULL DEFAULT 0,
    hourly_rate      DECIMAL(10,2)   NOT NULL DEFAULT 0,
    gross_pay        DECIMAL(10,2)   NOT NULL DEFAULT 0,
    business_revenue DECIMAL(10,2)   NOT NULL DEFAULT 0,
    generated_by     VARCHAR(80)     NOT NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_paystub_user_employee (user_id, employee_id),
    KEY idx_paystub_period (period_start, period_end),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── CalGen: Employee Work Schedules ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS work_schedules (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    employee_id   INT UNSIGNED    NOT NULL,
    schedule_date DATE            NOT NULL,
    shift_start   TIME            NOT NULL,
    shift_end     TIME            NOT NULL,
    notes         VARCHAR(255)    DEFAULT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sched_user_date (user_id, schedule_date),
    KEY idx_sched_employee (employee_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Done. Visit http://localhost/saltshore-v2/portal/login.php
-- Use: Admin / 1988
-- ============================================================
