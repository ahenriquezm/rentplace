<?php
/**
 * index.php
 * Landing / página de inicio de Rentplace.
 *
 * Requiere:
 *   - conexion.php   -> variable mysqli $conexion (usado solo por index-api.php, no aquí directamente)
 *   - Sesión ya iniciada por el módulo de login.
 *     Variables esperadas: $_SESSION['estado_sesion'], $_SESSION['nombre_usuario']
 */
require_once __DIR__ . '/control_lanzamiento.php'; // mientras MODO_COMINGSOON=true, redirige a comingsoon.php
require_once __DIR__ . '/tarifas.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sesion_activa = isset($_SESSION['estado_sesion']) && $_SESSION['estado_sesion'] === 'activa';
$nombre_usuario = $sesion_activa ? htmlspecialchars($_SESSION['nombre_usuario']) : '';

// El estimador (index.js) usa exactamente los mismos planes que cobra el sistema.
$tarifas_json = htmlspecialchars(json_encode([
    'planes'     => TARIFA_PLANES,
    'referencia' => TARIFA_REFERENCIA_AIRBNB,
]), ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rentplace — Reserva tu próxima escapada</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="index.css">
</head>
<body>

<header class="od-header">
  <div class="container d-flex align-items-center justify-content-between py-3">
    <a href="index.php" class="od-brand d-flex align-items-center gap-2 text-decoration-none">
      <svg width="36" height="36" viewBox="0 0 34 34">
        <path d="M17 2 C10 2 5 7 5 14 C5 22 17 32 17 32 C17 32 29 22 29 14 C29 7 24 2 17 2 Z" fill="#4a7182"/>
        <circle cx="17" cy="14" r="5" fill="#c98a5c"/>
      </svg>
      <span class="od-brand-name">Rentplace</span>
    </a>
    <nav class="idx-nav">
      <a href="buscar.php">Explorar</a>
      <a href="#como-funciona">Cómo funciona</a>
      <a href="#precios">Precios anfitrión</a>
      <a href="publicar_propiedad.php">Publica tu propiedad</a>
    </nav>
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

<main>
  <div class="container">

    <!-- HERO -->
    <section class="idx-hero">
      <h1 class="idx-hero-title">Reserva tu<br><span>próxima escapada</span></h1>
      <p class="idx-hero-sub">Cabañas, departamentos y casas para descansar de verdad. Reserva directa, sin comisiones ocultas.</p>

      <form id="form-hero-busqueda" class="idx-search-bar" action="buscar.php" method="get">
        <div class="idx-seg">
          <label class="idx-label" for="hero-destino">Destino</label>
          <input type="text" id="hero-destino" name="destino" class="idx-seg-input" placeholder="¿A dónde vas?" autocomplete="off">
          <div id="sugerencias-destino" class="idx-suggestions d-none"></div>
        </div>
        <div class="idx-seg">
          <label class="idx-label" for="hero-llegada">Llegada</label>
          <input type="date" id="hero-llegada" name="llegada" class="idx-seg-input">
        </div>
        <div class="idx-seg idx-seg-last">
          <label class="idx-label" for="hero-salida">Salida</label>
          <input type="date" id="hero-salida" name="salida" class="idx-seg-input">
        </div>
        <div class="idx-seg idx-seg-last">
          <label class="idx-label" for="hero-huespedes">Huéspedes</label>
          <input type="number" id="hero-huespedes" name="huespedes" class="idx-seg-input" min="1" placeholder="¿Cuántos?">
        </div>
        <button type="submit" class="idx-search-go" aria-label="Buscar">⌕</button>
      </form>
    </section>

    <!-- CATEGORÍAS -->
    <section class="idx-block">
      <h2 class="idx-section-title">Elige tu estilo de estadía</h2>
      <div id="categorias-grid" class="idx-cat-grid">
        <div class="idx-skeleton-card"></div>
        <div class="idx-skeleton-card"></div>
        <div class="idx-skeleton-card"></div>
      </div>
    </section>

    <!-- DESTINOS DESTACADOS -->
    <section class="idx-block">
      <div class="idx-section-head">
        <h2 class="idx-section-title" style="margin:0;">Destinos destacados</h2>
        <a href="buscar.php" class="idx-see-all">Ver todos →</a>
      </div>
      <div id="destinos-grid" class="idx-dest-grid">
        <div class="idx-skeleton-dest"></div>
        <div class="idx-skeleton-dest"></div>
        <div class="idx-skeleton-dest"></div>
        <div class="idx-skeleton-dest"></div>
      </div>
    </section>

    <!-- CÓMO FUNCIONA -->
    <section class="idx-how" id="como-funciona">
      <h2 class="idx-section-title" style="text-align:center;">Cómo funciona</h2>
      <div class="idx-how-grid">
        <div class="idx-how-step"><div class="idx-how-num">1</div><div class="idx-how-t">Busca tu lugar</div><div class="idx-how-s">Filtra por destino, fechas y tipo de propiedad.</div></div>
        <div class="idx-how-step"><div class="idx-how-num">2</div><div class="idx-how-t">Reserva al instante</div><div class="idx-how-s">Confirmación inmediata, sin intermediarios.</div></div>
        <div class="idx-how-step"><div class="idx-how-num">3</div><div class="idx-how-t">Llega y descansa</div><div class="idx-how-s">Check-in simple y soporte si lo necesitas.</div></div>
      </div>
    </section>

    <!-- ESTIMADOR DE SUSCRIPCIÓN (anfitriones) -->
    <section class="idx-block idx-est" id="precios" data-tarifas="<?= $tarifas_json ?>">
      <div class="idx-est-head">
        <div class="idx-est-eyebrow">Para anfitriones</div>
        <h2 class="idx-section-title">Pagas 1 noche al mes. Nada más.</h2>
        <p class="idx-est-lead">Sin comisión por reserva: una suscripción fija por propiedad equivalente a una noche de arriendo.
          Paga semestral o anual y ahorra hasta un 30%. Mientras más arriendas, menos pagas en proporción.</p>
      </div>

      <div class="idx-est-grid">
        <form class="idx-est-form" id="est-form" onsubmit="return false;">
          <div class="idx-est-field">
            <label for="est-precio" class="idx-est-label">Precio por noche de tu propiedad</label>
            <div class="idx-est-money">
              <span>$</span>
              <input type="text" id="est-precio" inputmode="numeric" value="50.000" autocomplete="off">
            </div>
          </div>

          <div class="idx-est-field">
            <label for="est-noches" class="idx-est-label">
              Noches que arriendas al mes <output id="est-noches-out" for="est-noches">15</output>
            </label>
            <input type="range" id="est-noches" min="1" max="30" value="15">
            <div class="idx-est-scale"><span>1</span><span>15</span><span>30</span></div>
          </div>

          <div class="idx-est-field">
            <span class="idx-est-label" id="est-plan-label">Plan</span>
            <div class="idx-est-plans" role="radiogroup" aria-labelledby="est-plan-label">
              <?php foreach (TARIFA_PLANES as $clave => $plan): ?>
                <label class="idx-est-plan">
                  <input type="radio" name="est-plan" value="<?= $clave ?>" <?= $clave === 'anual' ? 'checked' : '' ?>>
                  <span class="n"><?= $plan['nombre'] ?></span>
                  <span class="d"><?= $plan['factor'] < 1 ? '−' . round((1 - $plan['factor']) * 100) . '%' : 'Sin permanencia' ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </form>

        <div class="idx-est-result" aria-live="polite">
          <div class="idx-est-label">Tu suscripción</div>
          <div class="idx-est-cuota"><span id="est-cuota">$35.000</span><small>/ mes</small></div>
          <div class="idx-est-periodo" id="est-periodo">Pagas $420.000 al año</div>

          <div class="idx-est-pct">
            <div><span id="est-pct">4,7%</span> de lo que facturas</div>
            <div class="idx-est-muted" id="est-facturas">Facturas $750.000 al mes</div>
          </div>

          <div class="idx-est-bars">
            <div class="idx-est-bar">
              <div class="idx-est-bar-top"><span>Rentplace</span><b id="est-rp-val">$35.000</b></div>
              <div class="idx-est-track"><div class="idx-est-fill rp" id="est-rp-bar"></div></div>
            </div>
            <div class="idx-est-bar">
              <div class="idx-est-bar-top"><span>Comisión típica de 15,5%</span><b id="est-ab-val">$116.250</b></div>
              <div class="idx-est-track"><div class="idx-est-fill ab" id="est-ab-bar"></div></div>
            </div>
          </div>

          <div class="idx-est-ahorro" id="est-ahorro">Ahorras $975.000 al año</div>
          <a href="publicar_propiedad.php" class="idx-btn-gold">Publicar mi propiedad</a>
        </div>
      </div>
    </section>

    <!-- ANFITRIONES -->
    <section class="idx-host">
      <div>
        <h2 class="idx-host-title">¿Tienes una propiedad?<br>Publícala en Rentplace.</h2>
        <p class="idx-host-sub">Llega a viajeros que buscan cabañas, deptos y casas para descansar. Pagas 1 noche al mes por propiedad y el resto de cada reserva es tuyo.</p>
        <a href="publicar_propiedad.php" class="idx-btn-gold">Publica tu propiedad</a>
      </div>
      <img src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=600" alt="Anfitrión entregando llaves">
    </section>

  </div>

  <footer class="od-footer">
    <div class="container">
      <div class="idx-foot-grid">
        <div class="idx-foot-col">
          <div class="d-flex align-items-center gap-2">
            <svg width="30" height="30" viewBox="0 0 34 34"><path d="M17 2 C10 2 5 7 5 14 C5 22 17 32 17 32 C17 32 29 22 29 14 C29 7 24 2 17 2 Z" fill="#4a7182"/><circle cx="17" cy="14" r="5" fill="#c98a5c"/></svg>
            <span class="od-brand-name" style="font-size:20px;">Rentplace</span>
          </div>
          <p class="od-muted mt-2">Cabañas, departamentos y casas para tu próximo descanso.</p>
        </div>
        <div class="idx-foot-col">
          <div class="idx-foot-head">Explorar</div>
          <a href="buscar.php?tipo=cabana">Cabañas</a>
          <a href="buscar.php?tipo=departamento">Departamentos</a>
          <a href="buscar.php?tipo=casa">Casas</a>
        </div>
        <div class="idx-foot-col">
          <div class="idx-foot-head">Anfitriones</div>
          <a href="publicar_propiedad.php">Publica tu propiedad</a>
          <a href="#precios">Precios y planes</a>
        </div>
        <div class="idx-foot-col">
          <div class="idx-foot-head">Contacto</div>
          <a href="mailto:hola@rentplace.cl">hola@rentplace.cl</a>
          <div class="d-flex gap-3 mt-1">
            <a href="#">Instagram</a><a href="#">TikTok</a>
          </div>
        </div>
      </div>
      <div class="idx-foot-copy">© 2026 Rentplace. Todos los derechos reservados.</div>
    </div>
  </footer>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="index.js"></script>
</body>
</html>