<?php
/**
 * buscar.php
 * Resultados de búsqueda / listado de propiedades.
 *
 * Requiere:
 *   - control_lanzamiento.php (respeta el modo coming soon)
 *   - conexion.php -> usado por buscar-api.php, no directamente aquí
 *
 * Parámetros GET soportados (todos opcionales):
 *   destino, llegada, salida, huespedes, tipo
 */
require_once __DIR__ . '/control_lanzamiento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sesion_activa = isset($_SESSION['estado_sesion']) && $_SESSION['estado_sesion'] === 'activa';
$nombre_usuario = $sesion_activa ? htmlspecialchars($_SESSION['nombre_usuario']) : '';

$destino   = trim($_GET['destino'] ?? '');
$llegada   = $_GET['llegada'] ?? '';
$salida    = $_GET['salida'] ?? '';
$huespedes = $_GET['huespedes'] ?? '';
$tipo      = $_GET['tipo'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentplace — Buscar alojamiento</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="buscar.css">
</head>
<body>

<header class="od-header">
  <div class="container d-flex align-items-center justify-content-between py-3">
    <a href="index.php" class="od-brand d-flex align-items-center gap-2">
      <svg width="32" height="32" viewBox="0 0 34 34">
        <path d="M17 2 C10 2 5 7 5 14 C5 22 17 32 17 32 C17 32 29 22 29 14 C29 7 24 2 17 2 Z" fill="#4A7182"/>
        <circle cx="17" cy="14" r="5" fill="#C98A5C"/>
      </svg>
      <span class="od-brand-name">Rentplace</span>
    </a>
    <div class="d-flex align-items-center gap-3">
      <?php if ($sesion_activa): ?>
        <span class="od-muted">Hola, <?= $nombre_usuario ?></span>
        <a href="mis_reservas.php" class="od-btn od-btn-dark">Mis reservas</a>
      <?php else: ?>
        <a href="login.php" class="od-btn od-btn-ghost">Ingresar</a>
        <a href="login.php?tab=registro" class="od-btn od-btn-dark">Crear cuenta</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="container">

  <form id="form-buscar" class="bs-search-bar">
    <div class="bs-seg">
      <label class="bs-label" for="in-destino">Destino</label>
      <input type="text" id="in-destino" name="destino" class="bs-seg-input" placeholder="¿A dónde vas?" value="<?= htmlspecialchars($destino) ?>" autocomplete="off">
    </div>
    <div class="bs-seg">
      <label class="bs-label" for="in-llegada">Llegada</label>
      <input type="date" id="in-llegada" name="llegada" class="bs-seg-input" value="<?= htmlspecialchars($llegada) ?>">
    </div>
    <div class="bs-seg">
      <label class="bs-label" for="in-salida">Salida</label>
      <input type="date" id="in-salida" name="salida" class="bs-seg-input" value="<?= htmlspecialchars($salida) ?>">
    </div>
    <div class="bs-seg bs-seg-last">
      <label class="bs-label" for="in-huespedes">Huéspedes</label>
      <input type="number" id="in-huespedes" name="huespedes" class="bs-seg-input" min="1" placeholder="¿Cuántos?" value="<?= htmlspecialchars($huespedes) ?>">
    </div>
    <button type="submit" class="bs-search-go">⌕</button>
  </form>

  <div class="bs-tag-strip" id="tag-strip">
    <button type="button" class="bs-tag-chip <?= $tipo === '' ? 'active' : '' ?>" data-tipo="">Todos</button>
    <button type="button" class="bs-tag-chip <?= $tipo === 'cabana' ? 'active' : '' ?>" data-tipo="cabana">Cabañas</button>
    <button type="button" class="bs-tag-chip <?= $tipo === 'departamento' ? 'active' : '' ?>" data-tipo="departamento">Departamentos</button>
    <button type="button" class="bs-tag-chip <?= $tipo === 'casa' ? 'active' : '' ?>" data-tipo="casa">Casas</button>
  </div>

  <div id="bs-resumen" class="bs-resumen"></div>

  <div id="bs-grid" class="bs-grid">
    <div class="bs-skeleton"></div>
    <div class="bs-skeleton"></div>
    <div class="bs-skeleton"></div>
    <div class="bs-skeleton"></div>
    <div class="bs-skeleton"></div>
    <div class="bs-skeleton"></div>
  </div>

  <div id="bs-vacio" class="bs-empty d-none">
    <div class="bs-empty-t">No encontramos propiedades con esos filtros.</div>
    <div class="bs-empty-s">Prueba con otro destino o cambia las fechas.</div>
  </div>

  <div class="bs-load-more-wrap">
    <button id="btn-cargar-mas" class="od-btn od-btn-ghost-border d-none">Ver más resultados</button>
  </div>

</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="buscar.js"></script>
</body>
</html>