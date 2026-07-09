-- ============================================================
-- ARCHIVO: db/init.sql
-- PROPÓSITO: Script de inicialización completo del laboratorio
-- BASE DE DATOS: sat_lab
-- MOTOR: MySQL 5.7+ / MariaDB 10+
-- ============================================================
-- Este script es ejecutado AUTOMÁTICAMENTE por el contenedor
-- 'db' de Docker al primer arranque, gracias al mecanismo
-- de /docker-entrypoint-initdb.d/ de la imagen oficial MySQL.
-- ============================================================

-- Seleccionar la base de datos (ya creada por MYSQL_DATABASE en docker-compose)
USE sat_lab;

-- ============================================================
-- TABLA: usuarios
-- Almacena credenciales de contribuyentes simulados.
-- El campo 'email' usa identificadores numéricos de 8 dígitos
-- simulando el DNI/RUC del contribuyente (dato sensible).
-- Las contraseñas usan MD5 deliberadamente débil para el lab.
-- ============================================================
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    email    VARCHAR(20)  NOT NULL UNIQUE COMMENT 'Identificador numérico de 8 dígitos (simula DNI)',
    password VARCHAR(100) NOT NULL COMMENT 'Hash MD5 de contraseña débil (SOLO para lab)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- DATOS DE PRUEBA: Usuarios con hashes MD5 de contraseñas débiles
-- Contraseñas en texto claro (para el estudiante atacante):
--   72345678 → 123456     (MD5: e10adc3949ba59abbe56e057f20f883e)
--   45678912 → admin      (MD5: 21232f297a57a5a743894a0e4a801fc3)
--   31290456 → piura2026  (MD5: a0b9aab58ee2e1f413fa99c25adc8f5a)
--   86541230 → password   (MD5: 5f4dcc3b5aa765d61d8327deb882cf99)
--   10293847 → qwerty     (MD5: d8578edf8458ce06fbc5bb76a58c5ca4)
-- ──────────────────────────────────────────────────────────────
INSERT INTO usuarios (email, password) VALUES
('72345678', 'e10adc3949ba59abbe56e057f20f883e'),  -- 123456
('45678912', '21232f297a57a5a743894a0e4a801fc3'),  -- admin
('31290456', 'a0b9aab58ee2e1f413fa99c25adc8f5a'),  -- piura2026
('86541230', '5f4dcc3b5aa765d61d8327deb882cf99'),  -- password
('10293847', 'd8578edf8458ce06fbc5bb76a58c5ca4');  -- qwerty

-- ============================================================
-- TABLA: deudas
-- Almacena información financiera ficticia asociada a los
-- identificadores de la tabla 'usuarios'. Es el objetivo
-- principal de la consulta pública (consulta.php).
-- ============================================================
DROP TABLE IF EXISTS deudas;

CREATE TABLE deudas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    id_contribuyente VARCHAR(20) NOT NULL COMMENT 'Referencia al email/DNI del contribuyente',
    concepto        VARCHAR(150) NOT NULL,
    monto           DECIMAL(10, 2) NOT NULL,
    estado          ENUM('PENDIENTE', 'VENCIDA', 'PAGADA') NOT NULL DEFAULT 'PENDIENTE',
    fecha_emision   DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- DATOS DE DEUDAS FICTICIAS
-- Ingresando el id_contribuyente en consulta.php se devuelven
-- estos registros. Con SQLi UNION se puede extraer 'usuarios'.
-- ──────────────────────────────────────────────────────────────
INSERT INTO deudas (id_contribuyente, concepto, monto, estado, fecha_emision, fecha_vencimiento) VALUES
-- Deudas del contribuyente 72345678
('72345678', 'Impuesto Predial 2024 - Cuota 1', 350.75, 'VENCIDA',   '2024-03-01', '2024-03-31'),
('72345678', 'Impuesto Predial 2024 - Cuota 2', 350.75, 'PENDIENTE', '2024-06-01', '2024-06-30'),
('72345678', 'Papeleta de Infracción #2024-P-001', 195.00, 'VENCIDA',  '2024-01-15', '2024-02-15'),

-- Deudas del contribuyente 45678912
('45678912', 'Licencia de Funcionamiento 2024', 820.00, 'PENDIENTE', '2024-01-01', '2024-01-31'),
('45678912', 'Arbitrios Municipales 2024 - 1er Trim.', 145.50, 'PAGADA',   '2024-01-01', '2024-03-31'),

-- Deudas del contribuyente 31290456
('31290456', 'Impuesto de Alcabala - Transferencia', 2450.00, 'PENDIENTE', '2024-02-20', '2024-03-20'),

-- Deudas del contribuyente 86541230 (sin deuda pendiente)
('86541230', 'Impuesto Predial 2023 - Cuota Única', 275.00, 'PAGADA', '2023-03-01', '2023-03-31'),

-- Deudas del contribuyente 10293847
('10293847', 'Arbitrios Municipales 2024 - 2do Trim.', 210.80, 'VENCIDA',   '2024-04-01', '2024-06-30'),
('10293847', 'Papeleta de Infracción #2024-P-087', 390.00, 'PENDIENTE', '2024-05-10', '2024-06-10');

-- ============================================================
-- VERIFICACIÓN: Resumen de registros insertados
-- ============================================================
SELECT 'Tabla usuarios:' AS info;
SELECT id, email, LEFT(password, 10) AS pass_preview FROM usuarios;

SELECT 'Tabla deudas:' AS info;
SELECT id, id_contribuyente, concepto, monto, estado FROM deudas;
