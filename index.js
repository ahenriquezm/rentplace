/**
 * index.js
 * Controlador de la página de inicio: categorías, destinos, autocompletado
 * de destino y estimador de suscripción para anfitriones.
 * Depende de jQuery (incluido en index.php).
 */
$(function () {

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

  /* ==================== ESTIMADOR (plan Reservas vs. comisión) ==================== */

  function iniciarEstimador() {
    const $seccion = $('#precios');
    if ($seccion.length === 0) return;

    const tarifas = $seccion.data('tarifas'); // { planes: {vitrina:{precio_anual,...},...}, referencia: 0.155 }
    const plan = tarifas.planes.reservas;
    const $precio = $('#est-precio');
    const $noches = $('#est-noches');

    $precio.on('input', function () {
      const valor = leerPrecio();
      $precio.val(valor ? formatearMoneda(valor) : '');
      calcular();
    });
    $noches.on('input', calcular);

    calcular();

    function leerPrecio() {
      return parseInt(($precio.val() || '').replace(/\D/g, ''), 10) || 0;
    }

    function calcular() {
      const precio = leerPrecio();
      const noches = parseInt($noches.val(), 10);
      const costoPlan = plan.precio_anual;

      const facturacionAnual = precio * noches * 12;
      const comisionAnual = Math.round(facturacionAnual * tarifas.referencia);
      const pct = facturacionAnual > 0 ? costoPlan / facturacionAnual : 0;
      const ahorro = comisionAnual - costoPlan;
      const nochesEquilibrio = precio > 0 ? Math.ceil(costoPlan / (precio * tarifas.referencia)) : 0;

      $('#est-noches-out').text(noches + (noches === 1 ? ' noche' : ' noches'));
      $('#est-cuota').text('$' + formatearMoneda(costoPlan));
      $('#est-periodo').text(`Facturas $${formatearMoneda(facturacionAnual)} al año`);
      $('#est-pct').text(formatearPorcentaje(pct));
      $('#est-equilibrio').text(nochesEquilibrio
        ? `Se paga solo con ${nochesEquilibrio} noche${nochesEquilibrio === 1 ? '' : 's'} arrendada${nochesEquilibrio === 1 ? '' : 's'} al año`
        : '');
      $('#est-rp-val').text('$' + formatearMoneda(costoPlan));
      $('#est-ab-val').text('$' + formatearMoneda(comisionAnual));

      const maximo = Math.max(costoPlan, comisionAnual, 1);
      $('#est-rp-bar').css('width', (costoPlan / maximo * 100) + '%');
      $('#est-ab-bar').css('width', (comisionAnual / maximo * 100) + '%');

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
