<?php
/**
 * publicar_propiedad.php
 * Formulario para que un anfitrión publique una nueva propiedad.
 *
 * Requiere:
 *   - control_lanzamiento.php
 *   - conexion.php -> usado por publicar_propiedad-api.php
 *   - Sesión activa (si no hay, redirige a login con retorno a esta página)
 */
require_once __DIR__ . '/control_lanzamiento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['estado_sesion']) || $_SESSION['estado_sesion'] !== 'activa') {
    header('Location: login.php?redirect=' . urlencode('publicar_propiedad.php'));
    exit;
}

$nombre_usuario = htmlspecialchars($_SESSION['nombre_usuario']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Publica tu propiedad — Rentplace</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="publicar_propiedad.css">
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
    <span class="od-muted">Hola, <?= $nombre_usuario ?></span>
  </div>
</header>

<main class="container pp-wrap">

  <div class="pp-intro">
    <h1 class="pp-title">Publica tu propiedad</h1>
    <p class="pp-sub">Sin comisión por reserva: pagas una suscripción simple y el resto es tuyo. Completa los datos de tu propiedad para publicarla.</p>
  </div>

  <form id="form-propiedad" class="pp-form" novalidate>

    <section class="pp-section">
      <h2 class="pp-section-title">Datos básicos</h2>

      <div class="pp-field">
        <label class="pp-label" for="titulo">Título de la publicación</label>
        <input type="text" id="titulo" name="titulo" class="pp-input" placeholder="Ej. Cabaña de troncos con vista al lago" required maxlength="120">
      </div>

      <div class="pp-field">
        <label class="pp-label" for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" class="pp-input pp-textarea" placeholder="Cuenta qué hace especial a tu propiedad, el entorno, y qué pueden esperar tus huéspedes." required></textarea>
      </div>

      <div class="pp-field">
        <label class="pp-label" for="tipo">Tipo de propiedad</label>
        <select id="tipo" name="tipo" class="pp-input" required>
          <option value="">Selecciona un tipo</option>
          <option value="cabana">Cabaña</option>
          <option value="departamento">Departamento</option>
          <option value="casa">Casa</option>
        </select>
      </div>
    </section>

    <section class="pp-section">
      <h2 class="pp-section-title">Ubicación</h2>
      <div class="pp-field">
        <label class="pp-label" for="direccion">Dirección</label>
        <input type="text" id="direccion" name="direccion" class="pp-input" placeholder="Calle y número" required>
      </div>
      <div class="pp-field-row">
        <div class="pp-field">
          <label class="pp-label" for="ciudad">Ciudad</label>
          <input type="text" id="ciudad" name="ciudad" class="pp-input" placeholder="Ej. Pucón" required>
        </div>
        <div class="pp-field">
          <label class="pp-label" for="region">Región</label>
          <input type="text" id="region" name="region" class="pp-input" placeholder="Ej. Araucanía" required>
        </div>
      </div>
    </section>

    <section class="pp-section">
      <h2 class="pp-section-title">Capacidad</h2>
      <div class="pp-field-row pp-field-row-3">
        <div class="pp-field">
          <label class="pp-label" for="capacidad">Huéspedes</label>
          <input type="number" id="capacidad" name="capacidad" class="pp-input" min="1" value="2" required>
        </div>
        <div class="pp-field">
          <label class="pp-label" for="habitaciones">Habitaciones</label>
          <input type="number" id="habitaciones" name="habitaciones" class="pp-input" min="0" value="1" required>
        </div>
        <div class="pp-field">
          <label class="pp-label" for="banos">Baños</label>
          <input type="number" id="banos" name="banos" class="pp-input" min="0" value="1" required>
        </div>
      </div>
    </section>

    <section class="pp-section">
      <h2 class="pp-section-title">Precio</h2>
      <div class="pp-field-row">
        <div class="pp-field">
          <label class="pp-label" for="precio_noche">Precio por noche (CLP)</label>
          <input type="number" id="precio_noche" name="precio_noche" class="pp-input" min="0" step="1000" required>
        </div>
        <div class="pp-field">
          <label class="pp-label" for="precio_limpieza">Cargo de limpieza (CLP)</label>
          <input type="number" id="precio_limpieza" name="precio_limpieza" class="pp-input" min="0" step="1000" value="0">
        </div>
      </div>
      <div class="pp-transparency-note">
        💧 <span>Este precio es exactamente lo que verá el huésped. Rentplace no agrega comisión de servicio encima.</span>
      </div>
    </section>

    <section class="pp-section">
      <h2 class="pp-section-title">Comodidades</h2>
      <div class="pp-amenity-grid" id="amenity-grid">
        <label class="pp-amenity-chip"><input type="checkbox" name="amenities[]" value="🌿|Jardín privado"> 🌿 Jardín privado</label>
        <label class="pp-amenity-chip"><input type="checkbox" name="amenities[]" value="🔥|Chimenea"> 🔥 Chimenea</label>
        <label class="pp-amenity-chip"><input type="checkbox" name="amenities[]" value="📶|Wifi de alta velocidad"> 📶 Wifi de alta velocidad</label>
        <label class="pp-amenity-chip"><input type="checkbox" name="amenities[]" value="🚗|Estacionamiento gratis"> 🚗 Estacionamiento gratis</label>
        <label class="pp-amenity-chip"><input type="checkbox" name="amenities[]" value="🍳|Cocina equipada"> 🍳 Cocina equipada</label>
        <label class="pp-amenity-chip"><input type="checkbox" name="amenities[]" value="🐾|Acepta mascotas"> 🐾 Acepta mascotas</label>
        <label class="pp-amenity-chip"><input type="checkbox" name="amenities[]" value="🏊|Piscina"> 🏊 Piscina</label>
        <label class="pp-amenity-chip"><input type="checkbox" name="amenities[]" value="🧺|Lavadora"> 🧺 Lavadora</label>
        <label class="pp-amenity-chip"><input type="checkbox" name="amenities[]" value="❄️|Aire acondicionado"> ❄️ Aire acondicionado</label>
      </div>
    </section>

    <section class="pp-section">
      <h2 class="pp-section-title">Fotos</h2>
      <p class="pp-hint">Sube al menos 3 fotos. La primera será la foto de portada.</p>

      <label for="input-fotos" class="pp-dropzone">
        <span class="pp-dropzone-icon">＋</span>
        <span>Haz clic para seleccionar fotos</span>
      </label>
      <input type="file" id="input-fotos" accept="image/*" multiple class="d-none">

      <div id="preview-fotos" class="pp-photo-grid"></div>
    </section>

    <div id="mensaje-form" class="pp-msg d-none"></div>

    <button type="submit" id="btn-publicar" class="pp-btn-submit">Publicar propiedad</button>

  </form>

</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="publicar_propiedad.js"></script>
</body>
</html>