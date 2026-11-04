-- WorkForce time tracking: database schema and demo accounts.
-- Works with MySQL 8 and MariaDB 10.5 or newer.
-- All names and accounts below are fictional.

CREATE DATABASE IF NOT EXISTS workforce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE workforce;

DROP TABLE IF EXISTS Hours;
DROP TABLE IF EXISTS Employee;
DROP TABLE IF EXISTS Company;

CREATE TABLE Company (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

CREATE TABLE Employee (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    Username    VARCHAR(50)  NOT NULL UNIQUE,
    Password    VARCHAR(255) NOT NULL COMMENT 'bcrypt hash from password_hash()',
    first_name  VARCHAR(50)  NOT NULL,
    last_name   VARCHAR(50)  NOT NULL,
    Company_id  INT          NULL,
    ShouldHours DECIMAL(5,2) NOT NULL DEFAULT 40.00 COMMENT 'contracted hours per week',
    is_admin    TINYINT(1)   NOT NULL DEFAULT 0,
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

INSERT INTO Company (id, name) VALUES (1, 'Musterfirma GmbH');

-- Demo logins: admin / admin1234 and max / demo1234
INSERT INTO Employee (id, Username, Password, first_name, last_name, Company_id, ShouldHours, is_admin) VALUES
    (1, 'admin', '$2y$10$bduyQhFRsCdo3jqr741jUumkLTZdwuLoWIMBkDeAtf1GTq9tr9JZy', 'Anna',  'Admin',      1, 40.00, 1),
    (2, 'max',   '$2y$10$8AF3.7SVW2SDgD5n5V7EKubjNV8MLOEa7.ryiq67aWthDXiHswZpe', 'Max',   'Mustermann', 1, 40.00, 0),
    (3, 'erika', '$2y$10$8AF3.7SVW2SDgD5n5V7EKubjNV8MLOEa7.ryiq67aWthDXiHswZpe', 'Erika', 'Musterfrau', 1, 30.00, 0);
