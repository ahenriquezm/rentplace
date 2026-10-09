<?php
/**
 * suscripcion_retorno.php
 * Página a la que Mercado Pago devuelve al anfitrión después de pagar un plan.
 * Mercado Pago agrega ?payment_id=...&status=...&external_reference=...
 * El estado que se muestra NO se toma de la URL (se puede falsificar): se
 * consulta el pago a la API de Mercado Pago. El webhook hace lo mismo en
 * paralelo; el pago solo se aplica una vez.
 */
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/control_lanzamiento.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mercadopago.php';
require_once __DIR__ . '/suscripciones.php';

$id_pago = (string) ($_GET['payment_id'] ?? $_GET['collection_id'] ?? '');
$pago = null;
$suscripcion = null;

if ($id_pago !== '' && $id_pago !== 'null' && mp_configurado()) {
    $pago_mp = mp_obtener_pago($id_pago);
    if ($pago_mp) {
        $pago = suscripcion_procesar_pago($conexion, $pago_mp);
    }
}

if ($pago && (int) $pago['aplicado'] && $pago['id_suscripcion']) {
    $stmt = $conexion->prepare(
        'SELECT s.fecha_fin, p.nombre FROM suscripciones s INNER JOIN planes_suscripcion p ON p.id = s.id_plan WHERE s.id = ?'
    );
    $stmt->bind_param('i', $pago['id_suscripcion']);
    $stmt->execute();
    $suscripcion = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$estado = $pago['estado'] ?? (($_GET['status'] ?? '') === 'null' ? 'cancelado' : 'desconocido');

if ($estado === 'approved' && $suscripcion) {
    $titulo = '¡Pago aprobado!';
    $texto = 'Tu plan ' . $suscripcion['nombre'] . ' está activo hasta el ' . date('d-m-Y', strtotime($suscripcion['fecha_fin'])) . '.';
    $tono = 'ok';
} elseif (in_array($estado, ['pending', 'in_process', 'authorized'], true)) {
    $titulo = 'Pago en revisión';
    $texto = 'Mercado Pago está procesando tu pago. Activaremos tu plan apenas se confirme; no necesitas pagar de nuevo.';
    $tono = 'espera';
} elseif (in_array($estado, ['rejected', 'cancelled', 'cancelado'], true)) {
    $titulo = 'El pago no se completó';
    $texto = 'No se realizó ningún cobro. Puedes intentarlo de nuevo con otro medio de pago.';
    $tono = 'error';
} else {
    $titulo = 'No pudimos confirmar el pago';
    $texto = 'Si Mercado Pago te mostró el pago como aprobado, tu plan se activará en unos minutos. Si tienes dudas, escríbenos a hola@rentplace.cl.';
    $tono = 'espera';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= htmlspecialchars($titulo) ?> — Rentplace</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --ink:#23272E; --pine:#4A7182; --pine-dark:#395A69; --line:#E3E7EA; --muted:#6B7280; }
  *{ box-sizing:border-box; }
  body{ margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px; background:#FAFAF9; font-family:'Manrope',sans-serif; color:var(--ink); }
  .card{ width:100%; max-width:440px; background:#fff; border:1px solid var(--line); border-radius:20px; padding:36px 30px; text-align:center; box-shadow:0 20px 50px -30px rgba(35,39,46,.25); }
  .icono{ width:64px; height:64px; margin:0 auto 18px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:800; }
  .ok .icono{ background:#E6F3EC; color:#2E7D4F; }
  .espera .icono{ background:#FDF3E6; color:#B37542; }
  .error .icono{ background:#FBEAEA; color:#B23B3B; }
  h1{ font-family:'Sora',sans-serif; font-size:22px; margin:0 0 10px; }
  p{ color:var(--muted); line-height:1.6; margin:0 0 24px; }
  a.btn{ display:inline-block; background:var(--pine); color:#fff; text-decoration:none; font-weight:700; padding:13px 24px; border-radius:12px; }
  a.btn:hover{ background:var(--pine-dark); }
  .ref{ margin-top:18px; font-size:12px; color:var(--muted); }
  .modo{ display:inline-block; margin-bottom:14px; font-size:12px; font-weight:800; color:#B37542; background:#FDF3E6; padding:4px 10px; border-radius:999px; }
</style>
</head>
<body>
  <div class="card <?= $tono ?>">
    <?php if (!mp_es_produccion()): ?><div class="modo">MODO PRUEBA · no se cobró dinero real</div><?php endif; ?>
    <div class="icono"><?= $tono === 'ok' ? '✓' : ($tono === 'error' ? '!' : '…') ?></div>
    <h1><?= htmlspecialchars($titulo) ?></h1>
    <p><?= htmlspecialchars($texto) ?></p>
    <a class="btn" href="<?= $tono === 'ok' ? 'mis_propiedades.php' : 'index.php#precios' ?>"><?= $tono === 'ok' ? 'Ir a mi panel' : 'Volver a los planes' ?></a>
    <?php if ($pago): ?><div class="ref">Referencia: <?= htmlspecialchars($pago['referencia']) ?></div><?php endif; ?>
  </div>
</body>
</html>
