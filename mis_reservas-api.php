<?php
/**
 * mis_reservas-api.php
 * Endpoints del módulo de historial de reservas.
 *
 * GET  ?action=listar&filtro=proximas|pasadas|canceladas
 * POST action=cancelar (reserva)
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
    case 'listar':
        accion_listar($conexion);
        break;
    case 'cancelar':
        accion_cancelar($conexion);
        break;
    default:
        responder(false, null, 'Acción no reconocida.', 400);
}

function accion_listar(mysqli $conexion): void
{
    $id_usuario = requerir_sesion();
    $filtro = $_GET['filtro'] ?? 'proximas';

    $condicion = '';
    switch ($filtro) {
        case 'pasadas':
            $condicion = "AND r.estado = 'confirmada' AND r.fecha_salida < CURDATE()";
            break;
        case 'canceladas':
            $condicion = "AND r.estado = 'cancelada'";
            break;
        case 'proximas':
        default:
            $condicion = "AND r.estado IN ('pendiente_pago','confirmada') AND r.fecha_salida >= CURDATE()";
            break;
    }

    $sql = "SELECT r.id, r.id_propiedad, r.fecha_llegada, r.fecha_salida, r.huespedes, r.total, r.estado,
                   p.titulo, p.ciudad, p.region,
                   (SELECT foto.url FROM propiedades_fotos foto
                    WHERE foto.id_propiedad = p.id ORDER BY foto.orden ASC LIMIT 1) AS foto_portada
            FROM reservas r
            INNER JOIN propiedades p ON p.id = r.id_propiedad
            WHERE r.id_usuario = ? $condicion
            ORDER BY r.fecha_llegada ASC";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();
    $res = $stmt->get_result();

    $reservas = [];
    while ($row = $res->fetch_assoc()) {
        $reservas[] = [
            'id'            => (int) $row['id'],
            'id_propiedad'  => (int) $row['id_propiedad'],
            'titulo'        => $row['titulo'],
            'ciudad'        => $row['ciudad'],
            'region'        => $row['region'],
            'foto_portada'  => $row['foto_portada'],
            'fecha_llegada' => $row['fecha_llegada'],
            'fecha_salida'  => $row['fecha_salida'],
            'huespedes'     => (int) $row['huespedes'],
            'total'         => (float) $row['total'],
            'estado'        => $row['estado'],
        ];
    }
    $stmt->close();

    responder(true, $reservas);
}

function accion_cancelar(mysqli $conexion): void
{
    $id_usuario = requerir_sesion();
    $id_reserva = (int) ($_POST['reserva'] ?? 0);

    if ($id_reserva <= 0) {
        responder(false, null, 'Reserva inválida.', 422);
    }

    $stmt = $conexion->prepare(
        "UPDATE reservas
         SET estado = 'cancelada'
         WHERE id = ? AND id_usuario = ? AND estado IN ('pendiente_pago','confirmada')"
    );
    $stmt->bind_param('ii', $id_reserva, $id_usuario);
    $stmt->execute();
    $afectadas = $stmt->affected_rows;
    $stmt->close();

    if ($afectadas === 0) {
        responder(false, null, 'No se pudo cancelar esta reserva.', 409);
    }

    responder(true, null, 'Reserva cancelada.');
}

/* ==================== HELPERS ==================== */

function requerir_sesion(): int
{
    if (empty($_SESSION['estado_sesion']) || $_SESSION['estado_sesion'] !== 'activa') {
        responder(false, null, 'Debes iniciar sesión.', 401);
    }
    return (int) $_SESSION['id_usuario'];
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