<?php
/**
 * buscar-api.php
 * Endpoint del módulo de búsqueda / listado de propiedades.
 *
 * GET ?action=resultados
 *   Parámetros opcionales: destino, llegada, salida, huespedes, tipo, pagina, por_pagina
 *
 * Requiere conexion.php -> variable mysqli $conexion
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'resultados':
        accion_resultados($conexion);
        break;
    default:
        responder(false, null, 'Acción no reconocida.', 400);
}

function accion_resultados(mysqli $conexion): void
{
    $destino   = trim($_GET['destino'] ?? '');
    $llegada   = $_GET['llegada'] ?? '';
    $salida    = $_GET['salida'] ?? '';
    $huespedes = (int) ($_GET['huespedes'] ?? 0);
    $tipo      = $_GET['tipo'] ?? '';

    $pagina      = max(1, (int) ($_GET['pagina'] ?? 1));
    $porPagina   = min(24, max(1, (int) ($_GET['por_pagina'] ?? 12)));
    $offset      = ($pagina - 1) * $porPagina;

    $filtrarFechas = $llegada && $salida && strtotime($llegada) && strtotime($salida) && strtotime($salida) > strtotime($llegada);

    // ---- Construcción dinámica de condiciones ----
    $condiciones = ['p.activo = 1'];
    $params = [];
    $tipos = '';

    if ($destino !== '') {
        $condiciones[] = 'p.ciudad LIKE ?';
        $params[] = '%' . $destino . '%';
        $tipos .= 's';
    }

    if ($tipo !== '') {
        $condiciones[] = 'p.tipo = ?';
        $params[] = $tipo;
        $tipos .= 's';
    }

    if ($huespedes > 0) {
        $condiciones[] = 'p.capacidad >= ?';
        $params[] = $huespedes;
        $tipos .= 'i';
    }

    if ($filtrarFechas) {
        $condiciones[] = 'NOT EXISTS (
            SELECT 1 FROM reservas res
            WHERE res.id_propiedad = p.id
              AND res.estado IN ("pendiente_pago", "confirmada")
              AND res.fecha_llegada < ?
              AND res.fecha_salida > ?
        )';
        $params[] = $salida;
        $params[] = $llegada;
        $tipos .= 'ss';
    }

    $whereSql = implode(' AND ', $condiciones);

    // ---- Conteo total (para paginación) ----
    $sqlTotal = "SELECT COUNT(*) AS total FROM propiedades p WHERE $whereSql";
    $stmt = $conexion->prepare($sqlTotal);
    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // ---- Resultados de la página actual ----
    $sql = "SELECT p.id, p.titulo, p.ciudad, p.region, p.tipo, p.capacidad, p.precio_noche,
                   (SELECT foto.url FROM propiedades_fotos foto
                    WHERE foto.id_propiedad = p.id ORDER BY foto.orden ASC LIMIT 1) AS foto_portada,
                   (SELECT AVG(r.puntuacion) FROM resenas r WHERE r.id_propiedad = p.id) AS rating_promedio
            FROM propiedades p
            WHERE $whereSql
            ORDER BY p.destacado DESC, rating_promedio DESC, p.id DESC
            LIMIT ? OFFSET ?";

    $paramsPagina = $params;
    $paramsPagina[] = $porPagina;
    $paramsPagina[] = $offset;
    $tiposPagina = $tipos . 'ii';

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($tiposPagina, ...$paramsPagina);
    $stmt->execute();
    $res = $stmt->get_result();

    $propiedades = [];
    while ($row = $res->fetch_assoc()) {
        $propiedades[] = [
            'id'              => (int) $row['id'],
            'titulo'          => $row['titulo'],
            'ciudad'          => $row['ciudad'],
            'region'          => $row['region'],
            'tipo'            => $row['tipo'],
            'capacidad'       => (int) $row['capacidad'],
            'precio_noche'    => (float) $row['precio_noche'],
            'foto_portada'    => $row['foto_portada'],
            'rating_promedio' => $row['rating_promedio'] !== null ? (float) $row['rating_promedio'] : null,
        ];
    }
    $stmt->close();

    responder(true, [
        'propiedades' => $propiedades,
        'total'       => $total,
        'pagina'      => $pagina,
        'por_pagina'  => $porPagina,
    ]);
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