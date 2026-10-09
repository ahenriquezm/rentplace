<?php
/**
 * mp_webhook.php
 * Mercado Pago avisa aquí cada cambio de estado de un pago (Webhooks).
 * URL a configurar en Mercado Pago: https://TU-DOMINIO/mp_webhook.php
 *
 * Nunca se confía en el contenido del aviso: solo se usa el id del pago para
 * consultarlo a la API de Mercado Pago, y se procesa lo que la API responde.
 * Debe responder 200 rápido; si no, Mercado Pago reintenta el aviso.
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mercadopago.php';
require_once __DIR__ . '/suscripciones.php';

header('Content-Type: text/plain; charset=utf-8');

$cuerpo = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$tipo = $_GET['type'] ?? $_GET['topic'] ?? $cuerpo['type'] ?? $cuerpo['topic'] ?? '';
$id = (string) ($_GET['data_id'] ?? $_GET['data.id'] ?? $_GET['id'] ?? $cuerpo['data']['id'] ?? '');

if ($tipo !== 'payment' || $id === '') {
    http_response_code(200); // otros avisos (ej. merchant_order) no se usan
    echo 'ignorado';
    exit;
}

if (!mp_firma_valida($id)) {
    http_response_code(401);
    echo 'firma invalida';
    exit;
}

$pago = mp_obtener_pago($id);
if (!$pago) {
    http_response_code(500); // Mercado Pago reintentará más tarde
    echo 'no se pudo consultar el pago';
    exit;
}

suscripcion_procesar_pago($conexion, $pago);
http_response_code(200);
echo 'ok';
