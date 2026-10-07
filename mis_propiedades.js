/**
 * mis_propiedades.js
 * Controlador del panel de anfitrión.
 * Depende de jQuery y SweetAlert2 (incluidos en mis_propiedades.php).
 */
$(function () {

  const API = 'mis_propiedades-api.php';

  cargarResumen();
  cargarPropiedades();
  bindDrawer();

  function cargarResumen() {
    $.ajax({
      url: API, method: 'GET', data: { action: 'resumen' }, dataType: 'json'
    }).done(function (res) {
      if (!res.success) return;
      const d = res.data;
      $('#mp-resumen').html(`
        <div class="mp-kpi"><div class="mp-kpi-valor">${d.total_propiedades}</div><div class="mp-kpi-label">Propiedades publicadas</div></div>
        <div class="mp-kpi"><div class="mp-kpi-valor">${d.reservas_activas}</div><div class="mp-kpi-label">Reservas próximas</div></div>
        <div class="mp-kpi"><div class="mp-kpi-valor">$${formatearMoneda(d.ingresos_confirmados)}</div><div class="mp-kpi-label">Ingresos confirmados</div></div>
        ${tarifaHtml(d.cuotas, d.ingresos_mes)}
      `);
    });
  }

  function tarifaHtml(cuotas, ingresosMes) {
    if (!cuotas || !cuotas.mensual) return '';
    const pct = function (cuota) {
      if (!ingresosMes) return '';
      const v = (cuota / ingresosMes * 100).toLocaleString('es-CL', { maximumFractionDigits: 1 });
      return ` · ${v}% de lo facturado este mes`;
    };
    return `
      <div class="mp-kpi mp-kpi-tarifa">
        <div class="mp-kpi-label">Tu suscripción Rentplace: 1 noche al mes por propiedad, sin comisión por reserva</div>
        <div class="mp-tarifa-planes">
          <div><b>$${formatearMoneda(cuotas.mensual)}</b>/mes · Mensual${pct(cuotas.mensual)}</div>
          <div><b>$${formatearMoneda(cuotas.semestral)}</b>/mes · Semestral −15%${pct(cuotas.semestral)}</div>
          <div><b>$${formatearMoneda(cuotas.anual)}</b>/mes · Anual −30%${pct(cuotas.anual)}</div>
        </div>
      </div>
    `;
  }

  function cargarPropiedades() {
    $.ajax({
      url: API, method: 'GET', data: { action: 'listar' }, dataType: 'json'
    }).done(function (res) {
      if (!res.success || res.data.length === 0) {
        $('#mp-lista').addClass('d-none');
        $('#mp-vacio').removeClass('d-none');
        return;
      }
      $('#mp-vacio').addClass('d-none');
      $('#mp-lista').removeClass('d-none').html(res.data.map(tarjetaHtml).join(''));
    }).fail(function () {
      $('#mp-lista').addClass('d-none');
      $('#mp-vacio').removeClass('d-none');
    });
  }

  function tarjetaHtml(p) {
    return `
      <div class="mp-card">
        <img src="${escapeAttr(p.foto_portada || 'https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?w=200')}">
        <div class="mp-card-body">
          <div class="mp-card-top">
            <div>
              <div class="mp-card-title">${escapeHtml(p.titulo)}</div>
              <div class="mp-card-loc">${escapeHtml(p.ciudad)}, ${escapeHtml(p.region)}</div>
            </div>
            <span class="mp-badge ${p.activo ? 'activo' : 'inactivo'}">${p.activo ? 'Activa' : 'Pausada'}</span>
          </div>
          <div class="mp-card-stats">
            <span>${p.total_reservas} reserva(s)</span>
            <span>$${formatearMoneda(p.precio_noche)} / noche</span>
          </div>
          <div class="mp-card-bottom">
            <div class="mp-actions">
              <a href="ficha_propiedad.php?id=${p.id}" class="mp-btn-sm">Ver publicación</a>
              <a href="calendario.php?id=${p.id}" class="mp-btn-sm">Calendario y precios</a>
              <button type="button" class="mp-btn-sm" data-accion="ver-reservas" data-id="${p.id}" data-titulo="${escapeAttr(p.titulo)}">Ver reservas</button>
            </div>
            <button type="button" class="mp-btn-sm" data-accion="toggle" data-id="${p.id}" data-activo="${p.activo}">${p.activo ? 'Pausar' : 'Reactivar'}</button>
          </div>
        </div>
      </div>
    `;
  }

  $(document).on('click', '[data-accion="toggle"]', function () {
    const id = $(this).data('id');
    const activo = $(this).data('activo') ? 0 : 1;

    $.ajax({
      url: API, method: 'POST', data: { action: 'toggle_activo', id_propiedad: id, activo: activo }, dataType: 'json'
    }).done(function (res) {
      if (!res.success) {
        Swal.fire('No se pudo actualizar', res.message || 'Intenta nuevamente.', 'error');
        return;
      }
      cargarPropiedades();
    });
  });

  /* ---------------- Drawer de reservas por propiedad ---------------- */

  function bindDrawer() {
    $(document).on('click', '[data-accion="ver-reservas"]', function () {
      const id = $(this).data('id');
      const titulo = $(this).data('titulo');
      abrirDrawer(id, titulo);
    });

    $('#mp-drawer-cerrar, #mp-drawer-bg').on('click', cerrarDrawer);
  }

  function abrirDrawer(idPropiedad, titulo) {
    $('#mp-drawer-titulo').text('Reservas — ' + titulo);
    $('#mp-drawer-body').html('<div class="mp-skeleton"></div>');
    $('#mp-drawer-bg').removeClass('d-none');
    $('#mp-drawer').addClass('open');

    $.ajax({
      url: API, method: 'GET', data: { action: 'reservas_propiedad', id_propiedad: idPropiedad }, dataType: 'json'
    }).done(function (res) {
      if (!res.success || res.data.length === 0) {
        $('#mp-drawer-body').html('<p class="od-muted">Todavía no hay reservas para esta propiedad.</p>');
        return;
      }
      $('#mp-drawer-body').html(res.data.map(reservaItemHtml).join(''));
    });
  }

  function reservaItemHtml(r) {
    const etiquetas = { pendiente_pago: 'Pendiente de pago', confirmada: 'Confirmada', cancelada: 'Cancelada' };
    return `
      <div class="mp-reserva-item">
        <div class="top"><span>${escapeHtml(r.nombre_huesped)}</span><span>${etiquetas[r.estado] || r.estado}</span></div>
        <div class="sub">${r.fecha_llegada} – ${r.fecha_salida} · $${formatearMoneda(r.total)}</div>
      </div>
    `;
  }

  function cerrarDrawer() {
    $('#mp-drawer').removeClass('open');
    $('#mp-drawer-bg').addClass('d-none');
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