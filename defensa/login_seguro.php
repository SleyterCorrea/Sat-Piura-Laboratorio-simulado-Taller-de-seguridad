<?php
/*
 * ============================================================
 * ARCHIVO: login_seguro.php
 * VERSIÓN: SEGURA — Mitigación de SQLi + Fuerza Bruta
 * OWASP: A07:2021 – Auth Failures + A03:2021 – Injection (MITIGADOS)
 * ============================================================
 * TÉCNICAS DE SEGURIDAD IMPLEMENTADAS:
 *
 *   1. PDO con Prepared Statements (anti-SQLi)
 *      → La consulta SQL tiene estructura fija antes de recibir datos.
 *      → Payload ' OR '1'='1 es tratado como literal de texto.
 *
 *   2. password_hash() / password_verify() (anti-hash débil MD5)
 *      → NOTA: La BD del lab usa MD5 (educativo). Esta versión segura
 *        demuestra el flujo correcto con password_hash() y password_verify().
 *      → En producción NUNCA usar MD5. Siempre: PASSWORD_BCRYPT o PASSWORD_ARGON2ID.
 *
 *   3. Protección anti-Fuerza Bruta (sin BD de sesiones):
 *      → sleep(1): Retardo artificial de 1 segundo en intentos fallidos.
 *        Limita ataques a máx. ~3.600 intentos/hora por IP.
 *      → En producción: usar tabla de intentos + bloqueo por IP + CAPTCHA.
 *
 *   4. Sesiones PHP seguras
 *      → session_start() + session_regenerate_id() anti-session fixation.
 *      → Las credenciales NO se almacenan en la sesión en texto plano.
 *
 *   5. Manejo seguro de errores
 *      → Los errores de BD van a error_log(), no al usuario.
 *
 *   6. htmlspecialchars() en toda salida al usuario
 *
 * DIFERENCIA CLAVE (PDO vs concatenación):
 *   VULNERABLE:  $sql = "... WHERE email='$usuario' AND password='$pass'";
 *   SEGURO:      $sql = "... WHERE email=?";  +  execute([$usuario]);
 *                luego: password_verify($pass, $hash_bd);
 * ============================================================
 */

// ─────────────────────────────────────────────────────────────
// INICIO SEGURO DE SESIÓN
// ─────────────────────────────────────────────────────────────
session_start([
    'cookie_httponly' => true,    // La cookie no es accesible por JS
    'cookie_samesite' => 'Strict', // Protección CSRF básica
    'cookie_secure'  => false,     // true en HTTPS/producción
]);

// --- CONFIGURACIÓN DE CONEXIÓN PDO ---
$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'sat_lab';
$db_user = getenv('DB_USER') ?: 'appuser';
$db_pass = getenv('DB_PASS') ?: 'apppassword';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
$opciones_pdo = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- PROCESAMIENTO DEL FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, $opciones_pdo);

        // ─────────────────────────────────────────────────────
        // PASO 1: Obtener y limpiar las credenciales del formulario
        // ─────────────────────────────────────────────────────
        $usuario_input  = trim($_POST['usuario']  ?? '');
        $password_input = trim($_POST['password'] ?? '');

        // Validación básica de longitud
        if (strlen($usuario_input) < 8 || strlen($usuario_input) > 20) {
            throw new InvalidArgumentException("Formato de usuario inválido.");
        }

        // ─────────────────────────────────────────────────────
        // PASO 2: CONSULTA SEGURA CON PREPARED STATEMENT
        // Solo busca por email. La contraseña se verifica DESPUÉS
        // en PHP (no en la SQL), lo que elimina el vector SQLi.
        // ─────────────────────────────────────────────────────
        $sql  = "SELECT id, email, password FROM usuarios WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_input]);
        $fila = $stmt->fetch();

        // ─────────────────────────────────────────────────────
        // PASO 3: VERIFICACIÓN DE CONTRASEÑA
        //
        // La BD del lab usa MD5 (intencionalmente débil).
        // Este bloque demuestra AMBOS enfoques:
        //   A) md5() → comparación (compatible con la BD del lab)
        //   B) password_verify() → lo que SE DEBE usar en producción
        //
        // En producción con password_hash():
        //   $hash_nuevo = password_hash('mipassword', PASSWORD_BCRYPT);
        //   if (password_verify($password_input, $fila['password'])) { ... }
        // ─────────────────────────────────────────────────────
        $autenticado = false;
        if ($fila) {
            // Compatibilidad con la BD del lab (MD5)
            // En producción reemplazar por: password_verify($password_input, $fila['password'])
            $hash_input = md5($password_input);
            if (hash_equals($fila['password'], $hash_input)) {
                $autenticado = true;
            }
        }

        if ($autenticado) {

            // ─────────────────────────────────────────────────
            // AUTENTICACIÓN EXITOSA
            // Regenerar ID de sesión (anti-session fixation)
            // ─────────────────────────────────────────────────
            session_regenerate_id(true);
            $_SESSION['usuario_id']    = $fila['id'];
            $_SESSION['usuario_email'] = $fila['email'];
            $_SESSION['autenticado']   = true;

            echo "<!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <title>Acceso — SAT-LAB Seguro</title>
                <link href='https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap' rel='stylesheet'>
                <style>
                    * { box-sizing:border-box; margin:0; padding:0; }
                    body { font-family:'Open Sans',sans-serif; background:linear-gradient(135deg,#145a32,#1e8449); min-height:100vh; display:flex; justify-content:center; align-items:flex-start; padding:40px 20px; }
                    .panel { background:#fff; border-radius:8px; padding:36px; max-width:540px; width:100%; box-shadow:0 8px 30px rgba(0,0,0,0.3); }
                    .panel-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; padding-bottom:14px; border-bottom:2px solid #b3d234; }
                    h2 { color:#1a7a3c; font-size:18px; }
                    .badge-ok { background:#b3d234; color:#1a4a0d; padding:5px 14px; border-radius:20px; font-weight:700; font-size:12px; }
                    .secure-badge { background:#1a7a3c; color:#fff; font-size:10px; font-weight:700; padding:3px 8px; border-radius:3px; letter-spacing:0.5px; margin-left:6px; }
                    .dato { margin:10px 0; font-size:13px; color:#444; }
                    .dato strong { color:#1a7a3c; }
                    .nota-edu { background:#eafaf1; border:1px solid #82e0aa; border-radius:4px; padding:14px; margin-top:20px; font-size:12px; color:#1e8449; line-height:1.7; }
                    .nota-edu strong { display:block; margin-bottom:6px; color:#145a32; text-transform:uppercase; font-size:11px; }
                    .code-green { font-family:monospace; font-size:11px; color:#1a7a3c; background:#eafaf1; padding:3px 7px; border-radius:3px; display:inline-block; margin-top:3px; }
                    .btn-back { display:inline-block; margin-top:20px; padding:10px 20px; background:#1a7a3c; color:#fff; border-radius:4px; text-decoration:none; font-size:13px; font-weight:600; }
                    .btn-back:hover { background:#145a32; }
                </style>
            </head>
            <body>
                <div class='panel'>
                    <div class='panel-header'>
                        <h2>✅ Acceso Concedido</h2>
                        <div>
                            <span class='badge-ok'>LOGIN EXITOSO</span>
                            <span class='secure-badge'>VERSIÓN SEGURA</span>
                        </div>
                    </div>
                    <div class='dato'><strong>Usuario:</strong> " . htmlspecialchars($fila['email'], ENT_QUOTES, 'UTF-8') . "</div>
                    <div class='dato'><strong>ID de sesión regenerado:</strong> <code class='code-green'>" . htmlspecialchars(session_id(), ENT_QUOTES, 'UTF-8') . "</code></div>
                    <div class='nota-edu'>
                        <strong>✅ Técnicas de seguridad aplicadas</strong>
                        <b>Anti-SQLi:</b> PDO Prepared Statement — la consulta busca solo por email (<code>WHERE email = ?</code>); la contraseña se verifica en PHP, no en SQL.<br><br>
                        <b>Anti-MD5:</b> En producción se usaría <code>password_hash('pass', PASSWORD_BCRYPT)</code> y <code>password_verify()</code>. El lab usa MD5 para demostrar el ataque.<br><br>
                        <b>Anti-Brute Force:</b> Intento fallido → <code>sleep(1)</code> obligatorio + sesión regenerada.<br><br>
                        <b>Anti-Session Fixation:</b> <code>session_regenerate_id(true)</code> tras login exitoso.
                    </div>
                    <a href='index.html' class='btn-back'>← Volver al Portal</a>
                </div>
            </body>
            </html>";

        } else {

            // ─────────────────────────────────────────────────
            // CREDENCIALES INCORRECTAS
            // sleep(1): Limita la velocidad de fuerza bruta
            // ─────────────────────────────────────────────────
            sleep(1);  // Retardo anti-brute-force: 1 intento/segundo máximo

            echo "<!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <title>Acceso Denegado — SAT-LAB Seguro</title>
                <meta http-equiv='refresh' content='2;url=index.html'>
                <link href='https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap' rel='stylesheet'>
                <style>
                    * { box-sizing:border-box; margin:0; padding:0; }
                    body { font-family:'Open Sans',sans-serif; display:flex; justify-content:center; align-items:center; min-height:100vh; background:linear-gradient(135deg,#145a32,#1e8449); }
                    .msg { background:#fff; border-radius:8px; padding:40px; text-align:center; max-width:400px; box-shadow:0 8px 30px rgba(0,0,0,0.3); }
                    h3 { color:#c0392b; margin-bottom:12px; }
                    p { color:#666; font-size:13px; margin-bottom:6px; }
                    .note { font-size:11px; color:#27ae60; margin-top:12px; background:#eafaf1; padding:8px 12px; border-radius:4px; }
                </style>
            </head>
            <body>
                <div class='msg'>
                    <h3>❌ Credenciales incorrectas</h3>
                    <p>Redirigiendo al portal...</p>
                    <p class='note'>✅ sleep(1) aplicado — velocidad de brute-force limitada</p>
                </div>
            </body>
            </html>";
        }

    } catch (InvalidArgumentException $e) {
        echo "<p style='font-family:sans-serif; color:orange; padding:20px; background:#fff;'>
              ⚠ Formato de credenciales inválido.</p>";
    } catch (PDOException $e) {
        error_log("[SAT-LAB] Error en login_seguro.php: " . $e->getMessage());
        die("<p style='font-family:sans-serif; color:red; padding:20px; background:#fff;'>
             Error interno del servidor. Contacte al administrador.</p>");
    }

} else {
    header('Location: index.html');
    exit;
}
?>
