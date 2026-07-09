<?php
/*
 * ============================================================
 * ARCHIVO: web/defensa/buscar_seguro.php
 * VERSIÓN: SEGURA — Mitigación de XSS Reflejado
 * ============================================================
 * TÉCNICAS:
 *   1. htmlspecialchars(ENT_QUOTES | ENT_HTML5, 'UTF-8') — codifica
 *      todos los chars peligrosos en ambos puntos de salida.
 *   2. Content-Security-Policy (CSP) — bloquea scripts inline
 *      como segunda capa de defensa en el navegador.
 *   3. X-Content-Type-Options y X-Frame-Options como hardening HTTP.
 * ============================================================
 */

// ─── Cabeceras de seguridad ──────────────────────────────────
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador Seguro — SAT-LAB [MITIGADO]</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Open Sans', sans-serif; background: linear-gradient(135deg,#145a32,#1e8449); min-height: 100vh; padding: 30px 20px; }
        .wrapper { max-width: 700px; margin: 0 auto; }
        .top-bar { background: #1a7a3c; border-radius: 8px 8px 0 0; padding: 14px 22px; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #b3d234; }
        .top-bar h1 { color: #fff; font-size: 15px; font-weight: 700; }
        .badge-ok { background: #b3d234; color: #1a4a0d; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 3px; letter-spacing: .5px; }
        .panel { background: rgba(255,255,255,0.97); border-radius: 0 0 8px 8px; padding: 28px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
        .sec-note { background: #eafaf1; border: 1px solid #82e0aa; border-radius: 4px; padding: 12px 14px; margin-bottom: 18px; font-size: 12px; color: #1e8449; line-height: 1.6; }
        .sec-note strong { display: block; margin-bottom: 4px; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #145a32; }
        .code-block { background: #1a1a2e; border-radius: 5px; padding: 12px 16px; font-family: 'Courier New', monospace; font-size: 11px; color: #c8d6e5; line-height: 1.7; margin-bottom: 18px; }
        .cr { color: #e74c3c; } .cg { color: #2ecc71; } .cy { color: #f39c12; }
        .search-row { display: flex; gap: 10px; margin-bottom: 18px; }
        .search-row input { flex: 1; padding: 10px 14px; border: 1px solid #7a9ab0; border-radius: 4px; background: #c2d3dd; font-family: inherit; font-size: 13px; }
        .search-row input:focus { outline: none; border-color: #1a7a3c; background: #d8f5e4; }
        .search-row button { padding: 10px 18px; background: #1a7a3c; color: #fff; border: none; border-radius: 4px; font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; }
        .result-box { background: #eafaf1; border: 1px solid #82e0aa; border-radius: 4px; padding: 14px 18px; min-height: 48px; font-size: 17px; font-weight: 700; color: #1a7a3c; word-break: break-all; }
        .result-label { font-size: 12px; color: #666; margin-bottom: 8px; }
        .btn-back { display: inline-block; margin-top: 22px; padding: 9px 18px; background: #1a7a3c; color: #fff; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="top-bar">
        <h1>🔒 Buscador — Versión Segura</h1>
        <span class="badge-ok">✅ MITIGADO — htmlspecialchars + CSP</span>
    </div>
    <div class="panel">
        <div class="sec-note">
            <strong>✅ Técnica: Output Encoding + Content-Security-Policy</strong>
            Toda salida pasa por <code>htmlspecialchars($q, ENT_QUOTES | ENT_HTML5, 'UTF-8')</code>.
            La cabecera CSP bloquea scripts inline como segunda capa.
        </div>
        <div class="code-block">
            <span class="cy">// ❌ VULNERABLE (buscar.php):</span><br>
            <span class="cr">echo $_GET['q'];  // &lt;script&gt;alert(1)&lt;/script&gt; → EJECUTADO</span><br><br>
            <span class="cy">// ✅ SEGURO (esta página):</span><br>
            <span class="cg">echo htmlspecialchars($_GET['q'], ENT_QUOTES | ENT_HTML5, 'UTF-8');</span><br>
            <span class="cy">// Salida: &amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt; → TEXTO PLANO</span>
        </div>

        <form action="buscar_seguro.php" method="GET" class="search-row">
            <input
                type="text"
                name="q"
                id="q"
                placeholder="Nº de papeleta, placa o trámite..."
                maxlength="100"
                value="<?php
                    // ✅ SEGURO: el value también se codifica
                    if (isset($_GET['q'])) {
                        echo htmlspecialchars($_GET['q'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                ?>">
            <button type="submit">Buscar</button>
        </form>

<?php
if (isset($_GET['q']) && $_GET['q'] !== '') {

    if (strlen($_GET['q']) > 100) {
        echo "<p style='color:#c0392b;font-size:13px;'>Input demasiado largo (máx. 100 caracteres).</p>";
    } else {
        // ──────────────────────────────────────────────────────
        // CODIFICACIÓN DE SALIDA: htmlspecialchars()
        //
        // Input:  <script>alert('XSS-OK')</script>
        // Salida: &lt;script&gt;alert(&#039;XSS-OK&#039;)&lt;/script&gt;
        //         → Mostrado como texto plano. NO ejecutado.
        //
        // ENT_QUOTES  → codifica ' y "
        // ENT_HTML5   → estándar HTML5 completo
        // UTF-8       → charset explícito (evita ataques de encoding)
        // ──────────────────────────────────────────────────────
        $q_seguro = htmlspecialchars($_GET['q'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        echo "<p class='result-label'>Resultado de búsqueda para:</p>";
        echo "<div class='result-box'>" . $q_seguro . "</div>";
    }
} else {
    echo "<p style='color:#888;font-size:13px;'>Ingrese el número de papeleta, placa o trámite.</p>";
}
?>
        <a href="javascript:history.back()" class="btn-back">← Volver</a>
    </div>
</div>
</body>
</html>
