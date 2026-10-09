-- =====================================================================
-- 04_pagos_suscripcion.sql
-- Pagos de suscripción de los anfitriones a Rentplace (Mercado Pago).
-- Requiere 01_esquema_etapa1.sql y 02_catalogo_inicial.sql.
-- Se puede ejecutar en cualquier momento (solo crea una tabla nueva).
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pagos_suscripcion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  referencia VARCHAR(40) NOT NULL,              -- external_reference enviada a Mercado Pago
  id_anfitrion INT NOT NULL,
  id_plan INT NOT NULL,
  periodicidad ENUM('mensual','anual') NOT NULL,
  monto INT NOT NULL,                           -- CLP
  ambiente ENUM('prueba','produccion') NOT NULL,
  estado VARCHAR(20) NOT NULL DEFAULT 'pendiente', -- pendiente | approved | rejected | in_process | cancelled | refunded...
  mp_preference_id VARCHAR(80) NULL,
  mp_payment_id VARCHAR(40) NULL,
  aplicado TINYINT(1) NOT NULL DEFAULT 0,       -- 1 cuando ya extendió la suscripción (evita aplicarlo dos veces)
  id_suscripcion INT NULL,
  creado_en DATETIME NOT NULL,
  actualizado_en DATETIME NULL,
  UNIQUE KEY uq_pagos_referencia (referencia),
  UNIQUE KEY uq_pagos_mp_payment (mp_payment_id),
  KEY idx_pagos_anfitrion (id_anfitrion, creado_en),
  CONSTRAINT fk_pagos_anfitrion FOREIGN KEY (id_anfitrion) REFERENCES anfitriones (id),
  CONSTRAINT fk_pagos_plan FOREIGN KEY (id_plan) REFERENCES planes_suscripcion (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
