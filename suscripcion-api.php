<?php
/**
 * suscripcion-api.php
 * Inicia el pago de un plan con Mercado Pago (Checkout Pro).
 *
 * POST action=crear_pago (plan = basic|smart|pro, periodicidad = mensual|anual)
 *   -> { success, data: { url_pago } }  el navegador redirige a url_pago
 *
 * Requiere sesión activa, config.php con credenciales de Mercado Pago y las
 * tablas de sql/01, 02 y 04.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mercadopago.php';
require_once __DIR__ . '/suscripciones.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'crear_pago':
        accion_crear_pago($conexion);
        break;
    default:
        responder(false, null, 'Acción no reconocida.', 400);
}

function accion_crear_pago(mysqli $conexion): void
{
    if (empty($_SESSION['estado_sesion']) || $_SESSION['estado_sesion'] !== 'activa') {
        responder(false, null, 'Debes iniciar sesión para contratar un plan.', 401);
    }
    if (!mp_configurado()) {
        responder(false, null, 'Los pagos aún no están habilitados. Escríbenos a hola@rentplace.cl.', 503);
    }

    $plan = (string) ($_POST['plan'] ?? '');
    $periodicidad = (string) ($_POST['periodicidad'] ?? '');
    $monto = suscripcion_monto($plan, $periodicidad);
    if ($monto === null) {
        responder(false, null, 'Plan no válido.', 422);
    }

    $id_plan = suscripcion_id_plan($conexion, $plan);
    $id_anfitrion = $id_plan ? suscripcion_id_anfitrion($conexion, (int) $_SESSION['id_usuario']) : null;
    if (!$id_plan || !$id_anfitrion) {
        responder(false, null, 'No pudimos preparar tu suscripción. Intenta nuevamente.', 500);
    }

    $stmt = $conexion->prepare('SELECT email FROM usuarios WHERE id = ?');
    $id_usuario = (int) $_SESSION['id_usuario'];
    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();
    $email = (string) ($stmt->get_result()->fetch_assoc()['email'] ?? '');
    $stmt->close();

    $referencia = 'RP-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
    $ambiente = mp_ambiente();

    $stmt = $conexion->prepare(
        'INSERT INTO pagos_suscripcion (referencia, id_anfitrion, id_plan, periodicidad, monto, ambiente, estado, creado_en)
         VALUES (?, ?, ?, ?, ?, ?, "pendiente", NOW())'
    );
    $stmt->bind_param('siisis', $referencia, $id_anfitrion, $id_plan, $periodicidad, $monto, $ambiente);
    if (!$stmt->execute()) {
        $stmt->close();
        responder(false, null, 'No pudimos registrar el pago. Intenta nuevamente.', 500);
    }
    $stmt->close();

    $nombrePlan = TARIFA_PLANES[$plan]['nombre'];
    $preferencia = mp_crear_preferencia([
        'titulo'        => "Rentplace plan $nombrePlan (" . ($periodicidad === 'anual' ? '12 meses' : '1 mes') . ')',
        'monto'         => $monto,
        'referencia'    => $referencia,
        'email_pagador' => $email,
        'url_retorno'   => URL_SITIO . '/suscripcion_retorno.php',
        'url_webhook'   => URL_SITIO . '/mp_webhook.php',
    ]);

    if (!$preferencia) {
        responder(false, null, 'Mercado Pago no respondió. Intenta nuevamente en unos minutos.', 502);
    }

    $stmt = $conexion->prepare('UPDATE pagos_suscripcion SET mp_preference_id = ? WHERE referencia = ?');
    $stmt->bind_param('ss', $preferencia['id'], $referencia);
    $stmt->execute();
    $stmt->close();

    responder(true, ['url_pago' => $preferencia['url_pago']]);
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
