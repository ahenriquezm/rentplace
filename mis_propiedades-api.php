<?php
/**
 * mis_propiedades-api.php
 * Endpoints del panel de anfitrión.
 *
 * GET  ?action=resumen
 * GET  ?action=listar
 * GET  ?action=reservas_propiedad&id_propiedad=#
 * POST action=toggle_activo (id_propiedad, activo)
 *
 * Requiere conexion.php -> variable mysqli $conexion
 * Requiere sesión activa. Todas las consultas están acotadas al anfitrión
 * en sesión (id_anfitrion = id_usuario) para que nadie vea o modifique
 * propiedades ajenas.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/tarifas.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'resumen':
        accion_resumen($conexion);
        break;
    case 'listar':
        accion_listar($conexion);
        break;
    case 'reservas_propiedad':
        accion_reservas_propiedad($conexion);
        break;
    case 'toggle_activo':
        accion_toggle_activo($conexion);
        break;
    default:
        responder(false, null, 'Acción no reconocida.', 400);
}

function accion_resumen(mysqli $conexion): void
{
    $id_anfitrion = requerir_sesion();

    $stmt = $conexion->prepare('SELECT COUNT(*) AS total FROM propiedades WHERE id_anfitrion = ?');
    $stmt->bind_param('i', $id_anfitrion);
    $stmt->execute();
    $totalPropiedades = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conexion->prepare(
        "SELECT COUNT(*) AS total FROM reservas r
         INNER JOIN propiedades p ON p.id = r.id_propiedad
         WHERE p.id_anfitrion = ? AND r.estado IN ('pendiente_pago','confirmada') AND r.fecha_salida >= CURDATE()"
    );
    $stmt->bind_param('i', $id_anfitrion);
    $stmt->execute();
    $reservasActivas = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conexion->prepare(
        "SELECT COALESCE(SUM(r.total), 0) AS ingresos FROM reservas r
         INNER JOIN propiedades p ON p.id = r.id_propiedad
         WHERE p.id_anfitrion = ? AND r.estado = 'confirmada'"
    );
    $stmt->bind_param('i', $id_anfitrion);
    $stmt->execute();
    $ingresos = (float) $stmt->get_result()->fetch_assoc()['ingresos'];
    $stmt->close();

    // ---- Costo anual de cada plan para sus propiedades activas (ver tarifas.php) ----
    $stmt = $conexion->prepare('SELECT COUNT(*) AS total FROM propiedades WHERE id_anfitrion = ? AND activo = 1');
    $stmt->bind_param('i', $id_anfitrion);
    $stmt->execute();
    $propiedadesActivas = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conexion->prepare(
        "SELECT COALESCE(SUM(r.total), 0) AS ingresos FROM reservas r
         INNER JOIN propiedades p ON p.id = r.id_propiedad
         WHERE p.id_anfitrion = ? AND r.estado = 'confirmada'
           AND r.fecha_llegada >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
           AND r.fecha_llegada < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')"
    );
    $stmt->bind_param('i', $id_anfitrion);
    $stmt->execute();
    $ingresosMes = (float) $stmt->get_result()->fetch_assoc()['ingresos'];
    $stmt->close();

    responder(true, [
        'total_propiedades'   => $totalPropiedades,
        'reservas_activas'    => $reservasActivas,
        'ingresos_confirmados'=> $ingresos,
        'ingresos_mes'        => $ingresosMes,
        'propiedades_activas' => $propiedadesActivas,
        'planes'              => array_map(function ($clave) {
            return [
                'nombre'         => TARIFA_PLANES[$clave]['nombre'],
                'precio_mensual' => TARIFA_PLANES[$clave]['precio_mensual'],
                'precio_anual'   => tarifa_precio_anual($clave),
                'ubicaciones'    => TARIFA_PLANES[$clave]['ubicaciones'],
            ];
        }, array_keys(TARIFA_PLANES)),
    ]);
}

function accion_listar(mysqli $conexion): void
{
    $id_anfitrion = requerir_sesion();

    $sql = "SELECT p.id, p.titulo, p.ciudad, p.region, p.precio_noche, p.activo,
                   (SELECT foto.url FROM propiedades_fotos foto
                    WHERE foto.id_propiedad = p.id ORDER BY foto.orden ASC LIMIT 1) AS foto_portada,
                   (SELECT COUNT(*) FROM reservas res
                    WHERE res.id_propiedad = p.id AND res.estado IN ('pendiente_pago','confirmada')) AS total_reservas
            FROM propiedades p
            WHERE p.id_anfitrion = ?
            ORDER BY p.creado_en DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $id_anfitrion);
    $stmt->execute();
    $res = $stmt->get_result();

    $propiedades = [];
    while ($row = $res->fetch_assoc()) {
        $propiedades[] = [
            'id'             => (int) $row['id'],
            'titulo'         => $row['titulo'],
            'ciudad'         => $row['ciudad'],
            'region'         => $row['region'],
            'precio_noche'   => (float) $row['precio_noche'],
            'activo'         => (int) $row['activo'],
            'foto_portada'   => $row['foto_portada'],
            'total_reservas' => (int) $row['total_reservas'],
        ];
    }
    $stmt->close();

    responder(true, $propiedades);
}

function accion_reservas_propiedad(mysqli $conexion): void
{
    $id_anfitrion = requerir_sesion();
    $id_propiedad = (int) ($_GET['id_propiedad'] ?? 0);

    // Verificamos que la propiedad sea del anfitrión en sesión antes de mostrar sus reservas.
    if (!propiedad_pertenece_a($conexion, $id_propiedad, $id_anfitrion)) {
        responder(false, null, 'Propiedad no encontrada.', 404);
    }

    $stmt = $conexion->prepare(
        "SELECT r.fecha_llegada, r.fecha_salida, r.total, r.estado,
                CONCAT(u.nombre, ' ', u.apellido) AS nombre_huesped
         FROM reservas r
         INNER JOIN usuarios u ON u.id = r.id_usuario
         WHERE r.id_propiedad = ?
         ORDER BY r.fecha_llegada DESC"
    );
    $stmt->bind_param('i', $id_propiedad);
    $stmt->execute();
    $res = $stmt->get_result();

    $reservas = [];
    while ($row = $res->fetch_assoc()) {
        $reservas[] = [
            'nombre_huesped' => $row['nombre_huesped'],
            'fecha_llegada'  => $row['fecha_llegada'],
            'fecha_salida'   => $row['fecha_salida'],
            'total'          => (float) $row['total'],
            'estado'         => $row['estado'],
        ];
    }
    $stmt->close();

    responder(true, $reservas);
}

function accion_toggle_activo(mysqli $conexion): void
{
    $id_anfitrion = requerir_sesion();
    $id_propiedad = (int) ($_POST['id_propiedad'] ?? 0);
    $activo = (int) ($_POST['activo'] ?? 0) === 1 ? 1 : 0;

    $stmt = $conexion->prepare('UPDATE propiedades SET activo = ? WHERE id = ? AND id_anfitrion = ?');
    $stmt->bind_param('iii', $activo, $id_propiedad, $id_anfitrion);
    $stmt->execute();
    $afectadas = $stmt->affected_rows;
    $stmt->close();

    if ($afectadas === 0) {
        responder(false, null, 'No se pudo actualizar la propiedad.', 404);
    }

    responder(true, null, 'Actualizado.');
}

/* ==================== HELPERS ==================== */

function requerir_sesion(): int
{
    if (empty($_SESSION['estado_sesion']) || $_SESSION['estado_sesion'] !== 'activa') {
        responder(false, null, 'Debes iniciar sesión.', 401);
    }
    return (int) $_SESSION['id_usuario'];
}

function propiedad_pertenece_a(mysqli $conexion, int $id_propiedad, int $id_anfitrion): bool
{
    if ($id_propiedad <= 0) return false;
    $stmt = $conexion->prepare('SELECT id FROM propiedades WHERE id = ? AND id_anfitrion = ?');
    $stmt->bind_param('ii', $id_propiedad, $id_anfitrion);
    $stmt->execute();
    $existe = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $existe;
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