<?php
/**
 * mercadopago.php
 * Integración mínima con Mercado Pago Checkout Pro mediante su API REST (cURL),
 * sin SDK ni Composer, para que funcione en cualquier hosting cPanel.
 *
 * Configuración en config.php (ver config.example.php):
 *   MP_MODO = 'prueba' | 'produccion'  -> elige qué credenciales se usan.
 *
 * Funciones:
 *   mp_configurado()                    ¿hay access token para el modo actual?
 *   mp_crear_preferencia(array $datos)  crea el checkout y devuelve [id, url_pago]
 *   mp_obtener_pago(string $id)         consulta un pago (fuente de verdad del estado)
 *   mp_firma_valida(string $data_id)    valida la firma x-signature de un webhook
 */

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

defined('URL_SITIO')                    || define('URL_SITIO', '');
defined('MP_MODO')                      || define('MP_MODO', 'prueba');
defined('MP_ACCESS_TOKEN_PRUEBA')       || define('MP_ACCESS_TOKEN_PRUEBA', '');
defined('MP_ACCESS_TOKEN_PRODUCCION')   || define('MP_ACCESS_TOKEN_PRODUCCION', '');
defined('MP_WEBHOOK_SECRET_PRUEBA')     || define('MP_WEBHOOK_SECRET_PRUEBA', '');
defined('MP_WEBHOOK_SECRET_PRODUCCION') || define('MP_WEBHOOK_SECRET_PRODUCCION', '');
// Solo para pruebas automatizadas locales; en el servidor siempre es la API oficial.
defined('MP_API_BASE')                  || define('MP_API_BASE', 'https://api.mercadopago.com');

function mp_es_produccion(): bool
{
    return MP_MODO === 'produccion';
}

function mp_ambiente(): string
{
    return mp_es_produccion() ? 'produccion' : 'prueba';
}

function mp_access_token(): string
{
    return mp_es_produccion() ? MP_ACCESS_TOKEN_PRODUCCION : MP_ACCESS_TOKEN_PRUEBA;
}

function mp_configurado(): bool
{
    $token = mp_access_token();
    return $token !== '' && strpos($token, 'pega-aqui') === false && URL_SITIO !== '';
}

/**
 * Llamada a la API. Devuelve [codigo_http, respuesta_decodificada|null].
 */
function mp_request(string $metodo, string $ruta, ?array $cuerpo = null): array
{
    $ch = curl_init(MP_API_BASE . $ruta);
    $headers = [
        'Authorization: Bearer ' . mp_access_token(),
        'Content-Type: application/json',
    ];
    if ($metodo === 'POST') {
        // Evita crear dos cobros si la misma solicitud se reintenta.
        $headers[] = 'X-Idempotency-Key: ' . bin2hex(random_bytes(16));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    }
    $respuesta = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false) {
        error_log('Mercado Pago: error de conexión: ' . $error);
        return [0, null];
    }
    if ($codigo >= 400) {
        error_log('Mercado Pago: HTTP ' . $codigo . ' en ' . $ruta . ': ' . substr($respuesta, 0, 500));
    }
    return [$codigo, json_decode($respuesta, true)];
}

/**
 * Crea una preferencia de Checkout Pro.
 * $datos: titulo, monto (CLP entero), referencia, email_pagador, url_retorno, url_webhook
 * Devuelve ['id' => ..., 'url_pago' => ...] o null si falla.
 */
function mp_crear_preferencia(array $datos): ?array
{
    $cuerpo = [
        'items' => [[
            'id'          => $datos['referencia'],
            'title'       => $datos['titulo'],
            'quantity'    => 1,
            'currency_id' => 'CLP',
            'unit_price'  => (int) $datos['monto'],   // CLP no admite decimales
        ]],
        'payer'              => ['email' => $datos['email_pagador']],
        'external_reference' => $datos['referencia'],
        'back_urls'          => [
            'success' => $datos['url_retorno'],
            'failure' => $datos['url_retorno'],
            'pending' => $datos['url_retorno'],
        ],
        'auto_return'          => 'approved',
        'notification_url'     => $datos['url_webhook'],
        'statement_descriptor' => 'RENTPLACE',
    ];

    [$codigo, $respuesta] = mp_request('POST', '/checkout/preferences', $cuerpo);
    if ($codigo < 200 || $codigo >= 300 || empty($respuesta['id'])) {
        return null;
    }

    // En modo prueba se usa el checkout de pruebas si Mercado Pago lo entrega.
    $url = (!mp_es_produccion() && !empty($respuesta['sandbox_init_point']))
        ? $respuesta['sandbox_init_point']
        : ($respuesta['init_point'] ?? null);

    return $url ? ['id' => $respuesta['id'], 'url_pago' => $url] : null;
}

function mp_obtener_pago(string $id_pago): ?array
{
    if (!preg_match('/^\d+$/', $id_pago)) {
        return null;
    }
    [$codigo, $respuesta] = mp_request('GET', '/v1/payments/' . $id_pago);
    return ($codigo === 200 && is_array($respuesta)) ? $respuesta : null;
}

/**
 * Valida la firma que Mercado Pago envía en los webhooks (cabecera x-signature).
 * Si no hay clave secreta configurada, no se puede validar y se acepta: igual es
 * seguro porque el estado del pago SIEMPRE se vuelve a consultar a la API.
 */
function mp_firma_valida(string $data_id): bool
{
    $secreto = mp_es_produccion() ? MP_WEBHOOK_SECRET_PRODUCCION : MP_WEBHOOK_SECRET_PRUEBA;
    if ($secreto === '') {
        return true;
    }

    $firma = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $request_id = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    $ts = $v1 = '';
    foreach (explode(',', $firma) as $parte) {
        [$clave, $valor] = array_pad(array_map('trim', explode('=', $parte, 2)), 2, '');
        if ($clave === 'ts') $ts = $valor;
        if ($clave === 'v1') $v1 = $valor;
    }
    if ($ts === '' || $v1 === '') {
        return false;
    }

    $manifiesto = 'id:' . strtolower($data_id) . ';';
    if ($request_id !== '') {
        $manifiesto .= 'request-id:' . $request_id . ';';
    }
    $manifiesto .= 'ts:' . $ts . ';';

    return hash_equals(hash_hmac('sha256', $manifiesto, $secreto), $v1);
}
