-- =====================================================================
-- 01_esquema_etapa1.sql
-- Modelo multiempresa / multiubicación / multiunidad (ver DECISIONES.md).
--
--   usuarios (login) ──< anfitriones ──< ubicaciones ──< unidades
--                              │               │              │
--                       suscripciones   ubicaciones_atributos unidades_atributos
--                              │                                   │
--                     planes_suscripcion        tipos_alojamiento ─┤
--                                               tipos_atributos >── atributos
--
-- Se ejecuta UNA vez. Solo crea tablas nuevas: no toca las existentes.
-- Las tablas de tarifas/bloqueos/reservas_unidades se crean ya, aunque su
-- uso completo llega en la Etapa 2, para no volver a migrar después.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------- Planes y suscripciones ----------

CREATE TABLE IF NOT EXISTS planes_suscripcion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL,                  -- basic | smart | pro
  nombre VARCHAR(50) NOT NULL,
  precio_mensual INT NOT NULL,                  -- CLP
  meses_cobrados_anual TINYINT NOT NULL DEFAULT 10,
  max_ubicaciones INT NOT NULL,
  reserva_online TINYINT(1) NOT NULL DEFAULT 0, -- calendario + pago + confirmación automática
  multi_ubicacion TINYINT(1) NOT NULL DEFAULT 0,-- calendario consolidado, huéspedes, reportes
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_planes_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Empresa o persona que administra alojamientos. Un usuario puede ser dueño
-- de un anfitrión; en el futuro varios usuarios podrán operar el mismo.
CREATE TABLE IF NOT EXISTS anfitriones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,                      -- usuario propietario de la cuenta
  nombre_comercial VARCHAR(120) NOT NULL,
  email_contacto VARCHAR(190) NULL,
  telefono VARCHAR(30) NULL,
  whatsapp VARCHAR(30) NULL,                    -- formato internacional, ej. 56912345678
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL,
  UNIQUE KEY uq_anfitriones_usuario (id_usuario),
  CONSTRAINT fk_anfitriones_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suscripciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_anfitrion INT NOT NULL,
  id_plan INT NOT NULL,
  periodicidad ENUM('mensual','anual') NOT NULL DEFAULT 'mensual',
  estado ENUM('prueba','activa','vencida','cancelada') NOT NULL DEFAULT 'prueba',
  ubicaciones_contratadas INT NOT NULL DEFAULT 1, -- > max del plan solo vía "Contactar a ventas"
  precio_acordado INT NULL,                       -- para propuestas personalizadas
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NULL,
  creado_en DATETIME NOT NULL,
  KEY idx_suscripciones_anfitrion (id_anfitrion, estado),
  CONSTRAINT fk_suscripciones_anfitrion FOREIGN KEY (id_anfitrion) REFERENCES anfitriones (id),
  CONSTRAINT fk_suscripciones_plan FOREIGN KEY (id_plan) REFERENCES planes_suscripcion (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Catálogo administrable de tipos y atributos ----------

CREATE TABLE IF NOT EXISTS tipos_alojamiento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL,                  -- cabana, departamento, camping...
  nombre VARCHAR(60) NOT NULL,
  nombre_plural VARCHAR(60) NOT NULL,
  icono VARCHAR(16) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_tipos_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS atributos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(40) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  tipo_dato ENUM('booleano','entero','decimal','texto','opcion') NOT NULL,
  opciones TEXT NULL,                           -- JSON con valores permitidos si tipo_dato = 'opcion'
  unidad_medida VARCHAR(20) NULL,               -- m², camas, etc.
  ambito ENUM('unidad','ubicacion') NOT NULL DEFAULT 'unidad',
  filtrable TINYINT(1) NOT NULL DEFAULT 0,      -- aparece como filtro en el buscador
  icono VARCHAR(16) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_atributos_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Qué atributos de unidad corresponden a cada tipo, y si son obligatorios.
CREATE TABLE IF NOT EXISTS tipos_atributos (
  id_tipo_alojamiento INT NOT NULL,
  id_atributo INT NOT NULL,
  obligatorio TINYINT(1) NOT NULL DEFAULT 0,
  orden INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id_tipo_alojamiento, id_atributo),
  CONSTRAINT fk_ta_tipo FOREIGN KEY (id_tipo_alojamiento) REFERENCES tipos_alojamiento (id),
  CONSTRAINT fk_ta_atributo FOREIGN KEY (id_atributo) REFERENCES atributos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Ubicaciones (unidad facturable) ----------

CREATE TABLE IF NOT EXISTS ubicaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_anfitrion INT NOT NULL,
  nombre VARCHAR(120) NOT NULL,                 -- "Complejo Los Aromos"
  slug VARCHAR(140) NOT NULL,                   -- link público / QR: rentplace.cl/v/complejo-los-aromos
  descripcion TEXT NULL,
  direccion VARCHAR(200) NOT NULL,
  ciudad VARCHAR(100) NOT NULL,
  region VARCHAR(100) NOT NULL,
  latitud DECIMAL(10,7) NULL,
  longitud DECIMAL(10,7) NULL,
  whatsapp VARCHAR(30) NULL,                    -- si es NULL se usa el del anfitrión
  email_contacto VARCHAR(190) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  destacado TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL,
  UNIQUE KEY uq_ubicaciones_slug (slug),
  KEY idx_ubicaciones_anfitrion (id_anfitrion),
  KEY idx_ubicaciones_ciudad (ciudad, activo),
  CONSTRAINT fk_ubicaciones_anfitrion FOREIGN KEY (id_anfitrion) REFERENCES anfitriones (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ubicaciones_fotos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ubicacion INT NOT NULL,
  url VARCHAR(255) NOT NULL,
  orden INT NOT NULL DEFAULT 0,
  KEY idx_uf_ubicacion (id_ubicacion, orden),
  CONSTRAINT fk_uf_ubicacion FOREIGN KEY (id_ubicacion) REFERENCES ubicaciones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Servicios comunes: piscina compartida, recepción, baños comunes...
CREATE TABLE IF NOT EXISTS ubicaciones_atributos (
  id_ubicacion INT NOT NULL,
  id_atributo INT NOT NULL,
  valor VARCHAR(255) NOT NULL,                  -- '1' para booleanos verdaderos
  PRIMARY KEY (id_ubicacion, id_atributo),
  CONSTRAINT fk_ua_ubicacion FOREIGN KEY (id_ubicacion) REFERENCES ubicaciones (id) ON DELETE CASCADE,
  CONSTRAINT fk_ua_atributo FOREIGN KEY (id_atributo) REFERENCES atributos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Unidades arrendables ----------

CREATE TABLE IF NOT EXISTS unidades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ubicacion INT NOT NULL,
  id_tipo_alojamiento INT NOT NULL,             -- obligatorio
  nombre VARCHAR(120) NOT NULL,                 -- "Cabaña 01" o "Sitio de camping"
  descripcion TEXT NULL,
  modalidad_inventario ENUM('individual','agrupado') NOT NULL DEFAULT 'individual',
  cantidad INT NOT NULL DEFAULT 1,              -- en 'agrupado': cuántas unidades equivalentes hay
  modalidad_arriendo ENUM('completa','por_habitacion','por_cama','por_sitio') NOT NULL DEFAULT 'completa',
  capacidad INT NOT NULL,                       -- personas por unidad
  precio_base_noche DECIMAL(12,2) NULL,         -- precio "desde" (vitrina); temporadas en `tarifas`
  precio_limpieza DECIMAL(12,2) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden INT NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL,
  KEY idx_unidades_ubicacion (id_ubicacion, activo),
  KEY idx_unidades_tipo (id_tipo_alojamiento),
  CONSTRAINT fk_unidades_ubicacion FOREIGN KEY (id_ubicacion) REFERENCES ubicaciones (id),
  CONSTRAINT fk_unidades_tipo FOREIGN KEY (id_tipo_alojamiento) REFERENCES tipos_alojamiento (id),
  CONSTRAINT chk_unidades_cantidad CHECK (cantidad >= 1),
  CONSTRAINT chk_unidades_capacidad CHECK (capacidad >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS unidades_fotos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_unidad INT NOT NULL,
  url VARCHAR(255) NOT NULL,
  orden INT NOT NULL DEFAULT 0,
  KEY idx_unf_unidad (id_unidad, orden),
  CONSTRAINT fk_unf_unidad FOREIGN KEY (id_unidad) REFERENCES unidades (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS unidades_atributos (
  id_unidad INT NOT NULL,
  id_atributo INT NOT NULL,
  valor VARCHAR(255) NOT NULL,
  PRIMARY KEY (id_unidad, id_atributo),
  KEY idx_unat_atributo_valor (id_atributo, valor(20)),  -- filtros del buscador
  CONSTRAINT fk_unat_unidad FOREIGN KEY (id_unidad) REFERENCES unidades (id) ON DELETE CASCADE,
  CONSTRAINT fk_unat_atributo FOREIGN KEY (id_atributo) REFERENCES atributos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Tarifas y disponibilidad (uso completo en Etapa 2) ----------

-- Precio por temporada / días específicos. Si no hay tarifa vigente, rige precio_base_noche.
CREATE TABLE IF NOT EXISTS tarifas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_unidad INT NOT NULL,
  nombre VARCHAR(80) NULL,                      -- "Temporada alta", "Fin de semana"
  fecha_desde DATE NOT NULL,
  fecha_hasta DATE NOT NULL,                    -- inclusive
  dias_semana VARCHAR(13) NULL,                 -- ej. '5,6' (vie, sáb); NULL = todos
  precio_noche DECIMAL(12,2) NOT NULL,
  estadia_minima INT NOT NULL DEFAULT 1,
  KEY idx_tarifas_unidad_fechas (id_unidad, fecha_desde, fecha_hasta),
  CONSTRAINT fk_tarifas_unidad FOREIGN KEY (id_unidad) REFERENCES unidades (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cupos bloqueados manualmente por el anfitrión (mantención, uso propio, reservas externas).
CREATE TABLE IF NOT EXISTS bloqueos_unidades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_unidad INT NOT NULL,
  fecha_desde DATE NOT NULL,
  fecha_hasta DATE NOT NULL,                    -- exclusiva (igual que fecha_salida)
  cantidad INT NOT NULL DEFAULT 1,              -- cupos bloqueados en inventario agrupado
  motivo VARCHAR(120) NULL,
  KEY idx_bloqueos_unidad_fechas (id_unidad, fecha_desde, fecha_hasta),
  CONSTRAINT fk_bloqueos_unidad FOREIGN KEY (id_unidad) REFERENCES unidades (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Contacto (Basic) y reservas ----------

-- Consultas de huéspedes: formulario o clic en WhatsApp. Base de la coordinación
-- manual (Basic) y de las estadísticas del anfitrión.
CREATE TABLE IF NOT EXISTS consultas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_ubicacion INT NOT NULL,
  id_unidad INT NULL,
  canal ENUM('formulario','whatsapp','telefono') NOT NULL,
  nombre VARCHAR(120) NULL,
  email VARCHAR(190) NULL,
  telefono VARCHAR(30) NULL,
  fecha_llegada DATE NULL,
  fecha_salida DATE NULL,
  huespedes INT NULL,
  mensaje TEXT NULL,
  estado ENUM('nueva','respondida','convertida','descartada') NOT NULL DEFAULT 'nueva',
  ip VARCHAR(45) NULL,
  creado_en DATETIME NOT NULL,
  KEY idx_consultas_ubicacion (id_ubicacion, creado_en),
  CONSTRAINT fk_consultas_ubicacion FOREIGN KEY (id_ubicacion) REFERENCES ubicaciones (id),
  CONSTRAINT fk_consultas_unidad FOREIGN KEY (id_unidad) REFERENCES unidades (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Unidades incluidas en cada reserva (una reserva puede tomar varias unidades,
-- o N cupos de una unidad agrupada). La tabla `reservas` se amplía en 03_migracion.
CREATE TABLE IF NOT EXISTS reservas_unidades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_reserva INT NOT NULL,
  id_unidad INT NOT NULL,
  cantidad INT NOT NULL DEFAULT 1,
  huespedes INT NOT NULL DEFAULT 1,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  KEY idx_ru_unidad (id_unidad),
  KEY idx_ru_reserva (id_reserva),
  CONSTRAINT fk_ru_unidad FOREIGN KEY (id_unidad) REFERENCES unidades (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
