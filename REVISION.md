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
| Media | Faltaban `index.js` e `index-api.php` (inicio sin categorías ni destinos) y enlaces rotos a `registro.php` / `ayuda.php` | `index.*`, `buscar.php`, `login.js` | Creados; "Crear cuenta" abre la pestaña de registro. |
| Media | Marca mezclada "offday" / "Rentplace" en la ficha | `ficha_propiedad.php` | Unificada a Rentplace. |
| Baja | `error_log` del servidor versionado (expone rutas del hosting) | `.gitignore` | Fuera del repo. |

## 2. Pendiente — bloquea el lanzamiento

1. ~~Faltaban archivos~~: `ficha_propiedad.js`, `index.js` e `index-api.php` ya están creados. La ficha muestra galería, descripción, servicios, precio total en vivo y barra fija para reservar en el celular; sin sesión pide iniciar sesión y vuelve con las mismas fechas.
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

## 4. Modelo de cobro: suscripción de 1 noche al mes

Simple y fijo, implementado en `tarifas.php` (lo usan el estimador del inicio y el panel del anfitrión):

| Plan | Precio por propiedad | Con 20 noches arrendadas equivale a |
|---|---|---:|
| Mensual (sin permanencia) | 1 noche al mes | 5,0% |
| Semestral | 1 noche al mes −15% | 4,3% |
| Anual | 1 noche al mes −30% | 3,5% |

- El huésped no paga comisión de servicio.
- Nunca se cobra un porcentaje de las reservas: mientras más arriendas, menos pagas en proporción.
- Comisión equivalente = (factor del plan) ÷ (noches arrendadas en el mes). No depende del precio.
- Punto de equilibrio frente a una comisión de 15,5%: mensual ≈ 6,5 noches/mes, anual ≈ 4,5 noches/mes.
  Con menos noches que eso, al anfitrión le conviene más una comisión (el estimador lo muestra con honestidad).

El inicio (`index.php#precios`) tiene un estimador: precio por noche + noches al mes + plan → cuota mensual,
total del período, % equivalente, comparación con una comisión de 15,5% y ahorro anual.

**A decidir antes de lanzar:** quién absorbe el costo de la pasarela de pago (≈1,5%–3,5%) y validar la
comisión de referencia vigente (`TARIFA_REFERENCIA_AIRBNB`).
