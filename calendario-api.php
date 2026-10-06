<?php
/**
 * calendario-api.php
 * Endpoints del módulo de calendario y precios por fecha.
 *
 * GET  ?action=propiedad&id_propiedad=#
 * GET  ?action=mes&id_propiedad=#&anio=####&mes=1-12
 * POST action=guardar (id_propiedad, fechas=JSON array, disponible, precio_personalizado, restaurar)
 *
 * Requiere conexion.php -> variable mysqli $conexion
 * Requiere sesión activa, y que la propiedad pertenezca al anfitrión en sesión.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'propiedad':
        accion_propiedad($conexion);
        break;
    case 'mes':
        accion_mes($conexion);
        break;
    case 'guardar':
        accion_guardar($conexion);
        break;
    default:
        responder(false, null, 'Acción no reconocida.', 400);
}

function accion_propiedad(mysqli $conexion): void
{
    $id_anfitrion = requerir_sesion();
    $id_propiedad = (int) ($_GET['id_propiedad'] ?? 0);

    $propiedad = obtener_propiedad_del_anfitrion($conexion, $id_propiedad, $id_anfitrion);
    if (!$propiedad) {
        responder(false, null, 'Propiedad no encontrada.', 404);
    }

    responder(true, $propiedad);
}

function accion_mes(mysqli $conexion): void
{
    $id_anfitrion = requerir_sesion();
    $id_propiedad = (int) ($_GET['id_propiedad'] ?? 0);
    $anio = (int) ($_GET['anio'] ?? 0);
    $mes = (int) ($_GET['mes'] ?? 0);

    $propiedad = obtener_propiedad_del_anfitrion($conexion, $id_propiedad, $id_anfitrion);
    if (!$propiedad || $anio < 2000 || $mes < 1 || $mes > 12) {
        responder(false, null, 'Parámetros inválidos.', 422);
    }

    $primerDia = sprintf('%04d-%02d-01', $anio, $mes);
    $totalDias = (int) date('t', strtotime($primerDia));
    $ultimoDia = sprintf('%04d-%02d-%02d', $anio, $mes, $totalDias);

    // ---- Fechas reservadas (pendiente_pago o confirmada) que caen dentro del mes ----
    $reservadas = [];
    $stmt = $conexion->prepare(
        "SELECT fecha_llegada, fecha_salida FROM reservas
         WHERE id_propiedad = ? AND estado IN ('pendiente_pago','confirmada')
           AND fecha_llegada <= ? AND fecha_salida >= ?"
    );
    $stmt->bind_param('iss', $id_propiedad, $ultimoDia, $primerDia);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $cursor = strtotime($row['fecha_llegada']);
        $fin = strtotime($row['fecha_salida']); // la noche de salida no se ocupa
        while ($cursor < $fin) {
            $reservadas[date('Y-m-d', $cursor)] = true;
            $cursor = strtotime('+1 day', $cursor);
        }
    }
    $stmt->close();

    // ---- Overrides de disponibilidad / precio para el mes ----
    $overrides = [];
    $stmt = $conexion->prepare(
        'SELECT fecha, disponible, precio_personalizado FROM calendario_propiedad
         WHERE id_propiedad = ? AND fecha BETWEEN ? AND ?'
    );
    $stmt->bind_param('iss', $id_propiedad, $primerDia, $ultimoDia);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $overrides[$row['fecha']] = $row;
    }
    $stmt->close();

    // ---- Armar respuesta día por día ----
    $dias = [];
    for ($d = 1; $d <= $totalDias; $d++) {
        $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
        $override = $overrides[$fecha] ?? null;

        $dias[] = [
            'fecha'           => $fecha,
            'precio'          => $override && $override['precio_personalizado'] !== null
                                    ? (float) $override['precio_personalizado']
                                    : (float) $propiedad['precio_noche'],
            'disponible'      => $override ? (int) $override['disponible'] : 1,
            'es_personalizado'=> $override && $override['precio_personalizado'] !== null,
            'reservado'       => isset($reservadas[$fecha]),
        ];
    }

    responder(true, $dias);
}

function accion_guardar(mysqli $conexion): void
{
    $id_anfitrion = requerir_sesion();
    $id_propiedad = (int) ($_POST['id_propiedad'] ?? 0);

    $propiedad = obtener_propiedad_del_anfitrion($conexion, $id_propiedad, $id_anfitrion);
    if (!$propiedad) {
        responder(false, null, 'Propiedad no encontrada.', 404);
    }

    $fechas = json_decode($_POST['fechas'] ?? '[]', true);
    if (!is_array($fechas) || empty($fechas)) {
        responder(false, null, 'No se seleccionaron fechas.', 422);
    }

    // Nunca se modifican fechas que ya tienen una reserva activa: se recalculan server-side,
    // ignorando lo que el cliente haya podido seleccionar por error de sincronización.
    $fechasValidas = array_filter($fechas, function ($f) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && strtotime($f) >= strtotime(date('Y-m-d'));
    });
    if (empty($fechasValidas)) {
        responder(false, null, 'Las fechas seleccionadas ya no son válidas.', 422);
    }

    $restaurar = !empty($_POST['restaurar']);

    if ($restaurar) {
        $placeholders = implode(',', array_fill(0, count($fechasValidas), '?'));
        $tipos = 'i' . str_repeat('s', count($fechasValidas));
        $params = array_merge([$id_propiedad], array_values($fechasValidas));

        $stmt = $conexion->prepare("DELETE FROM calendario_propiedad WHERE id_propiedad = ? AND fecha IN ($placeholders)");
        $stmt->bind_param($tipos, ...$params);
        $stmt->execute();
        $stmt->close();

        responder(true, null, 'Fechas restauradas al valor por defecto.');
    }

    $disponible = isset($_POST['disponible']) ? (int) $_POST['disponible'] : 1;
    $precio = isset($_POST['precio_personalizado']) && $_POST['precio_personalizado'] !== ''
        ? (float) $_POST['precio_personalizado']
        : null;

    $stmt = $conexion->prepare(
        'INSERT INTO calendario_propiedad (id_propiedad, fecha, disponible, precio_personalizado)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE disponible = VALUES(disponible), precio_personalizado = VALUES(precio_personalizado)'
    );

    foreach ($fechasValidas as $fecha) {
        $stmt->bind_param('isid', $id_propiedad, $fecha, $disponible, $precio);
        $stmt->execute();
    }
    $stmt->close();

    responder(true, null, 'Cambios guardados.');
}

/* ==================== HELPERS ==================== */

function requerir_sesion(): int
{
    if (empty($_SESSION['estado_sesion']) || $_SESSION['estado_sesion'] !== 'activa') {
        responder(false, null, 'Debes iniciar sesión.', 401);
    }
    return (int) $_SESSION['id_usuario'];
}

function obtener_propiedad_del_anfitrion(mysqli $conexion, int $id_propiedad, int $id_anfitrion): ?array
{
    if ($id_propiedad <= 0) return null;
    $stmt = $conexion->prepare('SELECT id, titulo, precio_noche FROM propiedades WHERE id = ? AND id_anfitrion = ?');
    $stmt->bind_param('ii', $id_propiedad, $id_anfitrion);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
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