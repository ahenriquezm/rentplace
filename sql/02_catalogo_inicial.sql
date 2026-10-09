-- =====================================================================
-- 02_catalogo_inicial.sql
-- Datos iniciales: planes, tipos de alojamiento y atributos acordados.
-- Se puede re-ejecutar sin duplicar (INSERT IGNORE por código).
-- Después, el administrador de la plataforma agrega tipos/atributos nuevos
-- con INSERTs en estas mismas tablas, sin tocar código.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------- Planes (deben coincidir con tarifas.php) ----------
INSERT IGNORE INTO planes_suscripcion (codigo, nombre, precio_mensual, meses_cobrados_anual, max_ubicaciones, reserva_online, multi_ubicacion, orden) VALUES
  ('basic', 'Basic',  9990, 10, 1, 0, 0, 1),
  ('smart', 'Smart', 24990, 10, 1, 1, 0, 2),
  ('pro',   'Pro',   59990, 10, 3, 1, 1, 3);

-- ---------- Tipos de alojamiento ----------
INSERT IGNORE INTO tipos_alojamiento (codigo, nombre, nombre_plural, icono, orden) VALUES
  ('cabana',       'Cabaña',          'Cabañas',          '🏡', 1),
  ('departamento', 'Departamento',    'Departamentos',    '🏢', 2),
  ('casa',         'Casa vacacional', 'Casas vacacionales','🏠', 3),
  ('camping',      'Camping',         'Campings',         '⛺', 4),
  ('habitacion',   'Habitación',      'Habitaciones',     '🛏️', 5),
  ('glamping',     'Glamping / Domo', 'Glampings y domos','🔆', 6),
  ('tiny_house',   'Tiny House',      'Tiny Houses',      '🛖', 7),
  ('otro',         'Otro',            'Otros',            '✨', 99);

-- ---------- Atributos de UNIDAD ----------
INSERT IGNORE INTO atributos (codigo, nombre, tipo_dato, opciones, unidad_medida, ambito, filtrable, icono, orden) VALUES
  ('dormitorios',        'Dormitorios',              'entero',   NULL, NULL, 'unidad', 1, '🛏️', 10),
  ('camas',              'Camas',                    'entero',   NULL, NULL, 'unidad', 0, '🛌', 11),
  ('banos',              'Baños',                    'entero',   NULL, NULL, 'unidad', 0, '🚿', 12),
  ('tipo_cama',          'Tipo de cama',             'opcion',   '["1 plaza","2 plazas","Queen","King","Camarote"]', NULL, 'unidad', 0, '🛏️', 13),
  ('bano_privado',       'Baño',                     'opcion',   '["Privado","Compartido"]', NULL, 'unidad', 1, '🚿', 14),
  ('cocina',             'Cocina equipada',          'booleano', NULL, NULL, 'unidad', 1, '🍳', 20),
  ('calefaccion',        'Calefacción',              'opcion',   '["Leña","Pellet","Gas","Eléctrica","Central","Sin calefacción"]', NULL, 'unidad', 1, '🔥', 21),
  ('parrilla',           'Parrilla',                 'booleano', NULL, NULL, 'unidad', 1, '🍖', 22),
  ('tinaja',             'Tinaja / hot tub',         'booleano', NULL, NULL, 'unidad', 1, '♨️', 23),
  ('piscina',            'Piscina privada',          'booleano', NULL, NULL, 'unidad', 1, '🏊', 24),
  ('patio',              'Patio o jardín privado',   'booleano', NULL, NULL, 'unidad', 0, '🌿', 25),
  ('terraza',            'Terraza',                  'booleano', NULL, NULL, 'unidad', 0, '🪴', 26),
  ('balcon',             'Balcón',                   'booleano', NULL, NULL, 'unidad', 0, '🌇', 27),
  ('piso',               'Piso',                     'entero',   NULL, NULL, 'unidad', 0, '🏢', 28),
  ('ascensor',           'Ascensor',                 'booleano', NULL, NULL, 'unidad', 0, '🛗', 29),
  ('estacionamiento',    'Estacionamiento',          'booleano', NULL, NULL, 'unidad', 1, '🚗', 30),
  ('wifi',               'Wifi',                     'booleano', NULL, NULL, 'unidad', 1, '📶', 31),
  ('mascotas',           'Acepta mascotas',          'booleano', NULL, NULL, 'unidad', 1, '🐾', 32),
  ('lavadora',           'Lavadora',                 'booleano', NULL, NULL, 'unidad', 0, '🧺', 33),
  ('aire_acondicionado', 'Aire acondicionado',       'booleano', NULL, NULL, 'unidad', 0, '❄️', 34),
  ('chimenea',           'Chimenea',                 'booleano', NULL, NULL, 'unidad', 0, '🔥', 35),
  ('desayuno',           'Desayuno incluido',        'booleano', NULL, NULL, 'unidad', 1, '☕', 36),
  ('superficie',         'Superficie',               'decimal',  NULL, 'm²', 'unidad', 0, '📐', 40),
  ('carpas_permitidas',  'Carpas permitidas',        'entero',   NULL, NULL, 'unidad', 0, '⛺', 41),
  ('electricidad',       'Electricidad',             'booleano', NULL, NULL, 'unidad', 1, '🔌', 42),
  ('agua',               'Agua potable',             'booleano', NULL, NULL, 'unidad', 0, '🚰', 43),
  ('vehiculos',          'Vehículos permitidos',     'entero',   NULL, NULL, 'unidad', 0, '🚙', 44),
  ('equipamiento',       'Equipamiento',             'texto',    NULL, NULL, 'unidad', 0, '🧰', 45);

-- ---------- Atributos de UBICACIÓN (servicios comunes) ----------
INSERT IGNORE INTO atributos (codigo, nombre, tipo_dato, opciones, unidad_medida, ambito, filtrable, icono, orden) VALUES
  ('piscina_compartida',  'Piscina compartida',       'booleano', NULL, NULL, 'ubicacion', 1, '🏊', 60),
  ('recepcion',           'Recepción',                'booleano', NULL, NULL, 'ubicacion', 0, '🛎️', 61),
  ('banos_comunes',       'Baños comunes',            'booleano', NULL, NULL, 'ubicacion', 0, '🚻', 62),
  ('quincho',             'Quincho común',            'booleano', NULL, NULL, 'ubicacion', 0, '🍖', 63),
  ('estacionamiento_comun','Estacionamiento',         'booleano', NULL, NULL, 'ubicacion', 1, '🅿️', 64),
  ('wifi_comun',          'Wifi en áreas comunes',    'booleano', NULL, NULL, 'ubicacion', 0, '📶', 65),
  ('juegos_infantiles',   'Juegos infantiles',        'booleano', NULL, NULL, 'ubicacion', 0, '🛝', 66),
  ('acceso_playa_lago',   'Acceso a playa / lago / río', 'booleano', NULL, NULL, 'ubicacion', 1, '🏖️', 67);

-- ---------- Atributos por tipo (obligatorio = 1 se exige en el formulario) ----------
-- Formato: (tipo, atributo, obligatorio, orden)
INSERT IGNORE INTO tipos_atributos (id_tipo_alojamiento, id_atributo, obligatorio, orden)
SELECT t.id, a.id, x.obligatorio, x.orden
FROM (
            SELECT 'cabana' tipo, 'dormitorios' atributo, 1 obligatorio, 1 orden
  UNION ALL SELECT 'cabana', 'camas', 1, 2
  UNION ALL SELECT 'cabana', 'banos', 1, 3
  UNION ALL SELECT 'cabana', 'cocina', 0, 4
  UNION ALL SELECT 'cabana', 'calefaccion', 0, 5
  UNION ALL SELECT 'cabana', 'parrilla', 0, 6
  UNION ALL SELECT 'cabana', 'tinaja', 0, 7
  UNION ALL SELECT 'cabana', 'chimenea', 0, 8
  UNION ALL SELECT 'cabana', 'wifi', 0, 9
  UNION ALL SELECT 'cabana', 'estacionamiento', 0, 10
  UNION ALL SELECT 'cabana', 'mascotas', 0, 11

  UNION ALL SELECT 'departamento', 'dormitorios', 1, 1
  UNION ALL SELECT 'departamento', 'banos', 1, 2
  UNION ALL SELECT 'departamento', 'piso', 0, 3
  UNION ALL SELECT 'departamento', 'ascensor', 0, 4
  UNION ALL SELECT 'departamento', 'estacionamiento', 0, 5
  UNION ALL SELECT 'departamento', 'balcon', 0, 6
  UNION ALL SELECT 'departamento', 'cocina', 0, 7
  UNION ALL SELECT 'departamento', 'wifi', 0, 8
  UNION ALL SELECT 'departamento', 'lavadora', 0, 9
  UNION ALL SELECT 'departamento', 'aire_acondicionado', 0, 10

  UNION ALL SELECT 'casa', 'dormitorios', 1, 1
  UNION ALL SELECT 'casa', 'banos', 1, 2
  UNION ALL SELECT 'casa', 'piscina', 0, 3
  UNION ALL SELECT 'casa', 'patio', 0, 4
  UNION ALL SELECT 'casa', 'cocina', 0, 5
  UNION ALL SELECT 'casa', 'parrilla', 0, 6
  UNION ALL SELECT 'casa', 'wifi', 0, 7
  UNION ALL SELECT 'casa', 'estacionamiento', 0, 8
  UNION ALL SELECT 'casa', 'mascotas', 0, 9
  UNION ALL SELECT 'casa', 'lavadora', 0, 10

  UNION ALL SELECT 'camping', 'superficie', 0, 1
  UNION ALL SELECT 'camping', 'carpas_permitidas', 1, 2
  UNION ALL SELECT 'camping', 'electricidad', 0, 3
  UNION ALL SELECT 'camping', 'agua', 0, 4
  UNION ALL SELECT 'camping', 'vehiculos', 0, 5
  UNION ALL SELECT 'camping', 'mascotas', 0, 6

  UNION ALL SELECT 'habitacion', 'tipo_cama', 1, 1
  UNION ALL SELECT 'habitacion', 'bano_privado', 1, 2
  UNION ALL SELECT 'habitacion', 'desayuno', 0, 3
  UNION ALL SELECT 'habitacion', 'wifi', 0, 4
  UNION ALL SELECT 'habitacion', 'aire_acondicionado', 0, 5

  UNION ALL SELECT 'glamping', 'calefaccion', 0, 1
  UNION ALL SELECT 'glamping', 'bano_privado', 1, 2
  UNION ALL SELECT 'glamping', 'terraza', 0, 3
  UNION ALL SELECT 'glamping', 'tinaja', 0, 4
  UNION ALL SELECT 'glamping', 'equipamiento', 0, 5

  UNION ALL SELECT 'tiny_house', 'cocina', 0, 1
  UNION ALL SELECT 'tiny_house', 'banos', 1, 2
  UNION ALL SELECT 'tiny_house', 'calefaccion', 0, 3
  UNION ALL SELECT 'tiny_house', 'wifi', 0, 4
) x
INNER JOIN tipos_alojamiento t ON t.codigo = x.tipo
INNER JOIN atributos a ON a.codigo = x.atributo;
