<?php
/**
 * conexion.php
 * Conexión única a la base de datos, usada por todos los módulos (*-api.php).
 *
 * IMPORTANTE: reemplaza los 4 valores de abajo por los datos reales de tu
 * base de datos en cPanel (sección "Bases de datos MySQL®").
 *
 * En hosting cPanel, normalmente:
 *   - El nombre de la base y del usuario vienen con el prefijo de tu cuenta,
 *     ej. si tu usuario cPanel es "tuducl", la base suele llamarse algo como
 *     "tuducl_rentplace" y el usuario de BD "tuducl_rentplace_user".
 *   - El host casi siempre es "localhost".
 *
 * NO subas este archivo con las credenciales reales a un repositorio público.
 */

define('DB_HOST', 'localhost');
define('DB_USUARIO', 'tuducl_admChekeadosDb');   // <-- reemplaza por tu usuario real de BD
define('DB_PASSWORD', 'useradmin9876');    // <-- reemplaza por tu contraseña real de BD
define('DB_NOMBRE', 'tuducl_rentplaceDB');  // <-- reemplaza por el nombre real de tu base de datos



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