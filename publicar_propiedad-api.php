<?php
/**
 * publicar_propiedad-api.php
 * Endpoint del módulo de publicación de propiedades.
 *
 * POST action=crear (multipart/form-data)
 *   titulo, descripcion, tipo, direccion, ciudad, region,
 *   capacidad, habitaciones, banos, precio_noche, precio_limpieza,
 *   amenities[] (formato "icono|nombre"), fotos[] (archivos de imagen)
 *
 * Requiere conexion.php -> variable mysqli $conexion
 * Requiere sesión activa.
 * Requiere carpeta de escritura: /uploads/propiedades/ (crear si no existe, con permisos de escritura para el usuario del servidor web)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const CARPETA_SUBIDAS = __DIR__ . '/uploads/propiedades/';
const URL_SUBIDAS = 'uploads/propiedades/';
const TIPOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];
const TAMANO_MAX_BYTES = 8 * 1024 * 1024; // 8 MB por foto

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'crear':
        accion_crear($conexion);
        break;
    default:
        responder(false, null, 'Acción no reconocida.', 400);
}

function accion_crear(mysqli $conexion): void
{
    if (empty($_SESSION['estado_sesion']) || $_SESSION['estado_sesion'] !== 'activa') {
        responder(false, null, 'Debes iniciar sesión para publicar una propiedad.', 401);
    }
    $id_usuario = (int) $_SESSION['id_usuario'];

    // ---- Validación de campos de texto ----
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $direccion = trim($_POST['direccion'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $capacidad = (int) ($_POST['capacidad'] ?? 0);
    $habitaciones = (int) ($_POST['habitaciones'] ?? 0);
    $banos = (int) ($_POST['banos'] ?? 0);
    $precio_noche = (float) ($_POST['precio_noche'] ?? 0);
    $precio_limpieza = (float) ($_POST['precio_limpieza'] ?? 0);

    $tipos_validos = ['cabana', 'departamento', 'casa'];

    $errores = [];
    if ($titulo === '' || mb_strlen($titulo) > 120) $errores[] = 'El título es obligatorio (máximo 120 caracteres).';
    if ($descripcion === '') $errores[] = 'La descripción es obligatoria.';
    if (!in_array($tipo, $tipos_validos, true)) $errores[] = 'Selecciona un tipo de propiedad válido.';
    if ($direccion === '' || $ciudad === '' || $region === '') $errores[] = 'Completa la dirección, ciudad y región.';
    if ($capacidad < 1) $errores[] = 'La capacidad debe ser al menos 1 huésped.';
    if ($precio_noche <= 0) $errores[] = 'Ingresa un precio por noche válido.';

    // ---- Validación de fotos ----
    $fotos = $_FILES['fotos'] ?? null;
    $totalFotos = $fotos ? count($fotos['name']) : 0;
    if ($totalFotos < 3) {
        $errores[] = 'Sube al menos 3 fotos de la propiedad.';
    }

    if (!empty($errores)) {
        responder(false, null, implode(' ', $errores), 422);
    }

    if (!is_dir(CARPETA_SUBIDAS)) {
        mkdir(CARPETA_SUBIDAS, 0755, true);
    }

    $conexion->begin_transaction();

    try {
        // ---- Insertar propiedad ----
        $stmt = $conexion->prepare(
            'INSERT INTO propiedades
                (id_anfitrion, titulo, descripcion, tipo, direccion, ciudad, region,
                 capacidad, habitaciones, banos, precio_noche, precio_limpieza,
                 activo, destacado, creado_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW())'
        );
        $stmt->bind_param(
            'issssssiiidd',
            $id_usuario, $titulo, $descripcion, $tipo, $direccion, $ciudad, $region,
            $capacidad, $habitaciones, $banos, $precio_noche, $precio_limpieza
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('No se pudo guardar la propiedad.');
        }
        $id_propiedad = $stmt->insert_id;
        $stmt->close();

        // ---- Subir y guardar fotos ----
        $stmtFoto = $conexion->prepare(
            'INSERT INTO propiedades_fotos (id_propiedad, url, orden) VALUES (?, ?, ?)'
        );

        for ($i = 0; $i < $totalFotos; $i++) {
            if ($fotos['error'][$i] !== UPLOAD_ERR_OK) {
                continue; // se ignora un archivo individual con error, no se aborta toda la publicación
            }
            if (!in_array($fotos['type'][$i], TIPOS_PERMITIDOS, true)) {
                continue;
            }
            if ($fotos['size'][$i] > TAMANO_MAX_BYTES) {
                continue;
            }

            $extension = pathinfo($fotos['name'][$i], PATHINFO_EXTENSION);
            $nombreArchivo = uniqid('prop_' . $id_propiedad . '_', true) . '.' . strtolower($extension);
            $rutaDestino = CARPETA_SUBIDAS . $nombreArchivo;

            if (move_uploaded_file($fotos['tmp_name'][$i], $rutaDestino)) {
                $urlPublica = URL_SUBIDAS . $nombreArchivo;
                $orden = $i;
                $stmtFoto->bind_param('isi', $id_propiedad, $urlPublica, $orden);
                $stmtFoto->execute();
            }
        }
        $stmtFoto->close();

        // ---- Amenities ----
        $amenities = $_POST['amenities'] ?? [];
        if (!empty($amenities)) {
            $stmtAmenity = $conexion->prepare(
                'INSERT INTO propiedades_amenities (id_propiedad, icono, nombre, orden) VALUES (?, ?, ?, ?)'
            );
            $orden = 0;
            foreach ($amenities as $item) {
                // formato esperado: "icono|nombre"
                $partes = explode('|', $item, 2);
                if (count($partes) !== 2) continue;
                [$icono, $nombre] = $partes;
                $stmtAmenity->bind_param('issi', $id_propiedad, $icono, $nombre, $orden);
                $stmtAmenity->execute();
                $orden++;
            }
            $stmtAmenity->close();
        }

        // ---- Marcar al usuario como anfitrión ----
        $stmtUsuario = $conexion->prepare('UPDATE usuarios SET es_anfitrion = 1 WHERE id = ? AND es_anfitrion = 0');
        $stmtUsuario->bind_param('i', $id_usuario);
        $stmtUsuario->execute();
        $stmtUsuario->close();

        $conexion->commit();

        responder(true, ['id_propiedad' => $id_propiedad], 'Propiedad publicada.');

    } catch (\Throwable $e) {
        $conexion->rollback();
        responder(false, null, 'No pudimos publicar tu propiedad. Intenta nuevamente.', 500);
    }
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