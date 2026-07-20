<?php
ob_start(); // Buffer output — previene "headers already sent" (CWE fix)
/*
 * ============================================================
 * ARCHIVO: web/login.php
 * VECTOR:  V3 — SQLi en Autenticación + Fuerza Bruta
 * OWASP:   A07:2021 + A03:2021
 * ============================================================
 * VULNERABILIDADES:
 *   1. Concatenación directa de credenciales en SQL (CWE-89)
 *      Bypass: usuario = ' OR '1'='1' -- -
 *   2. Sin límite de intentos (CWE-307) — Brute Force
 *   3. Sin CAPTCHA, sin retardo, sin bloqueo de IP
 *   4. Error SQL expuesto al usuario
 *
 * USUARIOS VÁLIDOS (de database.sql):
 *   72345678 / 123456    →  hash md5: e10adc39...
 *   45678912 / admin     →  hash md5: 21232f29...
 *   31290456 / piura2026 →  hash md5: a0b9aab5...
 *
 * ¡SOLO PARA USO EDUCATIVO EN ENTORNO CONTROLADO!
 * ============================================================
 */

session_start();

// --- Conexión a la BD ---
$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'sat_lab';
$db_user = getenv('DB_USER') ?: 'appuser';
$db_pass = getenv('DB_PASS') ?: 'apppassword';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("<p style='color:red;font-family:sans-serif;padding:20px;'>
         Error de conexión: " . mysqli_connect_error() . "</p>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ═══════════════════════════════════════════════════════════
    // ERROR 1: Variables sin sanitización
    // ═══════════════════════════════════════════════════════════
    $usuario  = $_POST['usuario'];    // SIN trim(), SIN escape
    $password = $_POST['password'];   // SIN trim(), SIN escape

    // ═══════════════════════════════════════════════════════════
    // ERROR 2: Concatenación directa en la consulta SQL
    //
    // Consulta normal (credenciales correctas):
    //   ... WHERE email='72345678' AND password='e10adc...'
    //
    // Con payload SQLi (' OR '1'='1' -- -):
    //   ... WHERE email='' OR '1'='1' -- -' AND password='x'
    //   → La condición AND password queda comentada
    //   → '1'='1' siempre es TRUE
    //   → Devuelve el primer registro de la tabla (acceso concedido)
    // ═══════════════════════════════════════════════════════════
    $sql = "SELECT id, email, password
            FROM   usuarios
            WHERE  email    = '$usuario'
            AND    password = MD5('$password')";

    // ERROR 3: Sin sleep(), sin CAPTCHA, sin contador de intentos
    // Un atacante puede hacer miles de intentos por segundo
    $resultado = mysqli_query($conn, $sql);

    if ($resultado && mysqli_num_rows($resultado) > 0) {

        // ─── AUTENTICACIÓN EXITOSA ───────────────────────────
        $fila = mysqli_fetch_assoc($resultado);

        // Inicializar sesión (básica, sin regeneración de ID)
        $_SESSION['autenticado']   = true;
        $_SESSION['usuario_email'] = $fila['email'];
        $_SESSION['usuario_id']    = $fila['id'];

        // Redirigir al dashboard privado
        header('Location: dashboard.php');
        exit;

    } else {

        // ─── CREDENCIALES INCORRECTAS ────────────────────────
        // Sin retardo — permite fuerza bruta a máxima velocidad
        header('Location: oficina.html?error=1');
        exit;
    }

} else {
    // Acceso directo sin POST → redirigir al login
    header('Location: oficina.html');
    exit;
}

mysqli_close($conn);
?>
