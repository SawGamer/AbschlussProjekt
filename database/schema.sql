-- WorkForce time tracking: database schema and demo accounts.
-- Works with MySQL 8 and MariaDB 10.5 or newer.
-- All names and accounts below are fictional.

CREATE DATABASE IF NOT EXISTS workforce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE workforce;

DROP TABLE IF EXISTS AuditLog;
DROP TABLE IF EXISTS LeaveRequest;
DROP TABLE IF EXISTS Breaks;
DROP TABLE IF EXISTS Hours;
DROP TABLE IF EXISTS Employee;
DROP TABLE IF EXISTS Company;

CREATE TABLE Company (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

CREATE TABLE Employee (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    Username      VARCHAR(50)  NOT NULL UNIQUE,
    Password      VARCHAR(255) NOT NULL COMMENT 'bcrypt hash from password_hash()',
    first_name    VARCHAR(50)  NOT NULL,
    last_name     VARCHAR(50)  NOT NULL,
    Company_id    INT          NULL,
    ShouldHours   DECIMAL(5,2) NOT NULL DEFAULT 40.00 COMMENT 'contracted hours per week',
    vacation_days INT          NOT NULL DEFAULT 30 COMMENT 'paid vacation days per year',
    is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
    active        TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'inactive accounts cannot log in',
    token_version INT          NOT NULL DEFAULT 0 COMMENT 'raised on logout and password change to revoke tokens',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_company FOREIGN KEY (Company_id) REFERENCES Company (id)
);

-- One row per employee and day.
CREATE TABLE Hours (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    employee_id  INT          NOT NULL,
    Date_        DATE         NOT NULL,
    startTime    TIME         NULL,
    endTime      TIME         NULL,
    pause        INT          NOT NULL DEFAULT 0 COMMENT 'break in minutes',
    Actual_hours DECIMAL(5,2) NULL,
    overtime     DECIMAL(5,2) NULL,
    type         VARCHAR(20)  NULL,
    Komment      VARCHAR(255) NULL,
    CONSTRAINT uq_hours_employee_day UNIQUE (employee_id, Date_),
    CONSTRAINT fk_hours_employee FOREIGN KEY (employee_id) REFERENCES Employee (id) ON DELETE CASCADE
);

-- Breaks taken with the stamp clock. Their total ends up in Hours.pause at clock-out.
CREATE TABLE Breaks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT  NOT NULL,
    Date_       DATE NOT NULL,
    startTime   TIME NOT NULL,
    endTime     TIME NULL COMMENT 'NULL while the break is running',
    INDEX idx_breaks_employee_day (employee_id, Date_),
    CONSTRAINT fk_breaks_employee FOREIGN KEY (employee_id) REFERENCES Employee (id) ON DELETE CASCADE
);

CREATE TABLE LeaveRequest (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    employee_id   INT          NOT NULL,
    start_date    DATE         NOT NULL,
    end_date      DATE         NOT NULL,
    days          INT          NOT NULL COMMENT 'working days in the range',
    comment       VARCHAR(255) NULL,
    status        VARCHAR(10)  NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected or cancelled',
    decided_by    INT          NULL,
    decided_at    DATETIME     NULL,
    decision_note VARCHAR(255) NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_status (status),
    CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id) REFERENCES Employee (id) ON DELETE CASCADE,
    CONSTRAINT fk_leave_decider FOREIGN KEY (decided_by) REFERENCES Employee (id) ON DELETE SET NULL
);

-- Who changed what. Written for admin actions and for leave decisions.
CREATE TABLE AuditLog (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    actor_id   INT          NULL,
    subject_id INT          NULL COMMENT 'employee the action was about',
    action     VARCHAR(40)  NOT NULL,
    details    VARCHAR(500) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES Employee (id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_subject FOREIGN KEY (subject_id) REFERENCES Employee (id) ON DELETE SET NULL
);

INSERT INTO Company (id, name) VALUES (1, 'Musterfirma GmbH');

-- Demo logins: admin / admin1234, every other account / demo1234
INSERT INTO Employee (id, Username, Password, first_name, last_name, Company_id, ShouldHours, vacation_days, is_admin) VALUES
    (1, 'admin', '$2y$10$bduyQhFRsCdo3jqr741jUumkLTZdwuLoWIMBkDeAtf1GTq9tr9JZy', 'Anna',  'Admin',      1, 40.00, 30, 1),
    (2, 'max',   '$2y$10$8AF3.7SVW2SDgD5n5V7EKubjNV8MLOEa7.ryiq67aWthDXiHswZpe', 'Max',   'Mustermann', 1, 40.00, 30, 0),
    (3, 'erika', '$2y$10$8AF3.7SVW2SDgD5n5V7EKubjNV8MLOEa7.ryiq67aWthDXiHswZpe', 'Erika', 'Musterfrau', 1, 30.00, 24, 0),
    (4, 'jonas', '$2y$10$8AF3.7SVW2SDgD5n5V7EKubjNV8MLOEa7.ryiq67aWthDXiHswZpe', 'Jonas', 'Becker',     1, 40.00, 30, 0),
    (5, 'leyla', '$2y$10$8AF3.7SVW2SDgD5n5V7EKubjNV8MLOEa7.ryiq67aWthDXiHswZpe', 'Leyla', 'Yilmaz',     1, 32.00, 26, 0),
    (6, 'tom',   '$2y$10$8AF3.7SVW2SDgD5n5V7EKubjNV8MLOEa7.ryiq67aWthDXiHswZpe', 'Tom',   'Weber',      1, 20.00, 20, 0);
