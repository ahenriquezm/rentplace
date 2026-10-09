-- =====================================================================
-- 00_esquema_actual_referencia.sql
-- Esquema de la versión ACTUAL (propiedades), reconstruido desde el código
-- porque el repositorio no lo tenía. Sirve como referencia y para probar
-- la migración en un entorno vacío. NO ejecutar en producción: esas tablas
-- ya existen allí (los tipos exactos pueden diferir levemente).
-- =====================================================================

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  apellido VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  es_anfitrion TINYINT(1) NOT NULL DEFAULT 0,
  avatar_url VARCHAR(255) NULL,
  tiempo_respuesta_horas INT NULL,
  creado_en DATETIME NOT NULL,
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS propiedades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_anfitrion INT NOT NULL,
  titulo VARCHAR(120) NOT NULL,
  descripcion TEXT NULL,
  tipo VARCHAR(20) NOT NULL,
  direccion VARCHAR(200) NOT NULL,
  ciudad VARCHAR(100) NOT NULL,
  region VARCHAR(100) NOT NULL,
  capacidad INT NOT NULL,
  habitaciones INT NOT NULL DEFAULT 0,
  banos INT NOT NULL DEFAULT 0,
  precio_noche DECIMAL(12,2) NOT NULL,
  precio_limpieza DECIMAL(12,2) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  destacado TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS propiedades_fotos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_propiedad INT NOT NULL,
  url VARCHAR(255) NOT NULL,
  orden INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS propiedades_amenities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_propiedad INT NOT NULL,
  icono VARCHAR(16) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  orden INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS calendario_propiedad (
  id_propiedad INT NOT NULL,
  fecha DATE NOT NULL,
  disponible TINYINT(1) NOT NULL DEFAULT 1,
  precio_personalizado DECIMAL(12,2) NULL,
  PRIMARY KEY (id_propiedad, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_propiedad INT NOT NULL,
  id_usuario INT NOT NULL,
  fecha_llegada DATE NOT NULL,
  fecha_salida DATE NOT NULL,
  huespedes INT NOT NULL,
  precio_noche DECIMAL(12,2) NOT NULL,
  noches INT NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  precio_limpieza DECIMAL(12,2) NOT NULL DEFAULT 0,
  cargo_servicio DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL,
  estado VARCHAR(20) NOT NULL,
  creado_en DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resenas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_propiedad INT NOT NULL,
  id_usuario INT NOT NULL,
  puntuacion TINYINT NOT NULL,
  comentario TEXT NULL,
  creado_en DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suscriptores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NULL,
  origen VARCHAR(50) NULL,
  creado_en DATETIME NOT NULL,
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
