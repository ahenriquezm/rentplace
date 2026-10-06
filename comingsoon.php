<?php
/**
 * comingsoon.php
 * Landing de pre-lanzamiento (coming soon) de Rentplace.
 * Objetivo único: captar el email del visitante antes del lanzamiento.
 *
 * Requiere:
 *   - conexion.php (usado por comingsoon-api.php, no directamente aquí)
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentplace — Muy pronto</title>
<meta name="description" content="Rentplace: reserva cabañas, departamentos y casas para tu descanso, sin comisiones ocultas. Sé el primero en enterarte cuando lancemos.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="comingsoon.css">
</head>
<body>

<div class="cs-blob cs-blob-1"></div>
<div class="cs-blob cs-blob-2"></div>

<div class="cs-wrap">

  <header class="cs-header">
    <div class="cs-brand">
      <svg width="34" height="34" viewBox="0 0 34 34">
        <path d="M17 2 C10 2 5 7 5 14 C5 22 17 32 17 32 C17 32 29 22 29 14 C29 7 24 2 17 2 Z" fill="#4a7182"/>
        <circle cx="17" cy="14" r="5" fill="#c98a5c"/>
      </svg>
      <span class="cs-brand-name">Rentplace</span>
    </div>
    <div class="cs-social">
      <a href="#" aria-label="Instagram">Instagram</a>
      <a href="#" aria-label="TikTok">TikTok</a>
    </div>
  </header>

  <main class="cs-hero">
    <div class="cs-eyebrow">Muy pronto en Chile</div>
    <h1 class="cs-title">Reserva y publica<br><span>sin las comisiones de siempre.</span></h1>
    <p class="cs-sub">
      Rentplace nace de un reclamo que escuchamos en todos lados: las comisiones de las plataformas de reserva
      subieron, y terminan pagándolas tanto el anfitrión como el huésped. Aquí el anfitrión paga una suscripción
      simple —no una comisión por cada reserva— y el huésped ve el precio final desde el primer clic, sin cargos
      que aparecen recién al momento de pagar.
    </p>

    <form id="form-suscripcion" class="cs-form" novalidate>
      <div class="cs-perfil-group" role="radiogroup" aria-label="¿Qué te trae a Rentplace?">
        <label class="cs-perfil-opt">
          <input type="radio" name="perfil" value="huesped" required> Quiero reservar
        </label>
        <label class="cs-perfil-opt">
          <input type="radio" name="perfil" value="anfitrion" required> Quiero publicar mi propiedad
        </label>
        <label class="cs-perfil-opt">
          <input type="radio" name="perfil" value="ambos" required> Ambas cosas
        </label>
      </div>

      <div class="cs-form-row">
        <input type="email" id="email-suscripcion" class="cs-input" placeholder="tu@correo.com" required autocomplete="email">
        <button type="submit" id="btn-suscribir" class="cs-btn">Quiero ser el primero en saber</button>
      </div>
    </form>
    <div id="mensaje-suscripcion" class="cs-msg d-none"></div>
    <div class="cs-microcopy">Sin spam. Un solo correo, el día que lancemos.</div>

    <div id="contador-suscriptores" class="cs-counter d-none">
      <span id="contador-numero">0</span> personas ya se anotaron
    </div>
  </main>

  <section class="cs-features">
    <div class="cs-feature">
      <div class="cs-feature-icon">🤝</div>
      <div class="cs-feature-t">Sin comisión por reserva, anfitrión</div>
      <div class="cs-feature-s">Pagas una suscripción fija, no un porcentaje de cada reserva. Publicas sabiendo exactamente cuánto te cuesta el mes, no cuánto te descuenta cada vez.</div>
    </div>
    <div class="cs-feature">
      <div class="cs-feature-icon">💧</div>
      <div class="cs-feature-t">Precio final, huésped</div>
      <div class="cs-feature-s">Lo que ves es lo que pagas. Sin comisión de servicio que aparece recién al momento de pagar.</div>
    </div>
    <div class="cs-feature">
      <div class="cs-feature-icon">🧭</div>
      <div class="cs-feature-t">Reserva directa</div>
      <div class="cs-feature-s">Confirmación al instante, sin vueltas ni intermediarios innecesarios de ningún lado.</div>
    </div>
  </section>

  <footer class="cs-footer">
    <div>© 2026 Rentplace. Todos los derechos reservados.</div>
    <a href="mailto:hola@rentplace.cl">hola@rentplace.cl</a>
  </footer>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="comingsoon.js"></script>
</body>
</html>