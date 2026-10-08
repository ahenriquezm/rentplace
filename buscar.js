/**
 * buscar.js
 * Controlador del módulo de búsqueda / listado de propiedades.
 * Depende de jQuery (incluido en buscar.php).
 */
$(function () {

  const API = 'buscar-api.php';
  const RESULTADOS_POR_PAGINA = 12;

  let paginaActual = 1;
  let tipoActual = $('.bs-tag-chip.active').data('tipo') || '';

  buscar(1, false);
  bindEventos();

  function bindEventos() {
    $('#form-buscar').on('submit', function (e) {
      e.preventDefault();
      actualizarUrl();
      buscar(1, false);
    });

    $('#tag-strip').on('click', '.bs-tag-chip', function () {
      $('.bs-tag-chip').removeClass('active');
      $(this).addClass('active');
      tipoActual = $(this).data('tipo') || '';
      actualizarUrl();
      buscar(1, false);
    });

    $('#btn-cargar-mas').on('click', function () {
      buscar(paginaActual + 1, true);
    });
  }

  function obtenerFiltros() {
    return {
      destino: $('#in-destino').val().trim(),
      llegada: $('#in-llegada').val(),
      salida: $('#in-salida').val(),
      huespedes: $('#in-huespedes').val(),
      tipo: tipoActual
    };
  }

  function actualizarUrl() {
    const f = obtenerFiltros();
    const params = new URLSearchParams();
    Object.keys(f).forEach(function (k) {
      if (f[k]) params.set(k, f[k]);
    });
    const nuevaUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.history.replaceState({}, '', nuevaUrl);
  }

  function buscar(pagina, agregar) {
    paginaActual = pagina;
    const filtros = obtenerFiltros();
    filtros.action = 'resultados';
    filtros.pagina = pagina;
    filtros.por_pagina = RESULTADOS_POR_PAGINA;

    if (!agregar) {
      $('#bs-grid').removeClass('d-none').html(skeletons());
      $('#bs-vacio').addClass('d-none');
      $('#btn-cargar-mas').addClass('d-none');
    } else {
      $('#btn-cargar-mas').prop('disabled', true).text('Cargando...');
    }

    $.ajax({
      url: API,
      method: 'GET',
      data: filtros,
      dataType: 'json'
    }).done(function (res) {
      if (!res.success) {
        $('#bs-grid').addClass('d-none');
        $('#bs-vacio').removeClass('d-none');
        return;
      }
      renderResultados(res.data, agregar);
    }).fail(function () {
      $('#bs-grid').addClass('d-none');
      $('#bs-vacio').removeClass('d-none');
    });
  }

  function renderResultados(data, agregar) {
    const propiedades = data.propiedades || [];

    if (propiedades.length === 0 && !agregar) {
      $('#bs-grid').addClass('d-none');
      $('#bs-vacio').removeClass('d-none');
      $('#bs-resumen').text('');
      $('#btn-cargar-mas').addClass('d-none');
      return;
    }

    $('#bs-vacio').addClass('d-none');
    $('#bs-grid').removeClass('d-none');
    $('#bs-resumen').text(`${data.total} propiedad${data.total === 1 ? '' : 'es'} encontrada${data.total === 1 ? '' : 's'}`);

    const html = propiedades.map(tarjetaHtml).join('');
    if (agregar) {
      $('#bs-grid').append(html);
      $('#btn-cargar-mas').prop('disabled', false).text('Ver más resultados');
    } else {
      $('#bs-grid').html(html);
    }

    const yaMostradas = $('#bs-grid .bs-card').length;
    if (yaMostradas < data.total) {
      $('#btn-cargar-mas').removeClass('d-none');
    } else {
      $('#btn-cargar-mas').addClass('d-none');
    }
  }

  function tarjetaHtml(p) {
    // Lleva las fechas y huéspedes buscados a la ficha, para no volver a escribirlos.
    const f = obtenerFiltros();
    const params = new URLSearchParams({ id: p.id });
    ['llegada', 'salida', 'huespedes'].forEach(function (k) { if (f[k]) params.set(k, f[k]); });
    const rating = p.rating_promedio ? `★ ${parseFloat(p.rating_promedio).toFixed(1)}` : 'Nuevo';
    const foto = p.foto_portada || 'https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?w=500';
    return `
      <a href="ficha_propiedad.php?${params.toString()}" class="bs-card">
        <div class="photo" style="background-image:url('${escapeAttr(foto)}')">
          <div class="bs-price-final"><div class="dot"></div>Precio final $${formatearMoneda(p.precio_noche)}</div>
        </div>
        <div class="meta">
          <div class="row1"><span>${escapeHtml(p.titulo)}</span><span class="rating">${rating}</span></div>
          <div class="loc">${escapeHtml(p.ciudad)}, ${escapeHtml(p.region)}</div>
          <div class="price"><b>$${formatearMoneda(p.precio_noche)}</b> / noche</div>
        </div>
      </a>
    `;
  }

  function skeletons() {
    return new Array(6).fill('<div class="bs-skeleton"></div>').join('');
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