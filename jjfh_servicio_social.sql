-- ============================================================
-- JJFH - SISTEMA DE CONTROL DE HORAS DE SERVICIO SOCIAL
-- Base de datos diseñada a partir del index.html del prototipo
-- MySQL 8+ / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS jjfh_servicio_social
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE jjfh_servicio_social;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW IF EXISTS v_review_history;
DROP VIEW IF EXISTS v_student_progress;
DROP TABLE IF EXISTS review_history;
DROP TABLE IF EXISTS hour_records;
DROP TABLE IF EXISTS access_requests;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS system_settings;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- ROLES
-- ============================================================

CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

INSERT INTO roles (code, name, description) VALUES
('estudiante', 'Estudiante', 'Registra sus horas de servicio social'),
('profesor', 'Profesor', 'Revisa registros de servicio social'),
('administrador', 'Administrador', 'Docente con permisos administrativos'),
('superadmin', 'Super Administrador', 'Control general del sistema');

-- ============================================================
-- USUARIOS
-- identification es el identificador general.
-- El rol SIEMPRE se obtiene desde la BD.
-- ============================================================

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identification VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL,
    status ENUM('pendiente','activo','rechazado','inactivo') NOT NULL DEFAULT 'activo',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id),

    INDEX idx_users_role_status (role_id, status)
) ENGINE=InnoDB;

-- ============================================================
-- ESTUDIANTES
-- student_code identifica al estudiante de forma específica.
-- Académico = 100 h / Técnica = 70 h.
-- ============================================================

CREATE TABLE students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    student_code VARCHAR(50) NOT NULL UNIQUE,
    grade VARCHAR(20) NULL,
    modality ENUM('academico','tecnico') NOT NULL,
    technical_program VARCHAR(120) NULL,
    target_hours DECIMAL(6,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_students_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT chk_students_target
        CHECK (target_hours IN (70.00, 100.00)),

    CONSTRAINT chk_students_program
        CHECK (
            (modality = 'tecnico' AND technical_program IS NOT NULL)
            OR modality = 'academico'
        ),

    INDEX idx_students_modality (modality)
) ENGINE=InnoDB;

-- ============================================================
-- PERSONAL DOCENTE
-- staff_code identifica específicamente al profesor/admin.
-- Un administrador también puede ser docente.
-- ============================================================

CREATE TABLE staff (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    staff_code VARCHAR(50) NOT NULL UNIQUE,
    position VARCHAR(80) NOT NULL DEFAULT 'Docente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_staff_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SOLICITUDES DE REGISTRO
-- Las solicitudes todavía NO son usuarios activos.
-- ============================================================

CREATE TABLE access_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identification VARCHAR(50) NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    requested_role_id TINYINT UNSIGNED NOT NULL,

    modality ENUM('academico','tecnico') NULL,
    technical_program VARCHAR(120) NULL,
    grade VARCHAR(20) NULL,

    status ENUM('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',

    validated_by BIGINT UNSIGNED NULL,
    validation_observation VARCHAR(500) NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    validated_at DATETIME NULL,

    CONSTRAINT fk_requests_role
        FOREIGN KEY (requested_role_id) REFERENCES roles(id),

    CONSTRAINT fk_requests_validator
        FOREIGN KEY (validated_by) REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_requests_status_role (status, requested_role_id),
    INDEX idx_requests_email (email)
) ENGINE=InnoDB;

-- ============================================================
-- REGISTROS DE HORAS
-- ============================================================

CREATE TABLE hour_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,

    record_date DATE NOT NULL,
    entry_time TIME NOT NULL,
    exit_time TIME NOT NULL,
    hours DECIMAL(6,2) NOT NULL,

    activity_description TEXT NOT NULL,

    evidence_original_name VARCHAR(255) NULL,
    evidence_path VARCHAR(500) NULL,
    evidence_mime VARCHAR(100) NULL,

    status ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',

    observation TEXT NULL,
    rejection_reason TEXT NULL,

    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_hours_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_hours_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES staff(id)
        ON DELETE SET NULL,

    CONSTRAINT chk_hours_positive
        CHECK (hours > 0),

    CONSTRAINT chk_hours_schedule
        CHECK (exit_time > entry_time),

    INDEX idx_hours_student_status (student_id, status),
    INDEX idx_hours_reviewer_status (reviewed_by, status),
    INDEX idx_hours_date (record_date)
) ENGINE=InnoDB;

-- ============================================================
-- HISTORIAL DE REVISIONES
-- Conserva cada cambio realizado por un profesor/admin.
-- ============================================================

CREATE TABLE review_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hour_record_id BIGINT UNSIGNED NOT NULL,
    reviewer_staff_id BIGINT UNSIGNED NOT NULL,

    previous_status ENUM('pendiente','aprobada','rechazada') NULL,
    new_status ENUM('pendiente','aprobada','rechazada') NOT NULL,

    observation TEXT NULL,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_review_record
        FOREIGN KEY (hour_record_id) REFERENCES hour_records(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_review_staff
        FOREIGN KEY (reviewer_staff_id) REFERENCES staff(id)
        ON DELETE RESTRICT,

    INDEX idx_review_record (hour_record_id),
    INDEX idx_review_staff_date (reviewer_staff_id, reviewed_at)
) ENGINE=InnoDB;

-- ============================================================
-- CONFIGURACIÓN
-- ============================================================

CREATE TABLE system_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('meta_horas_academico', '100', 'Meta de estudiantes académicos'),
('meta_horas_tecnico', '70', 'Meta de estudiantes de la técnica'),
('dominio_institucional', '@jjfh.edu.co', 'Dominio para docentes y administradores'),
('tamano_max_evidencia_mb', '5', 'Tamaño máximo recomendado de evidencia');

-- ============================================================
-- USUARIOS DE PRUEBA
-- Las claves originales del prototipo se convierten a bcrypt.
-- En PHP se comprobarán con password_verify().
-- ============================================================

INSERT INTO users
(identification, first_name, last_name, email, password_hash, role_id, status)
VALUES
('EST-0001', 'Miguel Angel', 'Roldan Devia', 'estudiante@gmail.com',
 '$2y$12$7MqYxuC1sudnt2TnODtlF.To1SHXFNoJS4/H2ScU4HCiroi6pLMBC', 1, 'activo'),

('DOC-0001', 'Profesor', 'de Prueba', 'profesor@jjfh.edu.co',
 '$2y$12$KGMBiqzel2STUXgg8GC7FugPQmOc4z96aXHFD6JV1wIH1qhz64GX6', 2, 'activo'),

('ADM-0001', 'Administrador', 'de Prueba', 'admin@jjfh.edu.co',
 '$2y$12$Q0u1kknZ0R97zFoxdnh6z.m2y5C69bHhwz678AjK86bVXt8KS9d3a', 3, 'activo'),

('SADM-0001', 'Super', 'Administrador', 'admintotaljjfh@gmail.com',
 '$2y$12$0XUqSV8zpAIm6XAWOmpU1O9V.mHHk0lDKoCAwGmWB/kbc34VJjHLu', 4, 'activo');

-- ============================================================
-- PERFILES
-- ============================================================

INSERT INTO students
(user_id, student_code, grade, modality, technical_program, target_hours)
SELECT id, 'EST-0001', '11', 'tecnico', 'Técnica', 70.00
FROM users
WHERE email = 'estudiante@gmail.com';

INSERT INTO staff (user_id, staff_code, position)
SELECT id, 'DOC-0001', 'Docente'
FROM users
WHERE email = 'profesor@jjfh.edu.co';

INSERT INTO staff (user_id, staff_code, position)
SELECT id, 'ADM-0001', 'Docente Administrador'
FROM users
WHERE email = 'admin@jjfh.edu.co';

-- ============================================================
-- VISTA DE PROGRESO DEL ESTUDIANTE
-- ============================================================

CREATE OR REPLACE VIEW v_student_progress AS
SELECT
    s.id AS student_id,
    u.identification,
    s.student_code,
    CONCAT(u.first_name, ' ', u.last_name) AS student_name,
    u.email,
    s.grade,
    s.modality,
    s.technical_program,
    s.target_hours,

    COALESCE(
        SUM(
            CASE
                WHEN hr.status = 'aprobada' THEN hr.hours
                ELSE 0
            END
        ), 0
    ) AS approved_hours,

    GREATEST(
        s.target_hours -
        COALESCE(
            SUM(
                CASE
                    WHEN hr.status = 'aprobada' THEN hr.hours
                    ELSE 0
                END
            ), 0
        ),
        0
    ) AS remaining_hours,

    LEAST(
        100,
        (
            COALESCE(
                SUM(
                    CASE
                        WHEN hr.status = 'aprobada' THEN hr.hours
                        ELSE 0
                    END
                ), 0
            ) / s.target_hours
        ) * 100
    ) AS progress_percent

FROM students s
INNER JOIN users u ON u.id = s.user_id
LEFT JOIN hour_records hr ON hr.student_id = s.id
GROUP BY
    s.id,
    u.identification,
    s.student_code,
    u.first_name,
    u.last_name,
    u.email,
    s.grade,
    s.modality,
    s.technical_program,
    s.target_hours;

-- ============================================================
-- VISTA DEL HISTORIAL PARA ADMINISTRACIÓN
-- ============================================================

CREATE OR REPLACE VIEW v_review_history AS
SELECT
    hr.id AS hour_record_id,

    CONCAT(su.first_name, ' ', su.last_name) AS student_name,
    su.email AS student_email,

    CONCAT(pu.first_name, ' ', pu.last_name) AS reviewer_name,
    pu.email AS reviewer_email,
    st.staff_code,

    hr.record_date,
    hr.entry_time,
    hr.exit_time,
    hr.hours,
    hr.activity_description,

    hr.evidence_original_name,
    hr.evidence_path,
    hr.evidence_mime,

    hr.status,
    hr.observation,
    hr.rejection_reason,
    hr.reviewed_at

FROM hour_records hr
INNER JOIN students s ON s.id = hr.student_id
INNER JOIN users su ON su.id = s.user_id
LEFT JOIN staff st ON st.id = hr.reviewed_by
LEFT JOIN users pu ON pu.id = st.user_id
WHERE hr.status <> 'pendiente';

-- ============================================================
-- CONSULTAS ÚTILES PARA LAS PRUEBAS
-- ============================================================

-- SELECT * FROM users;
-- SELECT * FROM students;
-- SELECT * FROM staff;
-- SELECT * FROM access_requests;
-- SELECT * FROM hour_records;
-- SELECT * FROM review_history;
-- SELECT * FROM v_student_progress;
-- SELECT * FROM v_review_history;

-- ============================================================
-- FIN DE LA BASE DE DATOS JJFH
-- ============================================================
