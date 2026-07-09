<?php
/*
 * ============================================================
 * ARCHIVO: dashboard.php
 * PROPÓSITO: Panel privado de la Oficina Virtual SAT-LAB
 * VECTOR:    V3 — Destino tras autenticación comprometida
 * ============================================================
 * LÓGICA DE SESIÓN:
 *   - Acceso normal  → $_SESSION['usuario'] = '72345678'
 *   - Bypass SQLi    → $_SESSION['usuario'] puede ser el primer
 *     registro retornado por la query (el primer usuario de la BD)
 *     En ese caso se muestra un banner de alerta "acceso comprometido".
 * ============================================================
 */

session_start();

// ─── Verificar sesión activa ─────────────────────────────────
if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: oficina.html');
    exit;
}

// ─── Conexión a la BD ────────────────────────────────────────
$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'sat_lab';
$db_user = getenv('DB_USER') ?: 'appuser';
$db_pass = getenv('DB_PASS') ?: 'apppassword';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('<p style="font-family:sans-serif;padding:20px;color:red;">Error de conexión a la base de datos.</p>');
}
$conn->set_charset('utf8mb4');

// ─── Obtener identidad del usuario en sesión ─────────────────
$dni_sesion = $_SESSION['usuario_email'] ?? '';
$bypass_activo = false;

// Si el DNI está vacío (bypass SQLi devolvió fila anónima) o
// si se forzó el primer registro, mostramos el banner de compromiso
if (empty($dni_sesion)) {
    $bypass_activo = true;
    // Cargar el primer contribuyente como "víctima del bypass"
    $res = $conn->query("SELECT id_contribuyente FROM contribuyentes ORDER BY id_contribuyente LIMIT 1");
    $dni_sesion = $res ? $res->fetch_row()[0] : '72345678';
}

// ─── Obtener datos del contribuyente ────────────────────────
$stmt = $conn->prepare("SELECT nombres, apellidos, direccion, telefono, correo_personal FROM contribuyentes WHERE id_contribuyente = ?");
$stmt->bind_param('s', $dni_sesion);
$stmt->execute();
$contribuyente = $stmt->get_result()->fetch_assoc();
$stmt->close();

$nombre_completo = $contribuyente
    ? htmlspecialchars($contribuyente['nombres'] . ' ' . $contribuyente['apellidos'], ENT_QUOTES, 'UTF-8')
    : 'Contribuyente ' . htmlspecialchars($dni_sesion, ENT_QUOTES, 'UTF-8');

// ─── Obtener deudas del contribuyente ───────────────────────
$stmt2 = $conn->prepare("SELECT tipo_tributo, periodo, monto, estado, fecha_emision FROM deudas WHERE id_contribuyente = ? ORDER BY FIELD(estado,'Vencido','Pendiente','Pagado'), fecha_emision DESC");
$stmt2->bind_param('s', $dni_sesion);
$stmt2->execute();
$deudas_result = $stmt2->get_result();
$deudas = [];
$total_pendiente = 0;
$total_pagado    = 0;
$total_tramites  = 0;

while ($row = $deudas_result->fetch_assoc()) {
    $deudas[] = $row;
    if ($row['estado'] === 'Pagado') {
        $total_pagado += $row['monto'];
    } else {
        $total_pendiente += $row['monto'];
        if ($row['estado'] === 'Pendiente') $total_tramites++;
    }
}
$stmt2->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SATP Oficina Virtual — Mi Estado de Cuenta</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        /* ── Variables institucionales ──────────────────── */
        :root {
            --sat-azul:      #0654a3;
            --sat-azul-c:    #00a4e4;
            --sat-verde:     #b3d234;
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
            background: var(--sat-azul);
            border-bottom: 3px solid var(--sat-azul-c);
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

        .nav-logo-box .big  { color: var(--sat-azul); font-size: 17px; font-weight: 700; }
        .nav-logo-box .sub  { color: var(--sat-naranja); font-size: 10px; font-weight: 700; }

        .nav-title {
            color: rgba(255,255,255,.75);
            font-size: 11px;
            font-weight: 600;
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

        .nav-user i { font-size: 18px; color: var(--sat-azul-c); }

        .btn-logout {
            background: #e74c3c;
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
        .btn-logout:hover { background: #c0392b; color: #fff; }

        /* ── SIDEBAR ─────────────────────────────────────── */
        .sat-sidebar {
            position: fixed;
            top: var(--nav-h);
            left: 0;
            width: var(--sidebar-w);
            bottom: 0;
            background: #1a2744;
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
            border-left-color: var(--sat-azul-c);
        }

        .sidebar-link i { font-size: 16px; width: 18px; text-align: center; }

        .sidebar-dni {
            margin: 16px;
            padding: 12px;
            background: rgba(255,255,255,.05);
            border-radius: 6px;
            text-align: center;
            color: rgba(255,255,255,.5);
            font-size: 10px;
        }
        .sidebar-dni strong { display: block; color: var(--sat-azul-c); font-size: 14px; letter-spacing: 1px; }

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
            color: var(--sat-azul);
            border-bottom: 2px solid var(--sat-azul-c);
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

        .card-deuda   { background: linear-gradient(135deg,#c0392b,#e74c3c); }
        .card-pagado  { background: linear-gradient(135deg,#1e8449,#27ae60); }
        .card-tramite { background: linear-gradient(135deg,#1a5276,#2980b9); }

        /* ── TABLA DE DEUDAS ─────────────────────────────── */
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
            color: var(--sat-azul);
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

        /* ── INFO DEL CONTRIBUYENTE ────────────────────────── */
        .perfil-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .perfil-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--sat-azul);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 24px;
            flex-shrink: 0;
        }
        .perfil-info h5 { font-size: 16px; font-weight: 700; color: var(--sat-azul); margin: 0 0 4px; }
        .perfil-info p  { font-size: 12px; color: #666; margin: 0; }

        /* ── BANNER BYPASS SQLi ─────────────────────────────── */
        .bypass-banner {
            background: linear-gradient(135deg, #1a1a2e, #2d1a47);
            border: 2px solid #e74c3c;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .bypass-banner .bypass-icon { font-size: 36px; flex-shrink: 0; }
        .bypass-banner h4 { color: #e74c3c; font-size: 14px; font-weight: 700; text-transform: uppercase; margin: 0 0 6px; }
        .bypass-banner p  { color: rgba(255,255,255,.8); font-size: 12px; line-height: 1.7; margin: 0; }
        .bypass-banner code { background: rgba(255,255,255,.1); padding: 1px 6px; border-radius: 3px; color: #f39c12; font-size: 11px; }

        /* ── FOOTER DEL LAB ──────────────────────────────────── */
        .lab-footer {
            position: fixed;
            bottom: 0;
            left: var(--sidebar-w);
            right: 0;
            background: rgba(26,39,68,.95);
            border-top: 2px solid var(--sat-azul-c);
            padding: 8px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 500;
        }
        .lab-footer .lab-badge {
            background: var(--sat-naranja);
            color: #1a1a2e;
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
        <span>OFICINA VIRTUAL</span>
        <span>SISTEMA TRIBUTARIO</span>
    </div>
    <div class="nav-spacer"></div>
    <div class="nav-user">
        <i class="bi bi-person-circle"></i>
        <span><?= $nombre_completo ?></span>
        <small class="text-white-50">&nbsp;|&nbsp;DNI: <?= htmlspecialchars($dni_sesion, ENT_QUOTES, 'UTF-8') ?></small>
    </div>
    <a href="logout.php" class="btn-logout ms-3">
        <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
    </a>
</nav>

<!-- ═══════════════════════════════ SIDEBAR ═══════════════════════════════ -->
<aside class="sat-sidebar">
    <div class="sidebar-section">Menú Principal</div>

    <a href="#resumen"       class="sidebar-link active"><i class="bi bi-grid-1x2-fill"></i> Resumen General</a>
    <a href="#predios"       class="sidebar-link"><i class="bi bi-house-fill"></i> Mis Predios</a>
    <a href="#estado-cuenta" class="sidebar-link"><i class="bi bi-receipt-cutoff"></i> Estado de Cuenta</a>
    <a href="#fraccionamiento" class="sidebar-link"><i class="bi bi-calendar2-range-fill"></i> Fraccionamientos</a>

    <div class="sidebar-section" style="margin-top:8px;">Ajustes</div>
    <a href="#config" class="sidebar-link"><i class="bi bi-gear-fill"></i> Configuración</a>
    <a href="logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a>

    <div class="sidebar-dni">
        <strong><?= htmlspecialchars($dni_sesion, ENT_QUOTES, 'UTF-8') ?></strong>
        Sesión activa
    </div>
</aside>

<!-- ═══════════════════════════════ MAIN ═══════════════════════════════ -->
<main class="sat-main">

    <!-- ── BANNER BYPASS SQLi (solo si fue por inyección) ────────── -->
    <?php if ($bypass_activo): ?>
    <div class="bypass-banner">
        <span class="bypass-icon">🔓</span>
        <div>
            <h4>✅ Bypass por SQL Injection — Acceso Comprometido (V3)</h4>
            <p>
                La autenticación fue eludida mediante el payload <code>' OR '1'='1' -- -</code>.<br>
                La condición <code>AND password='...'</code> quedó <strong>comentada</strong> por el operador <code>-- -</code>.<br>
                La condición <code>'1'='1'</code> siempre es <code>TRUE</code>, retornando el <strong>primer registro de la tabla</strong>.<br>
                Resultado: acceso concedido al perfil del contribuyente <strong><?= $nombre_completo ?></strong>
                sin conocer su contraseña. Esto demuestra el impacto real de la vulnerabilidad <strong>OWASP A07:2021</strong>.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── PERFIL DEL CONTRIBUYENTE ───────────────────────────── -->
    <div class="perfil-card" id="resumen">
        <div class="perfil-avatar"><i class="bi bi-person-fill"></i></div>
        <div class="perfil-info">
            <h5><?= $nombre_completo ?></h5>
            <?php if ($contribuyente): ?>
            <p>
                <i class="bi bi-geo-alt-fill text-primary me-1"></i>
                <?= htmlspecialchars($contribuyente['direccion'], ENT_QUOTES, 'UTF-8') ?>
                &nbsp;|&nbsp;
                <i class="bi bi-telephone-fill text-primary me-1"></i>
                <?= htmlspecialchars($contribuyente['telefono'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?>
                &nbsp;|&nbsp;
                <i class="bi bi-envelope-fill text-primary me-1"></i>
                <?= htmlspecialchars($contribuyente['correo_personal'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── TÍTULO ─────────────────────────────────────────────── -->
    <h1 class="page-title" id="estado-cuenta">
        <i class="bi bi-file-earmark-text-fill me-2"></i>Mi Estado de Cuenta Tributario
    </h1>

    <!-- ── TARJETAS DE RESUMEN ─────────────────────────────────── -->
    <div class="row g-3 mb-2">
        <div class="col-md-4">
            <div class="card-stat card-deuda">
                <i class="bi bi-exclamation-triangle-fill stat-icon"></i>
                <div class="stat-label">💰 Total Deuda Activa</div>
                <div class="stat-value">S/. <?= number_format($total_pendiente, 2) ?></div>
                <div class="stat-sub">Pendiente + Vencido</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-stat card-pagado">
                <i class="bi bi-check-circle-fill stat-icon"></i>
                <div class="stat-label">✅ Total Pagado</div>
                <div class="stat-value">S/. <?= number_format($total_pagado, 2) ?></div>
                <div class="stat-sub">Obligaciones canceladas</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-stat card-tramite">
                <i class="bi bi-hourglass-split stat-icon"></i>
                <div class="stat-label">⏳ Trámites en Curso</div>
                <div class="stat-value"><?= $total_tramites ?></div>
                <div class="stat-sub">Cuotas en estado Pendiente</div>
            </div>
        </div>
    </div>

    <!-- ── TABLA DE DEUDAS ─────────────────────────────────────── -->
    <div class="table-card">
        <div class="table-title">
            <i class="bi bi-table me-2"></i>Detalle de Obligaciones Tributarias
        </div>
        <?php if (!empty($deudas)): ?>
        <div class="table-responsive">
            <table class="table sat-table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Concepto / Tributo</th>
                        <th>Periodo</th>
                        <th>Monto</th>
                        <th>F. Emisión</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deudas as $i => $d):
                        $estado_limpio = htmlspecialchars($d['estado'], ENT_QUOTES, 'UTF-8');
                        $badge_class   = match($d['estado']) {
                            'Pagado'    => 'badge-pagado',
                            'Pendiente' => 'badge-pendiente',
                            'Vencido'   => 'badge-vencido',
                            default     => 'badge-pendiente',
                        };
                        $monto_color = match($d['estado']) {
                            'Pagado'  => 'color:#27ae60',
                            'Vencido' => 'color:#e74c3c',
                            default   => 'color:#e67e22',
                        };
                    ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($d['tipo_tributo'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($d['periodo'],     ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="monto-cell" style="<?= $monto_color ?>">
                                S/. <?= number_format((float)$d['monto'], 2) ?>
                            </span>
                        </td>
                        <td class="text-muted">
                            <?= $d['fecha_emision'] ? htmlspecialchars($d['fecha_emision'], ENT_QUOTES, 'UTF-8') : '—' ?>
                        </td>
                        <td>
                            <span class="badge-estado <?= $badge_class ?>"><?= $estado_limpio ?></span>
                        </td>
                        <td>
                            <?php if ($d['estado'] !== 'Pagado'): ?>
                                <a href="#" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:11px;">
                                    <i class="bi bi-credit-card me-1"></i>Pagar
                                </a>
                            <?php else: ?>
                                <a href="#" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:11px;">
                                    <i class="bi bi-download me-1"></i>Comprobante
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center text-muted py-4">
            <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
            No se encontraron obligaciones tributarias registradas para este contribuyente.
        </div>
        <?php endif; ?>
    </div>

    <!-- ── ACCESO RÁPIDO ─────────────────────────────────────── -->
    <div class="row g-3 mt-2" id="predios">
        <?php
        $servicios = [
            ['bi bi-house-fill',      'Mis Predios',          '#1a5276', 'Ver inmuebles registrados'],
            ['bi bi-car-front-fill',  'Impuesto Vehicular',   '#145a32', 'Vehículos a tu nombre'],
            ['bi bi-receipt',         'Mis Comprobantes',     '#6c3483', 'Historial de pagos'],
            ['bi bi-telephone-fill',  'Soporte en Línea',     '#b7950b', 'Atención al contribuyente'],
        ];
        foreach ($servicios as [$icon, $label, $color, $desc]):
        ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius:10px;cursor:pointer;transition:.2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
                <div class="card-body text-center p-4">
                    <div style="width:52px;height:52px;border-radius:50%;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                        <i class="<?= $icon ?>" style="color:#fff;font-size:22px;"></i>
                    </div>
                    <h6 class="fw-bold mb-1" style="font-size:13px;"><?= $label ?></h6>
                    <p class="text-muted mb-0" style="font-size:11px;"><?= $desc ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<!-- ═══════════════════════════════ FOOTER LAB ═══════════════════════════════ -->
<footer class="lab-footer">
    <span class="lab-badge">⚠ ENTORNO DE LABORATORIO</span>
    <p>Portal simulado con vulnerabilidades intencionales — Laboratorio Académico de Ciberseguridad OWASP Top 10 | Solo uso educativo en redes aisladas</p>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
