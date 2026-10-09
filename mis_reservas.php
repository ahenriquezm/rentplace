<?php
/**
 * mis_reservas.php
 * Historial de reservas del huésped que tiene la sesión iniciada.
 *
 * Requiere:
 *   - control_lanzamiento.php
 *   - conexion.php -> usado por mis_reservas-api.php
 *   - Sesión activa
 */
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/control_lanzamiento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['estado_sesion']) || $_SESSION['estado_sesion'] !== 'activa') {
    header('Location: login.php?redirect=' . urlencode('mis_reservas.php'));
    exit;
}

$nombre_usuario = htmlspecialchars($_SESSION['nombre_usuario']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mis reservas — Rentplace</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('mis_reservas.css') ?>">
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

  <h1 class="mr-title">Mis reservas</h1>

  <div class="mr-tabs" id="mr-tabs">
    <button type="button" class="mr-tab active" data-filtro="proximas">Próximas</button>
    <button type="button" class="mr-tab" data-filtro="pasadas">Pasadas</button>
    <button type="button" class="mr-tab" data-filtro="canceladas">Canceladas</button>
  </div>

  <div id="mr-lista" class="mr-lista">
    <div class="mr-skeleton"></div>
    <div class="mr-skeleton"></div>
  </div>

  <div id="mr-vacio" class="mr-empty d-none">
    <div class="mr-empty-t">Todavía no tienes reservas acá.</div>
    <div class="mr-empty-s">Cuando reserves un lugar para descansar, va a aparecer en esta lista.</div>
    <a href="buscar.php" class="od-btn od-btn-dark">Buscar un lugar</a>
  </div>

</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('mis_reservas.js') ?>"></script>
</body>
</html>