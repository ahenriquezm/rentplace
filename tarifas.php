<?php
/**
 * tarifas.php
 * Única fuente de verdad del precio de suscripción de Rentplace para anfitriones.
 * La usan index.php (estimador) y mis_propiedades-api.php (panel del anfitrión).
 *
 * MODELO: SUSCRIPCIÓN = 1 NOCHE AL MES
 *   - El huésped no paga comisión de servicio.
 *   - El anfitrión paga, por propiedad, el valor de 1 noche al mes (plan mensual).
 *   - Pagando por adelantado: semestral -15%, anual -30%.
 *   - Nunca se cobra un porcentaje de las reservas: mientras más arriendas,
 *     menor es lo que pagas en proporción.
 *
 * Comisión equivalente = factor del plan / noches reservadas en el mes.
 *   Ej.: con 20 noches reservadas -> mensual 5%, semestral 4,3%, anual 3,5%.
 */

const TARIFA_PLANES = [
    'mensual'   => ['nombre' => 'Mensual',   'meses' => 1,  'factor' => 1.00],
    'semestral' => ['nombre' => 'Semestral', 'meses' => 6,  'factor' => 0.85],
    'anual'     => ['nombre' => 'Anual',     'meses' => 12, 'factor' => 0.70],
];

// Comisión de referencia de Airbnb (modelo "solo anfitrión"), solo para comparar en el estimador.
const TARIFA_REFERENCIA_AIRBNB = 0.155;

/**
 * Cuota mensual de UNA propiedad en un plan dado.
 */
function tarifa_cuota_mensual(float $precio_noche, string $plan = 'mensual'): float
{
    $factor = TARIFA_PLANES[$plan]['factor'] ?? 1.0;
    return round(max(0.0, $precio_noche) * $factor);
}

/**
 * Cuota mensual de un anfitrión por cada plan, dada la lista de precios por noche
 * de sus propiedades activas. Devuelve ['mensual' => 50000, 'semestral' => 42500, ...].
 */
function tarifa_cuotas_anfitrion(array $precios_noche): array
{
    $cuotas = [];
    foreach (array_keys(TARIFA_PLANES) as $plan) {
        $cuotas[$plan] = 0.0;
        foreach ($precios_noche as $precio) {
            $cuotas[$plan] += tarifa_cuota_mensual((float) $precio, $plan);
        }
    }
    return $cuotas;
}
