<?php
/**
 * login-api.php
 * Endpoints del módulo de login / registro.
 *
 * POST action=iniciar_sesion  (email, password)
 * POST action=registrar       (nombre, apellido, email, password)
 *
 * Requiere conexion.php -> variable mysqli $conexion
 * Requiere tabla usuarios con al menos:
 *   id, nombre, apellido, email (UNIQUE), password_hash, es_anfitrion, creado_en
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'iniciar_sesion':
        accion_iniciar_sesion($conexion);
        break;
    case 'registrar':
        accion_registrar($conexion);
        break;
    default:
        responder(false, null, 'Acción no reconocida.', 400);
}

/* ==================== ACCIONES ==================== */

function accion_iniciar_sesion(mysqli $conexion): void
{
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        responder(false, null, 'Ingresa un correo y contraseña válidos.', 422);
    }

    $stmt = $conexion->prepare(
        'SELECT id, nombre, apellido, password_hash FROM usuarios WHERE email = ? LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Mensaje genérico a propósito: no revelamos si el correo existe o no (evita enumeración de usuarios).
    if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
        responder(false, null, 'Correo o contraseña incorrectos.', 401);
    }

    iniciar_sesion_usuario($usuario['id'], $usuario['nombre']);

    responder(true, null, 'Sesión iniciada.');
}

function accion_registrar(mysqli $conexion): void
{
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nombre === '' || $apellido === '') {
        responder(false, null, 'Completa tu nombre y apellido.', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responder(false, null, 'Ingresa un correo válido.', 422);
    }
    if (strlen($password) < 8) {
        responder(false, null, 'La contraseña debe tener al menos 8 caracteres.', 422);
    }

    $stmt = $conexion->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $existe = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existe) {
        responder(false, null, 'Ya existe una cuenta con ese correo.', 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conexion->prepare(
        'INSERT INTO usuarios (nombre, apellido, email, password_hash, es_anfitrion, creado_en)
         VALUES (?, ?, ?, ?, 0, NOW())'
    );
    $stmt->bind_param('ssss', $nombre, $apellido, $email, $hash);

    if (!$stmt->execute()) {
        $stmt->close();
        responder(false, null, 'No pudimos crear tu cuenta. Intenta nuevamente.', 500);
    }

    $id_usuario = $stmt->insert_id;
    $stmt->close();

    iniciar_sesion_usuario($id_usuario, $nombre);

    responder(true, null, 'Cuenta creada.');
}

/* ==================== HELPERS ==================== */

function iniciar_sesion_usuario(int $id_usuario, string $nombre): void
{
    // Regenerar el ID de sesión al autenticar: evita ataques de fijación de sesión.
    session_regenerate_id(true);

    $_SESSION['estado_sesion'] = 'activa';
    $_SESSION['id_usuario'] = $id_usuario;
    $_SESSION['nombre_usuario'] = $nombre;
}

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