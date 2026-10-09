<?php
/**
 * tarifas.php
 * Única fuente de verdad de los planes SaaS de Rentplace (ver DECISIONES.md).
 * La usan index.php (planes + estimador) y mis_propiedades-api.php (panel del anfitrión).
 *
 * MODELO: suscripción mensual fija por UBICACIÓN (dirección física), unidades sin límite.
 *   - Sin comisión de la plataforma por reserva.
 *   - Pago anual = 10 mensualidades (2 meses gratis).
 *   - Costos de procesamiento de pago: aparte, según el proveedor.
 *   - Más de 3 ubicaciones: "Contactar a ventas".
 *   - PENDIENTE: definir si los precios incluyen IVA (TARIFA_IVA_INCLUIDO).
 */

const TARIFA_PLANES = [
    'basic' => [
        'nombre'          => 'Basic',
        'precio_mensual'  => 9990,
        'ubicaciones'     => 1,
        'reserva_online'  => false,
        'resumen'         => 'Vitrina y contacto directo',
    ],
    'smart' => [
        'nombre'          => 'Smart',
        'precio_mensual'  => 24990,
        'ubicaciones'     => 1,
        'reserva_online'  => true,
        'resumen'         => 'Reservas y pago online',
    ],
    'pro' => [
        'nombre'          => 'Pro',
        'precio_mensual'  => 59990,
        'ubicaciones'     => 3,
        'reserva_online'  => true,
        'resumen'         => 'Varias ubicaciones y gestión completa',
    ],
];

// Matriz de funcionalidades: [etiqueta, basic, smart, pro]
const TARIFA_FUNCIONALIDADES = [
    ['Vitrina + QR + link para compartir',  true,  true,  true],
    ['Presencia en buscador público',       true,  true,  true],
    ['Contacto directo con anfitrión',      true,  true,  true],
    ['Coordinación directa de reserva',     true,  true,  true],
    ['Calendario de disponibilidad',        false, true,  true],
    ['Pago online integrado',               false, true,  true],
    ['Anticipo configurable',               false, true,  true],
    ['Confirmación automática tras pago',   false, true,  true],
    ['Gestión de múltiples ubicaciones',    false, false, true],
    ['Calendario consolidado',              false, false, true],
    ['Gestión de huéspedes',                false, false, true],
    ['Reportes y estadísticas',             false, false, true],
];

const TARIFA_MESES_PAGADOS_ANUAL = 10;   // 12 meses de servicio por 10 mensualidades
const TARIFA_IVA_INCLUIDO = null;         // null = por definir; true/false cuando se decida
const TARIFA_CONTACTO_VENTAS = 'mailto:hola@rentplace.cl?subject=Plan%20para%20m%C3%A1s%20de%203%20ubicaciones';

// Solo para el estimador: comisión de referencia de otras plataformas y costo estimado
// del procesador de pagos (se cobra aparte en Smart y Pro). Verificar antes de publicar.
const TARIFA_REFERENCIA_COMISION = 0.155;
const TARIFA_COSTO_PASARELA_ESTIMADO = 0.035;

function tarifa_precio_anual(string $plan): int
{
    return TARIFA_PLANES[$plan]['precio_mensual'] * TARIFA_MESES_PAGADOS_ANUAL;
}

/**
 * Plan mínimo que cubre una cantidad de ubicaciones con reserva online o sin ella.
 * Devuelve null si supera lo publicado (corresponde "Contactar a ventas").
 */
function tarifa_plan_sugerido(int $ubicaciones, bool $necesita_reserva_online): ?string
{
    foreach (TARIFA_PLANES as $clave => $plan) {
        if ($ubicaciones <= $plan['ubicaciones'] && (!$necesita_reserva_online || $plan['reserva_online'])) {
            return $clave;
        }
    }
    return null;
}
