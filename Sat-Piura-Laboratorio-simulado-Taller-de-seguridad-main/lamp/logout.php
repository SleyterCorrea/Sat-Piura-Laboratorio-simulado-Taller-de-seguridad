<?php
/*
 * logout.php — Cierra la sesión y redirige al login
 */
session_start();
session_unset();
session_destroy();
header('Location: index.html');
exit;
?>
