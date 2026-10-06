<?php
/**
 * ficha_propiedad-api.php
 * Endpoints del módulo de ficha de propiedad + reserva.
 *
 * Acciones soportadas:
 *   GET  ?action=detalle&id=#
 *   GET  ?action=disponibilidad&id=#&llegada=YYYY-MM-DD&salida=YYYY-MM-DD&huespedes=#
 *   POST  action=crear_reserva  (id_propiedad, llegada, salida, huespedes)
 *
 * Requiere conexion.php -> variable mysqli $conexion
 * Requiere sesión ya iniciada (session_start()) para crear_reserva.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

$CARGO_SERVICIO = 0; // offday: siempre 0. El modelo de negocio se financia por suscripción del anfitrión, no por comisión al huésped.

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'detalle':
        accion_detalle($conexion);
        break;
    case 'disponibilidad':
        accion_disponibilidad($conexion);
        break;
    case 'crear_reserva':
        accion_crear_reserva($conexion, $CARGO_SERVICIO);
        break;
    default:
        responder(false, null, 'Acción no reconocida.', 400);
}

/* ==================== ACCIONES ==================== */

function accion_detalle(mysqli $conexion): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        responder(false, null, 'ID de propiedad inválido.', 400);
    }

    $stmt = $conexion->prepare(
        'SELECT p.id, p.titulo, p.ciudad, p.region, p.capacidad, p.habitaciones, p.banos,
                p.precio_noche, p.precio_limpieza,
                u.id AS id_anfitrion, u.nombre AS anfitrion_nombre, u.avatar_url,
                YEAR(u.creado_en) AS anfitrion_anio_registro,
                COALESCE(u.tiempo_respuesta_horas, 1) AS tiempo_respuesta_horas
         FROM propiedades p
         INNER JOIN usuarios u ON u.id = p.id_anfitrion
         WHERE p.id = ? AND p.activo = 1
         LIMIT 1'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $propiedad = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$propiedad) {
        responder(false, null, 'Propiedad no encontrada.', 404);
    }

    // Fotos
    $fotos = [];
    $stmt = $conexion->prepare(
        'SELECT url FROM propiedades_fotos WHERE id_propiedad = ? ORDER BY orden ASC'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $fotos[] = ['url' => $row['url']];
    }
    $stmt->close();

    // Amenities
    $amenities = [];
    $stmt = $conexion->prepare(
        'SELECT icono, nombre FROM propiedades_amenities WHERE id_propiedad = ? ORDER BY orden ASC'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $amenities[] = $row;
    }
    $stmt->close();

    responder(true, [
        'id'              => (int) $propiedad['id'],
        'titulo'          => $propiedad['titulo'],
        'ciudad'          => $propiedad['ciudad'],
        'region'          => $propiedad['region'],
        'capacidad'       => (int) $propiedad['capacidad'],
        'habitaciones'    => (int) $propiedad['habitaciones'],
        'banos'           => (int) $propiedad['banos'],
        'precio_noche'    => (float) $propiedad['precio_noche'],
        'precio_limpieza' => (float) $propiedad['precio_limpieza'],
        'fotos'           => $fotos,
        'amenities'       => $amenities,
        'anfitrion'       => [
            'id'                     => (int) $propiedad['id_anfitrion'],
            'nombre'                 => $propiedad['anfitrion_nombre'],
            'avatar_url'             => $propiedad['avatar_url'],
            'anio_registro'          => $propiedad['anfitrion_anio_registro'],
            'tiempo_respuesta_horas' => $propiedad['tiempo_respuesta_horas'],
        ],
    ]);
}

function accion_disponibilidad(mysqli $conexion): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $llegada = $_GET['llegada'] ?? '';
    $salida = $_GET['salida'] ?? '';
    $huespedes = (int) ($_GET['huespedes'] ?? 1);

    [$ok, $mensaje] = validar_fechas($id, $llegada, $salida);
    if (!$ok) {
        responder(false, null, $mensaje, 422);
    }

    $precios = obtener_precios_propiedad($conexion, $id);
    if (!$precios) {
        responder(false, null, 'Propiedad no encontrada.', 404);
    }

    if ($huespedes > $precios['capacidad']) {
        responder(true, [
            'disponible' => false,
        ]);
    }

    $disponible = fechas_disponibles($conexion, $id, $llegada, $salida);

    $noches = (strtotime($salida) - strtotime($llegada)) / 86400;
    $subtotal = calcular_subtotal_estadia($conexion, $id, $llegada, $salida, $precios['precio_noche']);
    $total = $subtotal + $precios['precio_limpieza'];

    responder(true, [
        'disponible'   => $disponible,
        'noches'       => (int) $noches,
        'precio_noche' => $precios['precio_noche'],
        'subtotal'     => $subtotal,
        'limpieza'     => $precios['precio_limpieza'],
        'total'        => $total,
    ]);
}

function accion_crear_reserva(mysqli $conexion, float $cargo_servicio): void
{
    session_start();

    if (empty($_SESSION['estado_sesion']) || $_SESSION['estado_sesion'] !== 'activa') {
        responder(false, null, 'Debes iniciar sesión para reservar.', 401);
    }

    $id_usuario = (int) $_SESSION['id_usuario'];
    $id_propiedad = (int) ($_POST['id_propiedad'] ?? 0);
    $llegada = $_POST['llegada'] ?? '';
    $salida = $_POST['salida'] ?? '';
    $huespedes = (int) ($_POST['huespedes'] ?? 1);

    [$ok, $mensaje] = validar_fechas($id_propiedad, $llegada, $salida);
    if (!$ok) {
        responder(false, null, $mensaje, 422);
    }

    $precios = obtener_precios_propiedad($conexion, $id_propiedad);
    if (!$precios) {
        responder(false, null, 'Propiedad no encontrada.', 404);
    }

    if ($huespedes > $precios['capacidad']) {
        responder(false, null, 'La propiedad no admite esa cantidad de huéspedes.', 422);
    }

    // Re-validar disponibilidad server-side justo antes de insertar (nunca confiar solo en el front)
    $conexion->begin_transaction();

    if (!fechas_disponibles($conexion, $id_propiedad, $llegada, $salida, true)) {
        $conexion->rollback();
        responder(false, null, 'Esas fechas ya no están disponibles.', 409);
    }

    $noches = (int) ((strtotime($salida) - strtotime($llegada)) / 86400);
    $subtotal = calcular_subtotal_estadia($conexion, $id_propiedad, $llegada, $salida, $precios['precio_noche']);
    $total = $subtotal + $precios['precio_limpieza'] + $cargo_servicio;

    $stmt = $conexion->prepare(
        'INSERT INTO reservas
            (id_propiedad, id_usuario, fecha_llegada, fecha_salida, huespedes,
             precio_noche, noches, subtotal, precio_limpieza, cargo_servicio, total,
             estado, creado_en)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pendiente_pago", NOW())'
    );
    $stmt->bind_param(
        'iissiddddd',
        $id_propiedad,
        $id_usuario,
        $llegada,
        $salida,
        $huespedes,
        $precios['precio_noche'],
        $noches,
        $subtotal,
        $precios['precio_limpieza'],
        $cargo_servicio,
        $total
    );

    if (!$stmt->execute()) {
        $conexion->rollback();
        responder(false, null, 'No se pudo crear la reserva.', 500);
    }

    $id_reserva = $stmt->insert_id;
    $stmt->close();
    $conexion->commit();

    responder(true, ['id_reserva' => $id_reserva]);
}

/* ==================== HELPERS ==================== */

function validar_fechas(int $id, string $llegada, string $salida): array
{
    if ($id <= 0) return [false, 'Propiedad inválida.'];
    if (!$llegada || !$salida) return [false, 'Debes indicar fecha de llegada y salida.'];
    if (!strtotime($llegada) || !strtotime($salida)) return [false, 'Fechas inválidas.'];
    if (strtotime($salida) <= strtotime($llegada)) return [false, 'La salida debe ser posterior a la llegada.'];
    return [true, ''];
}

function obtener_precios_propiedad(mysqli $conexion, int $id): ?array
{
    $stmt = $conexion->prepare(
        'SELECT precio_noche, precio_limpieza, capacidad FROM propiedades WHERE id = ? AND activo = 1'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;

    return [
        'precio_noche'    => (float) $row['precio_noche'],
        'precio_limpieza' => (float) $row['precio_limpieza'],
        'capacidad'       => (int) $row['capacidad'],
    ];
}

/**
 * Verifica que el rango [llegada, salida) no se solape con reservas existentes
 * en estado activo (pendiente_pago o confirmada), Y que ninguna noche del rango
 * haya sido bloqueada manualmente por el anfitrión en el módulo de calendario.
 * $bloquear: usa FOR UPDATE dentro de una transacción para evitar doble reserva concurrente.
 */
function fechas_disponibles(mysqli $conexion, int $id_propiedad, string $llegada, string $salida, bool $bloquear = false): bool
{
    $sql = 'SELECT COUNT(*) AS total
            FROM reservas
            WHERE id_propiedad = ?
              AND estado IN ("pendiente_pago", "confirmada")
              AND fecha_llegada < ?
              AND fecha_salida > ?';
    if ($bloquear) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('iss', $id_propiedad, $salida, $llegada);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int) $row['total'] > 0) {
        return false;
    }

    // Ninguna noche del rango puede estar marcada disponible = 0 por el anfitrión.
    $stmt = $conexion->prepare(
        'SELECT COUNT(*) AS total FROM calendario_propiedad
         WHERE id_propiedad = ? AND fecha >= ? AND fecha < ? AND disponible = 0'
    );
    $stmt->bind_param('iss', $id_propiedad, $llegada, $salida);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ((int) $row['total']) === 0;
}

/**
 * Suma el precio de cada noche del rango [llegada, salida), usando el precio
 * personalizado del calendario cuando exista, o el precio base de la propiedad
 * en caso contrario. Esto es lo que hace que el precio mostrado (y cobrado)
 * refleje temporadas altas, fines de semana, etc. definidos en calendario.php.
 *
 * Nota: reservas.precio_noche guarda el precio BASE como referencia; el monto
 * real cobrado es reservas.subtotal, calculado acá noche por noche.
 */
function calcular_subtotal_estadia(mysqli $conexion, int $id_propiedad, string $llegada, string $salida, float $precio_base): float
{
    $overrides = [];
    $stmt = $conexion->prepare(
        'SELECT fecha, precio_personalizado FROM calendario_propiedad
         WHERE id_propiedad = ? AND fecha >= ? AND fecha < ? AND precio_personalizado IS NOT NULL'
    );
    $stmt->bind_param('iss', $id_propiedad, $llegada, $salida);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $overrides[$row['fecha']] = (float) $row['precio_personalizado'];
    }
    $stmt->close();

    $subtotal = 0.0;
    $cursor = strtotime($llegada);
    $fin = strtotime($salida);
    while ($cursor < $fin) {
        $fecha = date('Y-m-d', $cursor);
        $subtotal += $overrides[$fecha] ?? $precio_base;
        $cursor = strtotime('+1 day', $cursor);
    }

    return $subtotal;
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