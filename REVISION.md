# Revisión del código de Rentplace

Fecha: 2026-10-07 · Alcance: los 43 archivos de la carga inicial (PHP + jQuery, sin framework).

## Resumen

La base es ordenada y bien intencionada: consultas preparadas en todo el backend, contraseñas con
`password_hash`, regeneración de sesión al iniciar sesión, consultas acotadas al dueño (`id_anfitrion`),
re-validación de disponibilidad en el servidor y precios por noche desde el calendario. **Pero hoy no
está lista para producción**: faltan archivos que rompen el flujo principal, hay dos vulnerabilidades
críticas y el pago está simulado.

## 1. Corregido en este cambio

| Severidad | Problema | Archivo | Corrección |
|---|---|---|---|
| Crítica | Credenciales reales de la BD en el repositorio | `conexion.php` | Se leen de `config.php` (en `.gitignore`) o variables de entorno. Ver `config.example.php`. **Hay que cambiar la contraseña de la BD en cPanel**: la anterior quedó en el historial de git. |
| Crítica | Subida de fotos confiaba en el MIME y la extensión enviados por el navegador: se podía subir `foto.php` y ejecutarlo (control total del servidor) | `publicar_propiedad-api.php` | MIME detectado del contenido (`finfo` + `getimagesize`), extensión fija según el tipo real, nombre aleatorio, `.htaccess` que desactiva PHP en `uploads/`, máximo 20 fotos. |
| Alta | `login.php?redirect=javascript:...` ejecutaba código tras iniciar sesión (XSS / redirección abierta) | `login.php` | Solo se acepta una página interna `*.php`. |
| Alta | La ficha de propiedad no iniciaba sesión (siempre "Ingresar") y se saltaba el modo *coming soon* | `ficha_propiedad.php` | Incluye `control_lanzamiento.php`. |
| Media | Se podían reservar fechas pasadas | `ficha_propiedad.api.php` | Validación de llegada ≥ hoy. |
| Media | Calendario aceptaba precios ≤ 0 y valores arbitrarios en `disponible` | `calendario-api.php` | Validación. |
| Media | Marca mezclada "offday" / "Rentplace" en la ficha | `ficha_propiedad.php` | Unificada a Rentplace. |
| Baja | `error_log` del servidor versionado (expone rutas del hosting) | `.gitignore` | Fuera del repo. |

## 2. Pendiente — bloquea el lanzamiento

1. **Faltan archivos** referenciados por el sitio: `index.js`, `ficha_propiedad.js`, `index-api.php`,
   `registro.php` (enlazado en el header; el registro real está en una pestaña de `login.php`) y `ayuda.php`.
   Sin `ficha_propiedad.js` **no se puede reservar**. Además la API se llama `ficha_propiedad.api.php`
   (con punto) mientras todos los demás usan guion: confirmar qué nombre usa el JS que falta.
2. **Pago simulado** (`checkout-api.php`): cualquier reserva se marca "confirmada" sin cobrar. Integrar
   Webpay Plus / Mercado Pago / Flow con confirmación por webhook. Los campos de tarjeta del formulario
   deben eliminarse y reemplazarse por el formulario/redirect de la pasarela (nunca manejar tarjetas propias).
3. **Reservas "pendiente_pago" bloquean el calendario para siempre**: alguien puede bloquear todas las
   fechas de un anfitrión sin pagar. Agregar expiración (ej. 20 min) a todas las consultas de
   disponibilidad y en el checkout.
4. **Sin protección CSRF** en los POST (cancelar reserva, pausar propiedad, calendario). Agregar token por sesión.
5. **Sin límite de intentos** en login ni en la suscripción del *coming soon* (fuerza bruta / spam).
6. **No hay esquema de base de datos** versionado (`schema.sql` / migraciones). Necesario para reproducir el entorno.
7. Cancelación sin política: el huésped cancela una reserva confirmada sin reembolso definido ni aviso al anfitrión.

## 3. Pendiente — UX para competir con Airbnb

- **Una sola hoja de estilos y un header compartido** (`header.php`, `base.css`): hoy cada página repite
  variables, utilidades y el logo; ya produjo marcas distintas. `ficha_propiedad.php` carga Bootstrap y el resto no.
- **Móvil primero**: la barra de búsqueda y el menú no tienen versión móvil (en Chile la mayoría reserva desde el teléfono).
  Barra inferior fija con precio total + "Reservar" en la ficha.
- **Precio total desde el primer clic**: mostrar en las tarjetas de búsqueda el total de la estadía
  (noches + limpieza) cuando hay fechas, no solo el precio por noche. Es la mayor ventaja frente a Airbnb y hoy no se ve.
- Calendario de rango en un solo control (llegada→salida) en vez de dos `input type=date`; selector de huéspedes con +/−.
- Mapa en resultados, filtros por precio y amenities, favoritos, reseñas visibles en la ficha (la tabla `resenas` ya existe).
- Mensajería anfitrión–huésped y correos transaccionales (el checkout dice "te enviamos los detalles" pero no se envía nada).
- El formulario del *coming soon* pide el perfil (huésped/anfitrión) pero no lo envía a la API: se pierde el dato de segmentación.
- Accesibilidad: el botón de búsqueda "⌕" no tiene texto accesible; contraste de textos grises; foco visible.

## 4. Modelo de cobro: "Una noche al mes"

Objetivo: costo fijo que no dependa de cada arriendo, **al menos 35% más barato que Airbnb** para
todos los anfitriones, sin que el ingreso de la plataforma caiga a porcentajes irrisorios.

**Regla (implementada en `tarifas.php`):**

- El huésped **no paga comisión** de servicio (ya era así en el código).
- El anfitrión paga por cada propiedad activa una **cuota mensual fija = el precio de una noche**
  (mínimo $9.990, máximo $149.990).
- **Descuento por volumen:** −10% desde 3 propiedades, −20% desde 10.
- **Garantía 35%:** la cuota del mes nunca supera el **10%** de lo facturado ese mes
  (Airbnb host-only cobra 15,5%; 65% de eso = 10%). Mes sin reservas = $0.

**Por qué cumple los tres objetivos:** a ~10 noches/mes (33% de ocupación) la cuota equivale exactamente
al 10% → la plataforma mantiene su porcentaje objetivo. Con menos ocupación la garantía protege al anfitrión
pequeño; con más ocupación la cuota no sube, así que el anfitrión de alta facturación paga un porcentaje cada vez menor.

| Caso | Ingresos mes | Paga en Rentplace | % efectivo | Airbnb (15,5%) | Ahorro |
|---|---:|---:|---:|---:|---:|
| Cabaña $35.000, temporada baja (3 noches) | $105.000 | $10.500 | 10% | $16.275 | 35% |
| Cabaña $35.000, 10 noches | $350.000 | $35.000 | 10% | $54.250 | 35% |
| Depto $60.000, 22 noches | $1.320.000 | $60.000 | 4,6% | $204.600 | 71% |
| Casa premium $180.000, 12 noches | $2.160.000 | $149.990 | 6,9% | $334.800 | 55% |
| 4 propiedades, 40 noches | $2.100.000 | $189.000 | 9% | $325.500 | 42% |
| Operador 12 propiedades, 180 noches | $12.600.000 | $672.000 | 5,3% | $1.953.000 | 66% |

Además el huésped ahorra el cargo de servicio que Airbnb le cobra en el modelo de comisión dividida (~14%).

**A decidir antes de lanzar:**
- **Costo de la pasarela de pago** (Webpay/Mercado Pago ≈ 1,5%–3,5%): o lo absorbe Rentplace dentro de la cuota,
  o se traspasa explícitamente. Si lo absorbe, el margen real a 10 noches/mes baja a ~7%.
- Cobro de la cuota: al cierre de cada mes (permite aplicar la garantía con datos reales) descontándola
  del pago al anfitrión, lo que evita gestionar cobros con tarjeta aparte.
- Validar la referencia de Airbnb vigente en Chile al momento del lanzamiento y ajustar `TARIFA_REFERENCIA_AIRBNB`.

El panel del anfitrión (`mis_propiedades.php`) ya muestra la cuota del mes, el % efectivo y el ahorro estimado.
