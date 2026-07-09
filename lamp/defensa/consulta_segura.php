<?php
/*
 * ============================================================
 * ARCHIVO: web/defensa/consulta_segura.php
 * VERSIÓN: SEGURA — Mitigación de SQL Injection con PDO
 * ============================================================
 */

$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'sat_lab';
$db_user = getenv('DB_USER') ?: 'appuser';
$db_pass = getenv('DB_PASS') ?: 'apppassword';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
$opts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,  // Prepared statements REALES
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $opts);
} catch (PDOException $e) {
    error_log("[SAT-LAB] Conexión BD fallida: " . $e->getMessage());
    die("<p style='font-family:sans-serif;color:red;padding:20px;'>
         Error interno del servidor. Contacte al administrador.</p>");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta Segura — SAT-LAB [MITIGADO]</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Open Sans', sans-serif; background: linear-gradient(135deg,#145a32,#1e8449); min-height: 100vh; padding: 30px 20px; }
        .wrapper { max-width: 860px; margin: 0 auto; }
        .top-bar { background: #1a7a3c; border-radius: 8px 8px 0 0; padding: 14px 22px; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #b3d234; }
        .top-bar h1 { color: #fff; font-size: 15px; font-weight: 700; }
        .badge-ok { background: #b3d234; color: #1a4a0d; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 3px; letter-spacing: .5px; }
        .panel { background: rgba(255,255,255,0.97); border-radius: 0 0 8px 8px; padding: 28px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
        .sec-note { background: #eafaf1; border: 1px solid #82e0aa; border-radius: 4px; padding: 12px 14px; margin-bottom: 20px; font-size: 12px; color: #1e8449; line-height: 1.6; }
        .sec-note strong { display: block; margin-bottom: 4px; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #145a32; }
        .code-block { background: #1a1a2e; border-radius: 5px; padding: 12px 16px; font-family: 'Courier New', monospace; font-size: 11px; color: #c8d6e5; line-height: 1.7; margin-bottom: 18px; }
        .cr { color: #e74c3c; } .cg { color: #2ecc71; } .cy { color: #f39c12; }
        .err-input { color: #c0392b; background: #fdf2f2; border: 1px solid #e74c3c; border-radius: 4px; padding: 12px; font-size: 13px; }
        h2 { color: #1a7a3c; font-size: 15px; margin-bottom: 14px; border-bottom: 1px solid #d0e8d8; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #1a7a3c; color: #fff; padding: 10px 12px; text-align: left; font-size: 11px; text-transform: uppercase; }
        td { padding: 10px 12px; border-bottom: 1px solid #e8edf2; color: #333; }
        tr:hover td { background: #f0faf4; }
        .PENDIENTE { color: #e67e22; font-weight: 700; } .VENCIDA { color: #e74c3c; font-weight: 700; } .PAGADA { color: #27ae60; font-weight: 700; }
        .no-result { color: #888; font-size: 14px; padding: 20px 0; text-align: center; }
        .btn-back { display: inline-block; margin-top: 22px; padding: 9px 18px; background: #1a7a3c; color: #fff; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="top-bar">
        <h1>🔒 Consulta de Deudas — Versión Segura</h1>
        <span class="badge-ok">✅ MITIGADO — PDO Prepared Statements</span>
    </div>
    <div class="panel">
        <div class="sec-note">
            <strong>✅ Técnica: Prepared Statements con PDO (PDO::ATTR_EMULATE_PREPARES = false)</strong>
            La estructura SQL se envía al motor de BD <em>antes</em> de los datos.
            Cualquier payload SQLi es tratado como un string literal.
        </div>
        <div class="code-block">
            <span class="cy">// ❌ VULNERABLE (consulta.php):</span><br>
            <span class="cr">$sql = "... WHERE id_contribuyente = '" . $_POST['id_contribuyente'] . "'";</span><br><br>
            <span class="cy">// ✅ SEGURO (esta página):</span><br>
            <span class="cg">$sql  = "... WHERE id_contribuyente = ?";</span><br>
            <span class="cg">$stmt = $pdo->prepare($sql);</span><br>
            <span class="cg">$stmt->execute([$id_validado]);</span>
        </div>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_contribuyente'])) {

    $id_input = trim($_POST['id_contribuyente']);

    // ─── PASO 1: Validación estricta de formato ──────────────────
    // Rechaza cualquier input que no sea exactamente 8 dígitos.
    // Esto bloquea payloads con ', UNION, --, espacios, etc.
    if (!preg_match('/^\d{8}$/', $id_input)) {
        echo "<p class='err-input'>❌ Formato inválido. Ingrese exactamente 8 dígitos numéricos.</p>";
    } else {
        try {
            // ─── PASO 2: Consulta con Prepared Statement ──────────────
            // La consulta SQL tiene estructura FIJA. El '?' es el marcador.
            // El motor recibe: estructura SQL → luego el dato por separado.
            $sql  = "SELECT id_contribuyente, concepto, monto, estado, fecha_vencimiento
                     FROM   deudas
                     WHERE  id_contribuyente = ?";

            $stmt = $pdo->prepare($sql);       // Estructura enviada al motor
            $stmt->execute([$id_input]);       // Dato enviado como valor literal

            $filas = $stmt->fetchAll();

            // ─── PASO 3: Salida segura con htmlspecialchars() ─────────
            echo "<h2>Resultados para: <em>" . htmlspecialchars($id_input, ENT_QUOTES, 'UTF-8') . "</em></h2>";

            if (!empty($filas)) {
                echo "<table><thead><tr>
                        <th>Contribuyente</th><th>Concepto</th>
                        <th>Monto (S/.)</th><th>Estado</th><th>Vencimiento</th>
                      </tr></thead><tbody>";
                foreach ($filas as $f) {
                    $cls = 'estado-' . htmlspecialchars($f['estado'], ENT_QUOTES, 'UTF-8');
                    echo "<tr>
                            <td>" . htmlspecialchars($f['id_contribuyente'],  ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . htmlspecialchars($f['concepto'],          ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . number_format((float)$f['monto'], 2)                          . "</td>
                            <td class='" . htmlspecialchars($f['estado'], ENT_QUOTES, 'UTF-8') . "'>"
                               . htmlspecialchars($f['estado'], ENT_QUOTES, 'UTF-8')               . "</td>
                            <td>" . htmlspecialchars($f['fecha_vencimiento'], ENT_QUOTES, 'UTF-8') . "</td>
                          </tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<p class='no-result'>No se encontraron deudas para el identificador ingresado.</p>";
            }

        } catch (PDOException $e) {
            error_log("[SAT-LAB] consulta_segura.php: " . $e->getMessage());
            echo "<p class='err-input'>Error interno. Contacte al administrador.</p>";
        }
    }
} else {
    echo "<p class='no-result'>Use el formulario de la página principal.</p>";
}
?>
        <a href="javascript:history.back()" class="btn-back">← Volver</a>
    </div>
</div>
</body>
</html>
