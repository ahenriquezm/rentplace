<?php
/**
 * calendario.php
 * Calendario de disponibilidad y precios por fecha, por propiedad.
 *
 * Requiere:
 *   - control_lanzamiento.php
 *   - conexion.php -> usado por calendario-api.php
 *   - Sesión activa. Se valida además que la propiedad sea del anfitrión en sesión.
 *
 * Parámetro GET requerido: id (id de la propiedad)
 */
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/control_lanzamiento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['estado_sesion']) || $_SESSION['estado_sesion'] !== 'activa') {
    header('Location: login.php?redirect=' . urlencode('mis_propiedades.php'));
    exit;
}

$id_propiedad = (int) ($_GET['id'] ?? 0);
if ($id_propiedad <= 0) {
    header('Location: mis_propiedades.php');
    exit;
}

$nombre_usuario = htmlspecialchars($_SESSION['nombre_usuario']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Calendario y precios — Rentplace</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('calendario.css') ?>">
</head>
<body>

<header class="od-header">
  <div class="container d-flex align-items-center justify-content-between py-3">
    <a href="mis_propiedades.php" class="od-back">← Mis propiedades</a>
    <span class="od-muted">Hola, <?= $nombre_usuario ?></span>
  </div>
</header>

<main class="container" id="cal-root" data-id-propiedad="<?= $id_propiedad ?>">

  <h1 id="cal-titulo-propiedad" class="cal-title">Cargando...</h1>
  <p class="cal-sub">Haz clic en los días para seleccionarlos, o arrastra sobre el calendario para seleccionar un rango. Después aplica un precio o bloquéalos.</p>

  <div class="cal-legend">
    <span class="cal-legend-item"><i class="cal-dot cal-dot-disponible"></i> Disponible</span>
    <span class="cal-legend-item"><i class="cal-dot cal-dot-personalizado"></i> Precio personalizado</span>
    <span class="cal-legend-item"><i class="cal-dot cal-dot-bloqueado"></i> Bloqueado por ti</span>
    <span class="cal-legend-item"><i class="cal-dot cal-dot-reservado"></i> Reservado</span>
  </div>

  <div class="cal-nav">
    <button type="button" id="cal-mes-anterior" class="cal-nav-btn">‹</button>
    <div id="cal-mes-actual" class="cal-mes-actual">—</div>
    <button type="button" id="cal-mes-siguiente" class="cal-nav-btn">›</button>
  </div>

  <div class="cal-dias-semana">
    <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span>
  </div>

  <div id="cal-grid" class="cal-grid"></div>

</main>

<!-- Barra de acciones flotante, aparece cuando hay fechas seleccionadas -->
<div id="cal-barra-acciones" class="cal-barra-acciones d-none">
  <div class="cal-barra-info"><span id="cal-conteo-seleccion">0</span> fecha(s) seleccionada(s)</div>
  <div class="cal-barra-controles">
    <input type="number" id="cal-precio-input" class="cal-precio-input" placeholder="Precio por noche" min="0" step="1000">
    <button type="button" id="cal-btn-aplicar-precio" class="cal-btn cal-btn-primary">Aplicar precio</button>
    <button type="button" id="cal-btn-bloquear" class="cal-btn cal-btn-outline">Bloquear</button>
    <button type="button" id="cal-btn-restaurar" class="cal-btn cal-btn-outline">Restaurar por defecto</button>
    <button type="button" id="cal-btn-cancelar" class="cal-btn cal-btn-texto">Cancelar selección</button>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('calendario.js') ?>"></script>
</body>
</html>