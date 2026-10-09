# Rentplace — Historial de decisiones

Resumen de las conversaciones de trabajo, para no depender del historial del chat.
Rama de trabajo: `claude/vibrant-mccarthy-dq5vw6`. Detalle técnico en `REVISION.md`.

## Acuerdos vigentes (reemplazan todo lo anterior)

**Objetivo:** plataforma SaaS para que anfitriones y administradores publiquen, promocionen y gestionen
reservas de cabañas, departamentos, habitaciones, campings y otros alojamientos. Suscripción mensual fija,
**sin comisión de la plataforma por reserva**, diferenciada por funcionalidades y cantidad de ubicaciones.

### Planes (en `tarifas.php` y `planes_suscripcion`)

| Característica | Basic | Smart | Pro |
|---|:-:|:-:|:-:|
| Precio mensual | $9.990 | $24.990 | $59.990 |
| Pago anual (12 meses por 10) | $99.900 | $249.900 | $599.900 |
| Ubicaciones incluidas | 1 | 1 | Hasta 3 |
| Unidades por ubicación | Sin límite | Sin límite | Sin límite |
| Vitrina + QR + link, buscador, contacto y coordinación directa | ✓ | ✓ | ✓ |
| Calendario, pago online, anticipo configurable, confirmación automática | — | ✓ | ✓ |
| Múltiples ubicaciones, calendario consolidado, huéspedes, reportes | — | — | ✓ |

- Sin comisión de Rentplace por reserva; el procesador de pagos cobra aparte según el proveedor.
- Más de 3 ubicaciones: botón «Contactar a ventas».
- **Pendiente:** definir si los precios incluyen IVA (`TARIFA_IVA_INCLUIDO` en `tarifas.php`).

### Modelo de datos
- Se cobra por **ubicación** (dirección física), no por unidad. Ej.: Complejo Los Aromos = 5 cabañas +
  12 sitios de camping + 4 habitaciones = 1 ubicación facturable.
- El **tipo de alojamiento pertenece a la unidad**. Catálogo administrable de tipos y atributos
  (`tipos_alojamiento`, `atributos`, `tipos_atributos`) con atributos obligatorios/opcionales,
  de unidad o de ubicación, filtrables en el buscador.
- Inventario **individual** (Cabaña 01, 02…) o **agrupado** (20 sitios de camping iguales).
- Modalidad de arriendo separada del tipo físico (casa completa vs. por habitación).

### Flujo de reservas
- **Basic:** el huésped encuentra el alojamiento (buscador o QR/link), contacta al anfitrión, coordinan
  fechas y pago, y el anfitrión confirma manualmente.
- **Smart / Pro:** el huésped elige unidad, fechas y personas; el sistema verifica disponibilidad y calcula
  el total; se cobra el anticipo (porcentaje o monto fijo) o el total; tras verificar el pago se confirma
  automáticamente, se bloquea la disponibilidad, se registra el saldo y se notifica.

### Arquitectura
PHP 8 procedimental, MySQL con mysqli, HTML5 + Bootstrap + JS + jQuery, AJAX/JSON, módulos independientes
(PHP/JS/CSS + acceso a BD). Multiempresa, multiubicación y multiunidad. Proveedor de pagos por definir.

### Etapas
1. **Base funcional y vitrina:** modelo de datos, ubicaciones, unidades, tipos, atributos dinámicos,
   publicación, buscador, QR, contacto y coordinación manual.
2. **Reservas y pagos:** calendarios, tarifas, disponibilidad, anticipos, pagos y confirmación automática.
3. **Administración Pro:** múltiples ubicaciones, calendario consolidado, reportes, estadísticas y huéspedes.
4. **Evolución:** integraciones externas, automatizaciones, funcionalidades comerciales y optimización del buscador.

### Observaciones críticas registradas
- Basic aparece en el buscador sin calendario: marcarlo "Consultar disponibilidad" y, al filtrar por
  fechas, ordenarlo después de los que tienen disponibilidad confirmada.
- Unidades sin límite por ubicación: incluir cláusula de uso razonable en los términos.
- Comparaciones con otras plataformas: contrastar contra el costo total de la reserva y verificar los
  porcentajes vigentes antes de publicarlos. Basic es más barato que una comisión de 15,5% desde la
  noche 13 del año (noche de $50.000, pago anual).
- La pasarela debe operar con la cuenta del anfitrión; Rentplace no debe recibir el dinero de los huéspedes.

## Estado de la Etapa 1

**Hecho**
- [x] Planes, tabla comparativa, selector mensual/anual, «Contactar a ventas» y estimador en el inicio.
- [x] Esquema de base de datos (`sql/01_esquema_etapa1.sql`), catálogo inicial (`sql/02_catalogo_inicial.sql`)
      y migración de los datos actuales (`sql/03_migracion_desde_propiedades.sql`), probados en MariaDB.

- [x] Ahorro en 1 clic ("¿Cuánto arriendas al mes?") con el ahorro de cada plan.
- [x] Botones "Contratar" por plan con pago en Mercado Pago (Checkout Pro), modo prueba/producción
      configurable en `config.php` (`MP_MODO`), webhook con firma, activación idempotente de la
      suscripción y validación de monto. Guía: `GUIA_MERCADOPAGO.md`.
      Limitación: cada pago cubre 1 mes o 12 meses; no hay cobro recurrente automático.

**Por hacer (reescritura de módulos sobre el modelo nuevo)**
- [ ] Panel del anfitrión: datos del anfitrión, ubicaciones (con límite según plan) y unidades.
- [ ] Formulario de unidad con atributos dinámicos según el tipo (obligatorios/opcionales).
- [ ] Vitrina pública por ubicación (`/v/slug`), con sus unidades, servicios comunes y QR descargable.
- [ ] Contacto: botón WhatsApp y formulario, registrados en `consultas`.
- [ ] Buscador por tipo, ciudad, capacidad y atributos filtrables; Basic marcado "Consultar disponibilidad".
- [ ] Coordinación manual (Basic): el anfitrión registra/confirma reservas desde su panel.

## Historial (decisiones anteriores, ya reemplazadas)

### Objetivo inicial

Competir con Airbnb con una experiencia más simple y un costo menor, cobrando al anfitrión un
monto fijo que no dependa de cada arriendo (sin comisión por reserva). El huésped no paga cargo de servicio.

### Evolución del modelo de cobro

| # | Propuesta | Resultado |
|---|---|---|
| 1 | "Una noche al mes" con tope del 10% de lo facturado y descuento por volumen | Descartado: demasiado enredado |
| 2 | Suscripción = 1 noche al mes; semestral −15%, anual −30% | Reemplazado |
| 3 | Plan gratis + tarifa plana por reserva | Descartado por el dueño: muchos se quedarían en el plan gratis gestionando a mano |
| 4 | Vitrina gratis para captar anfitriones | Descartado por el dueño: hay anfitriones que **sí pagan** por una página web de su cabaña |
| 5 | **Planes anuales por propiedad (vigente)** | Ver abajo |

### Modelo anterior (reemplazado)

| Plan | Precio | Incluye |
|---|---:|---|
| Vitrina | $29.990 / año | Página propia con fotos, link para compartir, botón de contacto. Sin calendario ni reservas |
| Reservas | $99.990 / año | + calendario, reservas con confirmación manual, pago por transferencia confirmado por el anfitrión |
| Pro | **Por definir** (propuesto $149.990 / año) | + pago online con pasarela; el anfitrión elige cobrar el total o un % de anticipo |

Supuesto: precios por propiedad, con IVA incluido (pendiente de confirmar).

### Requisitos que se pidieron para el plan Pro
- Más barato que cualquier otra plataforma, sin dejar dudas.
- Muy atractivo para cambiarse a Rentplace.
- Sostenible.
- No tan barato que genere desconfianza.

### Propuesta que quedó sin aprobar
- Pro a **$149.990/año** (~$12.500/mes): se recupera desde la noche 26 del año (noche de $50.000,
  frente a comisión de 15,5%, incluyendo ~3,5% de pasarela de pago).
- **Garantía**: "si en tu primer año pagas más de lo que te habría cobrado una comisión de 15,5%,
  te devolvemos la diferencia". Hace la promesa de "más barato" imposible de discutir.
- Comparar contra el **costo total de la reserva** (anfitrión + huésped), no solo la parte del anfitrión:
  en el modelo de comisión dividida el anfitrión paga ~3% y el huésped ~14%.
- Cambiar el texto del estimador a: "Desde la noche 14 del año, Rentplace te sale más barato que una
  comisión de 15,5%". (Cálculo: $50.000 × 15,5% = $7.750 por noche; $99.990 ÷ $7.750 ≈ 13 noches **al año**.)

### Advertencias registradas en esa etapa
- Vitrina deja ≈$24.400 netos al año por cliente: solo es rentable con cero soporte (editor autoservicio)
  y si una parte pasa a Reservas. Debe dar valor visible (estadísticas de visitas y clics) y, idealmente,
  dominio propio en vez de `rentplace.cl/xxx`.
- Las propiedades Vitrina no deben mezclarse con las de reserva inmediata en el buscador.
- Reservas con pago manual: las reservas pendientes deben vencer solas (ej. 48 h).
- Anticipo parcial: definir dónde se paga el saldo, quién lo registra y la política de cancelación,
  mostrada antes de pagar.
- La pasarela debe ser la cuenta propia de cada anfitrión (Mercado Pago / Flow): Rentplace no debe
  recibir el dinero de los huéspedes. Validar con contador/abogado.
- Cobro anual por adelantado puede frenar a anfitriones pequeños: evaluar opción mensual más cara.
- Validar antes de construir: conversar con 10 anfitriones y conseguir cupos pagados de lanzamiento.
- Porcentajes de Airbnb/Booking citados de memoria: verificar antes de publicarlos.

## Lo construido
- Seguridad: credenciales fuera del repo (`config.php`), subida de fotos segura, redirección del login,
  validaciones de fechas y calendario.
- `index.js` + `index-api.php`: categorías, destinos y autocompletado del inicio.
- `ficha_propiedad.js`: galería, detalle, precio total en vivo, reserva → checkout, barra móvil.
- Inicio: tarjetas de los 3 planes + estimador del plan Reservas frente a comisión de 15,5%.
- Panel del anfitrión: costo anual de cada plan según sus propiedades activas.

## Pendiente del dueño
- [ ] Cambiar la contraseña de la base de datos y crear `config.php` en el servidor.
- [ ] Cambiar la clave de vista previa (`PREVIEW_TOKEN` en `control_lanzamiento.php`).
- [ ] Respaldar la base y ejecutar `sql/01`, `sql/02` y `sql/04` en phpMyAdmin (`sql/03` junto con la Etapa 1).
- [ ] Configurar Mercado Pago siguiendo `GUIA_MERCADOPAGO.md`.
- [ ] Definir si los precios incluyen IVA.
- [ ] Elegir proveedor de pagos (Etapa 2) y política de anticipo/cancelación.

Acceso de prueba con el sitio en "muy pronto": `https://rentplace.cl/index.php?preview=<clave>`
