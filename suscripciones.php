<?php
/**
 * suscripciones.php
 * Reglas de negocio de las suscripciones de anfitriones (sin salida HTML/JSON).
 * Lo usan suscripcion-api.php, suscripcion_retorno.php y mp_webhook.php.
 *
 * Requiere: $conexion (mysqli), tarifas.php y las tablas de sql/01, 02 y 04.
 */

require_once __DIR__ . '/tarifas.php';

/**
 * Devuelve el id de anfitrión del usuario; si aún no existe, lo crea.
 */
function suscripcion_id_anfitrion(mysqli $conexion, int $id_usuario): ?int
{
    $stmt = $conexion->prepare('SELECT id FROM anfitriones WHERE id_usuario = ?');
    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($fila) {
        return (int) $fila['id'];
    }

    $stmt = $conexion->prepare(
        "INSERT INTO anfitriones (id_usuario, nombre_comercial, email_contacto, creado_en)
         SELECT id, CONCAT(nombre, ' ', apellido), email, NOW() FROM usuarios WHERE id = ?"
    );
    $stmt->bind_param('i', $id_usuario);
    $ok = $stmt->execute() && $stmt->affected_rows === 1;
    $id = $ok ? (int) $stmt->insert_id : null;
    $stmt->close();
    return $id;
}

function suscripcion_id_plan(mysqli $conexion, string $codigo): ?int
{
    $stmt = $conexion->prepare('SELECT id FROM planes_suscripcion WHERE codigo = ? AND activo = 1');
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ? (int) $fila['id'] : null;
}

/**
 * Monto a cobrar según tarifas.php (nunca se confía en el monto que envía el navegador).
 */
function suscripcion_monto(string $plan, string $periodicidad): ?int
{
    if (!isset(TARIFA_PLANES[$plan]) || !in_array($periodicidad, ['mensual', 'anual'], true)) {
        return null;
    }
    return $periodicidad === 'anual' ? tarifa_precio_anual($plan) : TARIFA_PLANES[$plan]['precio_mensual'];
}

/**
 * Registra el resultado de un pago consultado a Mercado Pago y, si está aprobado,
 * activa o extiende la suscripción. Es idempotente: el mismo pago puede llegar
 * por el retorno del navegador y por el webhook, y solo se aplica una vez.
 *
 * Devuelve el registro de pagos_suscripcion actualizado, o null si no corresponde
 * a ningún pago de Rentplace.
 */
function suscripcion_procesar_pago(mysqli $conexion, array $pago_mp): ?array
{
    $referencia = (string) ($pago_mp['external_reference'] ?? '');
    $id_pago_mp = (string) ($pago_mp['id'] ?? '');
    $estado = (string) ($pago_mp['status'] ?? '');
    if ($referencia === '' || $id_pago_mp === '' || $estado === '') {
        return null;
    }

    $conexion->begin_transaction();

    $stmt = $conexion->prepare('SELECT * FROM pagos_suscripcion WHERE referencia = ? FOR UPDATE');
    $stmt->bind_param('s', $referencia);
    $stmt->execute();
    $pago = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pago) {
        $conexion->rollback();
        return null;
    }

    // Un pago aprobado debe coincidir en monto y moneda con lo que se cobró.
    $monto_ok = (int) round((float) ($pago_mp['transaction_amount'] ?? 0)) === (int) $pago['monto']
        && ($pago_mp['currency_id'] ?? '') === 'CLP';
    if ($estado === 'approved' && !$monto_ok) {
        error_log('Rentplace: pago ' . $id_pago_mp . ' aprobado con monto/moneda distinto a ' . $referencia);
        $estado = 'monto_invalido';
    }

    $stmt = $conexion->prepare(
        'UPDATE pagos_suscripcion SET estado = ?, mp_payment_id = ?, actualizado_en = NOW() WHERE id = ?'
    );
    $stmt->bind_param('ssi', $estado, $id_pago_mp, $pago['id']);
    $stmt->execute();
    $stmt->close();
    $pago['estado'] = $estado;
    $pago['mp_payment_id'] = $id_pago_mp;

    if ($estado === 'approved' && !(int) $pago['aplicado']) {
        $pago['id_suscripcion'] = suscripcion_extender($conexion, $pago);
        $pago['aplicado'] = 1;

        $stmt = $conexion->prepare('UPDATE pagos_suscripcion SET aplicado = 1, id_suscripcion = ? WHERE id = ?');
        $stmt->bind_param('ii', $pago['id_suscripcion'], $pago['id']);
        $stmt->execute();
        $stmt->close();
    }

    $conexion->commit();
    return $pago;
}

/**
 * Mismo plan vigente: suma el período a su fecha de término.
 * Plan distinto o sin plan vigente: termina el anterior y crea uno nuevo desde hoy.
 */
function suscripcion_extender(mysqli $conexion, array $pago): int
{
    $meses = $pago['periodicidad'] === 'anual' ? 12 : 1;
    $id_anfitrion = (int) $pago['id_anfitrion'];
    $id_plan = (int) $pago['id_plan'];

    $stmt = $conexion->prepare(
        "SELECT id, id_plan, fecha_fin FROM suscripciones
         WHERE id_anfitrion = ? AND estado IN ('activa','prueba')
         ORDER BY id DESC LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $id_anfitrion);
    $stmt->execute();
    $vigente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $hoy = date('Y-m-d');

    if ($vigente && (int) $vigente['id_plan'] === $id_plan) {
        $desde = ($vigente['fecha_fin'] && $vigente['fecha_fin'] > $hoy) ? $vigente['fecha_fin'] : $hoy;
        $hasta = date('Y-m-d', strtotime("$desde +$meses month"));
        $stmt = $conexion->prepare(
            "UPDATE suscripciones SET estado = 'activa', periodicidad = ?, fecha_fin = ? WHERE id = ?"
        );
        $stmt->bind_param('ssi', $pago['periodicidad'], $hasta, $vigente['id']);
        $stmt->execute();
        $stmt->close();
        return (int) $vigente['id'];
    }

    if ($vigente) {
        $stmt = $conexion->prepare("UPDATE suscripciones SET estado = 'cancelada', fecha_fin = ? WHERE id = ?");
        $stmt->bind_param('si', $hoy, $vigente['id']);
        $stmt->execute();
        $stmt->close();
    }

    $hasta = date('Y-m-d', strtotime("$hoy +$meses month"));
    $stmt = $conexion->prepare(
        "INSERT INTO suscripciones (id_anfitrion, id_plan, periodicidad, estado, ubicaciones_contratadas, fecha_inicio, fecha_fin, creado_en)
         SELECT ?, ?, ?, 'activa', max_ubicaciones, ?, ?, NOW() FROM planes_suscripcion WHERE id = ?"
    );
    $stmt->bind_param('iisssi', $id_anfitrion, $id_plan, $pago['periodicidad'], $hoy, $hasta, $id_plan);
    $stmt->execute();
    $id = (int) $stmt->insert_id;
    $stmt->close();
    return $id;
}
