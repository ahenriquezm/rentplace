/**
 * ficha_propiedad.js
 * Controlador de la ficha de propiedad: galería, detalle, disponibilidad con
 * precio total en vivo y creación de la reserva (luego pasa a checkout.php).
 * Depende de jQuery y SweetAlert2 (incluidos en ficha_propiedad.php).
 */
$(function () {

  const API = 'ficha_propiedad.api.php';
  const $root = $('#ficha-root');
  const idPropiedad = $root.data('id-propiedad');
  const sesionActiva = String($root.data('sesion-activa')) === '1';

  const TIPOS = { cabana: 'Cabaña', departamento: 'Departamento', casa: 'Casa' };
  const FOTO_DEFECTO = 'https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?w=1200';

  let propiedad = null;
  let cotizacion = null;   // última respuesta de disponibilidad válida
  let consultaActual = null;

  cargarDetalle();
  bindFormulario();

  /* ==================== DETALLE ==================== */

  function cargarDetalle() {
    $.ajax({ url: API, method: 'GET', data: { action: 'detalle', id: idPropiedad }, dataType: 'json' })
      .done(function (res) {
        if (!res.success) { mostrarNoEncontrada(res.message); return; }
        propiedad = res.data;
        renderDetalle(propiedad);
        prellenarDesdeUrl();
      })
      .fail(function (xhr) {
        const msg = xhr.responseJSON && xhr.responseJSON.message;
        mostrarNoEncontrada(msg || 'No pudimos cargar esta propiedad.');
      });
  }

  function mostrarNoEncontrada(texto) {
    $root.find('.od-gallery, .od-detail-body').remove();
    $root.append(`
      <div class="od-not-found">
        <h1 class="od-title">${escapeHtml(texto || 'Propiedad no encontrada.')}</h1>
        <p class="od-muted">Puede que ya no esté publicada.</p>
        <a href="buscar.php" class="od-btn od-btn-dark">Ver otras propiedades</a>
      </div>`);
    $('.od-mobile-bar').remove();
  }

  function renderDetalle(p) {
    document.title = p.titulo + ' — Rentplace';

    renderGaleria(p.fotos || []);

    $('#titulo-propiedad').text(p.titulo);
    const specs = [
      TIPOS[p.tipo] || '',
      `${p.capacidad} huésped${p.capacidad === 1 ? '' : 'es'}`,
      p.habitaciones ? `${p.habitaciones} habitaci${p.habitaciones === 1 ? 'ón' : 'ones'}` : '',
      p.banos ? `${p.banos} baño${p.banos === 1 ? '' : 's'}` : ''
    ].filter(Boolean);
    $('#subtitulo-propiedad').html(`
      <div>${escapeHtml(p.ciudad)}, ${escapeHtml(p.region)}</div>
      <div class="od-specs">${specs.map(function (s) { return `<span>${escapeHtml(s)}</span>`; }).join('<span>·</span>')}</div>
    `);

    const a = p.anfitrion || {};
    const avatar = a.avatar_url
      ? `<img class="od-avatar" src="${escapeAttr(a.avatar_url)}" alt="">`
      : `<div class="od-avatar" style="background:#4a7182;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:20px;">${escapeHtml((a.nombre || '?').charAt(0).toUpperCase())}</div>`;
    const horas = parseInt(a.tiempo_respuesta_horas, 10) || 1;
    $('#host-row').html(`
      ${avatar}
      <div>
        <div style="font-weight:700;">Anfitrión: ${escapeHtml(a.nombre || '')}</div>
        <div class="od-muted">${a.anio_registro ? 'En Rentplace desde ' + escapeHtml(String(a.anio_registro)) + ' · ' : ''}Responde en menos de ${horas} hora${horas === 1 ? '' : 's'}</div>
      </div>
    `);

    renderDescripcion(p.descripcion || '');

    const amenities = p.amenities || [];
    $('#amenities-grid').html(amenities.length
      ? amenities.map(function (am) { return `<div>${escapeHtml(am.icono)} ${escapeHtml(am.nombre)}</div>`; }).join('')
      : '<div class="od-muted">El anfitrión aún no detalla los servicios.</div>');

    $('#precio-noche').text('$' + formatearMoneda(p.precio_noche));
    $('#huespedes').attr('max', p.capacidad);
    actualizarBarraMovil();
  }

  function renderGaleria(fotos) {
    const urls = fotos.map(function (f) { return f.url; });
    if (urls.length === 0) urls.push(FOTO_DEFECTO);

    const img = function (url, i) {
      return `<img src="${escapeAttr(url)}" alt="Foto ${i + 1} de la propiedad" data-indice="${i}" ${i > 0 ? 'loading="lazy"' : ''}>`;
    };
    const extra = urls.length > 3 ? `<span>Ver las ${urls.length} fotos</span>` : '';

    $('#galeria').toggleClass('od-gallery-single', urls.length < 3);
    if (urls.length < 3) {
      $('#galeria').html(`<div class="od-gallery-main">${img(urls[0], 0)}</div>`);
      $('#galeria').on('click', 'img', function () { abrirVisor(urls, 0); });
      return;
    }

    $('#galeria').html(`
      <div class="od-gallery-main od-gallery-count">${img(urls[0], 0)}${extra}</div>
      <div class="od-gallery-side">
        ${urls[1] ? img(urls[1], 1) : '<div class="od-skeleton" style="animation:none"></div>'}
        ${urls[2] ? img(urls[2], 2) : '<div class="od-skeleton" style="animation:none"></div>'}
      </div>
    `);

    $('#galeria').on('click', 'img', function () {
      abrirVisor(urls, parseInt($(this).data('indice'), 10) || 0);
    });
  }

  function abrirVisor(urls, indice) {
    if (typeof Swal === 'undefined') return;
    const mostrar = function (i) {
      Swal.fire({
        imageUrl: urls[i],
        imageAlt: `Foto ${i + 1} de ${urls.length}`,
        title: `${i + 1} / ${urls.length}`,
        width: 900,
        showCancelButton: urls.length > 1,
        showConfirmButton: urls.length > 1,
        showCloseButton: true,
        confirmButtonText: 'Siguiente →',
        cancelButtonText: '← Anterior',
        confirmButtonColor: '#4a7182',
        cancelButtonColor: '#8A909C'
      }).then(function (r) {
        if (r.isConfirmed) mostrar((i + 1) % urls.length);
        else if (r.dismiss === Swal.DismissReason.cancel) mostrar((i - 1 + urls.length) % urls.length);
      });
    };
    mostrar(indice);
  }

  function renderDescripcion(texto) {
    const $d = $('#descripcion-propiedad');
    if (!texto) { $d.next('.od-hr').remove(); $d.remove(); return; }
    $d.text(texto);
    if (texto.length > 420) {
      $d.addClass('recortada');
      const $btn = $('<button type="button" class="od-link-btn">Leer más</button>');
      $btn.on('click', function () {
        const recortada = $d.toggleClass('recortada').hasClass('recortada');
        $btn.text(recortada ? 'Leer más' : 'Mostrar menos');
      });
      $d.after($btn);
    }
  }

  /* ==================== FECHAS Y DISPONIBILIDAD ==================== */

  function prellenarDesdeUrl() {
    const params = new URLSearchParams(window.location.search);
    const hoy = fechaIso(new Date());
    $('#fecha-llegada').attr('min', hoy);
    $('#fecha-salida').attr('min', hoy);

    const llegada = params.get('llegada');
    const salida = params.get('salida');
    const huespedes = parseInt(params.get('huespedes'), 10);

    if (esFecha(llegada) && llegada >= hoy) $('#fecha-llegada').val(llegada);
    if (esFecha(salida) && salida > ($('#fecha-llegada').val() || hoy)) $('#fecha-salida').val(salida);
    if (huespedes > 0) $('#huespedes').val(Math.min(huespedes, propiedad.capacidad));

    sincronizarMinSalida();
    consultarDisponibilidad();
  }

  function bindFormulario() {
    $('#fecha-llegada').on('change', function () {
      sincronizarMinSalida();
      consultarDisponibilidad();
    });
    $('#fecha-salida, #huespedes').on('change input', consultarDisponibilidad);

    $('#form-reserva').on('submit', function (e) {
      e.preventDefault();
      reservar();
    });

    $('#barra-reservar').on('click', function () {
      if (cotizacion && cotizacion.disponible) {
        reservar();
      } else {
        $('html, body').animate({ scrollTop: $('.od-booking-card').offset().top - 16 }, 250);
        $('#fecha-llegada').trigger('focus');
      }
    });
  }

  function sincronizarMinSalida() {
    const llegada = $('#fecha-llegada').val();
    if (!llegada) return;
    const minSalida = fechaIso(sumarDias(new Date(llegada + 'T00:00:00'), 1));
    $('#fecha-salida').attr('min', minSalida);
    if ($('#fecha-salida').val() && $('#fecha-salida').val() < minSalida) {
      $('#fecha-salida').val('');
    }
  }

  function consultarDisponibilidad() {
    if (!propiedad) return;

    const llegada = $('#fecha-llegada').val();
    const salida = $('#fecha-salida').val();
    const huespedes = parseInt($('#huespedes').val(), 10) || 1;

    cotizacion = null;
    $('#btn-reservar').prop('disabled', true).text('Reservar');
    actualizarBarraMovil();

    if (!llegada || !salida) {
      $('#desglose-precio').addClass('d-none');
      ocultarMensaje();
      return;
    }
    if (huespedes > propiedad.capacidad) {
      $('#desglose-precio').addClass('d-none');
      mostrarMensaje(`Esta propiedad admite hasta ${propiedad.capacidad} huésped${propiedad.capacidad === 1 ? '' : 'es'}.`, false);
      return;
    }

    if (consultaActual) consultaActual.abort();
    mostrarMensaje('Revisando disponibilidad...', true);

    consultaActual = $.ajax({
      url: API, method: 'GET', dataType: 'json',
      data: { action: 'disponibilidad', id: idPropiedad, llegada: llegada, salida: salida, huespedes: huespedes }
    }).done(function (res) {
      if (!res.success) {
        $('#desglose-precio').addClass('d-none');
        mostrarMensaje(res.message || 'Revisa las fechas.', false);
        return;
      }
      renderCotizacion(res.data);
    }).fail(function (xhr, estado) {
      if (estado === 'abort') return;
      $('#desglose-precio').addClass('d-none');
      const msg = xhr.responseJSON && xhr.responseJSON.message;
      mostrarMensaje(msg || 'No pudimos revisar la disponibilidad. Intenta nuevamente.', false);
    }).always(function () {
      consultaActual = null;
    });
  }

  function renderCotizacion(d) {
    if (!d.disponible || !d.noches) {
      $('#desglose-precio').addClass('d-none');
      mostrarMensaje('Esas fechas no están disponibles. Prueba con otras.', false);
      return;
    }

    cotizacion = d;
    const promedio = d.subtotal / d.noches;
    const etiquetaNoches = Math.round(promedio) === Math.round(d.precio_noche)
      ? `$${formatearMoneda(d.precio_noche)} x ${d.noches} noche${d.noches === 1 ? '' : 's'}`
      : `${d.noches} noche${d.noches === 1 ? '' : 's'} (promedio $${formatearMoneda(promedio)})`;

    $('#linea-noches').text(etiquetaNoches);
    $('#valor-noches').text('$' + formatearMoneda(d.subtotal));
    $('#valor-limpieza').text('$' + formatearMoneda(d.limpieza));
    $('#valor-total').text('$' + formatearMoneda(d.total));
    $('#desglose-precio').removeClass('d-none');

    mostrarMensaje('¡Disponible! Aún no se te cobra nada.', true);
    $('#btn-reservar').prop('disabled', false).text(`Reservar por $${formatearMoneda(d.total)}`);
    actualizarBarraMovil();
  }

  function actualizarBarraMovil() {
    if (!propiedad) return;
    if (cotizacion && cotizacion.disponible) {
      $('#barra-precio').text('$' + formatearMoneda(cotizacion.total));
      $('#barra-detalle').text(`total · ${cotizacion.noches} noche${cotizacion.noches === 1 ? '' : 's'}`);
      $('#barra-reservar').text('Reservar');
    } else {
      $('#barra-precio').text('$' + formatearMoneda(propiedad.precio_noche));
      $('#barra-detalle').text('/ noche');
      $('#barra-reservar').text('Elegir fechas');
    }
  }

  /* ==================== RESERVA ==================== */

  function reservar() {
    if (!cotizacion || !cotizacion.disponible) return;

    const llegada = $('#fecha-llegada').val();
    const salida = $('#fecha-salida').val();
    const huespedes = parseInt($('#huespedes').val(), 10) || 1;

    if (!sesionActiva) {
      const volver = `ficha_propiedad.php?id=${idPropiedad}&llegada=${llegada}&salida=${salida}&huespedes=${huespedes}`;
      window.location.href = 'login.php?redirect=' + encodeURIComponent(volver);
      return;
    }

    const $btn = $('#btn-reservar');
    $btn.prop('disabled', true).text('Reservando...');
    $('#barra-reservar').prop('disabled', true);

    $.ajax({
      url: API, method: 'POST', dataType: 'json',
      data: { action: 'crear_reserva', id_propiedad: idPropiedad, llegada: llegada, salida: salida, huespedes: huespedes }
    }).done(function (res) {
      if (!res.success) { errorReserva(res.message); return; }
      window.location.href = 'checkout.php?reserva=' + encodeURIComponent(res.data.id_reserva);
    }).fail(function (xhr) {
      if (xhr.status === 401) {
        const volver = `ficha_propiedad.php?id=${idPropiedad}&llegada=${llegada}&salida=${salida}&huespedes=${huespedes}`;
        window.location.href = 'login.php?redirect=' + encodeURIComponent(volver);
        return;
      }
      const msg = xhr.responseJSON && xhr.responseJSON.message;
      errorReserva(msg);
      if (xhr.status === 409) consultarDisponibilidad();
    });
  }

  function errorReserva(texto) {
    mostrarMensaje(texto || 'No pudimos crear la reserva. Intenta nuevamente.', false);
    $('#barra-reservar').prop('disabled', false);
    if (cotizacion) {
      $('#btn-reservar').prop('disabled', false).text(`Reservar por $${formatearMoneda(cotizacion.total)}`);
    }
  }

  /* ==================== HELPERS ==================== */

  function mostrarMensaje(texto, ok) {
    $('#mensaje-disponibilidad').removeClass('d-none ok error').addClass(ok ? 'ok' : 'error').text(texto);
  }

  function ocultarMensaje() {
    $('#mensaje-disponibilidad').addClass('d-none');
  }

  function esFecha(valor) {
    return /^\d{4}-\d{2}-\d{2}$/.test(valor || '');
  }

  function fechaIso(fecha) {
    const m = String(fecha.getMonth() + 1).padStart(2, '0');
    const d = String(fecha.getDate()).padStart(2, '0');
    return `${fecha.getFullYear()}-${m}-${d}`;
  }

  function sumarDias(fecha, dias) {
    const copia = new Date(fecha);
    copia.setDate(copia.getDate() + dias);
    return copia;
  }

  function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-CL').format(Math.round(valor));
  }

  function escapeHtml(str) {
    return $('<div>').text(str == null ? '' : String(str)).html();
  }

  function escapeAttr(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }

});
