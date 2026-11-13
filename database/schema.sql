-- CivicServe 3.0 — complete schema for a NEW installation (MySQL 5.7+ / 8.x, MariaDB 10.4+).
-- For an EXISTING CivicServe 2.x database, do not run this file: run tools/migrate.php instead.
--
-- Usage (phpMyAdmin): create an empty database, select it, open the SQL tab, paste and run.

SET NAMES utf8mb4;

CREATE TABLE users (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(120) NOT NULL,
    email          VARCHAR(190) NOT NULL,
    password       VARCHAR(255) NOT NULL,
    role           VARCHAR(20)  NOT NULL DEFAULT 'officer',      -- admin | supervisor | officer
    department     VARCHAR(40)  NULL,                            -- key from config 'departments'
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at  DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference_number    VARCHAR(24)  NOT NULL,
    citizen_name        VARCHAR(120) NOT NULL,
    citizen_email       VARCHAR(190) NULL,
    phone               VARCHAR(20)  NULL,
    category            VARCHAR(40)  NOT NULL,                   -- key from config 'categories'
    division            VARCHAR(20)  NULL,                       -- key from config 'divisions'
    description         TEXT         NOT NULL,
    location            VARCHAR(255) NOT NULL,
    latitude            DECIMAL(10,7) NULL,
    longitude           DECIMAL(10,7) NULL,
    priority            VARCHAR(10)  NOT NULL DEFAULT 'Normal',
    photo_path          VARCHAR(255) NULL,
    assigned_department VARCHAR(40)  NULL,
    due_date            DATE         NULL,
    status              VARCHAR(20)  NOT NULL DEFAULT 'Submitted',
    channel             VARCHAR(10)  NOT NULL DEFAULT 'online',  -- online | walk_in | phone
    created_by          INT UNSIGNED NULL,                       -- staff member who logged it, if any
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at         DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reports_reference (reference_number),
    KEY idx_reports_status_due (status, due_date),
    KEY idx_reports_dept_status (assigned_department, status),
    KEY idx_reports_category_status (category, status),
    KEY idx_reports_division_status (division, status),
    KEY idx_reports_created (created_at),
    KEY idx_reports_resolved (resolved_at),
    CONSTRAINT fk_reports_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE status_logs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id   INT UNSIGNED NOT NULL,
    status      VARCHAR(20)  NOT NULL,
    comment     TEXT         NULL,
    is_public   TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 = note may be shown to the resident
    updated_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_report (report_id, created_at),
    CONSTRAINT fk_logs_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
    CONSTRAINT fk_logs_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action        VARCHAR(20) NOT NULL,
    key_hash      CHAR(64)    NOT NULL,
    attempted_at  DATETIME    NOT NULL,
    PRIMARY KEY (id),
    KEY idx_rate (action, key_hash, attempted_at),
    KEY idx_rate_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schema_migrations (
    version     VARCHAR(40) NOT NULL,
    applied_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('3.0.0');

-- Next step: create the first administrator from a terminal:
--   php tools/create_staff.php --name="Your Name" --email=you@example.org --role=admin
