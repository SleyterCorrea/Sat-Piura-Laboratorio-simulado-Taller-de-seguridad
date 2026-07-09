<?php
/*
 * ============================================================
 * ARCHIVO: buscar_seguro.php
 * VERSIÓN: SEGURA — Mitigación de XSS Reflejado
 * OWASP: A03:2021 – Injection (XSS) (MITIGADO)
 * ============================================================
 * TÉCNICAS DE SEGURIDAD IMPLEMENTADAS:
 *
 *   1. htmlspecialchars() con flags correctos
 *      → Convierte caracteres especiales HTML en entidades:
 *        <  →  &lt;       (neutraliza etiquetas HTML)
 *        >  →  &gt;
 *        "  →  &quot;     (neutraliza cierre de atributos)
 *        '  →  &#039;
 *        &  →  &amp;
 *      → Flag ENT_QUOTES: codifica tanto comillas simples como dobles
 *      → Flag ENT_HTML5:  codifica según estándar HTML5
 *      → Charset UTF-8:   evita ataques por encoding alternativo
 *
 *   2. Validación de longitud de entrada
 *      → Evita ataques por payloads extremadamente largos (DoS básico)
 *
 *   3. Header Content-Security-Policy (CSP) como defensa en profundidad
 *      → Bloquea ejecución de scripts inline en navegadores modernos
 *      → Actúa como segunda línea de defensa si escapado falla
 *
 * COMPARACIÓN VULNERABLE vs SEGURO:
 *   VULNERABLE:  echo $_GET['q'];
 *   SEGURO:      echo htmlspecialchars($_GET['q'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
 *
 * POR QUÉ FUNCIONA:
 *   Input del atacante: <script>alert('XSS-OK')</script>
 *   Vulnerable recibe:  <script>alert('XSS-OK')</script>  → JS ejecutado
 *   Seguro recibe:      &lt;script&gt;alert(&#039;XSS-OK&#039;)&lt;/script&gt;
 *                       → Mostrado como texto, NO ejecutado
 * ============================================================
 */

// Cabecera de seguridad: Content-Security-Policy
// Bloquea scripts inline como segunda capa de defensa
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador Seguro — SAT-LAB [VERSIÓN SEGURA]</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Open Sans', sans-serif;
            background: linear-gradient(135deg, #145a32, #1e8449);
            min-height: 100vh;
            padding: 30px 20px;
        }
        .container { max-width: 700px; margin: 0 auto; }
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
            font-family: 'Courier New', monospace;
        }
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .search-box input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid #7a9ab0;
            border-radius: 4px;
            font-family: inherit;
            font-size: 13px;
            background: #c2d3dd;
            color: #333;
        }
        .search-box input:focus {
            outline: none;
            border-color: #1a7a3c;
            background: #d8f5e4;
        }
        .search-box button {
            padding: 10px 18px;
            background: #1a7a3c;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .search-box button:hover { background: #145a32; }
        .result-area { border-top: 2px solid #d0e8d8; padding-top: 18px; }
        .result-label { font-size: 13px; color: #666; margin-bottom: 10px; }
        .result-value {
            font-size: 16px;
            font-weight: 700;
            color: #1a7a3c;
            background: #eafaf1;
            border: 1px solid #82e0aa;
            border-radius: 4px;
            padding: 14px 18px;
            min-height: 50px;
            word-break: break-all;
        }
        .code-compare {
            background: #1a1a2e;
            border-radius: 6px;
            padding: 14px 18px;
            margin: 12px 0 20px 0;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #c8d6e5;
            line-height: 1.7;
        }
        .code-red   { color: #e74c3c; }
        .code-green { color: #2ecc71; }
        .code-gray  { color: #7f8c8d; }
        .btn-volver {
            display: inline-block;
            margin-top: 22px;
            padding: 9px 18px;
            background: #1a7a3c;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }
        .btn-volver:hover { background: #145a32; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <h1>🔒 Buscador de Trámites — Versión Segura</h1>
            <span class="secure-badge">✅ MITIGADO — htmlspecialchars() + CSP</span>
        </div>
        <div class="panel">

            <div class="security-note">
                <strong>✅ Técnica de seguridad: Codificación de salida (Output Encoding)</strong>
                Toda entrada del usuario se pasa por
                <code>htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8')</code>
                antes de mostrarse en el HTML. Esto convierte los caracteres peligrosos
                en entidades HTML inofensivas. Además, se aplica una cabecera CSP que
                bloquea scripts inline como segunda capa de defensa.
            </div>

            <div class="code-compare">
                <span class="code-gray">// ❌ VERSIÓN VULNERABLE (buscar.php):</span><br>
                <span class="code-red">echo $_GET['q'];  // Ejecuta &lt;script&gt;alert(1)&lt;/script&gt;</span><br><br>
                <span class="code-gray">// ✅ VERSIÓN SEGURA (esta página):</span><br>
                <span class="code-green">echo htmlspecialchars($_GET['q'], ENT_QUOTES | ENT_HTML5, 'UTF-8');</span><br>
                <span class="code-gray">// Salida: &amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt; → texto plano</span>
            </div>

            <!-- Formulario de búsqueda seguro -->
            <form action="buscar_seguro.php" method="GET" class="search-box" id="form-buscar-seguro">
                <input
                    type="text"
                    name="q"
                    id="q-seguro"
                    placeholder="Nº de papeleta, expediente o trámite..."
                    maxlength="100"
                    value="<?php
                        // ✅ SEGURO: el value del input también se codifica
                        if (isset($_GET['q'])) {
                            echo htmlspecialchars($_GET['q'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        }
                    ?>">
                <button type="submit">Buscar</button>
            </form>

<?php
if (isset($_GET['q']) && $_GET['q'] !== '') {

    // ─────────────────────────────────────────────────────────────
    // VALIDACIÓN: Longitud máxima para prevenir abusos
    // ─────────────────────────────────────────────────────────────
    $input_raw = $_GET['q'];

    if (strlen($input_raw) > 100) {
        echo "<p style='color:#c0392b; font-size:13px;'>
               ❌ El término de búsqueda es demasiado largo (máx. 100 caracteres).
              </p>";
    } else {
        // ─────────────────────────────────────────────────────────────
        // CODIFICACIÓN DE SALIDA: htmlspecialchars()
        //
        // ANTES (vulnerable):
        //   Input:  <script>alert('XSS-OK')</script>
        //   Output: <script>alert('XSS-OK')</script>  ← JS ejecutado
        //
        // DESPUÉS (seguro):
        //   Input:  <script>alert('XSS-OK')</script>
        //   Output: &lt;script&gt;alert(&#039;XSS-OK&#039;)&lt;/script&gt;
        //           ← Se muestra como texto, NO se ejecuta
        // ─────────────────────────────────────────────────────────────
        $input_seguro = htmlspecialchars($input_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        echo "<div class='result-area'>";
        echo "<p class='result-label'>Resultados de búsqueda para:</p>";
        echo "<div class='result-value'>" . $input_seguro . "</div>";
        echo "</div>";
    }

} elseif (isset($_GET['q']) && $_GET['q'] === '') {
    echo "<p style='color:#888; font-size:13px; margin-top:10px;'>Por favor, ingrese un término de búsqueda.</p>";
} else {
    echo "<p style='color:#888; font-size:13px; margin-top:10px;'>Ingrese el número de papeleta o expediente para buscar.</p>";
}
?>
            <a href="index.html" class="btn-volver">← Volver al Portal</a>
        </div>
    </div>
</body>
</html>
