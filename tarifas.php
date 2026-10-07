<?php
/**
 * tarifas.php
 * Única fuente de verdad del modelo de cobro de Rentplace al anfitrión.
 *
 * MODELO "UNA NOCHE AL MES"
 *   - El huésped no paga comisión de servicio (cargo_servicio = 0 siempre).
 *   - El anfitrión paga, por cada propiedad activa, una cuota mensual FIJA igual
 *     al precio base de UNA noche de esa propiedad (con piso y techo).
 *   - Descuento por volumen para anfitriones con varias propiedades.
 *   - Garantía: la cuota del mes nunca supera el TOPE_PORCENTAJE de lo que el
 *     anfitrión facturó ese mes en Rentplace (mes sin reservas = $0).
 *
 * Por qué funciona para ambos extremos:
 *   - Anfitrión chico / temporada baja: la garantía lo protege; nunca paga más
 *     que el 65% de lo que le cobraría Airbnb.
 *   - Anfitrión con alta ocupación: paga lo mismo aunque reserve 25 noches,
 *     así que su porcentaje efectivo cae a 4%-5%.
 *   - Plataforma: con ~10 noches/mes (≈33% de ocupación) la cuota equivale al
 *     ~10% del ingreso, es decir, 35% menos que el 15,5% de Airbnb, sin que el
 *     ingreso de Rentplace caiga a porcentajes irrisorios.
 */

// Referencia: comisión de servicio de Airbnb modelo "solo anfitrión" (host-only fee).
const TARIFA_REFERENCIA_AIRBNB = 0.155;
// Rentplace cobra como máximo el 65% de la referencia (35% más barato): 10,075% -> 10%.
const TARIFA_TOPE_PORCENTAJE = 0.10;

const TARIFA_PISO_POR_PROPIEDAD  = 9990;   // CLP/mes
const TARIFA_TECHO_POR_PROPIEDAD = 149990; // CLP/mes

// [mínimo de propiedades activas => descuento sobre la suma de cuotas]
const TARIFA_DESCUENTOS_VOLUMEN = [10 => 0.20, 3 => 0.10];

/**
 * Cuota base mensual de una propiedad: el precio de una noche, acotado entre piso y techo.
 */
function tarifa_cuota_propiedad(float $precio_noche): float
{
    return min(TARIFA_TECHO_POR_PROPIEDAD, max(TARIFA_PISO_POR_PROPIEDAD, round($precio_noche)));
}

function tarifa_descuento_volumen(int $propiedades_activas): float
{
    foreach (TARIFA_DESCUENTOS_VOLUMEN as $minimo => $descuento) {
        if ($propiedades_activas >= $minimo) {
            return $descuento;
        }
    }
    return 0.0;
}

/**
 * Calcula lo que paga un anfitrión en un mes.
 *
 * @param float[] $precios_noche   precio base por noche de cada propiedad ACTIVA
 * @param float   $ingresos_mes    total de reservas confirmadas del anfitrión en el mes
 * @return array{cuota_plan: float, descuento: float, tope_garantia: float, a_pagar: float,
 *               porcentaje_efectivo: ?float, costo_airbnb: float, ahorro: float}
 */
function tarifa_calcular_mes(array $precios_noche, float $ingresos_mes): array
{
    $suma = 0.0;
    foreach ($precios_noche as $precio) {
        $suma += tarifa_cuota_propiedad((float) $precio);
    }

    $descuento = tarifa_descuento_volumen(count($precios_noche));
    $cuota_plan = round($suma * (1 - $descuento));
    $tope = round(max(0.0, $ingresos_mes) * TARIFA_TOPE_PORCENTAJE);
    $a_pagar = min($cuota_plan, $tope);
    $costo_airbnb = round(max(0.0, $ingresos_mes) * TARIFA_REFERENCIA_AIRBNB);

    return [
        'cuota_plan'          => $cuota_plan,
        'descuento'           => $descuento,
        'tope_garantia'       => $tope,
        'a_pagar'             => $a_pagar,
        'porcentaje_efectivo' => $ingresos_mes > 0 ? round($a_pagar / $ingresos_mes, 4) : null,
        'costo_airbnb'        => $costo_airbnb,
        'ahorro'              => $costo_airbnb - $a_pagar,
    ];
}
