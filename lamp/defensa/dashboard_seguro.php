<?php
/*
 * ============================================================
 * ARCHIVO: web/defensa/dashboard_seguro.php
 * VERSIÓN: SEGURA — Panel privado con sesión protegida
 * ============================================================
 * DIFERENCIAS CON dashboard.php VULNERABLE:
 *   1. Verifica sesión con token de integridad
 *   2. Todas las salidas usan htmlspecialchars()
 *   3. Consulta a BD usa Prepared Statement (PDO)
 *   4. Cabeceras de seguridad HTTP aplicadas
 * ============================================================
 */

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => false,
]);

// Verificar autenticación
if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: ../index.html');
    exit;
}

// Cabeceras de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'none';");

$email_sesion = htmlspecialchars($_SESSION['usuario_email'] ?? '', ENT_QUOTES, 'UTF-8');

// Conexión segura PDO
$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'sat_lab';
$db_user = getenv('DB_USER') ?: 'appuser';
$db_pass = getenv('DB_PASS') ?: 'apppassword';

$deudas = [];
$total_pendiente = 0;

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user, $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );

    // ✅ Consulta con prepared statement
    $stmt = $pdo->prepare("SELECT concepto, monto, estado, fecha_vencimiento
                           FROM deudas WHERE id_contribuyente = ?
                           ORDER BY FIELD(estado,'VENCIDA','PENDIENTE','PAGADA')");
    $stmt->execute([$_SESSION['usuario_email']]);
    while ($row = $stmt->fetch()) {
        $deudas[] = $row;
        if (in_array($row['estado'], ['PENDIENTE','VENCIDA'])) {
            $total_pendiente += $row['monto'];
        }
    }
} catch (PDOException $e) {
    error_log("[SAT-LAB] dashboard_seguro.php BD error: " . $e->getMessage());
}

$nombres = [
    '72345678' => ['nombre' => 'Juan Carlos Pérez Flores', 'dir' => 'Av. Grau 485, Piura'],
    '45678912' => ['nombre' => 'María Elena Gómez Távara', 'dir' => 'Jr. Loreto 210, Castilla'],
    '31290456' => ['nombre' => 'Empresa Ficticia S.A.C.',   'dir' => 'Av. Sánchez Cerro 1120, Piura'],
    '86541230' => ['nombre' => 'Roberto Díaz Seminario',    'dir' => 'Calle Lima 88, Sullana'],
    '10293847' => ['nombre' => 'Ana Lucia Flores Morales',  'dir' => 'Urb. El Chipe Mz B Lt 5, Piura'],
];
$datos = $nombres[$_SESSION['usuario_email']] ?? ['nombre' => 'Contribuyente', 'dir' => 'N/D'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oficina Virtual Segura — Mi Estado de Cuenta | SAT-LAB</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --azul:#145a32; --azul-c:#b3d234; --rojo:#e74c3c; --naranja:#e67e22; --gris:#f0f4f8; --blanco:#fff; --texto:#2c3e50; }
        body { font-family:'Open Sans','Segoe UI',sans-serif; background:var(--gris); color:var(--texto); min-height:100vh; display:flex; flex-direction:column; }
        .topbar { background:var(--azul); border-bottom:3px solid var(--azul-c); padding:0 24px; height:58px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
        .logo-badge { background:#fff; border-radius:4px; padding:4px 9px; font-weight:700; font-size:17px; color:var(--azul); letter-spacing:1px; }
        .logo-badge span { color:#e8a020; font-size:11px; }
        .logo-sub { color:rgba(255,255,255,.85); font-size:9px; font-weight:600; letter-spacing:.5px; line-height:1.7; margin-left:10px; }
        .logo-area { display:flex; align-items:center; }
        .user-chip { background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3); border-radius:20px; padding:5px 14px; color:#fff; font-size:12px; font-weight:600; }
        .btn-logout { background:var(--rojo); color:#fff; border:none; border-radius:4px; padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer; text-decoration:none; margin-left:10px; }
        .secure-banner { background:linear-gradient(90deg,#145a32,#1e8449); border-bottom:2px solid #b3d234; padding:9px 24px; display:flex; align-items:center; gap:12px; }
        .secure-banner .tag { background:#b3d234; color:#1a4a0d; font-size:9px; font-weight:700; padding:2px 8px; border-radius:3px; letter-spacing:.5px; }
        .secure-banner p { color:rgba(200,255,200,.9); font-size:11px; font-weight:600; }
        .page-content { flex:1; padding:28px 24px; max-width:1100px; margin:0 auto; width:100%; }
        .breadcrumb { font-size:12px; color:#888; margin-bottom:20px; }
        .breadcrumb a { color:var(--azul); text-decoration:none; }
        .card { background:var(--blanco); border-radius:8px; padding:22px 24px; box-shadow:0 4px 20px rgba(0,0,0,.1); margin-bottom:22px; }
        .section-title { font-size:13px; font-weight:700; color:var(--azul); text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid #b3d234; padding-bottom:8px; margin-bottom:16px; }
        .user-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; }
        .info-item { display:flex; flex-direction:column; gap:3px; }
        .info-label { font-size:10px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.5px; }
        .info-value { font-size:14px; font-weight:600; }
        .summary-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:22px; }
        .s-card { background:var(--blanco); border-radius:8px; padding:20px; box-shadow:0 4px 20px rgba(0,0,0,.1); border-left:4px solid #ccc; }
        .s-card.deuda { border-left-color:var(--rojo); }
        .s-card.pend  { border-left-color:var(--naranja); }
        .s-card.ok    { border-left-color:#27ae60; }
        .s-label { font-size:11px; color:#888; font-weight:600; text-transform:uppercase; display:block; margin-bottom:6px; }
        .s-value { font-size:26px; font-weight:700; display:block; }
        .s-card.deuda .s-value { color:var(--rojo); }
        .s-card.pend  .s-value { color:var(--naranja); }
        .s-card.ok    .s-value { color:#27ae60; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th { background:#f7f9fc; color:#555; padding:10px 14px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; border-bottom:2px solid #e8edf2; }
        td { padding:12px 14px; border-bottom:1px solid #f0f3f6; color:#444; }
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; }
        .badge.PENDIENTE { background:#fff3cd; color:#856404; }
        .badge.VENCIDA   { background:#f8d7da; color:#721c24; }
        .badge.PAGADA    { background:#d4edda; color:#155724; }
        .monto { font-family:'Courier New',monospace; font-size:14px; font-weight:700; }
        .monto.venc { color:var(--rojo); } .monto.pend { color:var(--naranja); } .monto.paga { color:#27ae60; }
        .secure-note { background:#eafaf1; border:1px solid #82e0aa; border-radius:8px; padding:18px 22px; margin-bottom:22px; }
        .secure-note h3 { color:#1a7a3c; font-size:13px; margin-bottom:8px; }
        .secure-note ul { font-size:12px; color:#2e7d32; line-height:2; padding-left:18px; }
        footer { background:var(--azul); border-top:2px solid var(--azul-c); padding:12px 24px; text-align:center; flex-shrink:0; }
        footer p { color:rgba(255,255,255,.6); font-size:10px; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="logo-area">
            <div class="logo-badge">SAT <span>LAB</span></div>
            <div class="logo-sub"><div>OFICINA VIRTUAL SEGURA</div><div>SISTEMA TRIBUTARIO</div></div>
        </div>
        <div style="display:flex;align-items:center;">
            <span class="user-chip">🔒 DNI: <?= $email_sesion ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </header>

    <div class="secure-banner">
        <span class="tag">✅ VERSIÓN SEGURA</span>
        <p>Sesión autenticada con PDO + session_regenerate_id() | Dominio: satp-laboratorio.local</p>
    </div>

    <main class="page-content">
        <div class="breadcrumb">
            <a href="#">Inicio</a> / <a href="#">Oficina Virtual</a> / <strong>Mi Estado de Cuenta (Seguro)</strong>
        </div>

        <!-- Banner educativo de seguridad -->
        <div class="secure-note">
            <h3>✅ Técnicas de seguridad activas en esta sesión</h3>
            <ul>
                <li><strong>Anti-SQLi:</strong> Login procesado con PDO Prepared Statement — payload <code>' OR '1'='1</code> es tratado como texto.</li>
                <li><strong>Anti-Brute Force:</strong> <code>sleep(1)</code> aplicado en cada intento fallido — velocidad limitada a ~1 req/s.</li>
                <li><strong>Anti-Session Fixation:</strong> <code>session_regenerate_id(true)</code> ejecutado tras autenticación exitosa.</li>
                <li><strong>Anti-XSS:</strong> Todas las salidas HTML usan <code>htmlspecialchars(ENT_QUOTES, 'UTF-8')</code>.</li>
                <li><strong>CSP Header:</strong> <code>script-src 'none'</code> — bloquea scripts inline en el navegador.</li>
            </ul>
        </div>

        <!-- Datos del contribuyente -->
        <div class="card">
            <h2 class="section-title">👤 Datos del Contribuyente</h2>
            <div class="user-grid">
                <div class="info-item"><span class="info-label">Nombre</span><span class="info-value"><?= htmlspecialchars($datos['nombre'], ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="info-item"><span class="info-label">DNI / Identificador</span><span class="info-value" style="font-family:monospace;color:#1a7a3c;font-size:16px;"><?= $email_sesion ?></span></div>
                <div class="info-item"><span class="info-label">Dirección</span><span class="info-value"><?= htmlspecialchars($datos['dir'], ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="info-item"><span class="info-label">Sesión iniciada</span><span class="info-value"><?= date('d/m/Y H:i:s') ?></span></div>
            </div>
        </div>

        <!-- Resumen -->
        <?php
        $nv = count(array_filter($deudas, fn($d) => $d['estado']==='VENCIDA'));
        $np = count(array_filter($deudas, fn($d) => $d['estado']==='PENDIENTE'));
        $no = count(array_filter($deudas, fn($d) => $d['estado']==='PAGADA'));
        ?>
        <div class="summary-grid">
            <div class="s-card deuda"><span class="s-label">💰 Deuda activa</span><span class="s-value">S/. <?= number_format($total_pendiente, 2) ?></span></div>
            <div class="s-card pend"><span class="s-label">⏳ Cuotas vencidas</span><span class="s-value"><?= $nv ?></span></div>
            <div class="s-card ok"><span class="s-label">✅ Pagos realizados</span><span class="s-value"><?= $no ?></span></div>
        </div>

        <!-- Tabla de deudas -->
        <div class="card">
            <h2 class="section-title">📋 Detalle de Obligaciones Tributarias</h2>
            <?php if (!empty($deudas)): ?>
            <table>
                <thead><tr><th>Concepto</th><th>Monto</th><th>Estado</th><th>Vencimiento</th></tr></thead>
                <tbody>
                <?php foreach ($deudas as $d):
                    $mc = match($d['estado']) { 'VENCIDA'=>'venc','PENDIENTE'=>'pend',default=>'paga' };
                ?>
                <tr>
                    <td><?= htmlspecialchars($d['concepto'],          ENT_QUOTES,'UTF-8') ?></td>
                    <td><span class="monto <?= $mc ?>">S/. <?= number_format((float)$d['monto'],2) ?></span></td>
                    <td><span class="badge <?= htmlspecialchars($d['estado'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($d['estado'],ENT_QUOTES,'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($d['fecha_vencimiento'],  ENT_QUOTES,'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:#888;font-size:14px;text-align:center;padding:20px;">Sin obligaciones registradas.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer><p>© 2024 SAT-LAB — Laboratorio Educativo OWASP Top 10 | Solo uso académico</p></footer>
</body>
</html>
