<?php
/**
 * checkout-api.php
 * Endpoints del módulo de checkout.
 *
 * GET  ?action=detalle&reserva=#
 * POST action=confirmar_pago (reserva, nombre, email, telefono, metodo)
 *
 * Requiere conexion.php -> variable mysqli $conexion
 * Requiere sesión activa.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'detalle':
        accion_detalle($conexion);
        break;
    case 'confirmar_pago':
        accion_confirmar_pago($conexion);
        break;
    default:
        responder(false, null, 'Acción no reconocida.', 400);
}

function accion_detalle(mysqli $conexion): void
{
    [$id_usuario] = requerir_sesion();
    $id_reserva = (int) ($_GET['reserva'] ?? 0);

    $reserva = obtener_reserva_del_usuario($conexion, $id_reserva, $id_usuario);
    if (!$reserva) {
        responder(false, null, 'Reserva no encontrada.', 404);
    }

    responder(true, $reserva);
}

function accion_confirmar_pago(mysqli $conexion): void
{
    [$id_usuario] = requerir_sesion();
    $id_reserva = (int) ($_POST['reserva'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responder(false, null, 'Completa tu nombre y un correo válido.', 422);
    }

    $reserva = obtener_reserva_del_usuario($conexion, $id_reserva, $id_usuario);
    if (!$reserva) {
        responder(false, null, 'Reserva no encontrada.', 404);
    }
    if ($reserva['estado'] !== 'pendiente_pago') {
        responder(false, null, 'Esta reserva ya fue procesada anteriormente.', 409);
    }

    // ==================================================================
    // AQUÍ VA LA INTEGRACIÓN DE PAGO REAL (Stripe, Webpay, etc.)
    //
    // Por ahora, esto SIMULA un pago exitoso para que el flujo completo
    // (publicar -> buscar -> reservar -> pagar) funcione de punta a punta.
    // Cuando conectes una pasarela real, este es el lugar donde:
    //   1. Creas el cobro/PaymentIntent con el monto $reserva['total']
    //   2. Esperas la confirmación del proveedor (webhook o respuesta síncrona)
    //   3. Solo si el proveedor confirma el pago, recién ahí actualizas
    //      el estado de la reserva a 'confirmada' (el UPDATE de abajo)
    // ==================================================================
    $pago_simulado_exitoso = true;

    if (!$pago_simulado_exitoso) {
        responder(false, null, 'El pago no pudo ser procesado.', 402);
    }

    $stmt = $conexion->prepare(
        'UPDATE reservas SET estado = "confirmada" WHERE id = ? AND id_usuario = ? AND estado = "pendiente_pago"'
    );
    $stmt->bind_param('ii', $id_reserva, $id_usuario);
    $stmt->execute();
    $afectadas = $stmt->affected_rows;
    $stmt->close();

    if ($afectadas === 0) {
        responder(false, null, 'No se pudo confirmar la reserva.', 500);
    }

    responder(true, ['id_reserva' => $id_reserva], 'Reserva confirmada.');
}

/* ==================== HELPERS ==================== */

function requerir_sesion(): array
{
    if (empty($_SESSION['estado_sesion']) || $_SESSION['estado_sesion'] !== 'activa') {
        responder(false, null, 'Debes iniciar sesión.', 401);
    }
    return [(int) $_SESSION['id_usuario']];
}

function obtener_reserva_del_usuario(mysqli $conexion, int $id_reserva, int $id_usuario): ?array
{
    if ($id_reserva <= 0) return null;

    $stmt = $conexion->prepare(
        'SELECT r.id, r.fecha_llegada, r.fecha_salida, r.huespedes, r.precio_noche, r.noches,
                r.subtotal, r.precio_limpieza, r.cargo_servicio, r.total, r.estado,
                p.titulo,
                (SELECT foto.url FROM propiedades_fotos foto
                 WHERE foto.id_propiedad = p.id ORDER BY foto.orden ASC LIMIT 1) AS foto_portada
         FROM reservas r
         INNER JOIN propiedades p ON p.id = r.id_propiedad
         WHERE r.id = ? AND r.id_usuario = ?
         LIMIT 1'
    );
    $stmt->bind_param('ii', $id_reserva, $id_usuario);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;

    return [
        'id'              => (int) $row['id'],
        'titulo'          => $row['titulo'],
        'foto_portada'    => $row['foto_portada'],
        'fecha_llegada'   => $row['fecha_llegada'],
        'fecha_salida'    => $row['fecha_salida'],
        'huespedes'       => (int) $row['huespedes'],
        'precio_noche'    => (float) $row['precio_noche'],
        'noches'          => (int) $row['noches'],
        'subtotal'        => (float) $row['subtotal'],
        'precio_limpieza' => (float) $row['precio_limpieza'],
        'cargo_servicio'  => (float) $row['cargo_servicio'],
        'total'           => (float) $row['total'],
        'estado'          => $row['estado'],
    ];
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