<?php
/**
 * config.example.php
 * Copia este archivo como config.php (en la misma carpeta que conexion.php)
 * y completa los datos reales. config.php está en .gitignore: nunca debe
 * subirse al repositorio ni compartirse.
 */

// ==================== BASE DE DATOS ====================
define('DB_HOST', 'localhost');
define('DB_USUARIO', 'tu_usuario_bd');
define('DB_PASSWORD', 'tu_contraseña_bd');
define('DB_NOMBRE', 'tu_base_de_datos');

// ==================== SITIO ====================
// Dirección pública del sitio, SIN barra al final. Mercado Pago la usa para
// devolver al usuario después de pagar y para avisar los pagos (webhook).
define('URL_SITIO', 'https://rentplace.cl');

// ==================== MERCADO PAGO ====================
// ÚNICO INTERRUPTOR: 'prueba' mientras haces pruebas, 'produccion' para cobrar de verdad.
define('MP_MODO', 'prueba');

// Credenciales de PRUEBA (de la cuenta de prueba "vendedor" o de tus credenciales de prueba).
define('MP_ACCESS_TOKEN_PRUEBA', 'APP_USR-o-TEST-pega-aqui-el-access-token-de-prueba');
define('MP_WEBHOOK_SECRET_PRUEBA', '');     // "Clave secreta" de Webhooks en modo prueba (opcional al inicio)

// Credenciales de PRODUCCIÓN (tu cuenta real). Déjalas vacías hasta pasar a producción.
define('MP_ACCESS_TOKEN_PRODUCCION', '');
define('MP_WEBHOOK_SECRET_PRODUCCION', ''); // "Clave secreta" de Webhooks en modo productivo
