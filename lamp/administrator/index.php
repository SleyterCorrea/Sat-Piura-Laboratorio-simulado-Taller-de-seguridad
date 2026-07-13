
<?php
/*
// 1. Iniciamos el motor de sesiones de PHP
session_start();

// 2. Validamos si NO existe la sesión o si el rol NO es de administrador
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    
    // 3. Si es un intruso (atacante sin login), destruimos cualquier intento de sesión
    session_unset();
    session_destroy();

    // 4. Lo redirigimos forzosamente a la pantalla principal (login)
    header("Location: ../index.html");
    
    // 5. Detenemos la ejecución del código ABSOLUTAMENTE. 
    // Esto evita que el HTML del panel se renderice de fondo.
    exit(); 
}
*/
?>


<?php
/*
 * ============================================================
 * ARCHIVO: administrator/index.php
 * PROPÓSITO: Panel de Administración Interna - SAT-LAB
 * VULNERABILIDAD INTENCIONAL: OWASP A01:2021 - Broken Access Control
 * DETALLE: No hay validación de sesión (no session_start ni roles).
 *          Cualquier usuario puede acceder directamente a este archivo.
 * ============================================================
 */

// Simulación de datos globales para tarjetas
$total_recaudado = "S/. 45,820.00";
$multas_pendientes = 18;
$usuarios_registrados = 154;

// Lista simulada de movimientos y ciudadanos
$ultimos_movimientos = [
    ["dni" => "71234567", "nombre" => "Juan Carlos Medina", "monto" => 1250.00, "estado" => "Pendiente"],
    ["dni" => "42987654", "nombre" => "Maria Ines Ramos", "monto" => 350.00, "estado" => "Vencido"],
    ["dni" => "31567890", "nombre" => "Carlos Alberto Ruiz", "monto" => 2800.00, "estado" => "Pendiente"],
    ["dni" => "08976543", "nombre" => "Ana Sofia Pizarro", "monto" => 150.00, "estado" => "Pagado"],
    ["dni" => "76543210", "nombre" => "Luis Miguel Guerrero", "monto" => 980.00, "estado" => "Vencido"]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SATP - Panel de Administración Interna</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        /* ── Variables institucionales de Administración ──────────────────── */
        :root {
            --sat-admin-header: #2c3e50; /* Gris Oscuro para navbar */
            --sat-admin-accent: #c0392b; /* Rojo Oscuro para detalles */
            --sat-azul-c:    #00a4e4;
            --sat-naranja:   #e8a020;
            --sidebar-w:     240px;
            --nav-h:         58px;
        }

        body {
            font-family: 'Open Sans', 'Segoe UI', sans-serif;
            background-color: #f0f4f8;
            color: #2c3e50;
        }

        /* ── NAVBAR SUPERIOR ────────────────────────────── */
        .sat-navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: var(--nav-h);
            background: var(--sat-admin-header);
            border-bottom: 3px solid var(--sat-admin-accent);
            display: flex;
            align-items: center;
            padding: 0 20px;
            z-index: 1000;
            gap: 16px;
        }

        .nav-logo-box {
            background: #fff;
            border-radius: 4px;
            padding: 4px 9px;
            display: flex;
            align-items: baseline;
            gap: 2px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .nav-logo-box .big  { color: var(--sat-admin-header); font-size: 17px; font-weight: 700; }
        .nav-logo-box .sub  { color: var(--sat-admin-accent); font-size: 10px; font-weight: 700; }

        .nav-title {
            color: rgba(255,255,255,.9);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .5px;
            line-height: 1.4;
            text-transform: uppercase;
        }

        .nav-spacer { flex: 1; }

        .nav-user {
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-user i { font-size: 18px; color: var(--sat-admin-accent); }

        .btn-logout {
            background: #7f8c8d;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background .2s;
        }
        .btn-logout:hover { background: #95a5a6; color: #fff; }

        /* ── SIDEBAR ─────────────────────────────────────── */
        .sat-sidebar {
            position: fixed;
            top: var(--nav-h);
            left: 0;
            width: var(--sidebar-w);
            bottom: 0;
            background: #1a2536;
            overflow-y: auto;
            z-index: 900;
            padding-top: 16px;
        }

        .sidebar-section {
            padding: 8px 16px 4px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,.35);
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 20px;
            color: rgba(255,255,255,.7);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all .2s;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: #fff;
            background: rgba(255,255,255,.07);
            border-left-color: var(--sat-admin-accent);
        }

        .sidebar-link i { font-size: 16px; width: 18px; text-align: center; }

        .sidebar-dni {
            margin: 16px;
            padding: 12px;
            background: rgba(192, 57, 43, 0.15);
            border: 1px solid rgba(192, 57, 43, 0.3);
            border-radius: 6px;
            text-align: center;
            color: #ff9f9f;
            font-size: 11px;
            font-weight: bold;
        }

        /* ── MAIN CONTENT ────────────────────────────────── */
        .sat-main {
            margin-left: var(--sidebar-w);
            margin-top: var(--nav-h);
            padding: 28px 28px 80px;
            min-height: calc(100vh - var(--nav-h));
        }

        .page-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--sat-admin-header);
            border-bottom: 2px solid var(--sat-admin-accent);
            padding-bottom: 10px;
            margin-bottom: 24px;
        }

        /* ── TARJETAS DE RESUMEN ─────────────────────────── */
        .card-stat {
            border: none;
            border-radius: 10px;
            padding: 22px 20px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,.13);
        }

        .card-stat .stat-icon {
            position: absolute;
            right: 16px;
            top: 16px;
            font-size: 42px;
            opacity: .25;
        }

        .card-stat .stat-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            opacity: .85;
        }

        .card-stat .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin: 4px 0 2px;
            line-height: 1;
        }

        .card-stat .stat-sub {
            font-size: 11px;
            opacity: .75;
        }

        .card-recaudado { background: linear-gradient(135deg, #1e8449, #27ae60); }
        .card-multas     { background: linear-gradient(135deg, #d35400, #e67e22); }
        .card-usuarios   { background: linear-gradient(135deg, #2c3e50, #34495e); }

        /* ── TABLA DE MOVIMIENTOS ─────────────────────────────── */
        .table-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            padding: 22px;
            margin-top: 28px;
        }

        .table-card .table-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--sat-admin-header);
            margin-bottom: 16px;
            border-bottom: 1px solid #e8edf2;
            padding-bottom: 10px;
        }

        .sat-table th {
            background: #f7f9fc;
            color: #555;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            border-bottom: 2px solid #e8edf2;
            padding: 10px 14px;
        }

        .sat-table td {
            padding: 12px 14px;
            font-size: 13px;
            color: #444;
            vertical-align: middle;
            border-bottom: 1px solid #f0f3f6;
        }

        .sat-table tbody tr:hover td { background: #f8fbff; }

        .monto-cell {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 14px;
        }

        /* ── BADGES DE ESTADO ─────────────────────────────── */
        .badge-estado {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .3px;
        }
        .badge-pagado   { background: #d4edda; color: #155724; }
        .badge-pendiente{ background: #fff3cd; color: #856404; }
        .badge-vencido  { background: #f8d7da; color: #721c24; }

        /* ── BANNER ACCESO SIN AUTORIZACIÓN (Broken Access Control) ─────────────────────────────── */
        .vuln-banner {
            background: linear-gradient(135deg, #fff3cd, #ffeeba);
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .vuln-banner .vuln-icon { font-size: 36px; flex-shrink: 0; }
        .vuln-banner h4 { color: #856404; font-size: 14px; font-weight: 700; text-transform: uppercase; margin: 0 0 6px; }
        .vuln-banner p  { color: #533f03; font-size: 12px; line-height: 1.7; margin: 0; }
        .vuln-banner code { background: rgba(0,0,0,.05); padding: 1px 6px; border-radius: 3px; color: #b05c00; font-size: 11px; }

        /* ── FOOTER DEL LAB ──────────────────────────────────── */
        .lab-footer {
            position: fixed;
            bottom: 0;
            left: var(--sidebar-w);
            right: 0;
            background: rgba(26,39,68,.95);
            border-top: 2px solid var(--sat-admin-accent);
            padding: 8px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 500;
        }
        .lab-footer .lab-badge {
            background: var(--sat-admin-accent);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 3px;
            letter-spacing: .5px;
            white-space: nowrap;
        }
        .lab-footer p {
            color: rgba(255,200,80,.85);
            font-size: 11px;
            font-weight: 600;
            margin: 0;
        }
    </style>
</head>
<body>

<!-- ═══════════════════════════════ NAVBAR ═══════════════════════════════ -->
<nav class="sat-navbar">
    <a href="#" class="nav-logo-box text-decoration-none">
        <span class="big">SAT</span><span class="sub">P</span>
    </a>
    <div class="nav-title">
        <span>SATP - PANEL DE ADMINISTRACIÓN INTERNA</span>
    </div>
    <div class="nav-spacer"></div>
    <div class="nav-user">
        <i class="bi bi-shield-lock-fill"></i>
        <span>Administrador Anónimo</span>
        <small class="text-white-50">&nbsp;|&nbsp;Nivel: Root/SuperAdmin</small>
    </div>
    <a href="../logout.php" class="btn-logout ms-3">
        <i class="bi bi-box-arrow-right"></i> Salir del Panel
    </a>
</nav>

<!-- ═══════════════════════════════ SIDEBAR ═══════════════════════════════ -->
<aside class="sat-sidebar">
    <div class="sidebar-section">Operaciones SATP</div>

    <a href="#contribuyentes"   class="sidebar-link active"><i class="bi bi-people-fill"></i> Gestión de Contribuyentes</a>
    <a href="#auditoria"        class="sidebar-link"><i class="bi bi-currency-dollar"></i> Auditoría de Pagos</a>
    <a href="#fraccionamientos" class="sidebar-link"><i class="bi bi-calendar-check-fill"></i> Aprobación de Fraccionamientos</a>
    <a href="#resoluciones"     class="sidebar-link"><i class="bi bi-file-earmark-text-fill"></i> Emisión de Resoluciones</a>
    <a href="#logs"             class="sidebar-link"><i class="bi bi-journal-text"></i> Logs del Sistema</a>

    <div class="sidebar-section" style="margin-top:8px;">Panel de Control</div>
    <a href="#config" class="sidebar-link"><i class="bi bi-sliders"></i> Parámetros Locales</a>
    <a href="../logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a>

    <div class="sidebar-dni">
        MODO ADMINISTRADOR
    </div>
</aside>

<!-- ═══════════════════════════════ MAIN ═══════════════════════════════ -->
<main class="sat-main">

    <!-- ── BANNER ACCESO DESPROTEGIDO (Broken Access Control) ────────── -->
    <div class="vuln-banner">
        <span class="vuln-icon">🚨</span>
        <div>
            <h4>🔓 Control de Acceso Roto — Demostración OWASP A01:2021</h4>
            <p>
                Este portal simula una sección de administración crítica. Se encuentra expuesto de forma intencional sin verificar
                sesiones, cookies, ni roles de usuario. Cualquier atacante que descubra la ruta <code>/administrator/index.php</code>
                puede ver esta información sensible y realizar operaciones privilegiadas.
            </p>
        </div>
    </div>

    <!-- ── TARJETAS DE ADMINISTRACIÓN ───────────────────────────── -->
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="card-stat card-recaudado">
                <i class="bi bi-cash-stack stat-icon"></i>
                <div class="stat-label">Total Recaudado Hoy</div>
                <div class="stat-value"><?= $total_recaudado ?></div>
                <div class="stat-sub">Operaciones de caja SAT Piura</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card-stat card-multas">
                <i class="bi bi-exclamation-octagon-fill stat-icon"></i>
                <div class="stat-label">Multas Pendientes de Aprobación</div>
                <div class="stat-value"><?= $multas_pendientes ?></div>
                <div class="stat-sub">Por infracciones de tránsito e impuesto predial</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card-stat card-usuarios">
                <i class="bi bi-people-fill stat-icon"></i>
                <div class="stat-label">Usuarios Registrados</div>
                <div class="stat-value"><?= $usuarios_registrados ?></div>
                <div class="stat-sub">Contribuyentes activos en la plataforma</div>
            </div>
        </div>
    </div>

    <!-- ── TABLA DE ÚLTIMOS MOVIMIENTOS ───────────────────────── -->
    <div class="table-card">
        <div class="table-title">
            <i class="bi bi-clock-history me-1 text-danger"></i> Últimos Movimientos y Cuentas de Contribuyentes
        </div>
        <div class="table-responsive">
            <table class="table sat-table w-100 mb-0">
                <thead>
                    <tr>
                        <th>DNI Contribuyente</th>
                        <th>Nombres y Apellidos</th>
                        <th>Monto de Deuda</th>
                        <th>Estado de Cuenta</th>
                        <th class="text-center">Acciones Privilegiadas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimos_movimientos as $mov): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($mov['dni'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($mov['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="monto-cell">S/. <?= number_format($mov['monto'], 2) ?></td>
                        <td>
                            <span class="badge-estado badge-<?= strtolower($mov['estado']) ?>">
                                <?= htmlspecialchars($mov['estado'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil-square"></i> Editar Deuda</button>
                            <button class="btn btn-sm btn-outline-warning me-1"><i class="bi bi-trash-fill"></i> Eliminar Multa</button>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-shield-slash"></i> Bloquear Usuario</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- ═══════════════════════════════ FOOTER ═══════════════════════════════ -->
<footer class="lab-footer">
    <span class="lab-badge">ENTORNO SIMULADO</span>
    <p>Taller de Seguridad Informática — Demostración Práctica A01:2021 (Broken Access Control)</p>
</footer>

</body>
</html>
