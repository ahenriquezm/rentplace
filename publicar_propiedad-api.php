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
// MIME real (detectado del contenido, no del navegador) => extensión con que se guarda.
const TIPOS_PERMITIDOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const MAX_FOTOS = 20;
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
    } elseif ($totalFotos > MAX_FOTOS) {
        $errores[] = 'Puedes subir hasta ' . MAX_FOTOS . ' fotos.';
    }

    if (!empty($errores)) {
        responder(false, null, implode(' ', $errores), 422);
    }

    if (!is_dir(CARPETA_SUBIDAS)) {
        mkdir(CARPETA_SUBIDAS, 0755, true);
    }
    // Defensa adicional: aunque llegara un archivo no-imagen, Apache no lo ejecuta como script.
    if (!is_file(CARPETA_SUBIDAS . '.htaccess')) {
        file_put_contents(CARPETA_SUBIDAS . '.htaccess', "php_flag engine off\nRemoveHandler .php .phtml .php5 .php7 .php8 .phar\nOptions -ExecCGI -Indexes\n");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

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
            if ($fotos['size'][$i] > TAMANO_MAX_BYTES) {
                continue;
            }
            // Nunca confiar en $fotos['type'] ni en la extensión del nombre: ambos los controla el cliente.
            $mimeReal = $finfo->file($fotos['tmp_name'][$i]);
            if (!isset(TIPOS_PERMITIDOS[$mimeReal]) || @getimagesize($fotos['tmp_name'][$i]) === false) {
                continue;
            }

            $nombreArchivo = 'prop_' . $id_propiedad . '_' . bin2hex(random_bytes(8)) . '.' . TIPOS_PERMITIDOS[$mimeReal];
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