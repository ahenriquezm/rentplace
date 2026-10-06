<?php
/**
 * comingsoon-api.php
 * Endpoints de la landing de pre-lanzamiento.
 *
 * Acciones soportadas:
 *   POST action=suscribir   (email)
 *   GET  action=contador
 *
 * Requiere conexion.php -> variable mysqli $conexion
 * Requiere tabla:
 *   CREATE TABLE suscriptores (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     email VARCHAR(190) NOT NULL,
 *     ip VARCHAR(45) NULL,
 *     origen VARCHAR(50) NULL,
 *     creado_en DATETIME NOT NULL,
 *     UNIQUE KEY uq_email (email)
 *   );
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'suscribir':
        accion_suscribir($conexion);
        break;
    case 'contador':
        accion_contador($conexion);
        break;
    default:
        responder(false, null, 'Acción no reconocida.', 400);
}

/* ==================== ACCIONES ==================== */

function accion_suscribir(mysqli $conexion): void
{
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responder(false, null, 'Ingresa un correo válido.', 422);
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $origen = 'comingsoon';

    $stmt = $conexion->prepare(
        'INSERT INTO suscriptores (email, ip, origen, creado_en) VALUES (?, ?, ?, NOW())'
    );
    $stmt->bind_param('sss', $email, $ip, $origen);

    if (!$stmt->execute()) {
        // Código 1062 = entrada duplicada (unique key en email) -> no es un error real para el usuario
        if ($conexion->errno === 1062) {
            $stmt->close();
            responder(true, null, 'Ya estabas suscrito. ¡Gracias por el interés!');
        }
        $stmt->close();
        responder(false, null, 'No pudimos registrar tu correo. Intenta nuevamente.', 500);
    }

    $stmt->close();
    responder(true, null, 'Suscripción registrada.');
}

function accion_contador(mysqli $conexion): void
{
    $res = $conexion->query('SELECT COUNT(*) AS total FROM suscriptores');
    $row = $res->fetch_assoc();

    responder(true, ['total' => (int) $row['total']]);
}

/* ==================== HELPERS ==================== */

function responder(bool $success, $data, string $message = '', int $http_code = 200): void
{
    http_response_code($http_code);
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}