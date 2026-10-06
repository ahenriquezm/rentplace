<?php
/**
 * logout.php
 * Cierra la sesión activa y redirige al inicio.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
session_destroy();

header('Location: index.php');
exit;