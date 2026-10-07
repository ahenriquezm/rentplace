<?php
/**
 * index-api.php
 * Endpoints de la página de inicio.
 *
 * GET ?action=categorias            -> cantidad de propiedades y precio desde, por tipo
 * GET ?action=destinos              -> ciudades con más propiedades activas
 * GET ?action=sugerencias&q=texto   -> autocompletado de destinos
 *
 * Requiere conexion.php -> variable mysqli $conexion
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'categorias':
        accion_categorias($conexion);
        break;
    case 'destinos':
        accion_destinos($conexion);
        break;
    case 'sugerencias':
        accion_sugerencias($conexion);
        break;
    default:
        responder(false, null, 'Acción no reconocida.', 400);
}

function accion_categorias(mysqli $conexion): void
{
    $res = $conexion->query(
        'SELECT tipo, COUNT(*) AS total, MIN(precio_noche) AS desde
         FROM propiedades WHERE activo = 1 GROUP BY tipo'
    );

    $categorias = [];
    while ($row = $res->fetch_assoc()) {
        $categorias[] = [
            'tipo'  => $row['tipo'],
            'total' => (int) $row['total'],
            'desde' => (float) $row['desde'],
        ];
    }

    responder(true, $categorias);
}

function accion_destinos(mysqli $conexion): void
{
    $res = $conexion->query(
        'SELECT p.ciudad, MAX(p.region) AS region, COUNT(*) AS total,
                (SELECT foto.url FROM propiedades_fotos foto
                 INNER JOIN propiedades p2 ON p2.id = foto.id_propiedad
                 WHERE p2.ciudad = p.ciudad AND p2.activo = 1
                 ORDER BY p2.destacado DESC, foto.orden ASC LIMIT 1) AS foto
         FROM propiedades p
         WHERE p.activo = 1
         GROUP BY p.ciudad
         ORDER BY total DESC
         LIMIT 4'
    );

    $destinos = [];
    while ($row = $res->fetch_assoc()) {
        $destinos[] = [
            'ciudad' => $row['ciudad'],
            'region' => $row['region'],
            'total'  => (int) $row['total'],
            'foto'   => $row['foto'],
        ];
    }

    responder(true, $destinos);
}

function accion_sugerencias(mysqli $conexion): void
{
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 2) {
        responder(true, []);
    }

    $patron = '%' . $q . '%';
    $stmt = $conexion->prepare(
        'SELECT ciudad, MAX(region) AS region FROM propiedades
         WHERE activo = 1 AND ciudad LIKE ?
         GROUP BY ciudad ORDER BY COUNT(*) DESC LIMIT 6'
    );
    $stmt->bind_param('s', $patron);
    $stmt->execute();
    $res = $stmt->get_result();

    $sugerencias = [];
    while ($row = $res->fetch_assoc()) {
        $sugerencias[] = ['ciudad' => $row['ciudad'], 'region' => $row['region']];
    }
    $stmt->close();

    responder(true, $sugerencias);
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
