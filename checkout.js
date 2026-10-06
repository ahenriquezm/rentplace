/**
 * checkout.js
 * Controlador del módulo de checkout.
 * Depende de jQuery (incluido en checkout.php).
 */
$(function () {

  const API = 'checkout-api.php';
  const idReserva = $('#checkout-root').data('id-reserva');

  cargarReserva();
  bindPagoOpciones();
  bindSubmit();

  function cargarReserva() {
    $.ajax({
      url: API,
      method: 'GET',
      data: { action: 'detalle', reserva: idReserva },
      dataType: 'json'
    }).done(function (res) {
      if (!res.success) {
        mostrarErrorCarga(res.message || 'No pudimos cargar esta reserva.');
        return;
      }
      renderResumen(res.data);
      $('#estado-carga').addClass('d-none');
      $('#checkout-contenido').removeClass('d-none');
    }).fail(function () {
      mostrarErrorCarga('Error de conexión al cargar la reserva.');
    });
  }

  function mostrarErrorCarga(texto) {
    $('#estado-carga').text(texto);
  }

  function renderResumen(data) {
    $('#resumen-reserva').html(`
      <div class="co-summary-listing">
        <img src="${escapeAttr(data.foto_portada || 'https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?w=200')}">
        <div>
          <div style="font-weight:700;font-size:14.5px;">${escapeHtml(data.titulo)}</div>
          <div class="od-muted">${data.fecha_llegada} – ${data.fecha_salida} · ${data.huespedes} huésped(es)</div>
        </div>
      </div>
      <div class="co-cost-line"><span>$${formatearMoneda(data.precio_noche)} x ${data.noches} noche(s)</span><span>$${formatearMoneda(data.subtotal)}</span></div>
      <div class="co-cost-line"><span>Limpieza</span><span>$${formatearMoneda(data.precio_limpieza)}</span></div>
      <div class="co-cost-line"><span>Cargo por servicio</span><span>$${formatearMoneda(data.cargo_servicio)}</span></div>
      <div class="co-cost-line total"><span>Total</span><span>$${formatearMoneda(data.total)}</span></div>
      <div class="co-no-surprise">
        <b>Sin sorpresas al pagar</b>
        El monto que confirmas aquí es exactamente el mismo que viste en la ficha de la propiedad.
      </div>
    `);
  }

  function bindPagoOpciones() {
    $('.co-pay-opt').on('click', function () {
      $('.co-pay-opt').removeClass('active');
      $(this).addClass('active');
    });
  }

  function bindSubmit() {
    $('#form-checkout').on('submit', function (e) {
      e.preventDefault();
      ocultarMensaje();

      const nombre = $('#nombre-titular').val().trim();
      const email = $('#email-titular').val().trim();

      if (!nombre || !email) {
        mostrarMensaje('Completa tu nombre y correo antes de continuar.', false);
        return;
      }

      const $btn = $('#btn-confirmar');
      $btn.prop('disabled', true).text('Procesando pago...');

      $.ajax({
        url: API,
        method: 'POST',
        data: {
          action: 'confirmar_pago',
          reserva: idReserva,
          nombre: nombre,
          email: email,
          telefono: $('#telefono-titular').val().trim(),
          metodo: $('.co-pay-opt.active').data('metodo')
        },
        dataType: 'json'
      }).done(function (res) {
        if (!res.success) {
          mostrarMensaje(res.message || 'No pudimos procesar el pago.', false);
          $btn.prop('disabled', false).text('Confirmar y pagar');
          return;
        }
        mostrarExito();
      }).fail(function () {
        mostrarMensaje('Error de conexión. Intenta nuevamente.', false);
        $btn.prop('disabled', false).text('Confirmar y pagar');
      });
    });
  }

  function mostrarExito() {
    $('#checkout-contenido').html(`
      <div class="co-success" style="grid-column:1 / -1;">
        <div class="co-success-icon">✓</div>
        <div class="co-success-t">¡Reserva confirmada!</div>
        <div class="co-success-s">Te enviamos los detalles a tu correo. Que disfrutes tu descanso.</div>
        <a href="index.php" class="co-btn-submit" style="display:inline-block;width:auto;padding:14px 28px;">Volver al inicio</a>
      </div>
    `);
  }

  function mostrarMensaje(texto, ok) {
    $('#mensaje-checkout').removeClass('d-none ok error').addClass(ok ? 'ok' : 'error').text(texto);
  }

  function ocultarMensaje() {
    $('#mensaje-checkout').addClass('d-none');
  }

  function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-CL').format(Math.round(valor));
  }

  function escapeHtml(str) {
    return $('<div>').text(str || '').html();
  }

  function escapeAttr(str) {
    return (str || '').replace(/"/g, '&quot;');
  }

});