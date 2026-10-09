# Rentplace — Historial de decisiones

Resumen de las conversaciones de trabajo, para no depender del historial del chat.
Rama de trabajo: `claude/vibrant-mccarthy-dq5vw6`. Detalle técnico en `REVISION.md`.

## Objetivo del producto

Competir con Airbnb con una experiencia más simple y un costo menor, cobrando al anfitrión un
monto fijo que no dependa de cada arriendo (sin comisión por reserva). El huésped no paga cargo de servicio.

## Evolución del modelo de cobro

| # | Propuesta | Resultado |
|---|---|---|
| 1 | "Una noche al mes" con tope del 10% de lo facturado y descuento por volumen | Descartado: demasiado enredado |
| 2 | Suscripción = 1 noche al mes; semestral −15%, anual −30% | Reemplazado |
| 3 | Plan gratis + tarifa plana por reserva | Descartado por el dueño: muchos se quedarían en el plan gratis gestionando a mano |
| 4 | Vitrina gratis para captar anfitriones | Descartado por el dueño: hay anfitriones que **sí pagan** por una página web de su cabaña |
| 5 | **Planes anuales por propiedad (vigente)** | Ver abajo |

### Modelo vigente (en `tarifas.php`)

| Plan | Precio | Incluye |
|---|---:|---|
| Vitrina | $29.990 / año | Página propia con fotos, link para compartir, botón de contacto. Sin calendario ni reservas |
| Reservas | $99.990 / año | + calendario, reservas con confirmación manual, pago por transferencia confirmado por el anfitrión |
| Pro | **Por definir** (propuesto $149.990 / año) | + pago online con pasarela; el anfitrión elige cobrar el total o un % de anticipo |

Supuesto: precios por propiedad, con IVA incluido (pendiente de confirmar).

### Requisitos del plan Pro (definidos por el dueño)
- Más barato que cualquier otra plataforma, sin dejar dudas.
- Muy atractivo para cambiarse a Rentplace.
- Sostenible.
- No tan barato que genere desconfianza.

### Propuesta pendiente de aprobación
- Pro a **$149.990/año** (~$12.500/mes): se recupera desde la noche 26 del año (noche de $50.000,
  frente a comisión de 15,5%, incluyendo ~3,5% de pasarela de pago).
- **Garantía**: "si en tu primer año pagas más de lo que te habría cobrado una comisión de 15,5%,
  te devolvemos la diferencia". Hace la promesa de "más barato" imposible de discutir.
- Comparar contra el **costo total de la reserva** (anfitrión + huésped), no solo la parte del anfitrión:
  en el modelo de comisión dividida el anfitrión paga ~3% y el huésped ~14%.
- Cambiar el texto del estimador a: "Desde la noche 14 del año, Rentplace te sale más barato que una
  comisión de 15,5%". (Cálculo: $50.000 × 15,5% = $7.750 por noche; $99.990 ÷ $7.750 ≈ 13 noches **al año**.)

## Advertencias críticas registradas
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

## Pendiente
**Acciones del dueño**
- [ ] Cambiar la contraseña de la base de datos y crear `config.php` en el servidor.
- [ ] Cambiar la clave de vista previa (`PREVIEW_TOKEN` en `control_lanzamiento.php`).
- [ ] Definir precio del plan Pro y aprobar (o no) la garantía.
- [ ] Elegir pasarela de pago y política de anticipo/cancelación.

**Desarrollo**
- [ ] Ajustar texto del estimador ("desde la noche 14 del año") y agregar comparación del plan Pro.
- [ ] Página Vitrina pública por propiedad.
- [ ] Registro del plan contratado por cada anfitrión y permisos según plan.
- [ ] Flujo del plan Reservas: confirmación manual, pago marcado por el anfitrión, vencimiento automático.
- [ ] Plan Pro: integración de pasarela con anticipo configurable.
- [ ] Pago real en checkout (hoy está simulado).

Acceso de prueba con el sitio en "muy pronto": `https://rentplace.cl/index.php?preview=<clave>`
