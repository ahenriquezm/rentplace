/**
 * comingsoon.js
 * Controlador de la landing de pre-lanzamiento.
 * Depende de jQuery (incluido en comingsoon.php).
 */
$(function () {

  // Marca con .is-checked la etiqueta de cada radio/checkbox seleccionado.
  // Respaldo para navegadores sin soporte de :has() en CSS.
  function sincronizarSeleccion() {
    $('label').has('input[type="radio"], input[type="checkbox"]').each(function () {
      $(this).toggleClass('is-checked', $(this).find('input').prop('checked'));
    });
  }
  $(document).on('change', 'input[type="radio"], input[type="checkbox"]', sincronizarSeleccion);
  sincronizarSeleccion();

  const API = 'comingsoon-api.php';

  cargarContador();
  bindFormulario();

  function cargarContador() {
    $.ajax({
      url: API,
      method: 'GET',
      data: { action: 'contador' },
      dataType: 'json'
    }).done(function (res) {
      if (!res.success || res.data.total < 5) return; // no mostrar contador si es muy bajo, para no restar credibilidad
      $('#contador-numero').text(res.data.total);
      $('#contador-suscriptores').removeClass('d-none');
    });
  }

  function bindFormulario() {
    $('#form-suscripcion').on('submit', function (e) {
      e.preventDefault();

      const email = $('#email-suscripcion').val().trim();
      if (!validarEmail(email)) {
        mostrarMensaje('Ingresa un correo válido.', false);
        return;
      }

      const $btn = $('#btn-suscribir');
      $btn.prop('disabled', true).text('Enviando...');

      $.ajax({
        url: API,
        method: 'POST',
        data: { action: 'suscribir', email: email },
        dataType: 'json'
      }).done(function (res) {
        if (!res.success) {
          mostrarMensaje(res.message || 'No pudimos registrar tu correo.', false);
          $btn.prop('disabled', false).text('Quiero ser el primero en saber');
          return;
        }
        mostrarExito();
      }).fail(function () {
        mostrarMensaje('Error de conexión. Intenta nuevamente.', false);
        $btn.prop('disabled', false).text('Quiero ser el primero en saber');
      });
    });
  }

  function mostrarExito() {
    $('.cs-form').replaceWith(
      '<div class="cs-msg ok" style="max-width:480px;">' +
      '✓ ¡Listo! Te avisamos apenas lancemos. Gracias por sumarte temprano.' +
      '</div>'
    );
    $('.cs-microcopy').text('Compartí Rentplace con alguien que también está cansado de las comisiones altas.');
    cargarContador();
  }

  function mostrarMensaje(texto, ok) {
    $('#mensaje-suscripcion')
      .removeClass('d-none ok error')
      .addClass(ok ? 'ok' : 'error')
      .text(texto);
  }

  function validarEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

});