<?php
/**
 * login.php
 * Módulo de inicio de sesión y creación de cuenta.
 *
 * Requiere:
 *   - control_lanzamiento.php
 *   - conexion.php -> usado por login-api.php, no directamente aquí
 *
 * Parámetro GET opcional:
 *   redirect -> URL a la que volver después de iniciar sesión (ej. desde ficha_propiedad.php)
 */
require_once __DIR__ . '/control_lanzamiento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya hay sesión activa, no tiene sentido mostrar el login de nuevo.
if (isset($_SESSION['estado_sesion']) && $_SESSION['estado_sesion'] === 'activa') {
    header('Location: index.php');
    exit;
}

$redirect = $_GET['redirect'] ?? 'index.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingresar — Rentplace</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="login.css">
</head>
<body>

<header class="lg-header">
  <a href="index.php" class="lg-brand">
    <svg width="30" height="30" viewBox="0 0 34 34">
      <path d="M17 2 C10 2 5 7 5 14 C5 22 17 32 17 32 C17 32 29 22 29 14 C29 7 24 2 17 2 Z" fill="#4A7182"/>
      <circle cx="17" cy="14" r="5" fill="#C98A5C"/>
    </svg>
    <span>Rentplace</span>
  </a>
</header>

<main class="lg-wrap">
  <div class="lg-card">

    <div class="lg-tabs">
      <button type="button" class="lg-tab active" data-tab="ingresar">Ingresar</button>
      <button type="button" class="lg-tab" data-tab="registro">Crear cuenta</button>
    </div>

    <input type="hidden" id="redirect-url" value="<?= htmlspecialchars($redirect) ?>">

    <!-- INGRESAR -->
    <form id="form-ingresar" class="lg-form" novalidate>
      <div class="lg-field">
        <label class="lg-label" for="login-email">Correo electrónico</label>
        <input type="email" id="login-email" class="lg-input" required autocomplete="email">
      </div>
      <div class="lg-field">
        <label class="lg-label" for="login-password">Contraseña</label>
        <input type="password" id="login-password" class="lg-input" required autocomplete="current-password">
      </div>
      <div id="msg-ingresar" class="lg-msg d-none"></div>
      <button type="submit" class="lg-btn">Iniciar sesión</button>
    </form>

    <!-- CREAR CUENTA -->
    <form id="form-registro" class="lg-form d-none" novalidate>
      <div class="lg-field-row">
        <div class="lg-field">
          <label class="lg-label" for="reg-nombre">Nombre</label>
          <input type="text" id="reg-nombre" class="lg-input" required>
        </div>
        <div class="lg-field">
          <label class="lg-label" for="reg-apellido">Apellido</label>
          <input type="text" id="reg-apellido" class="lg-input" required>
        </div>
      </div>
      <div class="lg-field">
        <label class="lg-label" for="reg-email">Correo electrónico</label>
        <input type="email" id="reg-email" class="lg-input" required autocomplete="email">
      </div>
      <div class="lg-field">
        <label class="lg-label" for="reg-password">Contraseña</label>
        <input type="password" id="reg-password" class="lg-input" required autocomplete="new-password" minlength="8">
        <div class="lg-hint">Mínimo 8 caracteres.</div>
      </div>
      <div class="lg-field">
        <label class="lg-label" for="reg-password2">Confirmar contraseña</label>
        <input type="password" id="reg-password2" class="lg-input" required autocomplete="new-password">
      </div>
      <div id="msg-registro" class="lg-msg d-none"></div>
      <button type="submit" class="lg-btn">Crear cuenta</button>
    </form>

  </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="login.js"></script>
</body>
</html>