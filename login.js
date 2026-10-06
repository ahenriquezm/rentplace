/**
 * login.js
 * Controlador del módulo de login / registro.
 * Depende de jQuery (incluido en login.php).
 */
$(function () {

  const API = 'login-api.php';
  const redirectUrl = $('#redirect-url').val() || 'index.php';

  bindTabs();
  bindFormIngresar();
  bindFormRegistro();

  function bindTabs() {
    $('.lg-tab').on('click', function () {
      const tab = $(this).data('tab');
      $('.lg-tab').removeClass('active');
      $(this).addClass('active');
      $('#form-ingresar, #form-registro').addClass('d-none');
      $('#form-' + tab).removeClass('d-none');
    });
  }

  function bindFormIngresar() {
    $('#form-ingresar').on('submit', function (e) {
      e.preventDefault();

      const email = $('#login-email').val().trim();
      const password = $('#login-password').val();

      ocultarMensaje('msg-ingresar');

      if (!email || !password) {
        mostrarMensaje('msg-ingresar', 'Completa correo y contraseña.', false);
        return;
      }

      const $btn = $(this).find('.lg-btn');
      $btn.prop('disabled', true).text('Ingresando...');

      $.ajax({
        url: API,
        method: 'POST',
        data: { action: 'iniciar_sesion', email: email, password: password },
        dataType: 'json'
      }).done(function (res) {
        if (!res.success) {
          mostrarMensaje('msg-ingresar', res.message || 'No pudimos iniciar sesión.', false);
          $btn.prop('disabled', false).text('Iniciar sesión');
          return;
        }
        window.location.href = redirectUrl;
      }).fail(function () {
        mostrarMensaje('msg-ingresar', 'Error de conexión. Intenta nuevamente.', false);
        $btn.prop('disabled', false).text('Iniciar sesión');
      });
    });
  }

  function bindFormRegistro() {
    $('#form-registro').on('submit', function (e) {
      e.preventDefault();

      const nombre = $('#reg-nombre').val().trim();
      const apellido = $('#reg-apellido').val().trim();
      const email = $('#reg-email').val().trim();
      const password = $('#reg-password').val();
      const password2 = $('#reg-password2').val();

      ocultarMensaje('msg-registro');

      if (!nombre || !apellido || !email || !password || !password2) {
        mostrarMensaje('msg-registro', 'Completa todos los campos.', false);
        return;
      }
      if (password.length < 8) {
        mostrarMensaje('msg-registro', 'La contraseña debe tener al menos 8 caracteres.', false);
        return;
      }
      if (password !== password2) {
        mostrarMensaje('msg-registro', 'Las contraseñas no coinciden.', false);
        return;
      }

      const $btn = $(this).find('.lg-btn');
      $btn.prop('disabled', true).text('Creando cuenta...');

      $.ajax({
        url: API,
        method: 'POST',
        data: { action: 'registrar', nombre: nombre, apellido: apellido, email: email, password: password },
        dataType: 'json'
      }).done(function (res) {
        if (!res.success) {
          mostrarMensaje('msg-registro', res.message || 'No pudimos crear tu cuenta.', false);
          $btn.prop('disabled', false).text('Crear cuenta');
          return;
        }
        window.location.href = redirectUrl;
      }).fail(function () {
        mostrarMensaje('msg-registro', 'Error de conexión. Intenta nuevamente.', false);
        $btn.prop('disabled', false).text('Crear cuenta');
      });
    });
  }

  function mostrarMensaje(id, texto, ok) {
    $('#' + id).removeClass('d-none ok error').addClass(ok ? 'ok' : 'error').text(texto);
  }

  function ocultarMensaje(id) {
    $('#' + id).addClass('d-none');
  }

});