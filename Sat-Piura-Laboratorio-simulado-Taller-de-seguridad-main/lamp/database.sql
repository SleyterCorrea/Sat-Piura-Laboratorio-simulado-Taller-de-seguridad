-- ============================================================
-- ARCHIVO: database.sql
-- PROPÓSITO: Inicialización completa de la BD del laboratorio
-- BASE DE DATOS: sat_lab
-- MOTOR: MySQL 5.7 / MariaDB (compatible con XAMPP y Docker)
-- ============================================================

CREATE DATABASE IF NOT EXISTS sat_lab
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sat_lab;

GRANT ALL PRIVILEGES ON sat_lab.* TO 'appuser'@'%' IDENTIFIED BY 'apppassword';
FLUSH PRIVILEGES;

-- ============================================================
-- TABLA: contribuyentes
-- Perfil ciudadano ligado al DNI (id_contribuyente = email de usuarios)
-- ============================================================
DROP TABLE IF EXISTS deudas;
DROP TABLE IF EXISTS contribuyentes;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE contribuyentes (
    id_contribuyente VARCHAR(8)   NOT NULL PRIMARY KEY COMMENT 'DNI de 8 dígitos',
    nombres          VARCHAR(100) NOT NULL,
    apellidos        VARCHAR(100) NOT NULL,
    direccion        VARCHAR(150) NOT NULL,
    telefono         VARCHAR(15)  DEFAULT NULL,
    correo_personal  VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO contribuyentes (id_contribuyente, nombres, apellidos, direccion, telefono, correo_personal) VALUES
('72345678', 'Juan Carlos',  'Pérez Flores',    'Av. Grau 485, Piura',                   '073-321456', 'jperez@correo.pe'),
('45678912', 'María Elena',  'Gómez Távara',    'Jr. Loreto 210, Castilla',              '073-445512', 'mgomez@correo.pe'),
('31290456', 'Empresa Ficticia S.A.C.', 'Ruc:20601234567', 'Av. Sánchez Cerro 1120, Piura', '073-552233', 'empresa@ficticia.pe'),
('86541230', 'Roberto',      'Díaz Seminario',  'Calle Lima 88, Sullana',                '072-111223', 'rdiaz@correo.pe'),
('10293847', 'Ana Lucía',    'Flores Morales',  'Urb. El Chipe, Mz B Lt 5, Piura',       '073-889900', 'aflores@correo.pe');

-- ============================================================
-- TABLA: usuarios
-- email    = DNI peruano (identificador del sistema)
-- password = Hash MD5 de contraseña débil (SOLO para laboratorio)
-- ============================================================
CREATE TABLE usuarios (
    id       INT          AUTO_INCREMENT PRIMARY KEY,
    email    VARCHAR(20)  NOT NULL UNIQUE COMMENT 'DNI de 8 dígitos simulado',
    password VARCHAR(32)  NOT NULL       COMMENT 'Hash MD5 — Intencionalmente débil para el lab',
    FOREIGN KEY (email) REFERENCES contribuyentes(id_contribuyente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contraseñas en MD5:
--   72345678 → 123456    → e10adc3949ba59abbe56e057f20f883e
--   45678912 → admin     → 21232f297a57a5a743894a0e4a801fc3
--   31290456 → piura2026 → a0b9aab58ee2e1f413fa99c25adc8f5a
--   86541230 → password  → 5f4dcc3b5aa765d61d8327deb882cf99
--   10293847 → qwerty    → d8578edf8458ce06fbc5bb76a58c5ca4
INSERT INTO usuarios (email, password) VALUES
('72345678', 'e10adc3949ba59abbe56e057f20f883e'),
('45678912', '21232f297a57a5a743894a0e4a801fc3'),
('31290456', 'a0b9aab58ee2e1f413fa99c25adc8f5a'),
('86541230', '5f4dcc3b5aa765d61d8327deb882cf99'),
('10293847', 'd8578edf8458ce06fbc5bb76a58c5ca4');

-- ============================================================
-- TABLA: deudas
-- Obligaciones tributarias asociadas a cada contribuyente
-- estado: 'Pendiente' | 'Vencido' | 'Pagado'
-- ============================================================
CREATE TABLE deudas (
    id_deuda         INT          AUTO_INCREMENT PRIMARY KEY,
    id_contribuyente VARCHAR(8)   NOT NULL,
    tipo_tributo     VARCHAR(50)  NOT NULL,
    periodo          VARCHAR(10)  NOT NULL COMMENT 'Ejemplo: 2025-01',
    monto            DECIMAL(10,2) NOT NULL,
    estado           VARCHAR(20)  NOT NULL DEFAULT 'Pendiente',
    fecha_emision    DATE         DEFAULT NULL,
    FOREIGN KEY (id_contribuyente) REFERENCES contribuyentes(id_contribuyente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO deudas (id_contribuyente, tipo_tributo, periodo, monto, estado, fecha_emision) VALUES
-- ── Juan Carlos Pérez Flores (72345678) ──────────────────────
('72345678', 'Impuesto Predial',        '2025',    450.00, 'Pendiente', '2025-01-15'),
('72345678', 'Arbitrios Municipales',   '2025-T1', 210.50, 'Vencido',  '2025-03-31'),
('72345678', 'Papeleta de Tránsito',    'M01-2025', 950.00, 'Vencido', '2025-02-10'),
('72345678', 'Impuesto Vehicular',      '2024',    320.00, 'Pagado',   '2024-04-01'),
('72345678', 'Arbitrios Municipales',   '2024-T4', 190.00, 'Pagado',   '2024-10-15'),
-- ── María Elena Gómez Távara (45678912) ──────────────────────
('45678912', 'Impuesto Predial',        '2025',    880.00, 'Pendiente', '2025-01-15'),
('45678912', 'Licencia de Funcionamiento','2025',  350.00, 'Vencido',  '2025-02-28'),
('45678912', 'Arbitrios Municipales',   '2025-T1', 175.75, 'Pagado',   '2025-03-10'),
('45678912', 'Papeleta de Tránsito',    'M05-2025', 620.00,'Pendiente','2025-05-20'),
-- ── Empresa Ficticia S.A.C. (31290456) ───────────────────────
('31290456', 'Impuesto Predial',        '2025',   1250.00, 'Pendiente','2025-01-15'),
('31290456', 'Licencia de Funcionamiento','2025',  780.00, 'Pendiente','2025-02-01'),
('31290456', 'Arbitrios Municipales',   '2025-T1', 430.00, 'Vencido', '2025-03-31'),
('31290456', 'Impuesto a los Juegos',   '2024',    200.00, 'Pagado',   '2024-08-15'),
('31290456', 'Impuesto Predial',        '2024',   1100.00, 'Pagado',   '2024-03-01'),
-- ── Roberto Díaz Seminario (86541230) ────────────────────────
('86541230', 'Impuesto Predial',        '2025',    360.00, 'Pendiente','2025-01-15'),
('86541230', 'Papeleta de Tránsito',    'M03-2024', 380.00,'Pagado',   '2024-03-22'),
('86541230', 'Arbitrios Municipales',   '2025-T1', 145.00, 'Pagado',   '2025-02-05'),
-- ── Ana Lucía Flores Morales (10293847) ──────────────────────
('10293847', 'Impuesto Predial',        '2025',    510.00, 'Vencido',  '2025-01-15'),
('10293847', 'Arbitrios Municipales',   '2025-T1', 220.00, 'Pendiente','2025-03-15'),
('10293847', 'Impuesto Vehicular',      '2025',    275.50, 'Pendiente','2025-04-01'),
('10293847', 'Arbitrios Municipales',   '2024-T4', 198.00, 'Pagado',   '2024-11-20');
