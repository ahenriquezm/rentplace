/**
 * mis_reservas.js
 * Controlador del módulo de historial de reservas.
 * Depende de jQuery y SweetAlert2 (incluidos en mis_reservas.php).
 */
$(function () {

  const API = 'mis_reservas-api.php';
  let filtroActual = 'proximas';

  cargarReservas();
  bindTabs();

  function bindTabs() {
    $('#mr-tabs').on('click', '.mr-tab', function () {
      $('.mr-tab').removeClass('active');
      $(this).addClass('active');
      filtroActual = $(this).data('filtro');
      cargarReservas();
    });
  }

  function cargarReservas() {
    $('#mr-lista').removeClass('d-none').html('<div class="mr-skeleton"></div><div class="mr-skeleton"></div>');
    $('#mr-vacio').addClass('d-none');

    $.ajax({
      url: API,
      method: 'GET',
      data: { action: 'listar', filtro: filtroActual },
      dataType: 'json'
    }).done(function (res) {
      if (!res.success) {
        $('#mr-lista').addClass('d-none');
        $('#mr-vacio').removeClass('d-none');
        return;
      }
      renderLista(res.data);
    }).fail(function () {
      $('#mr-lista').addClass('d-none');
      $('#mr-vacio').removeClass('d-none');
    });
  }

  function renderLista(reservas) {
    if (reservas.length === 0) {
      $('#mr-lista').addClass('d-none');
      $('#mr-vacio').removeClass('d-none');
      return;
    }

    $('#mr-vacio').addClass('d-none');
    $('#mr-lista').removeClass('d-none').html(reservas.map(tarjetaHtml).join(''));
  }

  function tarjetaHtml(r) {
    const etiquetasEstado = {
      pendiente_pago: 'Pendiente de pago',
      confirmada: 'Confirmada',
      cancelada: 'Cancelada'
    };

    let acciones = `<a href="ficha_propiedad.php?id=${r.id_propiedad}" class="mr-btn-sm">Ver propiedad</a>`;
    if (r.estado === 'pendiente_pago') {
      acciones = `<a href="checkout.php?reserva=${r.id}" class="mr-btn-sm">Completar pago</a>` + acciones;
    }
    if (r.estado === 'pendiente_pago' || r.estado === 'confirmada') {
      acciones += `<button type="button" class="mr-btn-sm danger" data-accion="cancelar" data-id="${r.id}">Cancelar</button>`;
    }

    return `
      <div class="mr-card" data-card-id="${r.id}">
        <img src="${escapeAttr(r.foto_portada || 'https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?w=200')}">
        <div class="mr-card-body">
          <div class="mr-card-top">
            <div>
              <div class="mr-card-title">${escapeHtml(r.titulo)}</div>
              <div class="mr-card-loc">${escapeHtml(r.ciudad)}, ${escapeHtml(r.region)}</div>
            </div>
            <span class="mr-badge ${r.estado}">${etiquetasEstado[r.estado] || r.estado}</span>
          </div>
          <div class="mr-card-fechas">${r.fecha_llegada} – ${r.fecha_salida} · ${r.huespedes} huésped(es)</div>
          <div class="mr-card-bottom">
            <span class="mr-card-total">$${formatearMoneda(r.total)}</span>
            <div class="mr-actions">${acciones}</div>
          </div>
        </div>
      </div>
    `;
  }

  $(document).on('click', '[data-accion="cancelar"]', function () {
    const idReserva = $(this).data('id');

    Swal.fire({
      title: '¿Cancelar esta reserva?',
      text: 'Esta acción no se puede deshacer.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, cancelar',
      cancelButtonText: 'Volver',
      confirmButtonColor: '#B23B3B'
    }).then(function (r) {
      if (!r.isConfirmed) return;

      $.ajax({
        url: API,
        method: 'POST',
        data: { action: 'cancelar', reserva: idReserva },
        dataType: 'json'
      }).done(function (res) {
        if (!res.success) {
          Swal.fire('No se pudo cancelar', res.message || 'Intenta nuevamente.', 'error');
          return;
        }
        cargarReservas();
      }).fail(function () {
        Swal.fire('Error de conexión', 'Intenta nuevamente.', 'error');
      });
    });
  });

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