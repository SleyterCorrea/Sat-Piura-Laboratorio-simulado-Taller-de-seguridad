<?php
/*
 * ============================================================
 * ARCHIVO: consulta_segura.php
 * VERSIÓN: SEGURA — Mitigación de SQL Injection con PDO
 * OWASP: A03:2021 – Injection (MITIGADO)
 * ============================================================
 * TÉCNICAS DE SEGURIDAD IMPLEMENTADAS:
 *
 *   1. PDO con Prepared Statements (Sentencias Preparadas)
 *      → La estructura SQL se fija ANTES de recibir datos del usuario.
 *      → Los parámetros se enlazan como valores literales, nunca como código.
 *      → Cualquier payload SQLi es tratado como texto ordinario.
 *
 *   2. Validación y tipado de entrada (validación de DNI 8 dígitos)
 *      → Se verifica que el input sea exactamente 8 dígitos numéricos.
 *      → Payloads con comillas, guiones, espacios son rechazados antes
 *        de llegar a la capa de base de datos.
 *
 *   3. Manejo seguro de errores
 *      → Los errores de BD se registran internamente (error_log).
 *      → El usuario solo ve un mensaje genérico sin detalles técnicos.
 *      → La consulta SQL real NO se muestra en pantalla.
 *
 *   4. htmlspecialchars() en la salida
 *      → Previene XSS secundario al mostrar datos de la BD.
 *
 * COMPARACIÓN VULNERABLE vs SEGURO:
 *   VULNERABLE:  $sql = "... WHERE id = '$input'";
 *   SEGURO:      $sql = "... WHERE id = ?";  +  execute([$input]);
 * ============================================================
 */

// --- CONFIGURACIÓN DE CONEXIÓN PDO ---
$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'sat_lab';
$db_user = getenv('DB_USER') ?: 'appuser';
$db_pass = getenv('DB_PASS') ?: 'apppassword';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";

$opciones_pdo = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Excepciones en errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Arrays asociativos
    PDO::ATTR_EMULATE_PREPARES   => false,                    // Prepared statements REALES
];

// Crear conexión PDO (fuera del bloque POST para poder manejar errores de conexión)
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $opciones_pdo);
} catch (PDOException $e) {
    error_log("[SAT-LAB] Error de conexión a BD: " . $e->getMessage());
    // Usuario solo ve mensaje genérico
    die("<p style='font-family:sans-serif; color:red; padding:20px;'>
         Error interno del servidor. Contacte al administrador.</p>");
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta Segura — SAT-LAB [VERSIÓN SEGURA]</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Open Sans', sans-serif;
            background: linear-gradient(135deg, #145a32, #1e8449);
            min-height: 100vh;
            padding: 30px 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .header-bar {
            background-color: #1a7a3c;
            border-radius: 6px 6px 0 0;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #b3d234;
        }
        .header-bar h1 { color: #fff; font-size: 16px; font-weight: 700; }
        .secure-badge {
            background-color: #b3d234;
            color: #1a4a0d;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 3px;
            letter-spacing: 0.5px;
        }
        .panel {
            background: rgba(255,255,255,0.97);
            border-radius: 0 0 6px 6px;
            padding: 28px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.25);
        }
        .security-note {
            background: #eafaf1;
            border: 1px solid #82e0aa;
            border-radius: 4px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #1e8449;
            line-height: 1.6;
        }
        .security-note strong {
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #145a32;
        }
        .security-note code {
            background: #d5f5e3;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
        }
        h2 { color: #1a7a3c; font-size: 15px; margin-bottom: 14px; border-bottom: 1px solid #d0e8d8; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        th { background-color: #1a7a3c; color: #fff; padding: 10px 12px; text-align: left; font-size: 12px; text-transform: uppercase; }
        td { padding: 10px 12px; border-bottom: 1px solid #e8edf2; color: #333; }
        tr:hover td { background-color: #f0faf4; }
        .estado-PENDIENTE { color: #e67e22; font-weight: 700; }
        .estado-VENCIDA   { color: #e74c3c; font-weight: 700; }
        .estado-PAGADA    { color: #27ae60; font-weight: 700; }
        .no-result { color: #666; font-size: 14px; padding: 20px 0; text-align: center; }
        .error-input { color: #c0392b; font-size: 13px; background: #fdf2f2; border: 1px solid #e74c3c; border-radius: 4px; padding: 12px 14px; }
        .btn-volver { display:inline-block; margin-top:22px; padding:9px 18px; background:#1a7a3c; color:#fff; border-radius:4px; text-decoration:none; font-size:13px; font-weight:600; }
        .btn-volver:hover { background:#145a32; }
        .code-compare {
            background: #1a1a2e;
            border-radius: 6px;
            padding: 14px 18px;
            margin: 16px 0;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #c8d6e5;
            line-height: 1.7;
        }
        .code-red   { color: #e74c3c; }
        .code-green { color: #2ecc71; }
        .code-gray  { color: #7f8c8d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <h1>🔒 Consulta de Deudas — Versión Segura</h1>
            <span class="secure-badge">✅ MITIGADO — PDO Prepared Statements</span>
        </div>
        <div class="panel">

            <!-- Nota educativa comparativa -->
            <div class="security-note">
                <strong>✅ Técnica de seguridad: Prepared Statements con PDO</strong>
                La consulta usa marcadores de posición (<code>?</code>). El motor SQL recibe
                la <em>estructura</em> primero, y los <em>datos</em> después, como valores
                literales. Un payload como <code>' UNION SELECT...</code> es interpretado
                como texto, no como SQL.
            </div>

            <div class="code-compare">
                <span class="code-gray">// ❌ VERSIÓN VULNERABLE (consulta.php):</span><br>
                <span class="code-red">$sql = "SELECT ... WHERE id = '" . $_POST['id'] . "'";</span><br><br>
                <span class="code-gray">// ✅ VERSIÓN SEGURA (esta página):</span><br>
                <span class="code-green">$sql  = "SELECT ... WHERE id = ?";</span><br>
                <span class="code-green">$stmt = $pdo->prepare($sql);</span><br>
                <span class="code-green">$stmt->execute([$id_validado]);</span>
            </div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_contribuyente'])) {

    // ─────────────────────────────────────────────────────────────
    // PASO 1: VALIDACIÓN DE ENTRADA (antes de tocar la BD)
    // Se verifica que sea exactamente 8 dígitos numéricos.
    // Cualquier caracter especial (', ", -, espacio) es rechazado.
    // ─────────────────────────────────────────────────────────────
    $id_input = trim($_POST['id_contribuyente']);

    if (!preg_match('/^\d{8}$/', $id_input)) {
        echo "<p class='error-input'>
               ❌ Formato inválido. El identificador debe ser exactamente 8 dígitos numéricos.
               <br><small>Ejemplo válido: <strong>72345678</strong></small>
              </p>";
    } else {

        try {
            // ─────────────────────────────────────────────────────
            // PASO 2: CONSULTA CON PREPARED STATEMENT
            // El '?' es el marcador de posición.
            // La estructura SQL está completamente fijada.
            // ─────────────────────────────────────────────────────
            $sql = "SELECT id_contribuyente, concepto, monto, estado, fecha_vencimiento
                    FROM   deudas
                    WHERE  id_contribuyente = ?";

            // Preparar: envía la estructura SQL al motor de BD
            $stmt = $pdo->prepare($sql);

            // Ejecutar: envía el dato como valor tipado, no como SQL
            $stmt->execute([$id_input]);

            // Obtener resultados
            $filas = $stmt->fetchAll();

            // ─────────────────────────────────────────────────────
            // PASO 3: SALIDA SEGURA CON htmlspecialchars()
            // Previene XSS secundario al renderizar datos de la BD.
            // ─────────────────────────────────────────────────────
            echo "<h2>Resultados para el identificador: <em>" . htmlspecialchars($id_input, ENT_QUOTES, 'UTF-8') . "</em></h2>";

            if (!empty($filas)) {
                echo "<table>
                        <thead>
                            <tr>
                                <th>Contribuyente</th>
                                <th>Concepto</th>
                                <th>Monto (S/.)</th>
                                <th>Estado</th>
                                <th>Vencimiento</th>
                            </tr>
                        </thead>
                        <tbody>";
                foreach ($filas as $fila) {
                    $estado_class = 'estado-' . htmlspecialchars($fila['estado'], ENT_QUOTES, 'UTF-8');
                    echo "<tr>
                            <td>" . htmlspecialchars($fila['id_contribuyente'], ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . htmlspecialchars($fila['concepto'],         ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . number_format((float)$fila['monto'], 2) . "</td>
                            <td class='$estado_class'>" . htmlspecialchars($fila['estado'], ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . htmlspecialchars($fila['fecha_vencimiento'], ENT_QUOTES, 'UTF-8') . "</td>
                          </tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<p class='no-result'>No se encontraron deudas para el identificador ingresado.</p>";
            }

        } catch (PDOException $e) {
            // Error registrado en log del servidor, NO expuesto al usuario
            error_log("[SAT-LAB] Error en consulta_segura.php: " . $e->getMessage());
            echo "<p class='error-input'>
                   ❌ Error interno al procesar la consulta. Contacte al administrador.
                  </p>";
        }
    }

} else {
    echo "<p class='no-result'>Use el formulario de consulta en la página principal.</p>";
}
?>
            <a href="index.html" class="btn-volver">← Volver al Portal</a>
        </div>
    </div>
</body>
</html>
