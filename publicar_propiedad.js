/**
 * publicar_propiedad.js
 * Controlador del módulo de publicación de propiedades.
 * Depende de jQuery (incluido en publicar_propiedad.php).
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

  const API = 'publicar_propiedad-api.php';
  let archivosSeleccionados = [];

  bindSelectorFotos();
  bindSubmit();

  /* ---------------- Fotos ---------------- */

  function bindSelectorFotos() {
    $('#input-fotos').on('change', function (e) {
      const nuevos = Array.from(e.target.files || []);
      archivosSeleccionados = archivosSeleccionados.concat(nuevos);
      renderPreview();
      $(this).val(''); // permite volver a elegir el mismo archivo si lo saca y lo agrega de nuevo
    });

    $('#preview-fotos').on('click', '.pp-remove', function () {
      const idx = $(this).data('idx');
      archivosSeleccionados.splice(idx, 1);
      renderPreview();
    });
  }

  function renderPreview() {
    const $grid = $('#preview-fotos');
    $grid.empty();

    archivosSeleccionados.forEach(function (archivo, idx) {
      const url = URL.createObjectURL(archivo);
      const $item = $(`
        <div class="pp-photo-item">
          ${idx === 0 ? '<span class="pp-cover-tag">Portada</span>' : ''}
          <img src="${url}" alt="">
          <button type="button" class="pp-remove" data-idx="${idx}">✕</button>
        </div>
      `);
      $grid.append($item);
    });
  }

  /* ---------------- Envío del formulario ---------------- */

  function bindSubmit() {
    $('#form-propiedad').on('submit', function (e) {
      e.preventDefault();
      ocultarMensaje();

      if (archivosSeleccionados.length < 3) {
        mostrarMensaje('Sube al menos 3 fotos de tu propiedad.', false);
        return;
      }

      const formData = new FormData();
      formData.append('action', 'crear');
      formData.append('titulo', $('#titulo').val().trim());
      formData.append('descripcion', $('#descripcion').val().trim());
      formData.append('tipo', $('#tipo').val());
      formData.append('direccion', $('#direccion').val().trim());
      formData.append('ciudad', $('#ciudad').val().trim());
      formData.append('region', $('#region').val().trim());
      formData.append('capacidad', $('#capacidad').val());
      formData.append('habitaciones', $('#habitaciones').val());
      formData.append('banos', $('#banos').val());
      formData.append('precio_noche', $('#precio_noche').val());
      formData.append('precio_limpieza', $('#precio_limpieza').val() || 0);

      $('input[name="amenities[]"]:checked').each(function () {
        formData.append('amenities[]', $(this).val());
      });

      archivosSeleccionados.forEach(function (archivo) {
        formData.append('fotos[]', archivo);
      });

      const $btn = $('#btn-publicar');
      $btn.prop('disabled', true).text('Publicando...');

      $.ajax({
        url: API,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json'
      }).done(function (res) {
        if (!res.success) {
          mostrarMensaje(res.message || 'No pudimos publicar tu propiedad.', false);
          $btn.prop('disabled', false).text('Publicar propiedad');
          return;
        }
        window.location.href = 'ficha_propiedad.php?id=' + res.data.id_propiedad;
      }).fail(function () {
        mostrarMensaje('Error de conexión. Intenta nuevamente.', false);
        $btn.prop('disabled', false).text('Publicar propiedad');
      });
    });
  }

  function mostrarMensaje(texto, ok) {
    $('#mensaje-form').removeClass('d-none ok error').addClass(ok ? 'ok' : 'error').text(texto);
    $('html, body').animate({ scrollTop: $('#mensaje-form').offset().top - 100 }, 300);
  }

  function ocultarMensaje() {
    $('#mensaje-form').addClass('d-none');
  }

});