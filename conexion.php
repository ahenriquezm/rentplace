<?php
/**
 * conexion.php
 * Conexión única a la base de datos, usada por todos los módulos (*-api.php).
 *
 * IMPORTANTE: copia config.example.php como config.php en el servidor y pon
 * ahí los datos reales de tu base de datos en cPanel (sección "Bases de datos MySQL®").
 *
 * En hosting cPanel, normalmente:
 *   - El nombre de la base y del usuario vienen con el prefijo de tu cuenta,
 *     ej. si tu usuario cPanel es "tuducl", la base suele llamarse algo como
 *     "tuducl_rentplace" y el usuario de BD "tuducl_rentplace_user".
 *   - El host casi siempre es "localhost".
 *
 * config.php está en .gitignore: nunca se sube al repositorio.
 */

// Las credenciales NO se guardan en el repositorio. Se leen, en este orden, de:
//   1. config.php (junto a este archivo, ignorado por git; ver config.example.php)
//   2. Variables de entorno RENTPLACE_DB_HOST / _USUARIO / _PASSWORD / _NOMBRE
if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
defined('DB_HOST')     || define('DB_HOST', getenv('RENTPLACE_DB_HOST') ?: 'localhost');
defined('DB_USUARIO')  || define('DB_USUARIO', getenv('RENTPLACE_DB_USUARIO') ?: '');
defined('DB_PASSWORD') || define('DB_PASSWORD', getenv('RENTPLACE_DB_PASSWORD') ?: '');
defined('DB_NOMBRE')   || define('DB_NOMBRE', getenv('RENTPLACE_DB_NOMBRE') ?: '');

mysqli_report(MYSQLI_REPORT_OFF); // manejamos los errores nosotros mismos, sin exponer detalles internos

$conexion = @mysqli_connect(DB_HOST, DB_USUARIO, DB_PASSWORD, DB_NOMBRE);

if (!$conexion) {
    http_response_code(500);
    // En producción no mostramos el detalle técnico del error al usuario final.
    // Mientras depuras, puedes descomentar la siguiente línea para ver el motivo real:
    // die('Error de conexión: ' . mysqli_connect_error());
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'data'    => null,
        'message' => 'No se pudo conectar a la base de datos.',
    ]);
    exit;
}

mysqli_set_charset($conexion, 'utf8mb4');