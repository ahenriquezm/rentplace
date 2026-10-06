<?php
/**
 * ficha_propiedad.php
 * Vista de detalle de propiedad + flujo de reserva.
 *
 * Requiere:
 *   - conexion.php   -> variable mysqli $conexion
 *   - Sesión ya iniciada por el módulo de login (session_start() ya ejecutado)
 *     Variables de sesión esperadas: $_SESSION['estado_sesion'], $_SESSION['id_usuario'],
 *     $_SESSION['nombre_usuario']
 *
 * Ajusta las rutas de include según la ubicación real de este archivo
 * dentro de tu estructura de carpetas (ej. /modules/ficha_propiedad/).
 */
require_once __DIR__ . '/conexion.php';

$id_propiedad = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id_propiedad <= 0) {
    header('Location: buscar.php');
    exit;
}

$sesion_activa = isset($_SESSION['estado_sesion']) && $_SESSION['estado_sesion'] === 'activa';
$nombre_usuario = $sesion_activa ? htmlspecialchars($_SESSION['nombre_usuario']) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>offday — Detalle de propiedad</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="ficha_propiedad.css">
</head>
<body>

<header class="od-header">
  <div class="container d-flex align-items-center justify-content-between py-3">
    <a href="index.php" class="od-brand d-flex align-items-center gap-2 text-decoration-none">
      <svg width="32" height="32" viewBox="0 0 34 34">
        <ellipse cx="17" cy="18" rx="12" ry="10" fill="#4a7182"/>
        <circle cx="17" cy="7" r="5" fill="#4a7182"/>
        <circle cx="6" cy="14" r="4" fill="#c98a5c"/>
        <circle cx="28" cy="14" r="4" fill="#c98a5c"/>
        <circle cx="8" cy="26" r="4" fill="#c98a5c"/>
        <circle cx="26" cy="26" r="4" fill="#c98a5c"/>
      </svg>
      <span class="od-brand-name">offday</span>
    </a>
    <div>
      <?php if ($sesion_activa): ?>
        <span class="od-muted">Hola, <?= $nombre_usuario ?></span>
      <?php else: ?>
        <a href="login.php" class="od-btn od-btn-dark">Ingresar</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="container" id="ficha-root" data-id-propiedad="<?= $id_propiedad ?>" data-sesion-activa="<?= $sesion_activa ? '1' : '0' ?>">

  <a href="buscar.php" class="od-back">← Volver a la búsqueda</a>

  <!-- Galería: se completa vía JS desde ficha_propiedad-api.php -->
  <div id="galeria" class="od-gallery">
    <div class="od-skeleton od-gallery-main"></div>
    <div class="od-gallery-side">
      <div class="od-skeleton"></div>
      <div class="od-skeleton"></div>
    </div>
  </div>

  <div class="od-detail-body">
    <div class="od-detail-info">
      <h1 id="titulo-propiedad" class="od-title">Cargando...</h1>
      <div id="subtitulo-propiedad" class="od-muted"></div>

      <hr class="od-hr">

      <div id="host-row" class="od-host-row"><!-- anfitrión, vía JS --></div>

      <hr class="od-hr">

      <h3 class="od-h3">Lo que ofrece este lugar</h3>
      <div id="amenities-grid" class="od-amenity-grid"><!-- amenities, vía JS --></div>
    </div>

    <!-- Tarjeta de reserva -->
    <div class="od-booking-card">
      <div class="od-booking-price">
        <span id="precio-noche">$--</span> <span class="od-muted">/ noche</span>
      </div>

      <form id="form-reserva" novalidate>
        <div class="od-date-grid">
          <div>
            <label class="od-label" for="fecha-llegada">Llegada</label>
            <input type="date" id="fecha-llegada" name="llegada" class="od-input" required>
          </div>
          <div>
            <label class="od-label" for="fecha-salida">Salida</label>
            <input type="date" id="fecha-salida" name="salida" class="od-input" required>
          </div>
        </div>

        <div class="mb-2">
          <label class="od-label" for="huespedes">Huéspedes</label>
          <input type="number" id="huespedes" name="huespedes" class="od-input" min="1" value="1" required>
        </div>

        <div id="desglose-precio" class="od-price-breakdown d-none">
          <div class="od-cost-line"><span id="linea-noches"></span><span id="valor-noches"></span></div>
          <div class="od-cost-line"><span>Limpieza</span><span id="valor-limpieza"></span></div>
          <div class="od-cost-line"><span>Cargo por servicio</span><span>$0</span></div>
          <div class="od-cost-line od-total"><span>Total</span><span id="valor-total"></span></div>
        </div>

        <div id="mensaje-disponibilidad" class="od-availability-msg d-none"></div>

        <div class="od-transparency-note">
          🐢 <span>Este es el precio final. offday no agrega comisión de servicio al huésped — nunca.</span>
        </div>

        <button type="submit" id="btn-reservar" class="od-btn od-btn-primary w-100" disabled>
          Reservar
        </button>
      </form>
    </div>
  </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="ficha_propiedad.js"></script>
</body>
</html>