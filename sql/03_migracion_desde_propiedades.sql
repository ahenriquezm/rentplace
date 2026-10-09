-- =====================================================================
-- 03_migracion_desde_propiedades.sql
-- Pasa los datos del modelo antiguo (propiedades) al nuevo
-- (anfitriones → ubicaciones → unidades). Ejecutar UNA vez, después de
-- 01_esquema_etapa1.sql y 02_catalogo_inicial.sql.
--
-- Cada propiedad se convierte en 1 ubicación con 1 unidad individual,
-- conservando el MISMO id (ubicaciones.id = unidades.id = propiedades.id),
-- para que enlaces, reservas y reseñas existentes sigan apuntando bien.
--
-- No borra ni modifica datos de las tablas antiguas, salvo ampliar `reservas`
-- con columnas nuevas. HAZ UN RESPALDO de la base antes de ejecutarlo
-- (cPanel > phpMyAdmin > Exportar).
-- =====================================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- 1) Un anfitrión por cada usuario que tenga propiedades.
INSERT IGNORE INTO anfitriones (id_usuario, nombre_comercial, email_contacto, creado_en)
SELECT u.id, CONCAT(u.nombre, ' ', u.apellido), u.email, NOW()
FROM usuarios u
WHERE EXISTS (SELECT 1 FROM propiedades p WHERE p.id_anfitrion = u.id);

-- 2) Suscripción inicial: Smart en período de prueba por 30 días
--    (los anfitriones actuales ya usaban reservas online).
INSERT INTO suscripciones (id_anfitrion, id_plan, periodicidad, estado, ubicaciones_contratadas, fecha_inicio, fecha_fin, creado_en)
SELECT a.id, pl.id, 'mensual', 'prueba', 1, CURDATE(), CURDATE() + INTERVAL 30 DAY, NOW()
FROM anfitriones a
INNER JOIN planes_suscripcion pl ON pl.codigo = 'smart'
WHERE NOT EXISTS (SELECT 1 FROM suscripciones s WHERE s.id_anfitrion = a.id);

-- 3) Ubicaciones (mismo id que la propiedad). El slug provisional se puede
--    editar después desde el panel.
INSERT IGNORE INTO ubicaciones (id, id_anfitrion, nombre, slug, descripcion, direccion, ciudad, region, activo, destacado, creado_en)
SELECT p.id, a.id, p.titulo, CONCAT('alojamiento-', p.id), p.descripcion, p.direccion, p.ciudad, p.region,
       p.activo, p.destacado, p.creado_en
FROM propiedades p
INNER JOIN anfitriones a ON a.id_usuario = p.id_anfitrion;

-- 4) Unidades (mismo id que la propiedad), con el tipo según el código antiguo.
INSERT IGNORE INTO unidades (id, id_ubicacion, id_tipo_alojamiento, nombre, descripcion, modalidad_inventario, cantidad,
                             modalidad_arriendo, capacidad, precio_base_noche, precio_limpieza, activo, orden, creado_en)
SELECT p.id, p.id, COALESCE(t.id, tOtro.id), p.titulo, NULL, 'individual', 1,
       'completa', GREATEST(p.capacidad, 1), p.precio_noche, p.precio_limpieza, p.activo, 0, p.creado_en
FROM propiedades p
INNER JOIN ubicaciones ub ON ub.id = p.id
LEFT JOIN tipos_alojamiento t ON t.codigo = p.tipo
INNER JOIN tipos_alojamiento tOtro ON tOtro.codigo = 'otro';

-- 5) Fotos: pasan a la galería de la ubicación.
INSERT INTO ubicaciones_fotos (id_ubicacion, url, orden)
SELECT f.id_propiedad, f.url, f.orden
FROM propiedades_fotos f
INNER JOIN ubicaciones ub ON ub.id = f.id_propiedad
WHERE NOT EXISTS (SELECT 1 FROM ubicaciones_fotos x WHERE x.id_ubicacion = f.id_propiedad AND x.url = f.url);

-- 6) Dormitorios y baños pasan a atributos de la unidad.
INSERT IGNORE INTO unidades_atributos (id_unidad, id_atributo, valor)
SELECT p.id, a.id, CAST(p.habitaciones AS CHAR)
FROM propiedades p INNER JOIN unidades un ON un.id = p.id
INNER JOIN atributos a ON a.codigo = 'dormitorios'
WHERE p.habitaciones > 0;

INSERT IGNORE INTO unidades_atributos (id_unidad, id_atributo, valor)
SELECT p.id, a.id, CAST(p.banos AS CHAR)
FROM propiedades p INNER JOIN unidades un ON un.id = p.id
INNER JOIN atributos a ON a.codigo = 'banos'
WHERE p.banos > 0;

-- 7) Servicios (texto libre "icono|nombre") a atributos booleanos.
INSERT IGNORE INTO unidades_atributos (id_unidad, id_atributo, valor)
SELECT am.id_propiedad, a.id, '1'
FROM propiedades_amenities am
INNER JOIN unidades un ON un.id = am.id_propiedad
INNER JOIN (
            SELECT 'Jardín privado' nombre, 'patio' codigo
  UNION ALL SELECT 'Chimenea', 'chimenea'
  UNION ALL SELECT 'Wifi de alta velocidad', 'wifi'
  UNION ALL SELECT 'Estacionamiento gratis', 'estacionamiento'
  UNION ALL SELECT 'Cocina equipada', 'cocina'
  UNION ALL SELECT 'Acepta mascotas', 'mascotas'
  UNION ALL SELECT 'Piscina', 'piscina'
  UNION ALL SELECT 'Lavadora', 'lavadora'
  UNION ALL SELECT 'Aire acondicionado', 'aire_acondicionado'
) mapa ON mapa.nombre = am.nombre
INNER JOIN atributos a ON a.codigo = mapa.codigo;

-- 8) Calendario antiguo: precios por día -> tarifas; días bloqueados -> bloqueos.
INSERT INTO tarifas (id_unidad, nombre, fecha_desde, fecha_hasta, precio_noche, estadia_minima)
SELECT c.id_propiedad, 'Precio personalizado', c.fecha, c.fecha, c.precio_personalizado, 1
FROM calendario_propiedad c
INNER JOIN unidades un ON un.id = c.id_propiedad
WHERE c.precio_personalizado IS NOT NULL AND c.fecha >= CURDATE();

INSERT INTO bloqueos_unidades (id_unidad, fecha_desde, fecha_hasta, cantidad, motivo)
SELECT c.id_propiedad, c.fecha, c.fecha + INTERVAL 1 DAY, 1, 'Migrado del calendario anterior'
FROM calendario_propiedad c
INNER JOIN unidades un ON un.id = c.id_propiedad
WHERE c.disponible = 0 AND c.fecha >= CURDATE();

COMMIT;

-- 9) Reservas: se amplían para el nuevo modelo (los ALTER no son transaccionales).
--    - id_ubicacion: a qué ubicación pertenece (= id_propiedad en datos migrados)
--    - id_usuario pasa a opcional: el anfitrión puede registrar reservas coordinadas
--      por fuera (Basic) sin que el huésped tenga cuenta.
--    - estado pasa a texto libre para admitir los nuevos estados
--      (consulta, pendiente_pago, confirmada, cancelada, vencida).
ALTER TABLE reservas
  MODIFY id_usuario INT NULL,
  MODIFY estado VARCHAR(20) NOT NULL,
  ADD COLUMN id_ubicacion INT NULL AFTER id_propiedad,
  ADD COLUMN origen VARCHAR(20) NOT NULL DEFAULT 'online' AFTER estado,
  ADD COLUMN nombre_huesped VARCHAR(120) NULL,
  ADD COLUMN email_huesped VARCHAR(190) NULL,
  ADD COLUMN telefono_huesped VARCHAR(30) NULL,
  ADD COLUMN anticipo DECIMAL(12,2) NOT NULL DEFAULT 0,
  ADD COLUMN saldo_pendiente DECIMAL(12,2) NOT NULL DEFAULT 0,
  ADD COLUMN vence_en DATETIME NULL,
  ADD KEY idx_reservas_ubicacion (id_ubicacion, estado);

UPDATE reservas SET id_ubicacion = id_propiedad WHERE id_ubicacion IS NULL;

INSERT INTO reservas_unidades (id_reserva, id_unidad, cantidad, huespedes, subtotal)
SELECT r.id, r.id_propiedad, 1, r.huespedes, r.subtotal
FROM reservas r
INNER JOIN unidades un ON un.id = r.id_propiedad
WHERE NOT EXISTS (SELECT 1 FROM reservas_unidades x WHERE x.id_reserva = r.id);

-- Nota: `resenas.id_propiedad` sigue siendo válido porque ubicaciones.id = propiedades.id.
-- Las tablas antiguas (propiedades*, calendario_propiedad) quedan intactas como respaldo
-- hasta que todos los módulos usen el modelo nuevo; luego se pueden eliminar.
