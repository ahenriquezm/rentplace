<?php
/**
 * mis_propiedades.php
 * Panel de anfitrión: propiedades publicadas y reservas recibidas por cada una.
 *
 * Requiere:
 *   - control_lanzamiento.php
 *   - conexion.php -> usado por mis_propiedades-api.php
 *   - Sesión activa
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

$nombre_usuario = htmlspecialchars($_SESSION['nombre_usuario']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mis propiedades — Rentplace</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('mis_propiedades.css') ?>">
</head>
<body>

<header class="od-header">
  <div class="container d-flex align-items-center justify-content-between py-3">
    <a href="index.php" class="od-brand d-flex align-items-center gap-2">
      <svg width="30" height="30" viewBox="0 0 34 34">
        <path d="M17 2 C10 2 5 7 5 14 C5 22 17 32 17 32 C17 32 29 22 29 14 C29 7 24 2 17 2 Z" fill="#4A7182"/>
        <circle cx="17" cy="14" r="5" fill="#C98A5C"/>
      </svg>
      <span class="od-brand-name">Rentplace</span>
    </a>
    <div class="d-flex align-items-center gap-3">
      <span class="od-muted">Hola, <?= $nombre_usuario ?></span>
      <a href="logout.php" class="od-btn od-btn-ghost">Cerrar sesión</a>
    </div>
  </div>
</header>

<main class="container">

  <div class="mp-top">
    <h1 class="mp-title">Mis propiedades</h1>
    <a href="publicar_propiedad.php" class="od-btn od-btn-dark">+ Publicar otra propiedad</a>
  </div>

  <div id="mp-resumen" class="mp-resumen-grid">
    <div class="mp-skeleton-kpi"></div>
    <div class="mp-skeleton-kpi"></div>
    <div class="mp-skeleton-kpi"></div>
  </div>

  <div id="mp-lista" class="mp-lista">
    <div class="mp-skeleton"></div>
    <div class="mp-skeleton"></div>
  </div>

  <div id="mp-vacio" class="mp-empty d-none">
    <div class="mp-empty-t">Todavía no has publicado ninguna propiedad.</div>
    <div class="mp-empty-s">Publica tu primera propiedad: un pago fijo al año, nunca un porcentaje por reserva.</div>
    <a href="publicar_propiedad.php" class="od-btn od-btn-dark">Publicar mi primera propiedad</a>
  </div>

</main>

<!-- Panel lateral con las reservas de una propiedad -->
<div id="mp-drawer-bg" class="mp-drawer-bg d-none"></div>
<div id="mp-drawer" class="mp-drawer">
  <div class="mp-drawer-head">
    <h2 id="mp-drawer-titulo" class="mp-drawer-titulo">Reservas</h2>
    <button type="button" id="mp-drawer-cerrar" class="mp-drawer-cerrar">✕</button>
  </div>
  <div id="mp-drawer-body" class="mp-drawer-body"></div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('mis_propiedades.js') ?>"></script>
</body>
</html>