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

  /* ==================== PLANES, AHORRO Y CONTRATACIÓN ==================== */

  function iniciarEstimador() {
    const $seccion = $('#precios');
    if ($seccion.length === 0) return;

    // { planes: {basic:{nombre, precio_mensual, ubicaciones, reserva_online}, ...},
    //   meses_anual: 10, referencia: 0.155, pasarela: 0.035 }
    const tarifas = $seccion.data('tarifas');
    const sesionActiva = String($seccion.data('sesion')) === '1';

    // Mensual / anual: cambia precios de las tarjetas.
    $('input[name="facturacion"]').on('change', function () {
      const periodo = periodicidad();
      $('.idx-plan-precio').each(function () {
        $(this).contents().first().replaceWith($(this).data(periodo));
      });
      $('.idx-plan-periodo').text(periodo === 'anual' ? ' / año' : ' / mes');
      $('.idx-plan-mes').each(function () { $(this).text($(this).data(periodo)); });
    });

    // Ahorro en 1 clic.
    $('input[name="ahorro-monto"]').on('change', calcularAhorro);
    calcularAhorro();

    // Contratar -> Mercado Pago.
    $('.idx-plan-btn').on('click', function () {
      contratar($(this));
    });

    function periodicidad() {
      return $('input[name="facturacion"]:checked').val() === 'anual' ? 'anual' : 'mensual';
    }

    function calcularAhorro() {
      const mensual = parseInt($('input[name="ahorro-monto"]:checked').val(), 10) || 0;
      const anual = mensual * 12;
      const comision = Math.round(anual * tarifas.referencia);

      $('#ahorro-comision').text('$' + formatearMoneda(comision));
      $('#ahorro-planes').html(Object.keys(tarifas.planes).map(function (clave) {
        const plan = tarifas.planes[clave];
        const costo = plan.precio_mensual * tarifas.meses_anual
          + (plan.reserva_online ? Math.round(anual * tarifas.pasarela) : 0);
        const ahorro = comision - costo;
        return `
          <div class="idx-ahorro-plan ${ahorro > 0 ? '' : 'neg'}">
            <div class="n">${escapeHtml(plan.nombre)}</div>
            <div class="v">${ahorro > 0 ? 'Ahorras $' + formatearMoneda(ahorro) : 'Aún no te conviene'}</div>
            <div class="c">Pagas $${formatearMoneda(costo)} al año</div>
          </div>`;
      }).join(''));
    }

    function contratar($btn) {
      const plan = $btn.data('plan');
      if (!sesionActiva) {
        window.location.href = 'login.php?tab=registro&redirect=' + encodeURIComponent('index.php');
        return;
      }

      const textoOriginal = $btn.text();
      $('.idx-plan-btn').prop('disabled', true);
      $btn.text('Conectando con Mercado Pago...');
      $('#contratar-msg').addClass('d-none');

      $.ajax({
        url: 'suscripcion-api.php', method: 'POST', dataType: 'json',
        data: { action: 'crear_pago', plan: plan, periodicidad: periodicidad() }
      }).done(function (res) {
        if (res.success && res.data && res.data.url_pago) {
          window.location.href = res.data.url_pago;
          return;
        }
        error(res.message);
      }).fail(function (xhr) {
        if (xhr.status === 401) {
          window.location.href = 'login.php?redirect=' + encodeURIComponent('index.php');
          return;
        }
        error(xhr.responseJSON && xhr.responseJSON.message);
      });

      function error(texto) {
        $('.idx-plan-btn').prop('disabled', false);
        $btn.text(textoOriginal);
        $('#contratar-msg').removeClass('d-none').text(texto || 'No pudimos iniciar el pago. Intenta nuevamente.');
      }
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
