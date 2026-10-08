<?php
/**
 * tarifas.php
 * Única fuente de verdad de los planes de Rentplace para anfitriones.
 * La usan index.php (estimador) y mis_propiedades-api.php (panel del anfitrión).
 *
 * PLANES ANUALES, POR PROPIEDAD, IVA INCLUIDO. Sin comisión por reserva.
 *   - Vitrina:  página propia con fotos y link para compartir. Sin calendario ni reservas.
 *   - Reservas: + calendario, reservas con confirmación manual y pago coordinado
 *               con el anfitrión (transferencia), confirmado manualmente.
 *   - Pro:      + pago online con pasarela; el anfitrión elige cobrar el total o
 *               un porcentaje de anticipo.
 *
 * precio_anual = null significa "precio por definir": el estimador lo muestra
 * como "Próximamente" y no calcula con él.
 */

const TARIFA_PLANES = [
    'vitrina' => [
        'nombre'       => 'Vitrina',
        'precio_anual' => 29990,
        'reservas'     => false,
        'incluye'      => ['Página propia con fotos y descripción', 'Link para compartir en WhatsApp e Instagram', 'Botón de contacto directo'],
    ],
    'reservas' => [
        'nombre'       => 'Reservas',
        'precio_anual' => 99990,
        'reservas'     => true,
        'incluye'      => ['Todo lo de Vitrina', 'Calendario de disponibilidad', 'Reservas con confirmación manual', 'Pago por transferencia, confirmado por ti'],
    ],
    'pro' => [
        'nombre'       => 'Pro',
        'precio_anual' => null, // TODO: definir precio antes de lanzar
        'reservas'     => true,
        'incluye'      => ['Todo lo de Reservas', 'Pago online con tarjeta', 'Cobra el total o un % de anticipo'],
    ],
];

// Comisión de referencia de Airbnb (modelo "solo anfitrión"), solo para comparar en el estimador.
const TARIFA_REFERENCIA_AIRBNB = 0.155;

/**
 * Costo anual de un anfitrión en cada plan según cuántas propiedades activas tiene.
 * Devuelve ['vitrina' => 29990, 'reservas' => 99990, 'pro' => null] para 1 propiedad.
 */
function tarifa_costos_anfitrion(int $propiedades_activas): array
{
    $costos = [];
    foreach (TARIFA_PLANES as $clave => $plan) {
        $costos[$clave] = $plan['precio_anual'] === null ? null : $plan['precio_anual'] * max(0, $propiedades_activas);
    }
    return $costos;
}
