<?php
/**
 * control_lanzamiento.php
 *
 * Controla si el sitio muestra la landing "coming soon" o el sitio oficial.
 * Se incluye como PRIMERA línea de index.php (antes de cualquier salida/HTML),
 * y de cualquier otra página pública que también deba quedar bloqueada mientras
 * el sitio no está lanzado (ej. buscar.php, ficha_propiedad.php).
 *
 * USO:
 *   require_once __DIR__ . '/control_lanzamiento.php';
 *   // ... resto de index.php
 *
 * PARA LANZAR EL SITIO OFICIALMENTE:
 *   Cambia MODO_COMINGSOON a false más abajo. Es el único cambio necesario.
 *
 * VISTA PREVIA PARA EL EQUIPO MIENTRAS ESTÁ EN MODO COMING SOON:
 *   Visita cualquier página con ?preview=TU_CLAVE (ver PREVIEW_TOKEN abajo).
 *   Queda autorizado en sesión, así no hay que repetirlo en cada visita.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// ÚNICO INTERRUPTOR QUE NECESITAS TOCAR PARA LANZAR EL SITIO
// ============================================================
define('MODO_COMINGSOON', true);
// ============================================================

// Cambia esta clave por algo propio antes de compartirla con tu equipo.
define('PREVIEW_TOKEN', 'rentplace2026');

// Páginas que siempre deben quedar accesibles aunque el modo esté activo
// (la landing misma y sus archivos de soporte).
const ARCHIVOS_PERMITIDOS_EN_COMINGSOON = [
    'comingsoon.php',
    'comingsoon.css',
    'comingsoon.js',
    'comingsoon-api.php',
    'preview.php',
];

if (!MODO_COMINGSOON) {
    return; // Sitio lanzado oficialmente: este archivo no hace nada más.
}

// Bypass de vista previa para el equipo (diseño, marketing, dev).
if (isset($_GET['preview']) && hash_equals(PREVIEW_TOKEN, (string) $_GET['preview'])) {
    $_SESSION['preview_autorizado'] = true;
}

if (!empty($_SESSION['preview_autorizado'])) {
    return; // Ya autorizado en esta sesión: puede ver el sitio real.
}

// Evita loop de redirección si ya estamos en la landing o sus assets.
$archivo_actual = basename($_SERVER['SCRIPT_NAME']);
if (in_array($archivo_actual, ARCHIVOS_PERMITIDOS_EN_COMINGSOON, true)) {
    return;
}

header('Location: comingsoon.php');
exit;