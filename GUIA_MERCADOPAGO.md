# Guía paso a paso: Mercado Pago en Rentplace

Esta guía configura el cobro de los planes (Basic, Smart, Pro) con Mercado Pago:
primero en **modo prueba** (sin dinero real) y después en **producción**.

> Mercado Pago cambia a veces los nombres de los botones de su panel. Si algo no
> calza exactamente, busca el texto más parecido. Las ideas no cambian:
> **aplicación → credenciales → cuentas de prueba → webhooks**.

---

## Parte 0 — Antes de empezar (5 minutos)

Necesitas:

- [ ] Una cuenta de Mercado Pago Chile (la tuya o la de la empresa). Si no tienes: <https://www.mercadopago.cl> → **Crear cuenta**.
- [ ] Acceso a cPanel de rentplace.cl (Administrador de archivos y phpMyAdmin).
- [ ] La versión nueva del sitio subida, incluyendo `config.php` con los datos de la base.
- [ ] Haber ejecutado en phpMyAdmin, en este orden: `sql/01_esquema_etapa1.sql`, `sql/02_catalogo_inicial.sql`, `sql/04_pagos_suscripcion.sql`.
- [ ] El sitio con HTTPS funcionando (el candado en el navegador). Mercado Pago no avisa pagos a sitios sin HTTPS.

---

## Parte 1 — Crear la aplicación (10 minutos)

1. Entra a **<https://www.mercadopago.cl/developers>** e inicia sesión con tu cuenta de Mercado Pago.
2. Arriba a la derecha, haz clic en **Tus integraciones**.
3. Haz clic en **Crear aplicación**.
4. Completa:
   - **Nombre:** `Rentplace`
   - **¿Qué tipo de solución vas a integrar?:** *Pagos online*
   - **¿Estás usando una plataforma de e-commerce?:** *No*
   - **¿Qué producto estás integrando?:** **Checkout Pro**
5. Acepta los términos y haz clic en **Crear aplicación**.

✅ Listo cuando ves el panel de tu aplicación "Rentplace".

---

## Parte 2 — Crear cuentas de prueba (10 minutos)

Para probar sin dinero real, Mercado Pago usa **usuarios de prueba**: uno que vende (Rentplace) y uno que compra (el anfitrión que paga).

1. Dentro de tu aplicación, en el menú izquierdo, entra a **Cuentas de prueba**.
2. Haz clic en **Crear cuenta de prueba**:
   - **Descripción:** `Vendedor Rentplace`
   - **País:** Chile
   - **Tipo:** Vendedor
   - Crear.
3. Repite para el comprador:
   - **Descripción:** `Comprador prueba`
   - **País:** Chile
   - **Tipo:** Comprador
   - Crear.
4. Para cada una, **anota en un papel o en un archivo privado**:
   - Usuario (algo como `TESTUSER123456789`)
   - Contraseña
   - (Si te lo pide al iniciar sesión, el código de verificación son los **últimos 6 dígitos del ID de usuario**.)

---

## Parte 3 — Obtener el Access Token de PRUEBA (5 minutos)

El *Access Token* es la llave con la que Rentplace le pide cobros a Mercado Pago.
**Es secreto: nunca lo publiques ni lo envíes por chat o correo.**

1. Abre una **ventana de incógnito** del navegador.
2. Entra a **<https://www.mercadopago.cl/developers>** e inicia sesión con el usuario **Vendedor Rentplace** (el de prueba, no tu cuenta real).
3. Ve a **Tus integraciones** → **Crear aplicación** (sí, hay que crear una aplicación también dentro de la cuenta de prueba, igual que en la Parte 1).
4. Dentro de esa aplicación, en el menú izquierdo: **Credenciales de producción**.
   - Sí, "de producción": las credenciales de producción **de una cuenta de prueba** son las de prueba. Así lo recomienda Mercado Pago para Checkout Pro.
   - Si te pide datos de industria y sitio web, pon *Turismo / Alojamiento* y `https://rentplace.cl`.
5. Copia el **Access Token** (empieza con `APP_USR-`).

---

## Parte 4 — Pegar las credenciales en el sitio (5 minutos)

1. En cPanel, abre **Administrador de archivos** → carpeta del sitio → `config.php` → **Editar**.
2. Si tu `config.php` no tiene la sección de Mercado Pago, cópiala desde `config.example.php`.
3. Deja así (reemplazando el token por el tuyo):

```php
define('URL_SITIO', 'https://rentplace.cl');            // sin barra al final
define('MP_MODO', 'prueba');
define('MP_ACCESS_TOKEN_PRUEBA', 'APP_USR-xxxxxxxx...'); // el de la Parte 3
define('MP_WEBHOOK_SECRET_PRUEBA', '');                  // lo completas en la Parte 5
define('MP_ACCESS_TOKEN_PRODUCCION', '');
define('MP_WEBHOOK_SECRET_PRODUCCION', '');
```

4. **Guardar.**

---

## Parte 5 — Configurar el aviso de pagos (Webhook) (5 minutos)

Sirve para que el plan se active aunque el anfitrión cierre el navegador justo después de pagar.

1. En la aplicación del **Vendedor Rentplace** (la de prueba), menú izquierdo: **Webhooks** → **Configurar notificaciones**.
2. Pestaña **Modo de pruebas** (o *Modo productivo* si es lo único que aparece en la cuenta de prueba):
   - **URL:** `https://rentplace.cl/mp_webhook.php`
   - **Eventos:** marca solo **Pagos**.
   - **Guardar.**
3. Aparece una **Clave secreta**. Cópiala y pégala en `config.php`:
   ```php
   define('MP_WEBHOOK_SECRET_PRUEBA', 'la-clave-secreta');
   ```
4. Opcional: el botón **Simular notificación** debe responder **200**. Si responde 401, la clave secreta no coincide; revísala.

---

## Parte 6 — Hacer un pago de prueba (5 minutos)

1. Abre una ventana de incógnito y entra a `https://rentplace.cl/index.php?preview=TU_CLAVE`.
2. Crea una cuenta de anfitrión en Rentplace (o ingresa con una existente).
3. Baja a **Precios** → elige **Mensual** o **Anual** → **Contratar Smart**.
4. Te lleva a Mercado Pago. **Inicia sesión con el usuario Comprador prueba** (no con tu cuenta real).
5. Paga con una **tarjeta de prueba**. Están en <https://www.mercadopago.cl/developers/es/docs/checkout-pro/additional-content/your-integrations/test/cards>.
   - En el **nombre del titular** escribe el resultado que quieres simular:

     | Nombre del titular | Resultado |
     |---|---|
     | `APRO` | Pago aprobado |
     | `OTHE` | Rechazado |
     | `CONT` | Pendiente |

   - CVV y vencimiento: los que indica esa página.
6. Al terminar vuelves a Rentplace y ves **"¡Pago aprobado! Tu plan Smart está activo hasta…"** con la etiqueta *MODO PRUEBA*.
7. Verifica en phpMyAdmin:
   - Tabla `pagos_suscripcion`: `estado = approved` y `aplicado = 1`.
   - Tabla `suscripciones`: una fila `activa` con la fecha de término correcta.

Prueba también `OTHE` (debe decir "El pago no se completó") y `CONT` (debe decir "Pago en revisión").

### Si algo falla

| Síntoma | Causa probable | Solución |
|---|---|---|
| "Los pagos aún no están habilitados" | `config.php` sin token o sin `URL_SITIO` | Revisa la Parte 4 |
| "Mercado Pago no respondió" | Token mal copiado o de otra cuenta | Copia de nuevo el Access Token (Parte 3) |
| Mercado Pago dice "Una de las partes es de prueba" | Mezclaste cuenta real con cuenta de prueba | Vendedor y comprador deben ser **ambos** de prueba |
| Pagaste, pero el plan no se activa | El webhook no llega | Revisa la URL de la Parte 5 y que el sitio tenga HTTPS |
| El webhook responde 401 | Clave secreta distinta | Copia de nuevo la clave secreta (Parte 5, paso 3) |

Los errores técnicos quedan registrados en el archivo `error_log` de la carpeta del sitio (cPanel → Administrador de archivos).

---

## Parte 7 — Pasar a PRODUCCIÓN (cobrar de verdad)

Hazlo solo cuando las pruebas de la Parte 6 funcionen.

1. Entra a <https://www.mercadopago.cl/developers> con **tu cuenta real** (no la de prueba).
2. **Tus integraciones** → aplicación **Rentplace** (la de la Parte 1) → **Credenciales de producción** → **Activar credenciales**:
   - **Industria:** Turismo / Alojamiento
   - **Sitio web:** `https://rentplace.cl`
   - Acepta y activa.
3. Copia el **Access Token de producción** (`APP_USR-...`).
4. **Webhooks** → pestaña **Modo productivo**:
   - **URL:** `https://rentplace.cl/mp_webhook.php`
   - **Evento:** Pagos
   - Guardar y copiar la **Clave secreta**.
5. En `config.php`:
   ```php
   define('MP_ACCESS_TOKEN_PRODUCCION', 'APP_USR-...de-tu-cuenta-real');
   define('MP_WEBHOOK_SECRET_PRODUCCION', 'clave-secreta-de-produccion');
   define('MP_MODO', 'produccion');   // ← el ÚNICO cambio para pasar a producción
   ```
6. **Prueba real:** contrata el plan Basic mensual ($9.990) con tu propia tarjeta. Verifica que se active y luego devuélvete el dinero desde la actividad de tu cuenta de Mercado Pago (**Devolver dinero**).

Para volver a modo prueba en cualquier momento: `define('MP_MODO', 'prueba');`. Los pagos guardan en qué ambiente se hicieron (columna `ambiente`).

---

## Seguridad (léelo una vez)

- El Access Token y la clave secreta **solo** van en `config.php`, que nunca se sube al repositorio.
- Si crees que un token se filtró: en Mercado Pago, **Credenciales → Renovar**, y pega el nuevo en `config.php`.
- Rentplace nunca confía en lo que dice la URL de retorno ni el aviso: siempre vuelve a consultar el pago a Mercado Pago y compara el monto con el precio del plan.

## Limitación actual (a decidir)

Cada pago cubre **un período** (1 mes o 12 meses); no hay cobro automático mensual.
Al vencer, el anfitrión debe volver a pagar. Para cobrar automáticamente cada mes existe
"Suscripciones" de Mercado Pago, que se puede integrar después si lo necesitas.
