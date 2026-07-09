<?php
/*
 * ============================================================
 * ARCHIVO: web/defensa/login_seguro.php
 * VERSIÓN: SEGURA — Anti-SQLi (PDO) + Anti-Fuerza Bruta
 * ============================================================
 * TÉCNICAS:
 *   1. PDO Prepared Statement → Elimina SQLi
 *   2. password_verify()      → Verificación segura de hash
 *   3. sleep(1) en fallo      → Limita brute-force a 1 req/s
 *   4. session_regenerate_id() → Anti-session fixation
 *   5. hash_equals()           → Anti-timing attack
 *
 * NOTA SOBRE LA BD DEL LAB:
 *   La BD usa MD5 (intencionalmente débil). Este script
 *   demuestra el flujo correcto con password_hash/verify.
 *   Usa MD5 para compatibilidad con los datos del lab, pero
 *   documenta el reemplazo correcto para producción.
 * ============================================================
 */

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'  => false,   // true en HTTPS real
]);

$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'sat_lab';
$db_user = getenv('DB_USER') ?: 'appuser';
$db_pass = getenv('DB_PASS') ?: 'apppassword';

$dsn  = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
$opts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, $opts);

        $usuario_input  = trim($_POST['usuario']  ?? '');
        $password_input = trim($_POST['password'] ?? '');

        // Validación básica de longitud antes de tocar la BD
        if (strlen($usuario_input) < 6 || strlen($usuario_input) > 20) {
            sleep(1);
            header('Location: ../index.html?error=formato');
            exit;
        }

        // ─── PREPARED STATEMENT: busca solo por email ──────────────
        // La contraseña se verifica en PHP, no en la SQL.
        // Esto elimina COMPLETAMENTE el vector SQLi de login.
        $sql  = "SELECT id, email, password FROM usuarios WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_input]);
        $fila = $stmt->fetch();

        $autenticado = false;
        if ($fila) {
            // ─── VERIFICACIÓN DE CONTRASEÑA ────────────────────────
            // La BD del lab usa MD5. En producción usar:
            //   password_hash('contraseña', PASSWORD_BCRYPT)  ← al guardar
            //   password_verify($input, $hash_bd)             ← al verificar
            //
            // Para compatibilidad con la BD del lab:
            $hash_input = md5($password_input);
            if (hash_equals($fila['password'], $hash_input)) {
                $autenticado = true;
            }
        }

        if ($autenticado) {
            // ─── LOGIN EXITOSO ─────────────────────────────────────
            session_regenerate_id(true);  // Anti-session fixation
            $_SESSION['autenticado']   = true;
            $_SESSION['usuario_email'] = $fila['email'];
            $_SESSION['usuario_id']    = $fila['id'];

            header('Location: dashboard_seguro.php');
            exit;

        } else {
            // ─── FALLO: sleep(1) anti-brute-force ──────────────────
            // Limita la velocidad máxima a ~1 intento/segundo.
            // En producción: registrar IP + bloquear tras N intentos.
            sleep(1);
            header('Location: ../index.html?error=credenciales');
            exit;
        }

    } catch (PDOException $e) {
        error_log("[SAT-LAB] login_seguro.php: " . $e->getMessage());
        sleep(1);
        die("<p style='font-family:sans-serif;color:red;padding:20px;'>
             Error interno del servidor.</p>");
    }

} else {
    header('Location: ../index.html');
    exit;
}
?>
