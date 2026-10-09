<?php
/**
 * checkout.php
 * Confirmación y pago de una reserva ya creada (estado 'pendiente_pago').
 *
 * Requiere:
 *   - control_lanzamiento.php
 *   - conexion.php -> usado por checkout-api.php
 *   - Sesión activa
 *
 * Parámetro GET requerido: reserva (id de la reserva creada en ficha_propiedad-api.php)
 *
 * NOTA IMPORTANTE SOBRE PAGOS:
 * Este módulo todavía NO está conectado a una pasarela de pago real (Stripe, Webpay, etc.).
 * El botón "Confirmar y pagar" simula el pago y marca la reserva como 'confirmada'
 * directamente, para que todo el flujo (publicar → buscar → reservar → pagar) funcione
 * de punta a punta mientras se integra el cobro real. Busca el comentario
 * "AQUÍ VA LA INTEGRACIÓN DE PAGO REAL" en checkout-api.php para saber exactamente
 * dónde reemplazar la simulación.
 */
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/control_lanzamiento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['estado_sesion']) || $_SESSION['estado_sesion'] !== 'activa') {
    $volver = 'checkout.php?reserva=' . urlencode($_GET['reserva'] ?? '');
    header('Location: login.php?redirect=' . urlencode($volver));
    exit;
}

$id_reserva = (int) ($_GET['reserva'] ?? 0);
if ($id_reserva <= 0) {
    header('Location: index.php');
    exit;
}

$nombre_usuario = htmlspecialchars($_SESSION['nombre_usuario']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Confirma y paga — Rentplace</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('checkout.css') ?>">
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
    <span class="od-muted">Hola, <?= $nombre_usuario ?></span>
  </div>
</header>

<main class="container" id="checkout-root" data-id-reserva="<?= $id_reserva ?>">

  <div id="estado-carga" class="co-loading">Cargando tu reserva...</div>

  <div id="checkout-contenido" class="co-grid d-none">
    <div>
      <h1 class="co-title">Confirma y paga</h1>

      <form id="form-checkout" novalidate>
        <div class="co-field">
          <label class="co-label" for="nombre-titular">Nombre completo</label>
          <input type="text" id="nombre-titular" class="co-input" placeholder="Como aparece en tu identificación" required>
        </div>

        <div class="co-field-row">
          <div class="co-field">
            <label class="co-label" for="email-titular">Correo electrónico</label>
            <input type="email" id="email-titular" class="co-input" required>
          </div>
          <div class="co-field">
            <label class="co-label" for="telefono-titular">Teléfono</label>
            <input type="tel" id="telefono-titular" class="co-input" placeholder="+56 9 ....">
          </div>
        </div>

        <div class="co-hr"></div>
        <h3 class="co-h3">Método de pago</h3>

        <div class="co-pay-options">
          <div class="co-pay-opt active" data-metodo="tarjeta">Tarjeta</div>
          <div class="co-pay-opt" data-metodo="transferencia">Transferencia</div>
        </div>

        <div class="co-field-row">
          <div class="co-field">
            <label class="co-label" for="numero-tarjeta">Número de tarjeta</label>
            <input type="text" id="numero-tarjeta" class="co-input" placeholder="•••• •••• •••• ••••">
          </div>
          <div class="co-field">
            <label class="co-label" for="vencimiento-tarjeta">Vencimiento / CVC</label>
            <input type="text" id="vencimiento-tarjeta" class="co-input" placeholder="MM/AA · CVC">
          </div>
        </div>

        <div id="mensaje-checkout" class="co-msg d-none"></div>

        <button type="submit" id="btn-confirmar" class="co-btn-submit">Confirmar y pagar</button>
      </form>
    </div>

    <div class="co-summary" id="resumen-reserva"><!-- resumen de la reserva, vía JS --></div>
  </div>

</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= asset('checkout.js') ?>"></script>
</body>
</html>