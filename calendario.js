/**
 * calendario.js
 * Controlador del módulo de calendario y precios.
 * Depende de jQuery y SweetAlert2 (incluidos en calendario.php).
 *
 * UX de selección:
 *   - Clic simple en un día: selecciona solo ese día (reemplaza la selección anterior).
 *   - Clic y arrastrar sobre varios días: selecciona el rango completo (como una hoja de cálculo).
 *   - Ctrl/Cmd + clic (o arrastre): agrega la selección a lo ya elegido, sin reemplazar
 *     (útil para elegir, por ejemplo, todos los viernes y sábados de golpe).
 */
$(function () {

  const API = 'calendario-api.php';
  const MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

  const idPropiedad = $('#cal-root').data('id-propiedad');
  const hoy = new Date();
  let anioActual = hoy.getFullYear();
  let mesActual = hoy.getMonth(); // 0-11

  let datosDias = {};           // { 'YYYY-MM-DD': {precio, disponible, reservado} }
  let seleccion = new Set();    // fechas seleccionadas
  let arrastrando = false;
  let inicioArrastre = null;
  let aditivo = false;          // true si se mantiene Ctrl/Cmd

  cargarPropiedad();
  cargarMes();
  bindNavegacion();
  bindSeleccionDrag();
  bindBarraAcciones();

  /* ---------------- Carga de datos ---------------- */

  function cargarPropiedad() {
    $.ajax({ url: API, method: 'GET', data: { action: 'propiedad', id_propiedad: idPropiedad }, dataType: 'json' })
      .done(function (res) {
        if (res.success) $('#cal-titulo-propiedad').text(res.data.titulo);
      });
  }

  function cargarMes() {
    $('#cal-mes-actual').text(MESES[mesActual] + ' ' + anioActual);

    $.ajax({
      url: API, method: 'GET',
      data: { action: 'mes', id_propiedad: idPropiedad, anio: anioActual, mes: mesActual + 1 },
      dataType: 'json'
    }).done(function (res) {
      if (!res.success) return;
      datosDias = {};
      res.data.forEach(function (d) { datosDias[d.fecha] = d; });
      seleccion.clear();
      actualizarBarraAcciones();
      renderGrid();
    });
  }

  /* ---------------- Render del grid ---------------- */

  function renderGrid() {
    const $grid = $('#cal-grid');
    $grid.empty();

    const primerDia = new Date(anioActual, mesActual, 1);
    const totalDias = new Date(anioActual, mesActual + 1, 0).getDate();

    // Lunes = 0 ... Domingo = 6
    let diaSemanaInicio = primerDia.getDay() - 1;
    if (diaSemanaInicio < 0) diaSemanaInicio = 6;

    for (let i = 0; i < diaSemanaInicio; i++) {
      $grid.append('<div class="cal-dia vacio"></div>');
    }

    for (let dia = 1; dia <= totalDias; dia++) {
      const fecha = formatearFecha(anioActual, mesActual, dia);
      const info = datosDias[fecha] || { precio: null, disponible: 1, reservado: false };
      const esPasado = compararSoloFecha(new Date(anioActual, mesActual, dia)) < compararSoloFecha(hoy);

      const clases = ['cal-dia'];
      if (esPasado) clases.push('pasado');
      if (info.reservado) clases.push('reservado');
      else if (!info.disponible) clases.push('bloqueado');
      else if (info.es_personalizado) clases.push('personalizado');
      if (seleccion.has(fecha)) clases.push('seleccionado');

      const $dia = $(`
        <div class="${clases.join(' ')}" data-fecha="${fecha}">
          <div class="num">${dia}</div>
          ${!info.reservado && info.disponible !== 0 ? `<div class="precio">$${formatearMoneda(info.precio)}</div>` : ''}
        </div>
      `);
      $grid.append($dia);
    }
  }

  /* ---------------- Navegación entre meses ---------------- */

  function bindNavegacion() {
    $('#cal-mes-anterior').on('click', function () {
      mesActual--;
      if (mesActual < 0) { mesActual = 11; anioActual--; }
      cargarMes();
    });
    $('#cal-mes-siguiente').on('click', function () {
      mesActual++;
      if (mesActual > 11) { mesActual = 0; anioActual++; }
      cargarMes();
    });
  }

  /* ---------------- Selección por clic / arrastre ---------------- */

  function bindSeleccionDrag() {
    $('#cal-grid').on('mousedown', '.cal-dia', function (e) {
      if ($(this).hasClass('vacio') || $(this).hasClass('pasado') || $(this).hasClass('reservado')) return;

      aditivo = e.ctrlKey || e.metaKey;
      arrastrando = true;
      inicioArrastre = $(this).data('fecha');

      if (!aditivo) seleccion.clear();
      seleccionarRango(inicioArrastre, inicioArrastre);
      renderGrid();
      actualizarBarraAcciones();
      e.preventDefault();
    });

    $('#cal-grid').on('mouseenter', '.cal-dia', function () {
      if (!arrastrando) return;
      if ($(this).hasClass('vacio') || $(this).hasClass('pasado') || $(this).hasClass('reservado')) return;

      const fechaActual = $(this).data('fecha');
      if (!aditivo) seleccion.clear();
      seleccionarRango(inicioArrastre, fechaActual);
      renderGrid();
      actualizarBarraAcciones();
    });

    $(document).on('mouseup', function () {
      arrastrando = false;
    });
  }

  function seleccionarRango(fechaA, fechaB) {
    const [inicio, fin] = [fechaA, fechaB].sort();
    Object.keys(datosDias).forEach(function (fecha) {
      if (fecha >= inicio && fecha <= fin) seleccion.add(fecha);
    });
    // Asegura incluir también fechas dentro del rango que no vinieron en datosDias (no debería pasar, pero por robustez)
    let cursor = new Date(inicio);
    const finDate = new Date(fin);
    while (cursor <= finDate) {
      const f = formatearFechaDesdeDate(cursor);
      const diaInfo = datosDias[f];
      if (diaInfo && !diaInfo.reservado) seleccion.add(f);
      cursor.setDate(cursor.getDate() + 1);
    }
  }

  /* ---------------- Barra de acciones ---------------- */

  function actualizarBarraAcciones() {
    if (seleccion.size === 0) {
      $('#cal-barra-acciones').addClass('d-none');
      return;
    }
    $('#cal-conteo-seleccion').text(seleccion.size);
    $('#cal-barra-acciones').removeClass('d-none');
  }

  function bindBarraAcciones() {
    $('#cal-btn-cancelar').on('click', function () {
      seleccion.clear();
      renderGrid();
      actualizarBarraAcciones();
    });

    $('#cal-btn-aplicar-precio').on('click', function () {
      const precio = parseFloat($('#cal-precio-input').val());
      if (!precio || precio <= 0) {
        Swal.fire('Precio inválido', 'Ingresa un precio por noche mayor a 0.', 'warning');
        return;
      }
      guardarCambios({ disponible: 1, precio_personalizado: precio });
    });

    $('#cal-btn-bloquear').on('click', function () {
      guardarCambios({ disponible: 0, precio_personalizado: null });
    });

    $('#cal-btn-restaurar').on('click', function () {
      guardarCambios({ restaurar: true });
    });
  }

  function guardarCambios(cambios) {
    $.ajax({
      url: API, method: 'POST',
      data: Object.assign({
        action: 'guardar',
        id_propiedad: idPropiedad,
        fechas: JSON.stringify(Array.from(seleccion))
      }, cambios),
      dataType: 'json'
    }).done(function (res) {
      if (!res.success) {
        Swal.fire('No se pudo guardar', res.message || 'Intenta nuevamente.', 'error');
        return;
      }
      seleccion.clear();
      $('#cal-precio-input').val('');
      actualizarBarraAcciones();
      cargarMes();
    }).fail(function () {
      Swal.fire('Error de conexión', 'Intenta nuevamente.', 'error');
    });
  }

  /* ---------------- Helpers ---------------- */

  function formatearFecha(anio, mes, dia) {
    return `${anio}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
  }

  function formatearFechaDesdeDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  function compararSoloFecha(d) {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
  }

  function formatearMoneda(valor) {
    if (valor === null || valor === undefined) return '';
    return new Intl.NumberFormat('es-CL').format(Math.round(valor));
  }

});