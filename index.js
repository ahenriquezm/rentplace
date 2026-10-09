/**
 * index.js
 * Controlador de la página de inicio: categorías, destinos, autocompletado
 * de destino y estimador de suscripción para anfitriones.
 * Depende de jQuery (incluido en index.php).
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

  const API = 'index-api.php';

  const CATEGORIAS = {
    cabana:       { nombre: 'Cabañas',       foto: 'https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?w=600' },
    departamento: { nombre: 'Departamentos', foto: 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=600' },
    casa:         { nombre: 'Casas',         foto: 'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=600' }
  };

  cargarCategorias();
  cargarDestinos();
  bindSugerencias();
  bindFechasHero();
  iniciarEstimador();

  /* ==================== CATEGORÍAS ==================== */

  function cargarCategorias() {
    $.ajax({ url: API, method: 'GET', data: { action: 'categorias' }, dataType: 'json' })
      .done(function (res) { renderCategorias(res.success ? res.data : []); })
      .fail(function () { renderCategorias([]); });
  }

  function renderCategorias(datos) {
    const porTipo = {};
    datos.forEach(function (c) { porTipo[c.tipo] = c; });

    const html = Object.keys(CATEGORIAS).map(function (tipo) {
      const cat = CATEGORIAS[tipo];
      const d = porTipo[tipo];
      const detalle = d && d.total > 0
        ? `${d.total} ${d.total === 1 ? 'propiedad' : 'propiedades'} · desde $${formatearMoneda(d.desde)}`
        : 'Explorar';
      return `
        <a href="buscar.php?tipo=${tipo}" class="idx-cat-card">
          <img src="${cat.foto}" alt="${cat.nombre}" loading="lazy">
          <div class="txt"><div class="t">${cat.nombre}</div><div class="s">${detalle}</div></div>
        </a>`;
    }).join('');

    $('#categorias-grid').html(html);
  }

  /* ==================== DESTINOS ==================== */

  function cargarDestinos() {
    $.ajax({ url: API, method: 'GET', data: { action: 'destinos' }, dataType: 'json' })
      .done(function (res) {
        if (!res.success || res.data.length === 0) {
          $('#destinos-grid').closest('.idx-block').addClass('d-none');
          return;
        }
        $('#destinos-grid').html(res.data.map(function (d) {
          const foto = d.foto || 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=600';
          return `
            <a href="buscar.php?destino=${encodeURIComponent(d.ciudad)}" class="idx-dest-card">
              <img src="${escapeAttr(foto)}" alt="${escapeAttr(d.ciudad)}" loading="lazy">
              <div>
                <div class="t">${escapeHtml(d.ciudad)}</div>
                <div class="s">${escapeHtml(d.region || '')} · ${d.total} ${d.total === 1 ? 'propiedad' : 'propiedades'}</div>
              </div>
            </a>`;
        }).join(''));
      })
      .fail(function () {
        $('#destinos-grid').closest('.idx-block').addClass('d-none');
      });
  }

  /* ==================== BÚSQUEDA ==================== */

  function bindSugerencias() {
    const $input = $('#hero-destino');
    const $lista = $('#sugerencias-destino');
    let temporizador = null;

    $input.on('input', function () {
      clearTimeout(temporizador);
      const q = $input.val().trim();
      if (q.length < 2) { $lista.addClass('d-none'); return; }

      temporizador = setTimeout(function () {
        $.ajax({ url: API, method: 'GET', data: { action: 'sugerencias', q: q }, dataType: 'json' })
          .done(function (res) {
            if (!res.success || res.data.length === 0) { $lista.addClass('d-none'); return; }
            $lista.html(res.data.map(function (s) {
              return `<div class="item" data-ciudad="${escapeAttr(s.ciudad)}"><b>${escapeHtml(s.ciudad)}</b>${s.region ? ', ' + escapeHtml(s.region) : ''}</div>`;
            }).join('')).removeClass('d-none');
          });
      }, 200);
    });

    $lista.on('mousedown', '.item', function () {
      $input.val($(this).data('ciudad'));
      $lista.addClass('d-none');
    });

    $input.on('blur', function () { setTimeout(function () { $lista.addClass('d-none'); }, 150); });
  }

  function bindFechasHero() {
    const ahora = new Date();
    const hoy = ahora.getFullYear() + '-' + String(ahora.getMonth() + 1).padStart(2, '0') + '-' + String(ahora.getDate()).padStart(2, '0');
    $('#hero-llegada').attr('min', hoy);
    $('#hero-salida').attr('min', hoy);
    $('#hero-llegada').on('change', function () {
      const llegada = $(this).val();
      $('#hero-salida').attr('min', llegada || hoy);
      if ($('#hero-salida').val() && $('#hero-salida').val() <= llegada) {
        $('#hero-salida').val('');
      }
    });
  }

  /* ==================== PLANES Y ESTIMADOR ==================== */

  function iniciarEstimador() {
    const $seccion = $('#precios');
    if ($seccion.length === 0) return;

    // { planes: {basic:{nombre, precio_mensual, ubicaciones, reserva_online}, ...},
    //   meses_anual: 10, referencia: 0.155, pasarela: 0.035 }
    const tarifas = $seccion.data('tarifas');
    const $precio = $('#est-precio');
    const $noches = $('#est-noches');

    $('input[name="facturacion"]').on('change', function () {
      const periodo = $('input[name="facturacion"]:checked').val();
      $('.idx-plan-precio').each(function () {
        $(this).contents().first().replaceWith($(this).data(periodo));
      });
      $('.idx-plan-periodo').text(periodo === 'anual' ? ' / año' : ' / mes');
      $('.idx-plan-mes').each(function () { $(this).text($(this).data(periodo)); });
      calcular();
    });

    $precio.on('input', function () {
      const valor = leerPrecio();
      $precio.val(valor ? formatearMoneda(valor) : '');
      calcular();
    });
    $noches.on('input', calcular);
    $('input[name="est-plan"]').on('change', calcular);

    calcular();

    function leerPrecio() {
      return parseInt(($precio.val() || '').replace(/\D/g, ''), 10) || 0;
    }

    function calcular() {
      const clave = $('input[name="est-plan"]:checked').val() || 'smart';
      const plan = tarifas.planes[clave];
      const anual = $('input[name="facturacion"]:checked').val() === 'anual';
      const precio = leerPrecio();
      const noches = parseInt($noches.val(), 10);

      // Costo anual de la suscripción según la frecuencia de pago elegida.
      const suscripcion = plan.precio_mensual * (anual ? tarifas.meses_anual : 12);
      // En Smart/Pro el huésped paga online: el procesador de pagos cobra aparte (estimado).
      const tasaPasarela = plan.reserva_online ? tarifas.pasarela : 0;

      const facturacion = precio * noches * 12;
      const costoRentplace = suscripcion + Math.round(facturacion * tasaPasarela);
      const comision = Math.round(facturacion * tarifas.referencia);
      const ahorro = comision - costoRentplace;
      // Noche del año desde la que Rentplace sale más barato que la comisión.
      const ahorroPorNoche = precio * (tarifas.referencia - tasaPasarela);
      const desde = ahorroPorNoche > 0 ? Math.floor(suscripcion / ahorroPorNoche) + 1 : null;

      $('#est-noches-out').text(noches + (noches === 1 ? ' noche' : ' noches'));
      $('#est-titulo').text(`Plan ${plan.nombre}, pago ${anual ? 'anual' : 'mensual'}`);
      $('#est-cuota').text('$' + formatearMoneda(suscripcion));
      $('#est-periodo').text(`Facturas $${formatearMoneda(facturacion)} al año`);
      $('#est-desde').text(desde ? `desde la noche ${formatearMoneda(desde)}` : '—');
      $('#est-nota').text(tasaPasarela
        ? `Incluye un costo estimado de ${formatearPorcentaje(tasaPasarela)} del procesador de pagos`
        : 'En Basic coordinas el pago directo con el huésped: sin procesador de pagos');
      $('#est-rp-label').text(`Rentplace ${plan.nombre}${tasaPasarela ? ' + procesador de pagos' : ''}`);
      $('#est-rp-val').text('$' + formatearMoneda(costoRentplace));
      $('#est-ab-val').text('$' + formatearMoneda(comision));

      const maximo = Math.max(costoRentplace, comision, 1);
      $('#est-rp-bar').css('width', (costoRentplace / maximo * 100) + '%');
      $('#est-ab-bar').css('width', (comision / maximo * 100) + '%');

      $('#est-ahorro')
        .toggleClass('neg', ahorro <= 0)
        .text(ahorro > 0
          ? `Ahorras $${formatearMoneda(ahorro)} al año`
          : 'Con tan pocas noches al año, una comisión por reserva te sale más barata');
    }
  }

  /* ==================== HELPERS ==================== */

  function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-CL').format(Math.round(valor));
  }

  function formatearPorcentaje(valor) {
    return (valor * 100).toLocaleString('es-CL', { maximumFractionDigits: 1 }) + '%';
  }

  function escapeHtml(str) {
    return $('<div>').text(str || '').html();
  }

  function escapeAttr(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }

});
